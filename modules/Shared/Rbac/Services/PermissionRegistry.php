<?php

namespace Modules\Shared\Rbac\Services;

use BackedEnum;
use InvalidArgumentException;
use Modules\Shared\Rbac\Contracts\PermissionDefinition;
use Modules\Shared\Rbac\Contracts\RoleTemplateDefinition;

/**
 * Collects every module's permission enum and default role templates. Modules register theirs from their
 * service provider.
 */
final class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition&BackedEnum>
     */
    private array $permissions = [];

    /**
     * @var array<string, RoleTemplateDefinition&BackedEnum>
     */
    private array $roleTemplates = [];

    /**
     * @param  class-string<PermissionDefinition&BackedEnum>  $enum
     */
    public function register(string $enum): void
    {
        if (! is_subclass_of($enum, PermissionDefinition::class) || ! is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("{$enum} must be a backed enum implementing PermissionDefinition.");
        }

        foreach ($enum::cases() as $permission) {
            $this->permissions[(string) $permission->value] = $permission;
        }
    }

    /**
     * @param  class-string<RoleTemplateDefinition&BackedEnum>  $enum
     */
    public function registerRoleTemplates(string $enum): void
    {
        if (! is_subclass_of($enum, RoleTemplateDefinition::class) || ! is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("{$enum} must be a backed enum implementing RoleTemplateDefinition.");
        }

        foreach ($enum::cases() as $template) {
            $modules = array_unique(array_map(fn (PermissionDefinition $permission): string => $permission->module(), $template->permissions()));

            if (count($modules) !== 1) {
                throw new InvalidArgumentException("Role template {$template->value} must use permissions of exactly one module.");
            }

            $this->roleTemplates[(string) $template->value] = $template;
        }
    }

    /**
     * @return list<PermissionDefinition&BackedEnum>
     */
    public function all(): array
    {
        return array_values($this->permissions);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->permissions);
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    /**
     * @return (PermissionDefinition&BackedEnum)|null
     */
    public function find(string $name): ?PermissionDefinition
    {
        return $this->permissions[$name] ?? null;
    }

    /**
     * The optional module a permission belongs to, or null when every branch has its module (or it is unknown).
     */
    public function optionalModuleOf(string $name): ?string
    {
        $permission = $this->find($name);

        return $permission !== null && $permission->isOptionalModule() ? $permission->module() : null;
    }

    /**
     * @return list<RoleTemplateDefinition&BackedEnum>
     */
    public function roleTemplates(): array
    {
        return array_values($this->roleTemplates);
    }
}
