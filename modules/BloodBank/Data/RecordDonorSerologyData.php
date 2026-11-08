<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\MalariaResult;
use Modules\BloodBank\Enums\ScreeningResult;
use Modules\BloodBank\Enums\SerologyDecision;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\Shared\Patient\Enums\BloodGroup;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The donor serological investigation form: the blood group, the five screening results (the malarial parasite
 * test is `not_found` / `found`, the others `non_reactive` / `reactive`) and the physician's required decision
 * (`accepted` / `deferred`). `deferral_reason` is required for a deferral and only allowed then. `amendment_reason` is needed when a
 * saved result is changed (checked by the action, which knows the saved values).
 */
#[MapName(SnakeCaseMapper::class)]
final class RecordDonorSerologyData extends Data
{
    public function __construct(
        public BloodGroup $bloodGroup,
        public ScreeningResult $hbsag,
        public ScreeningResult $antiHcv,
        public ScreeningResult $antiHiv,
        public ScreeningResult $syphilis,
        public MalariaResult $malarialParasite,
        public SerologyDecision $decision,
        public ?string $deferralReason = null,
        public ?string $amendmentReason = null,
    ) {}

    /**
     * Rules of nullable keys are declared here: laravel-data skips attribute rules of a nullable property that
     * has a default when its key is missing.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'deferral_reason' => ['nullable', 'string', 'required_if:decision,'.SerologyDecision::Deferred->value, 'prohibited_unless:decision,'.SerologyDecision::Deferred->value, 'max:1000'],
            'amendment_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The result value stored for each test, keyed by test code.
     *
     * @return array<string, string>
     */
    public function results(): array
    {
        return [
            SerologyTest::BloodGroup->value => $this->bloodGroup->value,
            SerologyTest::Hbsag->value => $this->hbsag->value,
            SerologyTest::AntiHcv->value => $this->antiHcv->value,
            SerologyTest::AntiHiv->value => $this->antiHiv->value,
            SerologyTest::Syphilis->value => $this->syphilis->value,
            SerologyTest::MalarialParasite->value => $this->malarialParasite->value,
        ];
    }

    /**
     * Whether any screening test is reactive, or the malarial parasite test is found.
     */
    public function hasReactiveResult(): bool
    {
        return $this->malarialParasite === MalariaResult::Found
            || in_array(ScreeningResult::Reactive, [$this->hbsag, $this->antiHcv, $this->antiHiv, $this->syphilis], true);
    }
}
