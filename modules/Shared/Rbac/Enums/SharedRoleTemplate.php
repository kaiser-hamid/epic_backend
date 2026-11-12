<?php

namespace Modules\Shared\Rbac\Enums;

use Modules\Shared\Rbac\Contracts\RoleTemplateDefinition;

/**
 * Default Billing and User & Permission role templates, created once by `permissions:sync`; admins may then
 * edit or delete them.
 */
enum SharedRoleTemplate: string implements RoleTemplateDefinition
{
    case Cashier = 'Cashier';
    case BillingViewer = 'Billing viewer';
    case UserAdmin = 'User admin';
    case PermissionAdmin = 'Permission admin';

    public function description(): string
    {
        return match ($this) {
            self::Cashier => 'Register patients, create and print bills',
            self::BillingViewer => 'View the branch\'s bills',
            self::UserAdmin => 'Create and manage staff accounts',
            self::PermissionAdmin => 'Assign branch permissions and manage role templates',
        };
    }

    /**
     * @return list<SharedPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Cashier => [SharedPermission::BillingView, SharedPermission::BillingCreate, SharedPermission::BillingPrint, SharedPermission::PatientView, SharedPermission::PatientCreate],
            self::BillingViewer => [SharedPermission::BillingView],
            self::UserAdmin => [
                SharedPermission::UserView, SharedPermission::UserCreate, SharedPermission::UserUpdate, SharedPermission::UserDeactivate,
                SharedPermission::UserResetPassword, SharedPermission::UserUnlock, SharedPermission::UserForceLogout,
            ],
            self::PermissionAdmin => [SharedPermission::UserView, SharedPermission::UserAssignPermissions, SharedPermission::RoleView, SharedPermission::RoleManage],
        };
    }
}
