<?php

namespace Modules\Shared\Rbac\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The complete permission list the user should hold in one branch. Anything not listed is revoked.
 */
#[MapName(SnakeCaseMapper::class)]
final class SyncBranchPermissionsData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        #[Present]
        public array $permissions,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'permissions.*' => ['string', 'distinct', 'exists:sh_permissions,name'],
        ];
    }
}
