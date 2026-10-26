<?php

namespace Modules\Shared\Rbac\Services;

use BackedEnum;
use InvalidArgumentException;
use Modules\Shared\Rbac\Contracts\PermissionDefinition;

/**
 * Collects every module's permission enum. Modules register theirs from their service provider.
 */
final class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition&BackedEnum>
     */
    private array $permissions = [];

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
}
