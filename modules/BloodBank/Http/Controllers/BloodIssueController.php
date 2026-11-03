<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\IssueBlood;
use Modules\BloodBank\Actions\PrintCompatibilityReports;
use Modules\BloodBank\Data\BloodIssueLookupData;
use Modules\BloodBank\Data\IssueBloodData;
use Modules\BloodBank\Data\PrintCompatibilityReportsData;
use Modules\BloodBank\Http\Resources\BloodIssueResource;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListPendingBloodIssues;
use Modules\BloodBank\Queries\ShowBloodIssue;
use Modules\Shared\Printing\Data\PdfDocument;

/**
 * The blood issue (Compatibility Test Report) menu. "Issue Blood" and the reprint answer with the report PDF
 * itself (`application/pdf`), not JSON, so the print service can send it straight to the printer; errors are
 * still the usual JSON.
 */
final class BloodIssueController
{
    public function __construct(
        private readonly ShowBloodIssue $showBloodIssue,
        private readonly ListPendingBloodIssues $listPendingBloodIssues,
        private readonly IssueBlood $issueBlood,
        private readonly PrintCompatibilityReports $printCompatibilityReports,
    ) {}

    public function lookup(BloodIssueLookupData $data): BloodIssueResource
    {
        return BloodIssueResource::make($this->showBloodIssue->find($data));
    }

    public function show(PatientCase $case): BloodIssueResource
    {
        return BloodIssueResource::make($this->showBloodIssue->get($case));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listPendingBloodIssues->get($request));
    }

    public function issue(Request $request, PatientCase $case, IssueBloodData $data): PdfDocument
    {
        return $this->issueBlood->handle($request->user(), $case, $data);
    }

    public function printReports(Request $request, PatientCase $case, PrintCompatibilityReportsData $data): PdfDocument
    {
        return $this->printCompatibilityReports->handle($request->user(), $case, $data);
    }
}
