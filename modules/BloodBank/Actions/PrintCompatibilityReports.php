<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\PrintCompatibilityReportsData;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\CrossmatchNotRecorded;
use Modules\BloodBank\Exceptions\UnitNotInCase;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\CompatibilityReportBuilder;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Prints the Compatibility Test Report again for units of this case, from their current crossmatch, one page per
 * unit in the requested order. Every page is logged; a reprint without a reason rejects the whole batch.
 */
final class PrintCompatibilityReports
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly CompatibilityReportBuilder $reports,
    ) {}

    /**
     * @throws UnitNotInCase
     * @throws CrossmatchNotRecorded
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, PatientCase $case, PrintCompatibilityReportsData $data): PdfDocument
    {
        $units = Unit::query()
            ->whereKey($data->unitIds)
            ->where('case_id', $case->id)
            ->with(['crossmatches' => fn (HasMany $query) => $query->where('case_id', $case->id)->where('is_current', true)])
            ->get()
            ->keyBy('id');

        if ($units->count() !== count($data->unitIds)) {
            throw new UnitNotInCase;
        }

        $crossmatches = new EloquentCollection(array_map(
            fn (int $unitId): Crossmatch => $units[$unitId]->crossmatches->sortByDesc('version')->first() ?? throw new CrossmatchNotRecorded,
            $data->unitIds,
        ));

        return DB::transaction(function () use ($actor, $case, $data, $crossmatches): PdfDocument {
            $isReprint = [];

            foreach ($crossmatches as $crossmatch) {
                $isReprint[$crossmatch->id] = $this->printLogger
                    ->record(PrintDocumentType::CompatibilityReport, $crossmatch, $actor, $data->reprintReason)
                    ->is_reprint;
            }

            return $this->reports->build($case, $crossmatches, $isReprint);
        });
    }
}
