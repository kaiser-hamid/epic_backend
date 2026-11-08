# Blood Bank: what data goes where

Personal reference. Follows one patient case from registration to an issued unit, naming the table and columns written at each step. Based on the migrations as they stand (2026-10-25).

## The big picture

```
sh_patient_master ─┐
                   ├─> sh_billing ──> sh_billing_items (what was billed)
                   │       └────────> sh_billing_referrals (referring doctor = "Name of consultant")
                   │
                   └─> bb_cases ──┬─> bb_case_components (what the patient needs: 2 × PCV, 1 × FP)
                                  ├─> bb_samples (subject = patient) ──> bb_test_results (ABO, Rh)
                                  ├─> bb_crossmatches (patient sample × unit → Compatibility Test Report)
                                  ├─> bb_units.case_id (units reserved for / issued to this case)
                                  │
                                  └─> bb_case_donors <── bb_donors (person, reused across cases; optional: stock units need none)
                                         │   status: registered → fit (physical exam) → eligible (serology) → collected; deferred at either check
                                         ├─> bb_donor_screenings (weight, height, Hb, pre/post vitals)
                                         ├─> bb_samples (subject = donor) ──> bb_test_results (serology)
                                         └─> bb_collections (one bag, bag_no)
                                                └─> bb_units (whole blood, then components via parent_unit_id)
                                                       └─> bb_unit_movements (every status / location change, with case_id)

sh_print_logs     ← every label / report print, for samples, bags, units, cases
bb_consumable_*   ← separate stock ledger for bags, reagents, etc.
```

Shared tables (`sh_`) are owned by Shared. Blood Bank only stores their IDs (`patient_master_id`, `billing_id`) and never writes to them.

## Build status and menus

All APIs are under `/api/blood-bank`.

| Menu | Steps | Status | APIs |
|---|---|---|---|
| **Cases** (Register Case, Case List) | 1, 2, 4.1–4.2 | Done | `GET/POST cases`, `GET cases/{case}` |
| **Donors** | 4.1 | Done | `GET donors`, `GET donors/lookup`, `GET donors/{donor}` |
| **Sample Collection** (tabs: Collection, Pending, Collected) | 3.1–3.2, 5.2 | Done | see below |
| **Donor Examination** (physician: pre-donation physical exam + Accept / Defer on one form) | 5.1 | Done | see step 5 |
| **Donor Serology** (blood group + screening results and Accept / Defer on one form) | 5.3–5.4 | Done | see step 5 |
| **Test Results** (patient ABO, Rh; review, amend) | 3.3–3.5 | Next | |
| **Add donor** (Cases page button: invoice lookup, then one donor per save, billed separately) | 4.3 | Done | `GET add-donor?invoice_no=`, `POST cases/{case}/donors` |
| **Settings → Test Definitions** | 0 | Next | |
| **Blood Collection** (tabs: Collection, Pending, Collected; bag + bag label) | 6.1–6.2, 6.4 | Done | see step 6 |
| **Post-donation vitals** | 6.3 | Done | on the Blood Issue form (step 9) |
| Bag stock issue | 6.5 | Later | |
| **Component Preparation** (tabs: Preparation, Pending, Units; whole blood or components, storage, component labels) | 7 | Done | see step 7 |
| **Blood Issue / Compatibility Test Report** (patient blood group, crossmatch per unit, post-donation vitals, issue, supply time, close case, report PDF) | 3.3, 6.3, 8, 9 | Done | see step 9 |
| Return / release a reservation, stock unit reservation | 9 | Later | |
| **Inventory / Stock** (units by location, move, quarantine, discard) | 9 | Later | |
| **Expiry Dashboard** | 9 | Later | |
| **Consumables** (items, receive / issue / adjust, ledger) | 10 | Later | |
| **Settings → Component Types, Storage Locations** | 0 | Later | `GET component-types`, `GET storage-locations` (pickers; locations are per branch) exist |

Sample Collection APIs (permissions `bb.sample.view` / `bb.sample.collect` / `bb.sample.print_label`, branch scoped):

| API | Screen |
|---|---|
| `GET sample-collection?invoice_no=` | Collection tab: invoice scan → case, patient, invoice, one row per subject (patient, then donors) with its latest sample, `is_collectable`, `label_print_count`. The invoice no is used until the vendor system is replaced; the case no lookup is kept commented out (`SampleCollectionLookupData`, `ShowSampleCollection`). |
| `GET cases/{case}/sample-collection` | Same screen, opened from the Pending tab or a case. |
| `POST cases/{case}/samples` | "Collect": `subjects: [{subject_type, case_donor_id}]`, optional `collected_at`. All or nothing; returns the screen plus `collected_sample_ids` (201). The new samples' labels are logged as printed (first print) and each of those samples carries `label_command` for the print service; printing them again is a reprint. |
| `POST cases/{case}/sample-labels` | "Label" / "Label (All)": `sample_ids`, `reprint_reason` (required for a reprint). Returns label content per sample with `label_command`. |

Sample label: `label_command` is the GoDEX EZPL command from `resources/label/bb_barcode_label.ezpx` (Shared `LabelRenderer`, CRLF line endings), each `^Vxx` replaced with its value cut to the declared length; text fields of centred variables (`jc..`) get the x that centres the value on the label, measured with the font's character widths, dropping to a smaller font when too wide: `V00` barcode = sample no; `V01` `{invoice no without its 2-letter branch code}|{sample no}` (the additional donor's own invoice, else the case's); `V02` the test item codes billed on that invoice; `V03` `{M|F|O}-{age}Y {print time d/m/Y h:i A}`.
| `GET sample-collection/pending` | Pending tab: open cases still waiting for a sample from the patient or a donor who was not deferred. |
| `GET samples` | Collected tab and barcode scan (`filter[sample_no]`, `filter[case_no]`, `filter[status]`, `filter[subject_type]`, `filter[collected_from/to]`). |

Collection rules: a subject is collectable while the case is `open` and it has no sample (or only `rejected` ones); a donor also needs to be `fit` (accepted at the physical exam, step 5.1). Error codes are `CASE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_DEFERRED`, `DONOR_NOT_FIT` (donor still `registered`), `SAMPLE_ALREADY_COLLECTED` (409), `SAMPLE_NOT_IN_CASE` and `REPRINT_REASON_REQUIRED`.

## Step by step

### 0. Setup (master data, once)

| Table | What goes in |
|---|---|
| `sh_departments`, `sh_test_items` | Billable catalog: what the counter can charge for (code, name, `rate` in paisa). |
| `sh_doctors` | Referring doctors / consultants. |
| `bb_test_definitions` | The Blood Bank lab tests and their result fields: `result_type` (text / number / option), `unit`, `reference_range`, `method` (printed on the report, e.g. "Immunochromatographic Test", "ICT method"), `options` (for option lists), `applies_to` (patient / donor / both). Inserted by the migration `insert_bb_serology_test_definitions` (codes in `SerologyTest`; the form depends on them, so they are not a seeder): `BLOOD_GROUP` (one combined option, the `BloodGroup` values `A_POS` … `O_NEG`), and donor screening `HBSAG`, `ANTI_HCV`, `ANTI_HIV` (1 & 2), `SYPHILIS` (option `non_reactive` / `reactive`). The screening tests get `method` = "Immunochromatographic Test" from the migration `set_bb_serology_test_methods` (blood group has none). Malaria is not on the form. |
| `bb_component_types` | WB, PCV, FP, CRYO, PLATELET, PLASMA… with `shelf_life_hours` (drives `expires_at`) and `default_location_type`. |
| `bb_storage_locations` | Fridges, freezers, agitators (`type`), with temperature range. |
| `bb_consumables` | Stock items (bags, reagents) with `minimum_stock`. |

Steps 1, 2 and 4.1–4.2 are one form and one request: `POST /api/blood-bank/cases` (`RegisterCase`). Blood Bank is a branch (code `NB`). The action calls Shared's `InvoiceIssuer` contract for step 1, then writes steps 2 and 4 in the same transaction; it reads patient and invoice details back through `PatientDirectory` / `InvoiceDirectory`.

### 1. Patient registration and billing (Shared)

1. Find or create the patient → `sh_patient_master` (`patient_no` = `P{yyMMdd}{0001}`, global, daily reset; name, age, gender, blood group, phone…).
2. Counter bills the blood bank service → `sh_billing` (`invoice_no` = `{branch code}{yyMMdd}{0001}`, e.g. `NB2610280001`, per branch, daily reset; `branch_id` of the issuing branch, `amount` total in paisa, `status`), lines in `sh_billing_items` (`test_item_id`, `quantity`, `rate` snapshot, `amount`), referral in `sh_billing_referrals` (doctor, second doctor, or self-referral). The referring doctor here is the register's **"Name of consultant"**; Blood Bank does not store it again.

### 2. Open the case (patient register)

1. `bb_cases`: `case_no` (`BC{yyMM}{000001}`, global, monthly reset), `patient_master_id`, `billing_id`, `branch_id` (session branch; lists and the case Policy are scoped to it), `care_type` (**"Care of"**: `daycare` = used in our daycare unit, `out` = takeaway), `transfusion_reason` (**"Cause of transfusion"**, nullable), `reference_note` (any other note), `status = open`, `created_by`.
2. Requested components (**"Component"** column) → `bb_case_components`, one row per component type: `case_id`, `component_type_id`, `quantity` (e.g. PCV × 2 and FP × 1 are two rows). Unique per (case, component type).

Patient details are **not copied** here; read them from `sh_patient_master`.

### 3. Patient sample (blood group)

1. Draw the patient's sample → `bb_samples`: `sample_no` (printed on the label; `S{yyMMdd}{0001}`, global, daily reset, shared by patient and donor samples), `case_id`, `case_donor_id = null`, `subject_type = patient`, `collected_at/by`, `status = collected`.
2. Print the sample label → `sh_print_logs` (`document_type = bb.sample_label`, `printable_type = bb.sample`).
3. Blood group → `bb_test_results`, one row each for Blood Group (ABO) and Rhesus (D): `sample_id`, `test_definition_id`, `result_value`, `observation`, `tested_by/at`, `version = 1`, `is_current = true`, `status = entered`. Sample moves `in_testing` → `tested`.
4. Review → same row: `status = reviewed`, `reviewed_by/at`.
5. Correction → **new row** with `version + 1`, `amendment_reason`; the old row gets `is_current = false`. Nothing is overwritten.

The same patient sample is used for the crossmatch against each unit (step 8).

### 4. Donors (only when the patient brings donors)

Skip steps 4–7 when the blood is supplied directly from storage; go to step 8 with a stock unit.

1. Find or create the donor (a person, reused across cases) → `bb_donors`: `donor_no` (`D{yyMM}{000001}`, global, monthly reset; find returning donors with `GET /api/blood-bank/donors`), name, `father_name`, age/DOB, gender, `marital_status`, `religion`, `blood_group`, phone, `national_id`, `occupation` (free text), `is_regular_volunteer` (the form's "Would you like to be a regular voluntary donor?"; yes / no / not asked = null, updated each time they're asked).
2. Attach them to this case → `bb_case_donors`: `case_id`, `donor_id`, `sequence_no` (1, 2, 3… per case), `status = registered`.
3. Add a donor to an existing case (a replacement after a deferral, or one more). The "Add donor" page first opens the case with `GET add-donor?invoice_no=` (the case bill or an added donor's bill, session branch only, else `CASE_NOT_FOUND`), which returns the case detail; every donor row there has `invoice_no` (its own bill, or null for donors registered with the case). Then one donor per save → `POST cases/{case}/donors` (`AddCaseDonor`, permission `bb.case.create` for both), one transaction:
   - Body: `invoice_no` (the vendor's invoice for the donor charge, alpha-numeric, max 30) and `donor_id` (returning donor, found with `GET donors/lookup?phone=`) **or** `donor` (new donor, registered in the same request).
   - Shared bills it through `InvoiceIssuer::issueAdditional()`: a new `sh_billing` row under that invoice no (not generated), for the case's patient, with the case bill's referral copied, and one line of the fixed catalog item `BB-ADDL-DONOR` "Additional Donor Charge" (Tk 1000, inserted by a Shared migration, identified by code, rate snapshotted).
   - **New** `bb_case_donors` row: next `sequence_no`, `status = registered`, `billing_id` = the new bill. Earlier rows keep their status (a deferred donor stays `deferred`).
   - Audit `bb.case.donor_added` on `bb.case` (case no, donor, sequence, invoice no, amount); Shared audits the bill.
   - Errors: `CASE_NOT_OPEN`, `DONOR_ALREADY_IN_CASE` (the person is already on this case, even deferred), `INVOICE_NO_TAKEN` (any bill, also a deleted one, has this number), `VALIDATION_FAILED`, `FORBIDDEN` (another branch's case). Returns the case detail (201).
   - Scanning the new invoice no on the Sample Collection, Donor Examination or Donor Serology screen opens the case.

### 5. Donor screening and eligibility

The paper "Physical/Medical Examination" + "Serological Investigations" form, done as two checks, each with its own Accept / Defer. Every decision is a physician's manual input, never automatic; the system only records it. A deferral at either check ends the donor for this case (a replacement donor is a new case donor, step 4.3).

**Decisions are required and forward only.** Each form must be saved with a decision and offers only its own two outcomes: the physical exam `accepted` → `fit` / `deferred`; serology `accepted` → `eligible` / `deferred`. No form can set `registered` again or a status of another stage, so a donor never moves backward. A form can be corrected (including switching its own Accept / Defer) only until the next stage starts: the physical exam until the donor sample is drawn, serology until the bag is collected.

1. **Physical examination (physician, one form)** → `bb_donor_screenings` (one row per `case_donor_id`): `weight_kg`, `height_cm` (optional; UI may accept feet/inches, stored as cm), `hemoglobin_g_dl`, pre-donation `pre_pulse_bpm`, `pre_bp_systolic` / `pre_bp_diastolic` (140/90 → 140 and 90), `pre_temperature_f`, `examined_by/at`. Post-donation columns stay empty until step 6. The same form records the required decision → `bb_case_donors`: `status` (`fit` = "Accepted" / `deferred`), `deferral_reason` (= "Reason for deferral", required when deferred), `assessed_by/at`. The audit log keeps old and new values of a correction.
2. **Donor sample** (only for a `fit` donor; `DONOR_NOT_FIT` otherwise) → `bb_samples` with `subject_type = donor`, `case_donor_id` set. Label printed (`sh_print_logs`).
3. **Serological investigation** (one form with step 4) → `bb_test_results` on the donor sample, one row per test (`BLOOD_GROUP`, `HBSAG`, `ANTI_HCV`, `ANTI_HIV`, `SYPHILIS`), `status = entered`, `tested_by/at` = the user and server time of the save. A changed value gets a **new row** (`version + 1`, `amendment_reason` required, old row `is_current = false`); an unchanged value is not re-versioned. The blood group overwrites `bb_donors.blood_group` on every save. The sample moves to `tested`. These results are what the Compatibility Test Report later prints under "Screening Tests". There is no review step: the physician who saves the form signs the results (`tested_by`), and `reviewed_by/at` stay empty.
4. **Serology Accept / Defer** (required on the same form) → `bb_case_donors`: `status` (`eligible` / `deferred`), `deferral_reason` (replaces the earlier one; cleared on Accept), `assessed_by/at` overwritten with this decision only when the decision changes (the audit log keeps the physical-exam decision; the examining physician is also `bb_donor_screenings.examined_by`). Accept is refused while any screening result is `reactive`.

`fit` means passed the physical exam; `eligible` means **cleared to donate** (physical + serology). Whether a bag suits the patient is the crossmatch (step 8). One case can have several case donors; each is marked on its own.

Donor Examination APIs (permissions `bb.donor_exam.view` / `bb.donor_exam.record`, branch scoped):

| API | Screen |
|---|---|
| `GET donor-examination?invoice_no=` | Invoice scan (case bill or a replacement donor's bill) → case, patient, invoice, one row per case donor with its `examination` (null until saved), `decision` (`accepted` = `fit` or later, `deferred`, null), `deferral_reason`, `assessed_by/at`, `can_examine`. |
| `GET cases/{case}/donor-examination` | Same screen, opened from the Pending tab or a case. |
| `PUT cases/{case}/donors/{caseDonor}/physical-examination` | Save the form: `weight_kg`, `height_cm?`, `hemoglobin_g_dl`, `pulse_bpm`, `bp_systolic`, `bp_diastolic`, `temperature_f`, `decision` (required: `accepted` / `deferred`), `deferral_reason` (required when deferred). Returns the screen. |
| `GET donor-examination/pending` | Pending tab: open cases with a donor still `registered`. |

Examination rules: the case is `open`, the donor belongs to it, and no donor sample has been drawn yet (a rejected sample does not count) and the donor is not yet `eligible` / `collected` (otherwise `EXAMINATION_LOCKED`, 409). A save replaces the form; `examined_by/at` are the user and server time of the latest save (no time is accepted from the client); `assessed_by/at` change only when the decision changes. Audit: `bb.donor.examined` (first save), `bb.donor.examination_amended` (old/new values), on `bb.case_donor`. Values must be plausible (weight 20–250 kg, height 50–250 cm, Hb 1–25 g/dL, pulse 20–250, systolic 50–300, diastolic 20–200 and below systolic, temperature 90–110 °F); clinical thresholds are the physician's call, not enforced. Error codes: `CASE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `EXAMINATION_LOCKED`.

Donor Serology APIs (permissions `bb.serology.view` / `bb.serology.record`, branch scoped):

| API | Screen |
|---|---|
| `GET donor-serology?invoice_no=` or `?sample_no=` | Invoice scan (case bill or a replacement donor's bill) → every donor of the case; donor sample tube scan → only that sample's donor (a patient sample or another branch's sample is `DONOR_SAMPLE_NOT_FOUND`, 404). Returns case, patient, invoice, one row per donor with its `sample` (null until collected), `serology` (current results, `version`, `tested_by/at`; null until saved), `decision` (serology: `accepted`, `deferred`, null), `deferral_reason`, `assessed_by/at`, `can_record`. |
| `GET cases/{case}/donor-serology` | Same screen, opened from the Pending tab or a case. |
| `PUT cases/{case}/donors/{caseDonor}/serology` | Save the form: `blood_group`, `hbsag`, `anti_hcv`, `anti_hiv`, `syphilis`, `decision` (required: `accepted` / `deferred`), `deferral_reason` (required when deferred, else not allowed), `amendment_reason` (required when a saved result changes). Returns the screen. |
| `GET donor-serology/pending` | Pending tab: open cases with a `fit` donor whose sample is collected (not rejected). |

Serology rules (`DonorSerologyGate`): the case is `open` and the donor belongs to it; the donor is `fit`, or already decided at serology (`eligible`, or `deferred` with saved results) for a correction; the donor sample is collected (a rejected sample does not count). Switching the own Accept / Defer needs no amendment reason. Audit: `bb.donor.serology_recorded` (first save), `bb.donor.serology_amended` (old/new values, amendment reason), on `bb.case_donor`. Error codes: `CASE_NOT_FOUND`, `DONOR_SAMPLE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_NOT_FIT` (still `registered`), `DONOR_DEFERRED` (deferred at the physical exam), `DONOR_SAMPLE_NOT_COLLECTED`, `REACTIVE_DONOR_NOT_ACCEPTABLE`, `AMENDMENT_REASON_REQUIRED`, `SEROLOGY_LOCKED` (409, bag collected).

### 6. Collection (the bag)

1. Draw blood from an eligible donor → `bb_collections`: `bag_no` (printed on the bag label; `B{yyMMdd}{0001}`, global, daily reset), `case_donor_id`, `volume_ml`, `collected_at/by`, `approved_by` (not set yet), `status = collected`, `notes`. One bag per case donor.
2. `bb_case_donors.status = collected`. Physical exam and serology are locked from here on.
3. About 15 minutes after donation → fill the post-donation columns on the same `bb_donor_screenings` row: `post_pulse_bpm`, `post_bp_systolic` / `post_bp_diastolic`, `post_temperature_f`, `post_recorded_by/at`. Entered on the Blood Issue form (step 9) if not recorded yet; required there for every donor whose blood is issued, and never overwritten.
4. Print bag label → `sh_print_logs` (`bb.bag_label`, `printable_type = bb.collection`). The label shows the bag no, donor no and case no as barcodes, plus donor name, age, gender, blood group, patient no, name, age, gender, blood group, volume, collection time and collector.
5. Bag used up → `bb_consumable_transactions` (`type = issued`), see step 10. (Later.)

Blood Collection APIs (permissions `bb.collection.view` / `bb.collection.collect` / `bb.collection.print_label`, branch scoped):

| API | Screen |
|---|---|
| `GET blood-collection?invoice_no=` or `?donor_no=` | Collection tab: an invoice scan shows the case with every donor; a donor no scan shows only that donor, on the latest open case of this branch they are on. Each row has `is_collectable` and `collection` (the bag, with `label_print_count`). |
| `GET cases/{case}/blood-collection` | Same screen, opened from the Pending tab or a case. |
| `POST cases/{case}/donors/{caseDonor}/collection` | "Collect": `volume_ml` (50–600), optional `notes`, `collected_at`. Returns the screen plus `collected_collection_id` (201); the frontend then prints the label. |
| `POST cases/{case}/bag-labels` | "Label" / "Label (All)": `collection_ids`, `reprint_reason` (required for a reprint). Returns the label content per bag. |
| `GET blood-collection/pending` | Pending tab: open cases with an `eligible` donor (cleared to donate, no bag yet). |
| `GET blood-collections` | Collected tab and bag barcode scan (`filter[bag_no]`, `filter[case_no]`, `filter[donor_no]`, `filter[status]`, `filter[collected_from/to]`). |

Collection rules (`BloodCollectionGate`): the case is `open` and the donor belongs to it; the donor is `eligible` and has no bag (a cancelled bag does not count). Audit: `bb.blood.collected` on `bb.collection`. Error codes: `CASE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_NOT_FIT` (still `registered`), `DONOR_NOT_ELIGIBLE` (still `fit`, serology pending), `DONOR_DEFERRED`, `BAG_ALREADY_COLLECTED` (409), `COLLECTION_NOT_IN_CASE`, `REPRINT_REASON_REQUIRED`.

### 7. Units and components

A bag is prepared once, either kept as whole blood or separated into components. Stock is per branch: `bb_units.branch_id` comes from the case, and each branch has its own `bb_storage_locations`.

1. Keep as whole blood → one `bb_units` row: `unit_no = bag_no`, `component_type_id` (WB).
2. Or separate into components → one `bb_units` row per component: `unit_no = bag_no-CODE` (e.g. `B2611020001-PCV`; a component made twice gets `-1`, `-2`). No whole-blood row is kept for a separated bag; the components link to the bag through `collection_id` and have `parent_unit_id = null` (kept for splitting a unit later). Whole blood cannot be combined with components.
3. Every unit: `branch_id`, `collection_id`, `blood_group` (the donor's, from serology), `volume_ml`, `prepared_at`, `expires_at = bb_collections.collected_at + shelf_life_hours`, `storage_location_id` (any active location of the branch; required for stock, optional for a unit for the patient).
4. The preparation must strictly match what the case was billed for (`bb_case_components`) and still needs (quantity minus units already reserved or issued to the case):
   - "For this patient" → `status = reserved`, `case_id` = the case; only for a component the case still needs, up to the outstanding quantity.
   - The case needs whole blood (and no components) → the bag must be kept as whole blood for the patient.
   - The case needs components → the bag must be separated, with one unit for the patient of every component still needed; the rest goes to stock.
   - The case needs nothing more (e.g. an extra donor) → everything goes to stock: `status = available`, `case_id = null`.
5. One `bb_unit_movements` row per unit (`null → available / reserved`, to the location, with `case_id`). Audit `bb.units.prepared` on `bb.collection`.
6. Component label per unit → `sh_print_logs` (`bb.component_label`, `printable_type = bb.unit`): unit no barcode, component, blood group, volume, collected / prepared / expiry, storage condition, bag no, donor no, the donor's screening results and, when reserved, the case and patient.
7. Every barcode scan looks up `sample_no`, `bag_no` or `unit_no`; there are no barcode columns.

Component Preparation APIs (permissions `bb.unit.view` / `bb.unit.prepare` / `bb.unit.print_label`, branch scoped):

| API | Screen |
|---|---|
| `GET component-preparation?bag_no=` | Preparation tab: bag scan → bag (donor, case, patient), billed components with `held_count` and `needed_count`, the bag's units with `label_print_count`, `can_prepare`. |
| `GET cases/{case}/bags/{collection}/component-preparation` | Same screen, opened from the Pending tab. |
| `POST cases/{case}/bags/{collection}/units` | "Prepare": `units: [{component_type_id, storage_location_id?, volume_ml?, for_case}]` (1–6), optional `prepared_at` (ISO 8601; `toISOString()` with milliseconds and `Z` is accepted and converted to Asia/Dhaka). Returns the screen plus `prepared_unit_ids` (201); the frontend then prints the labels. |
| `POST cases/{case}/unit-labels` | "Label" / "Label (All)": `unit_ids`, `reprint_reason` (required for a reprint). Returns the label content per unit. |
| `GET component-preparation/pending` | Pending tab: collected bags with no units yet, oldest first. |
| `GET units` | Units tab and unit barcode scan (`filter[unit_no]`, `filter[bag_no]`, `filter[case_no]`, `filter[component_type_id]`, `filter[blood_group]`, `filter[status]`, `filter[storage_location_id]`, `filter[expires_before]`), soonest expiry first. |
| `GET storage-locations` | Location picker: the branch's active locations. |

Error codes: `BAG_NOT_FOUND` (404), `CASE_NOT_OPEN`, `COLLECTION_NOT_IN_CASE`, `BAG_CANCELLED`, `BAG_ALREADY_PREPARED` (409), `COMPONENT_NOT_NEEDED` (for the patient but not billed, or more than billed), `COMPONENT_REQUIREMENT_NOT_MET` (the patient does not get what the case still needs), `UNIT_NOT_IN_CASE`, `REPRINT_REASON_REQUIRED`, `VALIDATION_FAILED` (whole blood with components, inactive component type, stock unit without a location, another branch's or inactive location, `prepared_at` before collection).

### 8. Crossmatch and Compatibility Test Report

The unit is either from this case's own donor (steps 4–7) or straight from storage (another case's donor, or stock).

1. Crossmatch the patient sample against the unit → `bb_crossmatches`: `case_id`, `patient_sample_id`, `unit_id`, `major_result` and `minor_result` (`compatible` / `incompatible`; the report's "Compatible (Major/Minor)" means both are compatible), `remarks`, `tested_by/at` (Medical Technologist), `status` (entered → reviewed), `reviewed_by/at` (Physician), `version = 1`, `is_current = true`.
2. Correction → new row with `version + 1` and `amendment_reason`; the old row gets `is_current = false`.
3. Print the **Compatibility Test Report** (one per unit) → `sh_print_logs` (`bb.compatibility_report`, `printable_type = bb.crossmatch`). Any reprint needs `reprint_reason`.

Where each part of the report comes from (nothing is copied):

| Report section | Source |
|---|---|
| Patient name, gender, age, contact | `sh_patient_master` |
| Invoice, invoice date, Ref. by | `sh_billing`, `sh_billing_referrals` |
| Result date | `bb_crossmatches.reviewed_at` (or `tested_at`) |
| Blood Bag No., component, date of collection, expiry, collected by | `bb_units` → `bb_collections` (`bag_no`, `collected_at`, `collected_by`), `bb_component_types`, `bb_units.expires_at` |
| Unit | **Unconfirmed** (see Open questions). Built as: the unit's place in the case's issue order (1, 2, …), from the order of the current crossmatches of issued units; nothing stored |
| Patient ABO, Rh | patient sample's `bb_test_results` |
| Cause of transfusion | `bb_cases.transfusion_reason` |
| Donor name, age, sex, ABO, Rh | `bb_units` → `bb_collections` → `bb_case_donors` → `bb_donors`, and the donor sample's `bb_test_results` |
| Screening tests, method, result | donor sample's `bb_test_results` + `bb_test_definitions.method` |
| Compatibility with donor | `bb_crossmatches.major_result` / `minor_result` |
| Medical Technologist, Physician | `tested_by`, `reviewed_by` |

### 9. Assign, issue, storage, expiry

**Which unit goes to the patient:** a unit is assigned by setting `bb_units.case_id`. It must match one of the case's `bb_case_components` lines (same `component_type_id`). A stock unit also needs a current crossmatch with this case's patient where both major and minor are `compatible` before it is reserved; a unit from the case's own donor is reserved at preparation (step 7) and needs the compatible crossmatch before issue. A component line is fulfilled when the number of units assigned to the case for that component type reaches its `quantity`.

Every change to a unit updates `bb_units.status` / `storage_location_id` / `case_id` **and** appends a `bb_unit_movements` row: `from_status → to_status`, `from_location_id → to_location_id`, `case_id`, `reason`, `performed_by`, `occurred_at`.

| Event | `bb_units` |
|---|---|
| Stored / moved between fridges | stays `available`, location changes |
| Held for a patient | `reserved`, `case_id` set |
| Reservation released | back to `available`, `case_id` cleared (the movement row still records which case) |
| Handed over | `issued`, `case_id` stays |
| Returned unused (report: WB/PRBC within 30 min, with reason and doctor's signature) | back to `available`, `case_id` cleared, reason on the movement row |
| Platelet / FFP brought back after issue (cannot be received back) | `discarded` |
| Suspect, awaiting decision | `quarantined` |
| Destroyed | `discarded` (reason required) |
| Passed `expires_at` (hourly job) | `expired` |

When the blood is supplied (**"Supply time"**) → `bb_cases.supplied_at`.

The expiry dashboard reads `bb_units` by (`status`, `expires_at`).

When done, `bb_cases.status = closed` (or `cancelled`).

**Blood Issue / Compatibility Test Report menu** (permissions `bb.issue.view` / `bb.issue.record` / `bb.issue.print_report`, branch scoped). One form records what the report needs and hands the units over. "Issue Blood" (`IssueBlood`) is one transaction:

1. Patient blood group → `bb_test_results` (`BLOOD_GROUP`) on the latest non-rejected patient sample. It is required until one exists; an unchanged value is left alone, and a change is a new version with `amendment_reason`. `tested_by` is the chosen Medical Technologist. The sample moves to `tested`. `sh_patient_master.blood_group` is not touched (Shared owns it).
2. Post-donation vitals (step 6.3) for every donor whose unit is issued, unless already recorded.
3. One `bb_crossmatches` row per unit: `tested_by` = Medical Technologist, `reviewed_by` = Physician (both picked with `GET /api/staff/lookup`, not necessarily the user), `status = reviewed`. Both major and minor must be `compatible`, otherwise nothing is saved. A unit with an earlier current crossmatch gets a new version and needs `amendment_reason`.
4. Each unit goes `reserved → issued`; `storage_location_id` is cleared and a movement row is written.
5. `bb_cases.supplied_at = now()` (the latest issue). The case is `closed` when every `bb_case_components` line has as many issued units as its `quantity`.
6. One `sh_print_logs` row per page (`bb.compatibility_report`, `printable_type = bb.crossmatch`). The response is the PDF itself (mPDF, one page per unit, 215 × 292 mm letterhead paper: top margin 35 mm for the pre-printed header, bottom 30 mm, left/right 12.7 mm). It is rendered inside the transaction, so a failed render issues nothing.

Audit: `bb.patient.blood_grouped` / `bb.patient.blood_group_amended` (on `bb.sample`), `bb.donor.post_vitals_recorded` (on `bb.case_donor`), `bb.crossmatch.recorded` (on `bb.crossmatch`), `bb.blood.issued` and `bb.case.closed` (on `bb.case`).

| API | Screen |
|---|---|
| `GET blood-issue?invoice_no=` or `?donor_no=` | Scan → the whole case: `case` (with `supplied_at`, components with `reserved_count` / `issued_count` / `remaining_count`), `patient`, `invoice`, `patient_sample` with its current `blood_group`, every donor (decision, `invoice_no`, `examination` with `pre` / `post` vitals, `serology`, `bag`, `needs_post_vitals`), every unit reserved for or issued to the case (with `crossmatch`, `compatibility_report_print_count`, `can_issue`), and `can_issue`. A donor no opens the latest case of this branch the donor is on, closed ones included (for reprints). |
| `GET cases/{case}/blood-issue` | Same screen, opened from the Pending tab or a case. |
| `GET blood-issue/pending` | Pending tab: open cases with a `reserved` unit. |
| `POST cases/{case}/blood-issue` | "Issue Blood": `technologist_id`, `physician_id`, `units: [{unit_id, major_result, minor_result, remarks?}]` (1–20), `patient_blood_group` (required until recorded), `amendment_reason`, `post_donation: [{case_donor_id, pulse_bpm, bp_systolic, bp_diastolic, temperature_f}]`. Returns `application/pdf` (201). |
| `POST cases/{case}/compatibility-reports` | Reprint: `unit_ids`, `reprint_reason` (required for a reprint). Returns `application/pdf` (200) with a "REPRINT" mark. |

Error codes (JSON as usual): `CASE_NOT_OPEN`, `UNIT_NOT_IN_CASE`, `UNIT_NOT_RESERVED` (409), `UNIT_EXPIRED`, `CROSSMATCH_INCOMPATIBLE`, `PATIENT_SAMPLE_NOT_COLLECTED`, `AMENDMENT_REASON_REQUIRED`, `CASE_DONOR_NOT_IN_CASE`, `POST_DONATION_VITALS_REQUIRED`, `POST_DONATION_VITALS_RECORDED` (409), `CROSSMATCH_NOT_RECORDED` (reprint), `REPRINT_REASON_REQUIRED`, `VALIDATION_FAILED` (`patient_blood_group` missing, unknown user, vitals out of range), `FORBIDDEN`.

Not built yet: recording an incompatible crossmatch without issuing, returns / releasing a reservation, and reserving a stock unit for a case (so a case with no unit reserved through Component Preparation cannot be served yet).

### 10. Consumable stock (runs alongside everything)

- Item → `bb_consumables` (`current_balance` cached here).
- Batch / expiry (only if `tracks_batch` / `tracks_expiry`) → `bb_consumable_batches` (`batch_no`, `expires_at`, `quantity_on_hand`).
- Every movement → `bb_consumable_transactions`: `type` (received / issued / adjusted), `quantity`, `balance_after`, `reason`, `performed_by`, `occurred_at`. Append-only; the balance is derived from this ledger.

## Patient register → database

| Register column | Where |
|---|---|
| Patient details | `sh_patient_master` |
| Name of consultant | `sh_billing_referrals.doctor_id` |
| Component | `bb_case_components` (type + quantity) |
| Care of | `bb_cases.care_type` (`daycare` / `out`) |
| Cause of transfusion | `bb_cases.transfusion_reason` |
| Supply time | `bb_cases.supplied_at` |
| Blood group | `bb_test_results` on the patient sample |
| Crossmatch | `bb_crossmatches` (patient sample × unit), printed as the Compatibility Test Report |

## Status cheat sheet

| Table | Statuses |
|---|---|
| `bb_cases` | open → closed / cancelled |
| `bb_case_donors` | registered → fit (physical exam) → eligible (serology) → collected; deferred at either check; never backward |
| `bb_samples` | collected → in_testing → tested (or rejected) |
| `bb_test_results`, `bb_crossmatches` | entered → reviewed (or rejected) |
| `bb_collections` | collected / cancelled |
| `bb_units` | available ⇄ reserved → issued; quarantined, discarded, expired |

## History rules (nothing is overwritten)

- Test results and crossmatches: new `version` row, `is_current` flips, `amendment_reason` kept.
- Eligibility and physical examination: one row per donation; changes go through the audit log with old/new values.
- Units: every status, location and case assignment change is a `bb_unit_movements` row.
- Stock: every change is a `bb_consumable_transactions` row.
- Prints: every print and reprint is a `sh_print_logs` row.
- Cases, donors, consumables: soft deleted, never removed.

## Decisions so far

- `case_no` stays the case identifier; `invoice_no` is a search key only (a case can exist before billing, have several bills, or be rebilled).
- Eligibility lives on `bb_case_donors` (no separate assessments table), decided twice: physical exam (`fit` / `deferred`), then serology (`eligible` / `deferred`). Both are manual physician inputs.
- Donor serology has its own menu (not the Donor Examination form): different queue, time and place. Blood group is one combined result, and a reactive screening result blocks Accept.
- The crossmatch is patient sample × **unit**, not × donor, so units from storage (no donor brought) are covered.
- "Name of consultant" is the billing referral doctor; Blood Bank stores no doctor.
- Print logs are Shared (`sh_print_logs`), not Blood Bank.

## Open questions

1. **"Unit: 1" on the Compatibility Test Report.** Check old reports for a patient who got 2+ bags:
   - Unit 1, Unit 2… → which bag for the patient (current assumption, counted from the case's units).
   - Always Unit 1 → bags per report; always 1.
   - Changes only for split bags → the split number from `unit_no` (`-1`, `-2`).
2. **Returns.** The report says WB/PRBC may be returned within 30 min of issue, and Platelet/FFP can't be returned. Today a return is just a status change with a reason; nothing enforces the window or the per-component rule. Option: `is_returnable` and `return_window_minutes` on `bb_component_types`.
