<?php

namespace Modules\Shared\Branch\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * `modules` lists the optional modules (e.g. "blood-bank") the branch runs; it replaces the current list.
 * Leave it out to keep the list as it is.
 */
#[MapName(SnakeCaseMapper::class)]
final class BranchData extends Data
{
    /**
     * @param  Optional|list<string>  $modules
     */
    public function __construct(
        #[Regex('/^[A-Z]{2}$/'), Unique('sh_branches', 'code', ignore: new RouteParameterReference('branch', 'id', nullable: true))]
        public string $code,
        #[Max(255)]
        public string $name,
        public bool $isActive = true,
        #[Max(1000)]
        public ?string $address = null,
        #[Max(20)]
        public ?string $phone = null,
        public Optional|array $modules = new Optional,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'modules.*' => ['string', 'distinct', Rule::exists('sh_permissions', 'module')->where('module_is_optional', true)],
        ];
    }

    /**
     * The branch's own columns, without `modules`.
     *
     * @return array<string, mixed>
     */
    public function branchColumns(): array
    {
        return $this->except('modules')->toArray();
    }
}
