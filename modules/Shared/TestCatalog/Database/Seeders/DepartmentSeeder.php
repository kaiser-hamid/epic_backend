<?php

namespace Modules\Shared\TestCatalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shared\TestCatalog\Models\Department;

/**
 * The diagnostic departments test items are grouped under, with the rooms known so far (printed on the bill's
 * Non Lab Copy). Matched on `code`, so it is safe to re-run.
 */
final class DepartmentSeeder extends Seeder
{
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

        $rooms = ['BB' => '900'];

        foreach ($departments as $code => $name) {
            Department::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'is_active' => true,
                ...(isset($rooms[$code]) ? ['room_no' => $rooms[$code]] : []),
            ]);
        }
    }
}
