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

    public function module(): string
    {
        return 'shared';
    }

    public function group(): string
    {
        return match ($this) {
            self::RoleView, self::RoleManage => 'Roles',
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
        };
    }
}
