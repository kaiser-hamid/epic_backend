<?php

namespace Modules\Shared\TestCatalog\Database\Seeders;

use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\TestItem;
use RuntimeException;

/**
 * 500 demo test items with realistic names and rates, coded per department (`HAEM-001`, `XRAY-012`), about
 * 1 in 30 inactive. Needs `DepartmentSeeder`. Skipped when test items exist.
 */
final class TestItemSeeder extends Seeder
{
    private const COUNT = 500;

    public function run(): void
    {
        if (TestItem::query()->exists()) {
            return;
        }

        $departments = Department::query()->get()->keyBy('code');
        $items = [];

        foreach ($this->catalog() as $departmentCode => $tests) {
            $department = $departments->get($departmentCode)
                ?? throw new RuntimeException("Department {$departmentCode} is missing; run DepartmentSeeder first.");
            $serial = 0;

            foreach ($tests as $name => $rateInTaka) {
                $items[] = [
                    'department_id' => $department->id,
                    'department_name' => $department->name,
                    'code' => sprintf('%s-%03d', $departmentCode, ++$serial),
                    'name' => $name,
                    'rate' => $rateInTaka * 100,
                ];
            }
        }

        if (count($items) < self::COUNT) {
            throw new RuntimeException('The test catalog has fewer than '.self::COUNT.' items.');
        }

        $items = array_slice($items, 0, self::COUNT);

        TestItem::factory()
            ->count(self::COUNT)
            ->state(new Sequence(fn (Sequence $sequence): array => [
                ...$items[$sequence->index],
                'is_active' => fake()->boolean(97),
            ]))
            ->create();
    }

    /**
     * @return array<string, array<string, int>> department code => [test name => rate in taka]
     */
    private function catalog(): array
    {
        return [
            'HAEM' => [
                'Complete Blood Count (CBC)' => 400, 'Haemoglobin (Hb%)' => 150, 'ESR' => 150, 'TC, DC' => 250,
                'Platelet Count' => 200, 'Reticulocyte Count' => 300, 'Peripheral Blood Film (PBF)' => 400,
                'Bleeding Time & Clotting Time' => 200, 'Prothrombin Time (PT)' => 600, 'APTT' => 700, 'INR' => 600,
                'D-Dimer' => 2000, 'Fibrinogen' => 1200, 'Bone Marrow Study' => 4000, 'Hb Electrophoresis' => 2500,
                'Total Eosinophil Count' => 250, 'Total Circulating Eosinophil' => 250, 'G6PD' => 1200,
                'Sickling Test' => 400, 'Osmotic Fragility Test' => 800, 'Malarial Parasite (MP)' => 300,
                'LE Cell' => 500, 'Factor VIII Assay' => 4500, 'Factor IX Assay' => 4500, 'Red Cell Indices' => 300,
                'Packed Cell Volume (PCV)' => 150,
            ],
            'BIOC' => [
                'Fasting Blood Sugar' => 150, 'Random Blood Sugar' => 150, 'Blood Sugar 2 Hours ABF' => 150,
                'Oral Glucose Tolerance Test (OGTT)' => 400, 'HbA1c' => 1000, 'Serum Creatinine' => 350,
                'Blood Urea' => 350, 'Blood Urea Nitrogen (BUN)' => 350, 'Serum Uric Acid' => 400,
                'Serum Electrolytes' => 900, 'Serum Sodium' => 300, 'Serum Potassium' => 300, 'Serum Chloride' => 300,
                'Serum Bicarbonate' => 300, 'Serum Calcium' => 400, 'Ionized Calcium' => 600, 'Serum Phosphate' => 400,
                'Serum Magnesium' => 600, 'Lipid Profile' => 1200, 'Total Cholesterol' => 350, 'Serum Triglyceride' => 400,
                'HDL Cholesterol' => 450, 'LDL Cholesterol' => 450, 'SGPT (ALT)' => 350, 'SGOT (AST)' => 350,
                'Serum Bilirubin (Total)' => 300, 'Serum Bilirubin (Direct)' => 300, 'Alkaline Phosphatase' => 400,
                'Gamma GT' => 600, 'Serum Total Protein' => 400, 'Serum Albumin' => 400, 'Albumin/Globulin Ratio' => 500,
                'Liver Function Test' => 1800, 'Renal Function Test' => 1500, 'Serum Amylase' => 800,
                'Serum Lipase' => 1000, 'CPK' => 800, 'CK-MB' => 1000, 'Troponin I' => 1800, 'Troponin T' => 2200,
                'LDH' => 700, 'Serum Iron' => 800, 'TIBC' => 900, 'Serum Ferritin' => 1200, 'Vitamin D (25-OH)' => 3500,
                'Vitamin B12' => 2200, 'Folic Acid' => 2200, 'Homocysteine' => 2500, 'CRP' => 600, 'hs-CRP' => 1200,
                'Procalcitonin' => 3500, 'Arterial Blood Gas (ABG)' => 2500, 'Serum Lactate' => 1000,
                'Plasma Ammonia' => 1500, 'Urine Microalbumin' => 800, 'Urine Albumin/Creatinine Ratio' => 900,
                '24 Hours Urinary Protein' => 700, 'Urine Creatinine' => 400, 'Creatinine Clearance' => 800,
                'Serum Osmolality' => 1000, 'Serum Lithium' => 1200, 'Serum Digoxin' => 2000,
                'Serum Valproic Acid' => 2500, 'Serum Zinc' => 1500,
            ],
            'MICR' => [
                ...$this->combine('Culture & Sensitivity', [
                    'Urine', 'Blood', 'Stool', 'Sputum', 'Pus', 'Throat Swab', 'Wound Swab', 'CSF', 'Pleural Fluid',
                    'Ascitic Fluid', 'Synovial Fluid', 'High Vaginal Swab', 'Semen', 'Ear Swab', 'Eye Swab',
                    'Catheter Tip', 'Tracheal Aspirate', 'Bile',
                ], 1000, of: true),
                'Blood Culture (Automated)' => 2000, 'Gram Stain' => 300, 'AFB Stain (Sputum)' => 300,
                'Fungal Culture' => 1500, 'KOH Mount' => 400, 'India Ink Preparation' => 400,
            ],
            'IMMU' => [
                'ANA' => 1500, 'Anti-dsDNA' => 2000, 'RA Factor' => 600, 'Anti-CCP' => 2500, 'ASO Titre' => 600,
                'Complement C3' => 1500, 'Complement C4' => 1500, 'Total IgE' => 1800, 'IgG' => 1500, 'IgA' => 1500,
                'IgM' => 1500, 'ANCA (p & c)' => 3500, 'Anti-TPO' => 2000, 'Anti-Thyroglobulin' => 2000,
                'HLA-B27' => 4000, 'Allergy Panel' => 6000, 'Anti-Phospholipid Antibody' => 3000,
                'Anti-Cardiolipin Antibody' => 2500, 'Beta-2 Microglobulin' => 2500,
            ],
            'SERO' => [
                'HBsAg' => 500, 'Anti-HBs' => 800, 'HBeAg' => 1000, 'Anti-HBe' => 1000, 'Anti-HBc (Total)' => 1000,
                'Anti-HBc IgM' => 1200, 'Anti-HCV' => 900, 'HIV I & II' => 800, 'VDRL' => 300, 'TPHA' => 600,
                'Widal Test' => 400, 'Typhidot' => 800, 'Dengue NS1 Antigen' => 1000, 'Dengue IgG/IgM' => 1200,
                'Chikungunya IgM' => 1500, 'Febrile Antigen' => 600, 'Brucella Antibody' => 800,
                'Leptospira IgM' => 1500, 'H. pylori Antibody' => 1000, 'H. pylori Stool Antigen' => 1200,
                ...$this->combine('Toxoplasma', ['IgG', 'IgM'], 1000),
                ...$this->combine('Rubella', ['IgG', 'IgM'], 1000),
                ...$this->combine('CMV', ['IgG', 'IgM'], 1100),
                ...$this->combine('HSV', ['IgG', 'IgM'], 1100),
                'ICT for Malaria' => 500, 'ICT for Kala-azar' => 600, 'ICT for TB' => 600, 'Mantoux Test' => 300,
                'Anti-HEV IgM' => 1200, 'Anti-HAV IgM' => 1200, 'COVID-19 Rapid Antigen' => 500,
            ],
            'HIST' => [
                'Histopathology (Small Biopsy)' => 1500, 'Histopathology (Medium Specimen)' => 2500,
                'Histopathology (Large Specimen)' => 3500,
                ...$this->combine('Histopathology', [
                    'Appendix', 'Gall Bladder', 'Uterus', 'Cervix', 'Breast Lump', 'Thyroid', 'Lymph Node', 'Prostate',
                    'Endometrial Curettage', 'Skin', 'Kidney', 'Liver', 'Stomach', 'Colon', 'Placenta', 'Ovary',
                ], 2500, of: true),
                'Frozen Section' => 5000, 'IHC (ER, PR, HER2)' => 8000, 'Ki-67' => 4000,
            ],
            'CYTO' => [
                ...$this->combine('FNAC', ['Thyroid', 'Breast', 'Lymph Node', 'Salivary Gland', 'Soft Tissue Swelling'], 1500, of: true),
                'USG Guided FNAC' => 3000, 'Pap Smear' => 800, 'Liquid Based Cytology' => 2000,
                ...$this->combine('Fluid Cytology', ['Pleural Fluid', 'Ascitic Fluid', 'CSF'], 900, of: true),
                'Sputum for Malignant Cells' => 800, 'Urine for Malignant Cells' => 800,
            ],
            'CLIN' => [
                'Urine R/M/E' => 200, 'Stool R/M/E' => 200, 'Stool for Occult Blood Test' => 300,
                'Stool for Reducing Substance' => 300, 'Urine Pregnancy Test' => 200, 'Urine for Bence Jones Protein' => 500,
                'Semen Analysis' => 600, 'CSF Study' => 800, 'Pleural Fluid Study' => 800, 'Ascitic Fluid Study' => 800,
                'Synovial Fluid Study' => 800, 'Urine Ketone Bodies' => 200, 'Urine Sugar' => 100,
                'Urine Bile Salt & Bile Pigment' => 200, 'Stool Hanging Drop' => 300,
            ],
            'HORM' => [
                'TSH' => 700, 'FT3' => 700, 'FT4' => 700, 'T3' => 600, 'T4' => 600, 'Prolactin' => 900, 'LH' => 900,
                'FSH' => 900, 'Estradiol (E2)' => 1000, 'Progesterone' => 1000, 'Testosterone' => 1200,
                'Free Testosterone' => 2000, 'Beta-hCG' => 1000, 'AMH' => 3500, 'Cortisol (Morning)' => 1200,
                'Cortisol (Evening)' => 1200, 'ACTH' => 2500, 'Growth Hormone' => 1800, 'Fasting Insulin' => 1500,
                'C-Peptide' => 1800, 'PTH' => 2000, 'DHEA-S' => 2000, '17-OH Progesterone' => 2500, 'SHBG' => 2000,
                'Aldosterone' => 3000, 'Plasma Renin Activity' => 3500, 'Calcitonin' => 3000, 'Thyroglobulin' => 2500,
                'PSA (Total)' => 1200, 'PSA (Free)' => 1500, 'CEA' => 1500, 'CA-125' => 1800, 'CA 19-9' => 1800,
                'CA 15-3' => 1800, 'AFP' => 1500,
            ],
            'MOLB' => [
                'HBV DNA (Quantitative)' => 6000, 'HCV RNA (Quantitative)' => 7000, 'HCV Genotyping' => 12000,
                'HIV RNA Viral Load' => 8000, 'GeneXpert MTB/RIF' => 3500, 'COVID-19 RT-PCR' => 2500,
                'HPV DNA' => 6000, 'BCR-ABL (Qualitative)' => 9000, 'BCR-ABL (Quantitative)' => 12000,
                'JAK2 V617F Mutation' => 8000, 'Thalassemia Mutation Analysis' => 10000, 'HLA Typing' => 15000,
                'Dengue RT-PCR' => 3500, 'CMV DNA PCR' => 6000, 'EBV DNA PCR' => 6000, 'Chikungunya RT-PCR' => 3500,
                'Factor V Leiden Mutation' => 8000,
            ],
            'BB' => [
                'Blood Grouping & Rh Typing' => 150, 'Cross Matching' => 500, 'Antibody Screening' => 1500,
                'Direct Coombs Test' => 600, 'Indirect Coombs Test' => 600, 'Whole Blood Processing' => 1200,
                'Packed Red Cells' => 1500, 'Fresh Frozen Plasma' => 1200, 'Platelet Concentrate' => 1500,
                'Cryoprecipitate' => 1500, 'Apheresis Platelet' => 20000, 'Donor Screening Panel' => 1500,
                'Du Test' => 400, 'Antibody Titre' => 1500, 'Blood Bag Charge' => 600,
            ],
            'XRAY' => [
                'X-Ray Chest P/A View' => 500, 'X-Ray Chest Lateral View' => 500,
                ...$this->combine('X-Ray', [
                    'Skull', 'Cervical Spine', 'Thoracic Spine', 'Lumbo-Sacral Spine', 'Pelvis', 'Hip Joint',
                    'Knee Joint', 'Ankle Joint', 'Foot', 'Shoulder Joint', 'Elbow Joint', 'Wrist Joint', 'Hand',
                    'Femur', 'Tibia-Fibula', 'Humerus', 'Forearm', 'Mandible', 'Clavicle', 'Soft Tissue Neck',
                ], 0, suffixes: ['A/P View' => 500, 'Lateral View' => 500, 'A/P & Lateral View' => 800]),
                'X-Ray PNS O/M View' => 500, 'X-Ray KUB' => 600, 'X-Ray Abdomen Erect' => 600,
                'X-Ray Mastoid (Both)' => 900, 'Intravenous Urogram (IVU)' => 4000, 'Barium Swallow' => 3000,
                'Barium Meal' => 3500, 'Barium Enema' => 4000, 'Hysterosalpingogram (HSG)' => 4000,
                'Micturating Cystourethrogram (MCU)' => 4000, 'Fistulogram' => 3500, 'OPG' => 800,
                'Mammography (Both Breasts)' => 3000,
            ],
            'USG' => [
                'USG of Whole Abdomen' => 2000, 'USG of Upper Abdomen' => 1500, 'USG of Lower Abdomen' => 1500,
                'USG of KUB' => 1500, 'USG of KUB with PVR' => 1800, 'USG Pregnancy Profile' => 1500,
                'USG Anomaly Scan' => 3500, 'USG of Thyroid' => 1500, 'USG of Both Breasts' => 2000,
                'USG of Scrotum' => 1800, 'Transvaginal Sonography (TVS)' => 2500, 'Follicular Study' => 1500,
                'USG of Soft Tissue' => 1200, 'USG of Neck' => 1500, 'USG of Hepatobiliary System' => 1500,
                'Transrectal USG (TRUS)' => 3000, 'USG Musculoskeletal' => 2000,
                ...$this->combine('Color Doppler', [
                    'Carotid', 'Renal Artery', 'Lower Limb Arterial', 'Lower Limb Venous', 'Upper Limb Arterial',
                    'Upper Limb Venous', 'Obstetric', 'Scrotal', 'Portal Vein',
                ], 3500, of: true),
            ],
            'CT' => [
                ...$this->combine('CT Scan', [
                    'Brain', 'Head & Neck', 'PNS', 'Orbit', 'Temporal Bone', 'Chest', 'Whole Abdomen', 'Upper Abdomen',
                    'KUB', 'Pelvis', 'Cervical Spine', 'Thoracic Spine', 'Lumbar Spine', 'Neck',
                ], 0, of: true, suffixes: ['(Plain)' => 4000, '(With Contrast)' => 7000]),
                'HRCT Chest' => 6000, 'CT Urogram' => 9000, 'CT Virtual Colonoscopy' => 12000, 'CT Guided Biopsy' => 10000,
                ...$this->combine('CT Angiogram', ['Brain', 'Coronary', 'Pulmonary', 'Renal', 'Lower Limb', 'Aorta'], 15000, of: true),
            ],
            'MRI' => [
                ...$this->combine('MRI', [
                    'Brain', 'Orbit', 'Pituitary', 'IAM', 'Cervical Spine', 'Dorsal Spine', 'Lumbo-Sacral Spine',
                    'Shoulder Joint', 'Knee Joint', 'Hip Joint', 'Ankle Joint', 'Wrist Joint', 'Elbow Joint', 'Pelvis',
                    'Whole Abdomen', 'Breast', 'Neck', 'Prostate',
                ], 0, of: true, suffixes: ['(Plain)' => 8000, '(With Contrast)' => 12000]),
                'MRI Whole Spine Screening' => 10000, 'MRCP' => 10000, 'MR Angiogram (Brain)' => 12000,
                'MR Venogram (Brain)' => 12000, 'MR Spectroscopy' => 15000, 'MRI Fistulogram' => 12000,
            ],
            'ECG' => [
                'ECG (12 Lead)' => 400, 'ECG Rhythm Strip' => 300, 'Exercise Tolerance Test (ETT)' => 3000,
                'Holter Monitoring (24 Hours)' => 4000, 'Holter Monitoring (48 Hours)' => 6000,
                'Ambulatory BP Monitoring (ABPM)' => 3000,
            ],
            'ECHO' => [
                '2D Echocardiography' => 2500, 'Color Doppler Echocardiography' => 3000, 'Fetal Echocardiography' => 4000,
                'Transesophageal Echocardiography (TEE)' => 6000, 'Stress Echocardiography' => 7000,
                'Paediatric Echocardiography' => 3000,
            ],
            'ENDO' => [
                'Upper GI Endoscopy' => 3500, 'Colonoscopy' => 7000, 'Sigmoidoscopy' => 4000, 'Proctoscopy' => 1500,
                'ERCP' => 20000, 'Bronchoscopy' => 10000, 'Endoscopic Biopsy' => 2000, 'Endoscopic Polypectomy' => 10000,
                'Capsule Endoscopy' => 40000,
            ],
            'EEG' => [
                'EEG (Routine)' => 2500, 'Sleep EEG' => 3500, 'Video EEG' => 8000, 'Nerve Conduction Study (NCS)' => 4000,
                'EMG' => 4000, 'Visual Evoked Potential (VEP)' => 3000, 'BERA' => 3500,
            ],
            'PFT' => [
                'Spirometry' => 1200, 'Spirometry with Bronchodilator' => 1800, 'DLCO' => 3000,
                'Lung Volume Study' => 3000, 'Peak Expiratory Flow Rate' => 300, 'Polysomnography' => 15000,
            ],
        ];
    }

    /**
     * Expands a test family across sites, e.g. "Culture & Sensitivity of Urine" or "MRI of Brain (Plain)".
     *
     * @param  list<string>  $sites
     * @param  array<string, int>  $suffixes  suffix => rate in taka; overrides `$rateInTaka` when given
     * @return array<string, int>
     */
    private function combine(string $prefix, array $sites, int $rateInTaka, bool $of = false, array $suffixes = []): array
    {
        $tests = [];

        foreach ($sites as $site) {
            $base = $of ? "{$prefix} of {$site}" : "{$prefix} {$site}";

            if ($suffixes === []) {
                $tests[$base] = $rateInTaka;

                continue;
            }

            foreach ($suffixes as $suffix => $suffixRate) {
                $tests["{$base} {$suffix}"] = $suffixRate;
            }
        }

        return $tests;
    }
}
