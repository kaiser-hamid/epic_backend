<?php

namespace Modules\Shared\Rbac\Services;

use Illuminate\Support\Collection;
use Modules\Shared\Rbac\Models\Role;

/**
 * Describes a flat permission list in terms of role templates, so the admin sees "Cashier + BB Tech" instead
 * of 30 permission names. A role matches when the user holds every one of its permissions. A matched role that
 * is fully covered by a bigger matched role is hidden. Display only: roles never grant access.
 */
final class RoleMatcher
{
    /**
     * @param  list<string>  $permissions
     * @param  Collection<int, Role>  $roles  with `permissions` loaded
     * @return array{matched_roles: list<array{id: int, name: string}>, other_permissions: list<string>}
     */
    public function match(array $permissions, Collection $roles): array
    {
        $held = array_flip($permissions);

        $matched = $roles
            ->map(fn (Role $role): array => ['role' => $role, 'permissions' => $role->permissions->pluck('name')->all()])
            ->filter(fn (array $candidate): bool => $candidate['permissions'] !== []
                && array_diff($candidate['permissions'], array_keys($held)) === []);

        $matched = $matched->reject(fn (array $candidate): bool => $matched->contains(
            fn (array $other): bool => count($other['permissions']) > count($candidate['permissions'])
                && array_diff($candidate['permissions'], $other['permissions']) === [],
        ));

        $covered = $matched->flatMap(fn (array $candidate): array => $candidate['permissions'])->unique()->all();

        return [
            'matched_roles' => $matched
                ->map(fn (array $candidate): array => ['id' => $candidate['role']->id, 'name' => $candidate['role']->name])
                ->sortBy('name')
                ->values()
                ->all(),
            'other_permissions' => collect($permissions)->diff($covered)->sort()->values()->all(),
        ];
    }
}
