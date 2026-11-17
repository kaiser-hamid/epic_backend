<?php

namespace Modules\Shared\TestCatalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\TestCatalog\Enums\DepartmentType;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\DepartmentRoom;

/**
 * The diagnostic departments test items are grouped under, lab or non-lab (imaging and Blood Bank), and the
 * non-lab rooms known so far per branch (printed on the bill's Non-Lab Copy). Branches that do not exist yet are
 * skipped. Matched on `code`, so it is safe to re-run.
 */
final class DepartmentSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const array NON_LAB = ['BB', 'XRAY', 'USG', 'CT', 'MRI', 'ECG', 'ECHO', 'ENDO', 'EEG', 'PFT'];

    /**
     * @var array<string, array<string, string>> branch code => [department code => room no]
     */
    private const array ROOMS = [
        'NE' => ['BB' => '900'],
    ];

    public function run(): void
    {
        $departments = [
            'HAEM' => 'Haematology',
            'BIOC' => 'Biochemistry',
            'MICR' => 'Microbiology',
            'IMMU' => 'Immunology',
            'SERO' => 'Serology',
            'HIST' => 'Histopathology',
            'CYTO' => 'Cytopathology',
            'CLIN' => 'Clinical Pathology',
            'HORM' => 'Hormone',
            'MOLB' => 'Molecular Biology',
            'BB' => 'Blood Bank',
            'XRAY' => 'X-Ray',
            'USG' => 'Ultrasonography',
            'CT' => 'CT Scan',
            'MRI' => 'MRI',
            'ECG' => 'ECG',
            'ECHO' => 'Echocardiography',
            'ENDO' => 'Endoscopy',
            'EEG' => 'EEG',
            'PFT' => 'Pulmonary Function Test',
        ];

        foreach ($departments as $code => $name) {
            Department::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => in_array($code, self::NON_LAB, true) ? DepartmentType::NonLab : DepartmentType::Lab,
                'is_active' => true,
            ]);
        }

        foreach (self::ROOMS as $branchCode => $rooms) {
            $branch = Branch::query()->where('code', $branchCode)->first();

            if ($branch === null) {
                continue;
            }

            foreach ($rooms as $departmentCode => $roomNo) {
                DepartmentRoom::query()->updateOrCreate([
                    'branch_id' => $branch->id,
                    'department_id' => Department::query()->where('code', $departmentCode)->value('id'),
                ], ['room_no' => $roomNo]);
            }
        }
    }
}
