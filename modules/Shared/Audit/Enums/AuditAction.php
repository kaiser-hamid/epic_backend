<?php

namespace Modules\Shared\Audit\Enums;

enum AuditAction: string
{
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case AccountLocked = 'auth.locked';
    case Logout = 'auth.logout';
    case PasswordChanged = 'auth.password_changed';
    case SessionRevoked = 'auth.session_revoked';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserPasswordReset = 'user.password_reset';
    case UserUnlocked = 'user.unlocked';
    case UserForcedLogout = 'user.force_logout';
    case UserBranchPermissionsSynced = 'user.branch_permissions_synced';
    case SuperAdminGranted = 'user.super_admin_granted';
    case SuperAdminRevoked = 'user.super_admin_revoked';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case BranchCreated = 'branch.created';
    case BranchUpdated = 'branch.updated';
    case PatientRegistered = 'patient.registered';
    case InvoiceCreated = 'billing.invoice_created';
}
