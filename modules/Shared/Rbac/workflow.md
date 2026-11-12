# Permissions (RBAC): what data goes where

Reference for how access works in the ERP: where permissions come from, how they are granted, how a request is checked, and which table and columns each step writes. Based on the migrations and code as they stand (2026-11-12). Keep it in sync when RBAC tables, permission enums or role templates change.

## The rule in one line

> **Allowed = active user AND the session's branch runs the permission's module AND (super admin OR (holds the permission in the session's branch AND, for a record, the record is in that branch)).**

Access is **branch first, then permission**. A grant in one branch never applies in another. Roles are templates for ticking boxes on screen; they never grant anything.

## The big picture

```
 CODE (per module)                          DATABASE                                  RUNTIME
 ─────────────────                          ────────                                  ───────
 SharedPermission enum ─┐  permissions:sync
 BloodBankPermission    ├──────────────────> sh_permissions (catalog mirror)
   module / label /     │                          │
   order / group /      │                          │ permission_id
   description          │                          ▼
                        │                    sh_user_branch_permissions ─────────> BranchAccessChecker
 SharedRoleTemplate ────┤  permissions:sync   (user × branch × permission)          (every can:, Gate, Policy)
 BloodBankRoleTemplate ─┘  (create once) ──> sh_roles ──> sh_role_permissions
                                              (templates: UI only, never checked)

 users.is_super_admin  (php artisan auth:super-admin)  ─────────────────────────> bypasses every check
 sh_branches  ──  session branch chosen at login (CurrentBranch) ───────────────> the branch every check uses
 sh_audit_logs  ←  every grant change, role change, login
```

Three layers, each with one owner:

| Layer | Defined in | Stored in | Changed by |
|---|---|---|---|
| **Module** (Blood Bank, Billing, User & Permission) | Permission enums: `module()`, `moduleLabel()`, `moduleOrder()` | `sh_permissions.module`, `module_label`, `module_order` | Developers (deploy + `permissions:sync`) |
| **Permission** (`bb.case.view`, …) | Permission enums (one per module) | `sh_permissions` (mirror) | Developers (deploy + `permissions:sync`) |
| **Role template** (Cashier, Sample collection, …) | Defaults in template enums; then the database | `sh_roles`, `sh_role_permissions` | Created once by `permissions:sync`; then admins |
| **Grant** (who may do what, where) | Admin on the Permissions screen | `sh_user_branch_permissions` | Admins with `sh.user.assign_permissions` |
| **Super admin** | `php artisan auth:super-admin {username}` | `users.is_super_admin` | IT / developers only, never the API |

Modules are layout only (the headings of the Permissions screen). They are never selectable and never checked.

## Tables

### `sh_permissions`: the catalog (mirror of code)
| Column | Meaning |
|---|---|
| `id` | |
| `name` | Unique permission key, e.g. `bb.sample.collect`. This is what `can:` checks. |
| `module` | Module key: `blood-bank`, `billing`, `access`. A role template belongs to one. |
| `module_label` | Heading on screen: "Blood Bank", "Billing", "User & Permission". |
| `module_order` | Heading position: Blood Bank 10, Billing 20, User & Permission 30. |
| `group` | Functional group inside the module ("Samples", "Users"); used by the read-only summary. |
| `description` | Text shown next to the checkbox. |
| `created_at`, `updated_at` | |

Never edited by users. `permissions:sync` upserts every enum case by `name`. A permission removed from code is deleted only when nobody holds it and no role uses it; otherwise it is kept and the command warns.

### `sh_user_branch_permissions`: the grants (the only thing checked at runtime)
| Column | Meaning |
|---|---|
| `id` | |
| `user_id` | FK `users` |
| `branch_id` | FK `sh_branches` |
| `permission_id` | FK `sh_permissions` |
| `granted_by` | FK `users`, nullable: the admin who added the row |
| `created_at` | When it was granted |

Unique `(user_id, branch_id, permission_id)`. One row per permission per branch. Revoking deletes the row (the history lives in the audit log). **A user belongs to a branch only by holding at least one row there**; there is no separate user-to-branch table.

### `sh_roles`: role templates
| Column | Meaning |
|---|---|
| `id` | |
| `name` | Unique, e.g. "Sample collection" (the template enums use the same names) |
| `module` | Module key; every permission of the template must be from it. Nullable only for templates retired by the 2026-11-12 migration. |
| `description` | |
| `created_at`, `updated_at`, `deleted_at` | Soft delete |

### `sh_role_permissions`: template contents
`role_id` (FK `sh_roles`), `permission_id` (FK `sh_permissions`), primary key on both.

### `sh_branch_modules`: optional modules per branch
| Column | Meaning |
|---|---|
| `branch_id` | FK `sh_branches` |
| `module` | Module key of an **optional** module, e.g. `blood-bank` |

Unique `(branch_id, module)`. Only optional modules are listed: a module whose permission enum returns `isOptionalModule() = true` (today only Blood Bank). Every other module (Billing, User & Permission, and later Daycare and Physiotherapy) runs in every branch and is never listed. `sh_permissions.module_is_optional` mirrors the flag (filled by `permissions:sync`). Today only East Gate (NE) runs `blood-bank`. Edited by a super admin with the branch (`PUT /api/access/branches/{branch}` with `modules`), audited as `branch.updated`; owned by `Shared/Branch` (`Models/BranchModule`, `Contracts/BranchModules`).

### Related
- `users.is_super_admin`, `users.is_active`: checked before any grant.
- `sh_branches` (`code`, `name`, `is_active`, …): grants point at a branch; only active branches are offered at login.
- `sh_audit_logs`: append-only trail of every change below.

## Defined in code

| What | Where |
|---|---|
| Contract for permission enums | `Contracts/PermissionDefinition.php` (`module`, `moduleLabel`, `moduleOrder`, `group`, `description`) |
| Contract for default templates | `Contracts/RoleTemplateDefinition.php` (`description`, `permissions`; enum value = template name) |
| Shared permissions | `Enums/SharedPermission.php` (Billing: `sh.billing.*`, `sh.patient.*`; User & Permission: `sh.user.*`, `sh.role.*`) |
| Shared default templates | `Enums/SharedRoleTemplate.php` |
| Blood Bank permissions / templates | `modules/BloodBank/Enums/BloodBankPermission.php`, `BloodBankRoleTemplate.php` |
| Registry (singleton) | `Services/PermissionRegistry.php`: each module's provider calls `register()` and `registerRoleTemplates()`. A template mixing modules fails at boot. |
| Runtime check | `Services/BranchAccessChecker.php` (bound to `Contracts/AccessChecker`) |
| Gate hook | `SharedServiceProvider::bootAuthorization()`: any ability that is a catalog name goes to `AccessChecker::allows()` |
| Role matching (display) | `Services/RoleMatcher.php` |

### Default templates (created once by `permissions:sync`)

| Module | Template | Permissions |
|---|---|---|
| Blood Bank | Case desk | `bb.case.view`, `bb.case.update`, `bb.donor.view` |
| | Sample collection | `bb.case.view`, `bb.sample.view`, `bb.sample.collect`, `bb.sample.print_label` |
| | Physician | `bb.donor.view`, `bb.donor_exam.view`, `bb.donor_exam.record` |
| | Serology | `bb.serology.view`, `bb.serology.record` |
| | Blood collection | `bb.collection.view`, `bb.collection.collect`, `bb.collection.print_label` |
| | Component preparation | `bb.unit.view`, `bb.unit.prepare`, `bb.unit.print_label` |
| | Blood issue | `bb.issue.view`, `bb.issue.record`, `bb.issue.print_report` |
| Billing | Cashier | `sh.billing.view`, `sh.billing.create`, `sh.billing.print`, `sh.patient.view`, `sh.patient.create` |
| | Billing viewer | `sh.billing.view` |
| User & Permission | User admin | `sh.user.view`, `create`, `update`, `deactivate`, `reset_password`, `unlock`, `force_logout` |
| | Permission admin | `sh.user.view`, `sh.user.assign_permissions`, `sh.role.view`, `sh.role.manage` |

## Step by step

### 0. Deploy / setup
1. `php artisan migrate`.
2. `php artisan permissions:sync` (`Console/SyncPermissionsCommand`):
   - `SyncPermissionCatalog` upserts every enum case into `sh_permissions` (name, module, module_label, module_order, module_is_optional, group, description) and removes stale unused ones.
   - `SeedRoleTemplates` creates each default template that does not exist **by name, including deleted ones** (`sh_roles` + `sh_role_permissions`, through `CreateRole`, so it is audited). Admin edits are never overwritten; a deleted default is not brought back.
   - `DatabaseSeeder` runs both too.
3. Branches: `DatabaseSeeder` creates East Gate (NE), Medical Main Gate (NM), Anderkilla (NA), Halishahar (NH), Golden Core (NG), and switches Blood Bank on for East Gate only (`sh_branch_modules`). Later branches are managed by a super admin (`/api/access/branches`). The branch `code` is the prefix of its invoice numbers.
4. First admin: `php artisan auth:super-admin {username}` sets `users.is_super_admin` (`--revoke` clears it).

### 1. Create a user
`POST /api/access/users` writes `users` only. **A new user has no access anywhere** and cannot log in to any branch until granted something.

### 2. Grant permissions (Permissions screen)
Screen: User Management → Permissions (`sh.user.assign_permissions`).

1. **User list:** `GET /api/access/user-access?filter[search]=…` (`Queries/ListUserAccess`). Per user: `permission_count` (all branches) and, per branch, `permission_count`, `matched_roles` (templates fully held, via `RoleMatcher`) and `other_permission_count`.
2. **Open a user:** the page loads `GET /api/access/users/{user}` (abilities), `GET …/users/{user}/branch-permissions`, `GET /api/access/branch-options`, `GET /api/access/permission-options`, `GET /api/access/role-options`.
3. **Edit (in the browser only):** only the modules the branch runs are shown (common modules always; an optional one such as Blood Bank only if listed in the branch's `modules`). Module headings → templates → permissions. Ticking a template ticks all its permissions; a partly ticked template shows a dash. Permissions in no template sit under "Other … permissions" of their module. Nothing is written yet.
4. **Copy from…** (optional, still in the browser): pick a user; their permissions **in the branch on screen** (`GET …/users/{source}/branch-permissions`) replace the draft.
5. **Save** (branch on screen only): `PUT /api/access/users/{user}/branches/{branch}/permissions` with `{permissions: [...], applied_role_ids: [...]}` → `Actions/SyncUserBranchPermissions`:
   - `AccountGuard::ensureCanManage`: not your own account (`CANNOT_CHANGE_OWN_ACCOUNT`, 422), not a super admin unless you are one (`SUPER_ADMIN_PROTECTED`, 403).
   - No permission of an optional module the branch does not run (`MODULE_NOT_ENABLED_FOR_BRANCH`, 422).
   - In one transaction: deletes rows of `sh_user_branch_permissions` no longer wanted, inserts new ones with `granted_by` = the admin.
   - Audit `user.branch_permissions_synced` (subject = the user): old `{branch_id, removed}`, new `{branch_id, added, applied_roles}`. `applied_role_ids` is recorded only; it never changes what is granted.
   - Response: the branch with `permissions`, `matched_roles`, `other_permissions`.
6. **Remove a branch completely:** save that branch with no permissions. The user then no longer belongs to it.

### 3. Login
1. Username typed → `POST /api/auth/login-branches` (`Auth/Queries/ListLoginBranches`): active branches where the user holds any row in `sh_user_branch_permissions` (super admin: every active branch). Unknown, inactive and locked accounts get the same empty list.
2. `POST /api/auth/login` with `branch_id` (`Auth/Actions/LoginUser`): re-checks the user holds something in that branch (`BRANCH_ACCESS_DENIED` otherwise), stores `branch_id` in the session (`SessionCurrentBranch::SESSION_KEY`), audits the login.
3. `GET /api/auth/me` returns `permissions` = `AccessChecker::permissionsIn(user, session branch)`. The frontend uses it only to hide buttons.

The branch is fixed for the session; working elsewhere means signing out and in again.

### 4. Every request
1. Route middleware `can:bb.sample.collect` → `Gate::before` → `BranchAccessChecker::allows()`:
   1. `users.is_active` false → deny.
   2. The permission belongs to an optional module the session branch does not run (`sh_branch_modules`) → deny, **even for a super admin**.
   3. `users.is_super_admin` → allow.
   4. Session branch missing → deny.
   5. Row exists in `sh_user_branch_permissions` for (user, session branch, permission) → allow.
2. Records with a `branch_id` also go through their Policy, which calls `AccessChecker::allowsOnRecord()`: the record's branch must equal the session branch, then the same check as above.
3. No cache: a saved grant applies on the user's very next request.

Abilities that are not catalog names fall through to Policies as usual.

### 5. Role templates
- List: `GET /api/access/roles` (`sh.role.view`) or `GET /api/access/role-options` (`sh.user.view`, for the grants screen). Each has `module` and `permissions`.
- Create / edit: `POST` / `PUT /api/access/roles[/{role}]` (`sh.role.manage`) with `{name, module, description, permissions}`. Every permission must exist **in that module** (`VALIDATION_FAILED` on `permissions.N`). Audited `role.created` / `role.updated` (name, module, description, permissions).
- Delete: `DELETE /api/access/roles/{role}` soft-deletes, audited `role.deleted`. **No user loses anything**: grants are separate rows.
- Editing a template does not change any user. Users keep exactly what was saved for them.

### 6. Other events
| Event | Effect on access |
|---|---|
| User deactivated | `allows()` denies everything; login offers no branch. Grants are kept for reactivation. |
| User locked | Login offers no branch until unlocked. |
| Branch deactivated | Not offered at login; users signed in there get `BRANCH_INACTIVE` (401) and are logged out. Grants stay; the screen still lists the branch so they can be cleared. |
| Optional module switched off for a branch | Its permissions there allow nothing at once and leave `me.permissions` (the menu). The grant rows stay; the Permissions screen hides the module, notes the leftovers, and the next Save there removes them (audited). |
| Permission removed from code | `permissions:sync` deletes it if unused, else keeps it and warns. |

## API summary (`/api/access`)

| Method and path | Permission | Purpose |
|---|---|---|
| `GET user-access` | `sh.user.assign_permissions` | Permissions screen user list |
| `GET users/{user}/branch-permissions` | `sh.user.view` | A user's grants per branch (+ matched roles) |
| `PUT users/{user}/branches/{branch}/permissions` | `sh.user.assign_permissions` | Replace a user's grants in one branch |
| `GET branch-options` | `sh.user.assign_permissions` | Active branches to grant in, with the optional `modules` each runs |
| `GET permission-options` | `sh.user.view` | Catalog with module headings |
| `GET role-options` | `sh.user.view` | Templates with module and permissions |
| `GET permissions` | `sh.role.view` | Catalog (role management) |
| `GET/POST/PUT/DELETE roles…` | `sh.role.view` / `sh.role.manage` | Role template management |
| `GET/POST/PUT branches…` | super admin | Branch management |

Errors use the central format: `FORBIDDEN` (403), `VALIDATION_FAILED` (422), `CANNOT_CHANGE_OWN_ACCOUNT` (422), `MODULE_NOT_ENABLED_FOR_BRANCH` (422: a permission of an optional module the branch does not run), `SUPER_ADMIN_PROTECTED` (403).

## Audit trail

| Action | When | Values |
|---|---|---|
| `user.branch_permissions_synced` | A grant save changed something | old `{branch_id, removed}`, new `{branch_id, added, applied_roles?}` |
| `role.created` / `role.updated` / `role.deleted` | Template changes (including seeding) | name, module, description, permissions |
| Login / failed login | `LoginUser` | branch_id |

## Adding a permission or a module
1. Add the case to the module's permission enum with `module()`, `moduleLabel()`, `moduleOrder()`, `group()`, `description()`.
2. Use it with `can:` on the route and in the Policy; mirror it in the frontend `permissions.ts`.
3. Optionally add it to a default template enum (only affects installs where that template does not exist yet; existing templates are edited by admins).
4. New module: create its permission enum (and template enum), register both in its provider (`register`, `registerRoleTemplates`).
5. Deploy, then `php artisan permissions:sync`. New permissions not in any template appear under "Other … permissions" of their module.

## Decisions
- Permissions and modules are code-first (typo-safe, real foreign keys); templates and grants are data.
- Roles never grant access; templates are UI shortcuts. Grants are explicit rows per branch.
- Super admin is never set through the API.
- No self-assignment: an admin cannot change their own grants.
- Copy from another user and template ticking are drafts in the browser; only Save writes, per branch.
- Patient permissions belong to the Billing module (patients are registered on the bill).

## Open questions
- **Branch membership without permissions:** not possible today (membership = holding a grant). Needs a membership table if ever wanted.
- **Template name reuse after delete:** `sh_roles.name` has a plain unique index while validation ignores deleted rows, so recreating a deleted template's name fails at the database. Make the index partial (`WHERE deleted_at IS NULL`) when the Permission groups screen is built.
- **Permission groups screen** (template management UI) is not built; templates are managed through the API or seeded defaults.
