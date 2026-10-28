# Epic Health Care - ERP

Epic ERP system for the diagnostic center, built to gradually replace the current subscription-based vendor system.

The system is a **modular monolith**: one Laravel application, one PostgreSQL database, with strict boundaries between business modules. Modules are delivered one at a time and run alongside the vendor system until each area is fully replaced. The first module is **Blood Bank**.

---

## Table of Contents

1. [Tech Stack](#tech-stack)
2. [Architecture](#architecture)
3. [Directory Structure](#directory-structure)
4. [Inside a Module](#inside-a-module)
5. [Request Lifecycle](#request-lifecycle)
6. [Coding Conventions](#coding-conventions)
7. [API Conventions](#api-conventions)
8. [Database Conventions](#database-conventions)
9. [Vendor System Integration](#vendor-system-integration)
10. [Audit, Security & Access Control](#audit-security--access-control)
11. [Getting Started](#getting-started)
12. [Queues & Scheduler](#queues--scheduler)
13. [Testing & Code Quality](#testing--code-quality)
14. [Adding a New Module](#adding-a-new-module)
15. [Deployment Checklist](#deployment-checklist)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4.26 |
| Framework | Laravel 13 |
| Database | PostgreSQL 18.6 |
| Cache / Queue / Session / Locks | Redis |
| Web server | Nginx + PHP-FPM |
| Queue monitoring | Laravel Horizon |
| DTO & validation | `spatie/laravel-data` |
| List filtering & sorting | `spatie/laravel-query-builder` |
| Authentication | `laravel/sanctum` (SPA cookie sessions for the Next.js frontend) |
| Branch permissions | Custom Shared RBAC (`sh_user_branch_permissions`) |
| PDF generation | `mpdf/mpdf` |
| File storage | Local disk |
| Monitor | `Prometheus & Grafana` (self-hosted) OR `laravel/pulse` |
| Error tracking | `Sentry` (self-hosted) |
| Testing | Pest (feature, unit, architecture tests) |
| Static analysis / formatting | Larastan, Laravel Pint |

---

## Architecture

### Why a modular monolith

- Small in-house team: one deployable unit, no distributed-system overhead.
- Business data is tightly connected (patient → bill → sample → result → report) and needs strong transactional consistency.
- Audit log, RBAC, numbering and notifications are built once and shared by every module.
- Clear module boundaries keep the option open to extract a module later if it is ever needed.

### Modules

| Module | Status | Notes |
|---|---|---|
| Shared (kernel) | In progress | Auth, RBAC, Audit, Patient master, Numbering, External references, Notification |
| Blood Bank | In progress | Donor → donation → unit → component → request → crossmatch → issue |
| Registration & Billing | Planned | Currently handled by vendor system |
| Lab (Pathology) | Planned | Sample tracking, LIS integration, verify / authorize |
| Radiology (Non-Lab) | Planned | ECG, X-ray, USG, CT scan reports |
| Report Delivery | Planned | Merge lab + non-lab reports, delivery tracking |
| Accounts | Planned | |
| HR | Planned | |
| Supply Chain / Inventory | Planned | Central consumables inventory |
| OPD | Planned | Doctor appointments |
| Test Configuration | Planned | Test catalog, prices, reference ranges |

### Boundary rules

These are enforced by architecture tests in CI. A pull request that breaks them must not be merged.

1. A module **owns its tables**. No other module writes to them.
2. A module **never imports another module's `Models`**. Cross-module communication goes through:
   - the other module's **`Contracts`** (public interfaces), or
   - **domain events** (e.g. `BloodUnitIssued`).
3. Only the **Shared** kernel may be used freely by all modules. Keep it small: only things that are truly common.
4. Cross-module reporting uses database views or dedicated read models, not ad-hoc joins inside module code.

---

## Directory Structure

```
.
├── app/                      # App-wide bootstrap only; business code lives in modules/
├── bootstrap/
│   ├── app.php               # Middleware, unified exception rendering
│   └── providers.php         # Registers module service providers
├── config/
├── database/                 # Global seeders only
├── modules/
│   ├── Shared/
│   │   ├── Audit/
│   │   ├── Auth/
│   │   ├── ExternalReference/
│   │   ├── Notification/
│   │   ├── Numbering/
│   │   ├── Patient/
│   │   └── Support/          # Base DTOs, base exceptions, value objects, helpers
│   └── BloodBank/
├── resources/
├── routes/                   # Only global routes (health check etc.)
├── storage/
└── tests/
    └── Arch/                 # Global architecture tests
```

`composer.json` autoload:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "modules/"
    }
}
```

---

## Inside a Module

```
modules/BloodBank/
├── Actions/                  # One class per use case
├── Contracts/                # Public interfaces other modules may depend on
├── Data/                     # Input / output DTOs (laravel-data), includes validation
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   └── Seeders/
├── Enums/
├── Events/
├── Exceptions/               # Domain exceptions with error codes
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Resources/            # API response shape
├── Jobs/
├── Listeners/
├── Models/
├── Policies/
├── Providers/                # Module service provider
├── Queries/                  # Read-side query classes (laravel-query-builder)
├── Resources/views/          # Blade views for PDFs / prints
├── Routes/api.php
├── Services/                 # Reusable domain logic and integrations
└── Tests/
    ├── Feature/
    └── Unit/
```

| Folder | Responsibility | Must not |
|---|---|---|
| `Http/Controllers` | Receive request, call one Action or Query, return a Resource | Contain business logic or queries |
| `Data` | Typed input, validation rules, mapping snake_case ↔ camelCase | Touch the database |
| `Actions` | One use case. Transactions, business rules, event dispatch | Have more than one public method |
| `Services` | Logic reused by several Actions; external integrations | Represent a user-facing use case |
| `Queries` | List / search / filter / sort reads | Write data |
| `Http/Resources` | Response shape | Expose internal fields (file paths, secrets) |
| `Models` | Relationships, casts, scopes | Hold business workflows |
| `Policies` | Permission and record-level authorization | |
| `Events` / `Listeners` | Side effects (SMS, PDFs, cross-module reactions) | Run synchronously when slow; use queues |

---

## Request Lifecycle

```
HTTP request
  → Route (api/...)
  → Middleware (auth, audit context)
  → Controller
      → Data object (validated automatically by laravel-data)
      → Permission check
      → Action::handle(Data)
          → DB::transaction(...)
          → Services (if needed)
          → Event::dispatch (after commit)
      → Resource
  → JSON response

Queued listeners (after commit)
  → SMS / WhatsApp / PDF / cross-module reactions
```

Minimal controller:

```php
public function store(CreateBloodRequestData $data, CreateBloodRequest $action): JsonResponse
{
    return BloodRequestResource::make($action->handle($data))
        ->response()
        ->setStatusCode(201);
}
```

---

## Coding Conventions

### Actions

- One public method: `handle()`.
- Dependencies via constructor injection; private helper methods are allowed.
- Named as verb + noun: `RegisterDonor`, `IssueBloodUnit`, `CancelBloodRequest`.
- Own the transaction: wrap writes in `DB::transaction()`.
- An Action may call another Action.

### Services

- Reusable domain logic or external integration: `CompatibilityChecker`, `InvoicePdfService`.
- External providers (SMS, WhatsApp, LIS) are always behind an interface in `Contracts/` so they can be swapped and faked in tests.

### Data objects

- Extend `Spatie\LaravelData\Data`, use `#[MapName(SnakeCaseMapper::class)]`.
- Validation lives in the Data object (attributes or `rules()`).
- Partial updates use `Optional|type` properties.
- Never pass raw request arrays into Actions.

### Events

- Events that follow a database write implement `ShouldDispatchAfterCommit`.
- Each side effect is its own queued listener, so one failure does not block another.
- Listeners declare `$queue`, `$tries` and `$backoff`.

### General

- `final` classes by default.
- PHP Enums for every status, type and category; cast them on the model. No magic strings.
- No generic repository layer. Use Eloquent directly in Actions and dedicated Query classes for reads.
- `env()` is only called inside `config/` files; application code uses `config()`.
- Money is stored as integer **paisa** (`bigint`) and formatted at the edges. Never use `float` for amounts.
- Prices and totals are always calculated on the server, never trusted from the client.
- `Model::shouldBeStrict()` is enabled outside production.
- Timestamps are stored and displayed in `Asia/Dhaka`.

---

## API Conventions

- Base path: `/api/{module}/{resource}` e.g. `/api/blood-bank/requests`
- JSON keys: `snake_case`
- Dates: ISO 8601 (`2026-10-21T10:30:00+06:00`)

### Success response

Single resource:

```json
{
  "data": {
    "id": 101,
    "request_no": "BB-REQ-000101",
    "status": "registered",
    "status_label": "Registered"
  }
}
```

Collection (paginated):

```json
{
  "data": [ ... ],
  "meta": { "page_index": 0, "page_size": 20, "page_count": 7, "row_count": 134 }
}
```

`meta` is the server-side pagination state of the shadcn/ui data table (TanStack Table): `page_index` is zero-based. Requests page with Laravel's one-based `page` and `per_page` (send `page_index + 1`).

### Error response

All errors are rendered centrally in `bootstrap/app.php`.

```json
{
  "message": "Unit BB-U-00045 is not compatible with patient blood group.",
  "code": "BB_INCOMPATIBLE_UNIT"
}
```

Validation errors (422):

```json
{
  "message": "The customer phone field format is invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {
    "customer_phone": ["Valid Bangladeshi mobile number দিন (01XXXXXXXXX)।"]
  }
}
```

Every domain exception extends `Modules\Shared\Support\Exceptions\DomainException` and defines `errorCode()` and `status()`. The frontend reacts to `code`, not to `message`.

### Filtering & sorting

List endpoints use `spatie/laravel-query-builder`:

```
GET /api/blood-bank/units?filter[status]=available&filter[blood_group]=O_POS&sort=expires_at&include=component
```

Only explicitly allowed filters, sorts and includes are accepted.

---

## Database Conventions

- Tables are prefixed by module:

  | Module | Prefix | Example |
  |---|---|---|
  | Shared | `sh_` | `sh_patients`, `sh_audit_logs` |
  | Blood Bank | `bb_` | `bb_donors`, `bb_units` |
  | Lab | `lab_` | `lab_samples` |
  | Billing | `bill_` | `bill_invoices` |

- Use `timestampsTz()` / `timestampTz()` (PostgreSQL `timestamptz`).
- Use `jsonb` for snapshots and flexible metadata, never for data that is queried or joined regularly.
- Foreign keys are always declared with constraints.
- Business records are never hard-deleted: use soft deletes or status changes (`cancelled`, `discarded`).
- Medical results and reports are versioned: an amendment creates a new version with a reason; the previous version is kept.
- Human-facing numbers (invoice no, request no, unit no) come from the Shared **Numbering** service, never from auto-increment IDs.

---

## Vendor System Integration

The existing vendor system has no API or database access. Until it is replaced:

- Bills for Blood Bank are still created in the vendor system.
- The vendor invoice barcode is scanned into our system and stored in `sh_external_references`:

  | Column | Example |
  |---|---|
  | `source_system` | `vendor_v1` |
  | `ref_type` | `invoice`, `patient_id` |
  | `ref_value` | scanned barcode value |
  | `entity_type` / `entity_id` | the linked record in our system |

- If the vendor patient ID is printed on the invoice, it is captured too, to simplify patient merging during migration.
- Patients are registered in our own Shared **Patient** master, which becomes the primary record once the vendor system is retired.
- Bills cancelled or refunded in the vendor system are caught by the **daily reconciliation** against the vendor's exported invoice report.

---

## Audit, Security & Access Control

### Audit log

- Every create / update / delete records: actor, action, entity, old values, new values, IP, user agent, timestamp.
- Also logged: login / logout, failed logins, report print and reprint, exports, safety overrides (with mandatory reason).
- Audit records are append-only.

### Access control

- Login is username + password + branch. The session works in that one branch; switching branch means logging in again.
- Permissions are granular per action (e.g. `bb.request.create`, `bb.unit.issue`) and granted **per branch**: a grant in Branch A gives nothing in Branch B.
- Roles are admin-UI templates only (picking "Cash" pre-checks its permissions). They are never assigned to users and never checked.
- Super admin (IT/Developer only) bypasses checks and is set only with `php artisan auth:super-admin {username} [--revoke]`.
- Permission names are backed enums per module, registered with `PermissionRegistry`; `php artisan permissions:sync` mirrors them into `sh_permissions`.
- Record-level rules (e.g. an issued unit cannot be edited) live in Policies.

### Security basics

- Rate limiting on authentication and public endpoints.
- Secrets only in `.env` on the server; `.env` is never committed.
- Production server reachable remotely only through VPN.

---

## Getting Started

### Prerequisites

- PHP 8.4 with extensions: `pdo_pgsql`, `pgsql`, `redis`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `gd`
- Composer 2
- PostgreSQL 18.6
- Redis

### Setup

```bash
git clone <repo-url> diagnostic-erp
cd diagnostic-erp

composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:

```dotenv
APP_NAME="Diagnostic ERP"
APP_ENV=local
APP_TIMEZONE=Asia/Dhaka
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=diagnostic_erp
DB_USERNAME=erp
DB_PASSWORD=secret

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
# database: the sessions table lets users see and revoke their devices
SESSION_DRIVER=database

SMS_URL=
SMS_API_KEY=
SMS_SENDER_ID=

WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_API_VERSION=
WHATSAPP_INVOICE_TEMPLATE=
```

Create the database and run migrations:

```bash
createdb diagnostic_erp
php artisan migrate --seed
```

Run locally:

```bash
php artisan serve
php artisan horizon          # queue workers
php artisan schedule:work    # scheduler
```

---

## Queues & Scheduler

### Queues

| Queue | Used for | Priority |
|---|---|---|
| `documents` | PDF generation, WhatsApp documents | Hight |
| `default` | Everything else | Normal |

Worker order (also configured in Horizon):

```bash
php artisan queue:work redis --queue=sms,documents,default
```

In production, Horizon runs under Supervisor and is restarted on every deploy (`php artisan horizon:terminate`).

### Scheduled tasks

| Task | Frequency |
|---|---|
| Vendor invoice reconciliation report | Daily |
| Blood unit expiry alerts | Every hour |
| Database backup | Daily (plus continuous WAL archiving) |
| Prune old Horizon / temp files | Daily |

---

## Testing & Code Quality

```bash
php artisan test              # Pest: feature, unit and architecture tests
./vendor/bin/phpstan analyse  # Larastan
./vendor/bin/pint             # Code style
```

### Architecture tests

Module boundaries are enforced with Pest:

```php
arch('blood bank does not use billing internals')
    ->expect('Modules\BloodBank')
    ->not->toUse('Modules\Billing\Models');

arch('actions are final')
    ->expect('Modules\*\Actions')
    ->toBeFinal();
```

### CI gates

Every pull request must pass: Pint, Larastan, all Pest tests. Safety-critical logic (blood compatibility, numbering, billing totals) must have unit tests.

### Test rules

- External services (SMS, WhatsApp, LIS) are faked through their interfaces; tests never call real providers.
- Tests use a separate PostgreSQL database; never run against production data.
- Staging uses anonymized data only.

---

## Adding a New Module

1. Create `modules/{ModuleName}/` with the standard folder layout.
2. Create `{ModuleName}ServiceProvider` that loads migrations, routes, views and registers event listeners.
3. Register the provider in `bootstrap/providers.php`.
4. Choose a table prefix and add it to the [Database Conventions](#database-conventions) table.
5. Define the module's public `Contracts` before any other module depends on it.
6. Add a backed permission enum implementing `PermissionDefinition`, register it with `PermissionRegistry` in the provider, and run `php artisan permissions:sync`.
7. Add architecture tests for its boundaries.
8. Update the [Modules](#modules) table in this README.

---

## Deployment Checklist

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan optimize` (config, route, event, view cache)
- [ ] `php artisan horizon:terminate` (workers restart with new code)
- [ ] Verify the latest backup exists and a restore was tested this month
- [ ] Check Sentry and Horizon dashboards after deploy

### Backups

- Daily full backup (`pg_dump`) plus WAL archiving for point-in-time recovery.
- Copies kept on-site and off-site (3-2-1 rule).
- Restore tested monthly.

---

## License

Proprietary. Internal use only.
