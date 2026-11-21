<?php

namespace Modules\BloodBank\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Modules\BloodBank\Data\PlannedUnit;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;

/**
 * What the next donor's bag of a case is made into, decided by what the case still needs (billed and not yet
 * reserved or issued). The first recipe whose trigger the case needs is used; every component of the recipe the
 * case still needs goes to the patient, the rest to stock:
 *
 * | Case still needs        | Bag makes             | Patient          | Stock          |
 * |-------------------------|-----------------------|------------------|----------------|
 * | WB                      | WB (whole bag)        | WB               | —              |
 * | PA                      | PA (whole bag)        | PA               | —              |
 * | PLATELET (± PCV, FFP)   | PCV, PLATELET, FFP    | PLATELET (+ PCV) | PCV, FFP rest  |
 * | FP (± PCV)              | PCV, FP               | FP (+ PCV)       | PCV rest       |
 * | PCV or FFP              | PCV, FFP              | the needed ones  | the rest       |
 * | CRYO / PRP / PLASMA     | that component only   | it               | — (not kept)   |
 *
 * The codes are Blood Bank's own `bb_component_types.code` values; a stock component whose type is missing or
 * inactive is left out. An empty plan means the case needs nothing more.
 */
final class BagPlan
{
    /**
     * A unit in one of these statuses counts as the case's: a stock unit held for it, or a unit reserved or issued.
     */
    public const array HOLDING_STATUSES = [UnitStatus::Held, UnitStatus::Reserved, UnitStatus::Issued];

    /**
     * @var list<array{needs: list<string>, makes: list<string>}>
     */
    private const array RECIPES = [
        ['needs' => ['WB'], 'makes' => ['WB']],
        ['needs' => ['PA'], 'makes' => ['PA']],
        ['needs' => ['PLATELET'], 'makes' => ['PCV', 'PLATELET', 'FFP']],
        ['needs' => ['FP'], 'makes' => ['PCV', 'FP']],
        ['needs' => ['PCV', 'FFP'], 'makes' => ['PCV', 'FFP']],
        ['needs' => ['CRYO'], 'makes' => ['CRYO']],
        ['needs' => ['PRP'], 'makes' => ['PRP']],
        ['needs' => ['PLASMA'], 'makes' => ['PLASMA']],
    ];

    /**
     * @return list<PlannedUnit> patient units first, then stock, in recipe order
     */
    public function for(PatientCase $case): array
    {
        $neededCodes = ComponentType::query()
            ->whereKey(array_keys($this->neededCounts($case)))
            ->pluck('code')
            ->all();

        $recipe = collect(self::RECIPES)
            ->first(fn (array $recipe): bool => array_intersect($recipe['needs'], $neededCodes) !== []);

        if ($recipe === null) {
            return [];
        }

        /** @var SupportCollection<string, ComponentType> $types */
        $types = ComponentType::query()
            ->whereIn('code', $recipe['makes'])
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        $units = collect($recipe['makes'])
            ->filter(fn (string $code): bool => $types->has($code))
            ->map(function (string $code) use ($case, $neededCodes, $types): PlannedUnit {
                $forPatient = in_array($code, $neededCodes, true);

                return new PlannedUnit($types[$code], $forPatient, ! $forPatient || $case->keeps_units_in_storage);
            });

        return $units->sortByDesc(fn (PlannedUnit $unit): bool => $unit->forPatient)->values()->all();
    }

    /**
     * Narrows a case query to the cases whose next bag has a plan: a billed component with a recipe is not yet
     * all held, reserved or issued (the list-side match of a non-empty `for()`).
     *
     * @param  Builder<PatientCase>  $cases
     * @return Builder<PatientCase>
     */
    public function whereNeedsUnits(Builder $cases): Builder
    {
        $codes = collect(self::RECIPES)->pluck('needs')->flatten()->unique()->values()->all();

        return $cases->whereHas('components', fn (Builder $component) => $component
            ->whereHas('componentType', fn (Builder $type) => $type->whereIn('code', $codes))
            ->where('quantity', '>', Unit::query()
                ->selectRaw('count(*)')
                ->whereColumn('bb_units.case_id', 'bb_case_components.case_id')
                ->whereColumn('bb_units.component_type_id', 'bb_case_components.component_type_id')
                ->whereIn('bb_units.status', self::HOLDING_STATUSES)));
    }

    /**
     * Units the case was billed for and does not hold yet (held from stock, reserved or issued), per component
     * type id; only types still needed.
     *
     * @return array<int, int>
     */
    public function neededCounts(PatientCase $case): array
    {
        $held = $case->units()
            ->whereIn('status', self::HOLDING_STATUSES)
            ->selectRaw('component_type_id, count(*) as aggregate')
            ->groupBy('component_type_id')
            ->pluck('aggregate', 'component_type_id');

        return $case->components()
            ->pluck('quantity', 'component_type_id')
            ->map(fn (mixed $quantity, int $componentTypeId): int => (int) $quantity - (int) ($held[$componentTypeId] ?? 0))
            ->filter(fn (int $count): bool => $count > 0)
            ->all();
    }
}
