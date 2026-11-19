<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Phlebotomy;
use Modules\Shared\Auth\Models\User;

/**
 * @extends Factory<Phlebotomy>
 */
final class PhlebotomyFactory extends Factory
{
    protected $model = Phlebotomy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => PatientCase::factory()->state(['service' => CaseService::TherapeuticPhlebotomy]),
            'volume_ml' => 450,
            'performed_at' => now(),
            'performed_by' => User::factory(),
        ];
    }
}
