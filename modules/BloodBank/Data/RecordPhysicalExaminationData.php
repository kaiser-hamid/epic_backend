<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\ExaminationDecision;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The physician's pre-donation physical examination form. BP is sent as two numbers (140/90 → 140 and 90);
 * height is optional and in cm (the UI converts feet/inches). Ranges only catch typos; the clinical decision is
 * the physician's and required: `accepted` or `deferred`, nothing else (the form cannot move a donor to another
 * stage). `deferral_reason` is required for a deferral and only allowed then. The examination time is not
 * accepted from the client; the server stamps it.
 */
#[MapName(SnakeCaseMapper::class)]
final class RecordPhysicalExaminationData extends Data
{
    public function __construct(
        #[Between(20, 250), Rule('decimal:0,2')]
        public float $weightKg,
        #[Between(1, 25), Rule('decimal:0,1')]
        public float $hemoglobinGDl,
        #[Between(20, 250)]
        public int $pulseBpm,
        #[Between(50, 300)]
        public int $bpSystolic,
        #[Between(20, 200), Rule('lt:bp_systolic')]
        public int $bpDiastolic,
        #[Between(90, 110), Rule('decimal:0,1')]
        public float $temperatureF,
        public ExaminationDecision $decision,
        #[Between(50, 250), Rule('decimal:0,1')]
        public ?float $heightCm = null,
        public ?string $deferralReason = null,
    ) {}

    /**
     * `required_if` is declared here, not as an attribute: laravel-data skips attribute rules of a nullable
     * property that has a default when its key is missing, so a deferral without the key would pass.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'deferral_reason' => ['nullable', 'string', 'required_if:decision,'.ExaminationDecision::Deferred->value, 'prohibited_unless:decision,'.ExaminationDecision::Deferred->value, 'max:1000'],
        ];
    }
}
