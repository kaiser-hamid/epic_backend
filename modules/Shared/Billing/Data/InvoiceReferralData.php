<?php

namespace Modules\Shared\Billing\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\ProhibitedIf;
use Spatie\LaravelData\Attributes\Validation\RequiredUnless;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\Constraints\WhereConstraint;

/**
 * Who sent the patient: a referring doctor, or self-referral (never both). The second doctor and the staff
 * member who brought the patient are optional.
 */
#[MapName(SnakeCaseMapper::class)]
final class InvoiceReferralData extends Data
{
    public function __construct(
        #[RequiredUnless('is_self_referral', 'true'), ProhibitedIf('is_self_referral', 'true')]
        #[Exists('sh_doctors', 'id', withoutTrashed: true, where: new WhereConstraint('is_active', true))]
        public ?int $doctorId,
        public bool $isSelfReferral = false,
        #[Different('doctor_id'), Exists('sh_doctors', 'id', withoutTrashed: true, where: new WhereConstraint('is_active', true))]
        public ?int $secondDoctorId = null,
        #[Max(255)]
        public ?string $remark = null,
        #[Exists('users', 'id')]
        public ?int $referredByStaffId = null,
    ) {}
}
