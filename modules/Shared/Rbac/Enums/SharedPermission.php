<?php

namespace Modules\Shared\Rbac\Enums;

use Modules\Shared\Rbac\Contracts\PermissionDefinition;

enum SharedPermission: string implements PermissionDefinition
{
    case UserView = 'sh.user.view';
    case UserCreate = 'sh.user.create';
    case UserUpdate = 'sh.user.update';
    case UserDeactivate = 'sh.user.deactivate';
    case UserResetPassword = 'sh.user.reset_password';
    case UserUnlock = 'sh.user.unlock';
    case UserForceLogout = 'sh.user.force_logout';
    case UserAssignPermissions = 'sh.user.assign_permissions';
    case RoleView = 'sh.role.view';
    case RoleManage = 'sh.role.manage';
    case PatientView = 'sh.patient.view';
    case PatientCreate = 'sh.patient.create';
    case BillingView = 'sh.billing.view';
    case BillingCreate = 'sh.billing.create';

    public function module(): string
    {
        return 'shared';
    }

    public function group(): string
    {
        return match ($this) {
            self::RoleView, self::RoleManage => 'Roles',
            self::PatientView, self::PatientCreate => 'Patients',
            self::BillingView, self::BillingCreate => 'Billing',
            default => 'Users',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UserView => 'View users and their branch permissions',
            self::UserCreate => 'Create users',
            self::UserUpdate => 'Edit user details',
            self::UserDeactivate => 'Activate or deactivate users',
            self::UserResetPassword => 'Reset a user\'s password',
            self::UserUnlock => 'Unlock a locked account',
            self::UserForceLogout => 'Log a user out of all sessions',
            self::UserAssignPermissions => 'Assign branch permissions to users',
            self::RoleView => 'View roles and the permission catalog',
            self::RoleManage => 'Create, edit and delete roles',
            self::PatientView => 'Search and view patients',
            self::PatientCreate => 'Register patients',
            self::BillingView => 'View the branch\'s invoices',
            self::BillingCreate => 'Create invoices, registering a new patient on the bill if needed',
        };
    }
}
