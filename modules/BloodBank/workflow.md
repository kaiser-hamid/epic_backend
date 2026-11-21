# Blood Bank: what data goes where

Personal reference. Follows one patient case from billing to an issued unit, naming the table and columns written at each step. Based on the migrations and code as they stand (2026-11-21).

## Where things stand (2026-11-21)

The main path works end to end: **bill → case → samples → donor exam → donor serology → bag → units → crossmatch + issue → case closed**, with every label and the Compatibility Test Report printed and logged. A case can also be served from stock: the stock bag stands in for a donor (step 4a). What is missing is everything that happens to a unit or a bill *off* that path: returns, releasing a reservation, quarantine decisions / discard / expiry, cancelling a case, bag or bill, the review step for test results, and consumables.

```
 Counter (Shared Billing)                Blood Bank
 ─────────────────────────               ─────────────────────────────────────────────────────────────────────────
 New Bill Entry ──(has BB items)────>  case open ──> Add donor (1 per save, each with a saved bill)
   │ print: Customer + Lab Copy            │              │
   │        + Non-Lab Copy / dept          │              ├─> Donor Examination ── fit / deferred
   │                                       │              ├─> Sample Collection (donor) ──> Donor Serology ── eligible / deferred
   │                                       │              └─> Blood Collection (bag → units: reserved / stock, labels)
   │                                       ├─> Sample Collection (patient)                    │
   │                                       └──────────────────────────────────────────────> Blood Issue
   │                                                                                       (patient blood group, crossmatch,
   │                                                                                        post vitals, issue, report PDF)
   │                                                                                            └─> case closed when every
   └── bill w/o BB items (e.g. BB-ADDL-DONOR) opens no case; a donor's bill                         billed component is issued
```

| Area | State |
|---|---|
| Billing (Shared): bill, quote, discount policy, idempotency, paid amount, delivery time, mixed bills, print (Customer / Lab / Non-Lab Copies with branch rooms), preview | Done |
| Case opened from the bill, case details, Add donor | Done |
| Sample Collection, Donor Examination, Donor Serology, Blood Collection (bag + units), Units, Therapeutic Phlebotomy, Blood Issue + report | Done |
| Labels (sample, bag, component) as GoDEX EZPL commands; Compatibility Test Report PDF | Done |
| Test Results menu (patient ABO/Rh review and amend), Settings → Test Definitions | Next |
| Stock blood for a patient: the stock bag stands in for a donor (bag sample holds it, serology, patient label, Blood Issue) | Done (2026-11-21) |
| Returns, reservation release, quarantine decision / discard, hourly expiry job, Inventory, Expiry Dashboard | Later |
| Cancel a bill / case / bag, edit a bill | Later (no endpoint) |
| Consumables, bag stock issue | Later (tables exist, nothing writes them) |

## The big picture

```
sh_patient_master ─┐
                   ├─> sh_billing ──> sh_billing_items (what was billed; each line blood_bank or general, mixed freely)
                   │       ├────────> sh_billing_referrals (referring doctor = "Name of consultant")
                   │       ├────────> sh_billing_discounts (policy snapshot, e.g. "General 25%")
                   │       │
                   │       └─ a bill with Blood Bank lines opens exactly one case (bb_cases.billing_id, unique)
                   │
                   └─> bb_cases ──┬─> bb_case_components (billed component items: 2 × PCV, 1 × FP)
                                  ├─> bb_samples (subject = patient) ──> bb_test_results (ABO, Rh)
                                  ├─> bb_crossmatches (patient sample × unit → Compatibility Test Report)
                                  ├─> bb_units.case_id (units reserved for / issued to this case)
                                  │
                                  └─> bb_case_donors <── bb_donors (person, reused across cases; source = donor, or stock with unit_id: step 4a)
                                         │   status: registered → fit (physical exam) → eligible (serology) → collected; deferred at either check
                                         ├─> bb_donor_screenings (weight, height, Hb, pre/post vitals)
                                         ├─> bb_samples (subject = donor) ──> bb_test_results (serology)
                                         └─> bb_collections (one bag, bag_no)
                                                └─> bb_units (whole blood, then components via parent_unit_id)
                                                       └─> bb_unit_movements (every status / location change, with case_id)

sh_print_logs     ← every label / report print, for samples, bags, units, cases
bb_consumable_*   ← separate stock ledger for bags, reagents, etc.
```

Shared tables (`sh_`) are owned by Shared. Blood Bank only stores their IDs (`patient_master_id`, `billing_id`, `test_item_id`) and never writes to them. Billing never imports Blood Bank: it hands the Blood Bank lines of a saved bill to the `InvoiceCategoryHandler` that Blood Bank registers (`BloodBankInvoiceHandler`, tagged `billing.invoice_category_handlers`), inside the bill's transaction.

## Build status and menus

All APIs are under `/api/blood-bank`.

| Menu | Steps | Status | APIs |
|---|---|---|---|
| **Billing → New Bill Entry, Bill List** (Shared; a bill with Blood Bank items opens the case) | 1, 2 | Done | `/api/billing`: `GET/POST invoices`, `POST invoices/quote`, `GET invoices/{billing}`, `GET invoices/{billing}/preview`, `POST invoices/{billing}/print`, `GET patient-types` |
| **Cases** (Case List, case detail: edit details, Add donor) | 2 | Done | `GET cases`, `GET/PATCH cases/{case}` |
| **Donors** | 4.1 | Done | `GET donors`, `GET donors/lookup`, `GET donors/{donor}` |
| **Sample Collection** (tabs: Collection, Pending, Collected) | 3.1–3.2, 5.2 | Done | see below |
| **Donor Examination** (physician: pre-donation physical exam + Accept / Defer on one form) | 5.1 | Done | see step 5 |
| **Donor Serology** (blood group + screening results and Accept / Defer on one form) | 5.3–5.4 | Done | see step 5 |
| **Test Results** (patient ABO, Rh; review, amend) | 3.3–3.5 | Next | |
| **Add donor** (Cases page / case detail button: invoice lookup, then one donor per save under an existing bill) | 4.1–4.3 | Done | `GET add-donor?invoice_no=`, `POST cases/{case}/donors` |
| **Settings → Test Definitions** | 0 | Next | |
| **Blood Collection** (tabs: Collection, Pending, Collected; bag + its units and unit labels in one step; TP "Mark done") | 6.1–6.2, 6.4, 7 | Done | see step 6 |
| **Post-donation vitals** | 6.3 | Done | optional, on the Blood Issue form (step 9) |
| Bag stock issue | 6.5 | Later | |
| **Units** (list, scan, label reprint) | 7 | Done | see step 7 |
| **Blood Issue / Compatibility Test Report** (patient blood group, crossmatch per unit, post-donation vitals, issue, supply time, close case, report PDF) | 3.3, 6.3, 8, 9 | Done | see step 9 |
| **Stock bag** (Sample Collection scan + Collect → Donor Serology → Blood Collection "Print patient label" → Blood Issue) | 4a | Done | `GET cases/{case}/stock-units/check`, `POST cases/{case}/samples` (`unit_no` rows), `POST cases/{case}/donors/{caseDonor}/stock-unit` |
| Return / release a reservation | 9 | Later | |
| **Inventory / Stock** (units by location, move, quarantine, discard) | 9 | Later | |
| **Expiry Dashboard** | 9 | Later | |
| **Consumables** (items, receive / issue / adjust, ledger) | 10 | Later | |
| **Settings → Component Types, Storage Locations** | 0 | Later | `GET component-types`, `GET storage-locations` (pickers; locations are per branch) exist |

Sample Collection APIs (permissions `bb.sample.view` / `bb.sample.collect` / `bb.sample.print_label`, branch scoped):

| API | Screen |
|---|---|
| `GET sample-collection?invoice_no=` | Collection tab: invoice scan → case, patient, invoice, one row per subject (patient, then donors) with its latest sample, `is_collectable`, `label_print_count`. The invoice no is used until the vendor system is replaced; the case no lookup is kept commented out (`SampleCollectionLookupData`, `ShowSampleCollection`). |
| `GET cases/{case}/sample-collection` | Same screen, opened from the Pending tab or a case. |
| `GET cases/{case}/stock-units/check?unit_no=` | Stock bag scan: whether the unit can be taken for the case (step 4a), nothing written. Returns the unit (`unit_no`, `status`, `component`, `blood_group`, `expires_at`, `storage_location`). |
| `POST cases/{case}/samples` | "Collect": `subjects: [{subject_type, case_donor_id}]`, optional `collected_at`. All or nothing; returns the screen plus `collected_sample_ids` (201). The new samples' labels are logged as printed (first print) and each of those samples carries `label_command` for the print service; printing them again is a reprint. |
| `POST cases/{case}/sample-labels` | "Label" / "Label (All)": `sample_ids`, `reprint_reason` (required for a reprint). Returns label content per sample with `label_command`. |
| `GET sample-collection/pending` | Pending tab: open cases still waiting for a sample from the patient or a donor who was not deferred. |
| `GET samples` | Collected tab and barcode scan (`filter[sample_no]`, `filter[case_no]`, `filter[status]`, `filter[subject_type]`, `filter[collected_from/to]`). |

Sample label: `label_command` is the GoDEX EZPL command from `resources/label/bb_barcode_label.ezpx` (Shared `LabelRenderer`, CRLF line endings), each `^Vxx` replaced with its value cut to the declared length; text fields of centred variables (`jc..`) get the x that centres the value on the label, measured with the font's character widths, dropping to a smaller font when too wide: `V00` barcode = sample no; `V01` `{invoice no without its 2-letter branch code}|{sample no}` (the additional donor's own invoice, else the case's); `V02` the test item codes billed on that invoice; `V03` `{M|F|O}-{age label} {print time d/m/Y h:i A}` (age as its largest unit, e.g. `78Y`, `5M`).

Collection rules: a subject is collectable while the case is `open` and it has no sample (or only `rejected` ones); a donor also needs to be `fit` (accepted at the physical exam, step 5.1). Error codes are `CASE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_DEFERRED`, `DONOR_NOT_FIT` (donor still `registered`), `SAMPLE_ALREADY_COLLECTED` (409), `SAMPLE_NOT_IN_CASE` and `REPRINT_REASON_REQUIRED`.

## Step by step

### 0. Setup (master data, once)

| Table | What goes in |
|---|---|
| `sh_departments`, `sh_department_rooms`, `sh_test_items` | Departments carry `type` (`DepartmentType`): `lab` items print together on the bill's Lab Copy (Pathology, the branch's `sh_branches.lab_room_no`: East Gate 201, Main Gate 301); each `non_lab` department (imaging, ECG, Echo, Endoscopy, EEG, PFT, Blood Bank) prints its own Non-Lab Copy with its room in that branch (`sh_department_rooms`, e.g. Blood Bank at East Gate = 900). Billable catalog: what the counter can charge for (code, name, `rate` in paisa). `category` (`general` / `blood_bank`) says which module a line also belongs to (a `blood_bank` line goes to the bill's case); department grouping is unrelated. `discount_rate_bp` is the item's own discount under a policy (null = follow the policy, 0 = never discounted). The additional donor charge `BB-ADDL-DONOR` is `general`, so its bill opens no case. |
| `sh_patient_types`, `sh_discount_policies` | Patient type (required on every bill; "General Patient" = `GENERAL`) grouping the discount policies staff may pick, at most one per bill ("General 25%" = `GENERAL_25`, `rate_bp` 2500). Inserted by migration. |
| `sh_doctors` | Referring doctors / consultants. |
| `bb_test_definitions` | The Blood Bank lab tests and their result fields: `result_type` (text / number / option), `unit`, `reference_range`, `method` (printed on the report, e.g. "Immunochromatographic Test", "ICT method"), `options` (for option lists), `applies_to` (patient / donor / both). Inserted by the migration `insert_bb_serology_test_definitions` (codes in `SerologyTest`; the form depends on them, so they are not a seeder): `BLOOD_GROUP` (one combined option, the `BloodGroup` values `A_POS` … `O_NEG`), and donor screening `HBSAG`, `ANTI_HCV`, `ANTI_HIV` (1 & 2), `SYPHILIS` (option `non_reactive` / `reactive`), and `MALARIAL_PARASITE` (option `not_found` / `found`, method "ICT method", added by the migration `insert_bb_malarial_parasite_test_definition`). The first four screening tests get `method` = "Immunochromatographic Test" from the migration `set_bb_serology_test_methods` (blood group has none). |
| `bb_component_types` | What a bag can be made into: Blood Bank's own short `code` (drives preparation logic and labels), `name`, `unit_prefix` (unit number prefix), `shelf_life_hours` (drives `expires_at`), `default_location_type`. `ComponentTypeSeeder`: WB `BH`, PCV `BC`, FP `BP`, FFP `BF`, CRYO `BR`, PLASMA "Cryo Poor Plasma" `BS`, PLATELET `BT`, PRP `BQ`, PA `BA`. |
| `bb_billable_items` | What a `blood_bank` catalog item means to Blood Bank (`test_item_id` unique, plus `test_item_code` / `test_item_name` copies, `is_active`), by `kind` (`BillableItemKind`): `component` → `component_type_id` and `units_per_quantity` (units requested per billed quantity); `procedure` → `service` (`CaseService`); `storage_fee` → the patient's units are kept in storage. Items not listed (cross matching, screening, bed charges, BB001, infusion) only add to the bill. `BillableItemSeeder` (by item code, from the price list seeded by Shared's `BloodBankTestItemSeeder`): WB01 → WB, BBT03 → PCV, TH01 (Thalassemia package) → PCV, FP001 → FP, FFP01 → FFP, PC001 → PLATELET, PC002 (Platelet 4:1) → PLATELET × 4, PRP01 → PRP, PA01 → PA, CP01 → CRYO, CPP01 → PLASMA; APRP01 → procedure `autologous_prp`; TP01 → procedure `therapeutic_phlebotomy`; SWPF01 → storage fee. |
| `bb_storage_locations` | Fridges, freezers, agitators (`type`), with temperature range. |
| `bb_consumables` | Stock items (bags, reagents) with `minimum_stock`. |

Steps 1 and 2 are one form and one request in Billing: `POST /api/billing/invoices` (`CreateInvoice`, New Bill Entry). There is no manual case creation. Blood Bank is a branch (code `NB`). When the bill has Blood Bank items (mixed with any other items or not), `CreateInvoice` hands its Blood Bank lines to `BloodBankInvoiceHandler`, which opens the case (step 2) in the same transaction; if that fails, no bill is saved either. Donors are added afterwards, one per save (step 4). Blood Bank reads patient and invoice details back through `PatientDirectory` / `InvoiceDirectory`.

### 1. Patient registration and billing (Shared)

1. Find or create the patient → `sh_patient_master` (`patient_no` = `P{yyMMdd}{0001}`, global, daily reset; name, age, gender, blood group, phone…).
2. Counter bills the blood bank service → `sh_billing` (`invoice_no` = the vendor's number when typed, else `{branch code}{yyMMdd}{0001}`, e.g. `NB2610280001`, per branch, daily reset; `branch_id` of the issuing branch, `patient_type_id`, `subtotal_amount`, `discount_amount`, signed `rounding_amount`, `amount` = payable in paisa, `paid_amount` (paid at the counter, paisa), `status = active`, `billed_at`, `delivery_at` (when the reports will be ready; today or later, printed on the bill), `idempotency_key`), lines in `sh_billing_items` (`test_item_id`, `category`, `quantity`, `rate` snapshot, `amount`, `discount_rate_bp`, `discount_amount`, `net_amount`), the chosen policy snapshot in `sh_billing_discounts`, referral in `sh_billing_referrals` (doctor, second doctor, or self-referral). The referring doctor here is the register's **"Name of consultant"**; Blood Bank does not store it again.
   - A bill may mix Blood Bank and general items. Each line keeps its item's `category`; the bill and quote resources list the distinct ones as `categories` (the form shows "Care of" when `blood_bank` is among them), and `filter[category]` on the bill list finds bills with at least one such line. Only the Blood Bank lines reach the case (its requested components).
   - Discount: each line `rate × qty × (item rate ?? policy rate)`, rounded half-up to the paisa (no policy = no discount, the item rate is ignored). Payable: drop the paisa, then round up to the next 10 taka (Tk 1,022.25 → 1,030; Tk 1,020.50 → 1,020), on every bill. `POST invoices/quote` returns the same totals without saving. `DISCOUNT_POLICY_NOT_ALLOWED` when the policy isn't an active one of the patient type.
   - Double submit / retry: the form sends one `idempotency_key` per session. The same key from the same user returns the saved bill (200) and opens no second case; from another user, `IDEMPOTENCY_KEY_REUSED` (409).
   - A bill with Blood Bank items needs `category_details.care_type` ("Care of"); a bill without them refuses `category_details`.
   - A bill with Blood Bank items is only accepted in a branch that runs a blood bank (`sh_branch_modules` lists `blood-bank`; today East Gate only). Elsewhere it is refused with `MODULE_NOT_ENABLED_FOR_BRANCH` (422) and nothing is saved. All Blood Bank screens are blocked in such a branch too (see `modules/Shared/Rbac/workflow.md`).
   - Payment: `paid_amount` may be anything from 0 up to the payable the server computes (`PAID_AMOUNT_EXCEEDS_PAYABLE`, and a DB check `paid_amount <= amount`). Due = `amount − paid_amount`; the resource sends `paid_amount`, `due_amount`, `delivery_at`. The form's "Given" / "Change" are not sent or stored.
3. Bill List / print: `POST invoices/{billing}/print` (`sh.billing.print`) logs the print (`sh_print_logs`, `document_type = sh.invoice`) and returns a PDF on the pre-printed A5 pad (10 mm sides, 27 mm top/bottom kept for the pad's header and QR footer): a **Customer Copy** (prices, totals, paid, PAID/DUE, amount in words in lakh/crore) then one **Lab Copy** with every lab item ("Pathology (Sample Collection Zone), Room No. 201", the branch's `lab_room_no`) and one **Non-Lab Copy** per non-lab department on the bill ("Blood Bank, Room No. 900", from `sh_department_rooms` for the bill's branch; no room part when none is set), each listing its items without prices. Bill no and ULID barcodes. The first print says "Original Copy"; every later one is a "Duplicate Copy" and needs a reason.
4. `GET invoices/{billing}/preview` (`view` on the bill) returns the same PDF marked "Preview"; it is not logged.

### 2. Open the case (patient register)

Opened by `OpenCaseForInvoice` when a bill with Blood Bank items is saved (it receives only those lines) (audit `bb.case.opened`). If a case already has this `billing_id` it is returned instead.

1. `bb_cases`: `case_no` (`BC{yyMM}{000001}`, global, monthly reset), `patient_master_id` and `branch_id` of the bill, `billing_id` (required, unique: one case per bill), `care_type` (**"Care of"** from the bill: `daycare` = used in our daycare unit, `out` = takeaway), `service` (`CaseService`, below), `keeps_units_in_storage` (SWPF01 billed), `transfusion_reason` (**"Cause of transfusion"**, nullable), `reference_note` (any other note), `status = open`, `created_by`.
2. Requested components (**"Component"** column) → `bb_case_components`, from the billed lines whose item is an active `component` billable item: `case_id`, `component_type_id`, `test_item_id` (the first such line), `quantity` = line quantity × `units_per_quantity`, added up per component type (BBT03 + TH01 → PCV × 2; PC002 × 1 → PLATELET × 4). Unique per (case, component type). Other Blood Bank items add no row.
3. **One bill, one service.** `service` = the billed procedure (`autologous_prp`, `therapeutic_phlebotomy`), else `transfusion`. A procedure can't be billed with a component or another procedure: `SERVICES_NOT_COMBINABLE` (422), nothing saved. Extras (bed charges, BB001, infusion, cross matching, SWPF01) go with anything. A procedure case takes no donors (`CASE_TAKES_NO_DONORS`), is never issued blood (`CASE_TAKES_NO_BLOOD`, so it never gets a Compatibility Test Report) and closes inside its own step (step 3 for Autologous PRP, step 6 for Therapeutic Phlebotomy).
4. Case screen: `PATCH cases/{case}` (`UpdateCaseDetails`, `bb.case.update`, open case only, else `CASE_NOT_OPEN`) corrects `care_type` and records `transfusion_reason` / `reference_note`; audit `bb.case.details_updated` with old and new values.
5. **Close case** (case details, `POST cases/{case}/close`, `CloseCase`, `bb.case.close`), for a case that does not close by itself (services only, or work that cannot finish). Body: `reason` (max 1000). `CaseClosingGate`: refused with `CASE_NOT_OPEN`, or `CASE_HAS_RESERVED_UNITS` (409) while a unit is reserved for the case; `reason` is required (`CLOSE_REASON_REQUIRED`) when billed work is outstanding (a procedure not done, or components not fully issued). Writes `status = closed`, `closed_at`, `closed_by`, `close_reason`. The case resource sends `closing: {can_close, needs_reason}` on the detail. Case list filter `filter[needs_closing]=1`: open transfusion cases with no component line (they never close by themselves); `filter[service]` too.

Every close goes through `CaseCloser` (audit `bb.case.closed` with `via` = `close_case` / `blood_issue` / `sample_collection` / `therapeutic_phlebotomy`, `close_reason`, and for `close_case` the `outstanding` component counts).

Patient details are **not copied** here; read them from `sh_patient_master`.

### 3. Patient sample (blood group)

1. Draw the patient's sample (Sample Collection) → `bb_samples`: `sample_no` (printed on the label; `S{yyMMdd}{0001}`, global, daily reset, shared by patient and donor samples), `case_id`, `case_donor_id = null`, `subject_type = patient`, `collected_at/by`, `status = collected`.
2. Print the sample label → `sh_print_logs` (`document_type = bb.sample_label`, `printable_type = bb.sample`, `copies`).
   - **Autologous PRP**: the patient sample is the whole service. Its label prints 5 copies (the EZPL `^C` line is set to `^C5` by `LabelRenderer`; the design is unchanged) and is logged with `copies = 5`; collecting it sets `supplied_at` and closes the case (`via = sample_collection`).
   - **Therapeutic Phlebotomy**: no sample. The patient is never collectable (`PATIENT_SAMPLE_NOT_NEEDED`) and the case is not on the Sample Collection pending list.
3. Blood group (today: entered on the Blood Issue form, step 9) → `bb_test_results`, one combined `BLOOD_GROUP` row (`A_POS` … `O_NEG`): `sample_id`, `test_definition_id`, `result_value`, `tested_by` = the chosen Medical Technologist, `tested_at`, `version = 1`, `is_current = true`, `status = entered`. Sample goes straight `collected → tested` (`in_testing` is not used yet).
4. Review → same row: `status = reviewed`, `reviewed_by/at`. **Not built** (Test Results menu, next).
5. Correction → **new row** with `version + 1`, `amendment_reason`; the old row gets `is_current = false`. Nothing is overwritten.

The same patient sample is used for the crossmatch against each unit (step 8).

### 4. Donors (only when the patient brings donors)

When the blood is supplied from storage, the stock bag joins the case as a stand-in donor instead (step 4a).

1. Find or create the donor (a person, reused across cases) → `bb_donors`: `donor_no` (`D{yyMM}{000001}`, global, monthly reset; find returning donors with `GET /api/blood-bank/donors`), name, `father_name`, age/DOB, gender, `marital_status`, `religion`, `blood_group`, phone, `national_id`, `occupation` (free text), `is_regular_volunteer` (the form's "Would you like to be a regular voluntary donor?"; yes / no / not asked = null, updated each time they're asked).
2. Attach them to this case → `bb_case_donors`: `case_id`, `donor_id`, `sequence_no` (1, 2, 3… per case), `status = registered`, `billing_id` (the bill given for this donor).
3. Every donor (the first ones and replacements alike) is added on the "Add donor" page. It first opens the case with `GET add-donor?invoice_no=` (the case bill or a donor's bill, session branch only, else `CASE_NOT_FOUND`), which returns the case detail with `invoice_no` on every donor row. Then one donor per save → `POST cases/{case}/donors` (`AddCaseDonor`, permission `bb.case.update` for both), one transaction:
   - Body: `invoice_no` (required: a bill **already saved** in Billing, e.g. the case bill or a general bill for the `BB-ADDL-DONOR` charge) and `donor_id` (returning donor, found with `GET donors/lookup?phone=`) **or** `donor` (new donor, registered in the same request). Nothing is billed here, and what the bill contains is not checked.
   - The bill must be this branch's (`INVOICE_NOT_IN_BRANCH`), active (`INVOICE_NOT_ACTIVE`), for the case's patient (`INVOICE_PATIENT_MISMATCH`) and not the bill of another case or of another case's donor (`INVOICE_LINKED_TO_OTHER_CASE`); unknown numbers give `INVOICE_NOT_FOUND`. Several donors of one case may share a bill.
   - **New** `bb_case_donors` row: next `sequence_no`, `status = registered`, `billing_id` = that bill. Earlier rows keep their status (a deferred donor stays `deferred`).
   - Audit `bb.case.donor_added` on `bb.case` (case no, donor, sequence, invoice no).
   - Other errors: `CASE_NOT_OPEN`, `DONOR_ALREADY_IN_CASE` (the person is already on this case, even deferred), `VALIDATION_FAILED`, `FORBIDDEN` (another branch's case). Returns the case detail (201).
   - Scanning a donor's invoice no on the Sample Collection, Donor Examination or Donor Serology screen opens the case.

### 4a. Stock blood (a stock bag stands in for a donor)

The patient calls and asks for stored blood (e.g. 2 × PCV); if the bank has it, the patient pays the usual bill (the case opens with its components, no donors). The stock bag then goes through the donor steps as a **stock row** of `bb_case_donors`: `source = stock` (`CaseDonorSource`), `unit_id` = the stock unit, `donor_id` = the unit's original donor (`unit.collection.caseDonor.donor_id`; that donor record is what the labels and report print), `billing_id` = the case's bill. A check keeps `source = stock` ⇔ `unit_id` set; a person is unique per case only as a real donor (partial unique index `WHERE source = 'donor'`), a stock row is unique per (case, unit).

| Step | `bb_units.status` | Stock row status |
|---|---|---|
| In stock | `available` | — |
| Sample Collection: scan unit no, then Collect | `held`, `case_id` = the case (no other case can take it) | created `fit` (no physical exam; `assessed_by/at` = the user, now) + donor sample, label printed |
| Donor Serology: Accept ("booked") | `held` | `eligible` |
| Donor Serology: Defer | `available` (`case_id` cleared), or `quarantined` when a screening result is reactive / malaria found | `deferred` |
| Blood Collection: "Print patient label" | `reserved` (stays in its fridge unless moved) | `collected` |
| Blood Issue (as usual: crossmatch, report) | `issued` | `collected` |

1. **Scan the stock bag** → `GET cases/{case}/stock-units/check?unit_no=` (`CheckStockUnit`, `bb.sample.collect`): nothing is written; the screen lists the bag as a ticked row. **Collect** → `POST cases/{case}/samples` with the row as `{subject_type: donor, unit_no}` (a donor row has `case_donor_id` or `unit_no`, never both). In the Collect transaction `StockUnitIntake::admit` checks the bag again (another case may have taken it since the scan), adds the stock row and holds the unit (`StockUnitHold`, movement row, audit `bb.stock_unit.held`); then its donor sample is drawn as for any donor, with the usual donor sample label (case invoice, test codes, the original donor's gender and age). Audit `bb.case.donor_added` (with `source`, `unit_no`) and `bb.sample.collected` (with `unit_no`). A refusal rejects the whole Collect. `StockUnitGate` refuses (at the scan and at Collect): `CASE_NOT_OPEN`, `CASE_TAKES_NO_BLOOD` (procedure case), `UNIT_NOT_FOUND` (404, no unit of the case's branch has this no), `UNIT_ALREADY_IN_CASE` (409, taken for this case before, even deferred), `UNIT_NOT_AVAILABLE` (409, not `available`: held / reserved / issued elsewhere, quarantined…), `UNIT_EXPIRED`, `UNIT_NOT_NEEDED` (the case's billed components of that type are all held, reserved or issued). Blood group is not checked (serology and the crossmatch decide).
2. **Donor Examination**: a stock row is never examined (`can_examine = false`, `EXAMINATION_LOCKED`) and never pending.
3. **Donor Serology** on the bag sample, the same form and rules as a donor (results versioned, reactive blocks Accept, `bb_donors.blood_group` overwritten; `bb_units.blood_group` is left as labelled). The unit follows the decision (`RecordDonorSerology::moveStockUnit`): Defer releases it (audit `bb.stock_unit.released`, movement row with the case), Defer → Accept takes it again if it is still `available` and not expired (`UNIT_NOT_AVAILABLE` / `UNIT_EXPIRED` otherwise). Correctable until the patient label is printed.
4. **Print patient label** → `POST cases/{case}/donors/{caseDonor}/stock-unit` (`ReserveStockUnit`, `bb.collection.collect`): optional `storage_location_id` (an active location of the branch). Unit `held` → `reserved` (movement, audit `bb.stock_unit.reserved`), row → `collected`, the patient label (`bb_bag_label.ezpx`, printed over the old storage label) logged as `bb.patient_label` (its first print: the storage label is a separate document). Returns the Blood Collection screen with `collected_units` (201). Errors: `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `CASE_DONOR_NOT_STOCK_UNIT` (a real donor), `DONOR_NOT_ELIGIBLE`, `DONOR_DEFERRED`, `BAG_ALREADY_COLLECTED`, `UNIT_NOT_HELD`, `UNIT_EXPIRED`. Collect on a stock row is refused with `CASE_DONOR_IS_STOCK_UNIT`. The screen sends `can_reserve_stock_unit` per row; the Pending tab also lists open cases with an `eligible` stock row.
5. **Blood Issue** is unchanged. The report's donor, bag no, collection date and collected-by come from the original collection; its screening tests are the stock row's re-test, falling back per test to the original donor's (`DonorScreeningResults::forRetestedUnit`). Post-donation vitals are only taken for this case's own donors.
6. If the bag is deferred, the patient brings a donor, another stock bag is taken, or the case is closed by hand; **Close case** sends any unit still `held` for the case back to `available` (reason "Case closed: …").

Every screen's donor row carries `source` and `stock_unit` (unit no, status, component, blood group, expiry, location). `BagPlan` counts `held` units with reserved / issued ones, so a donor's next bag is not planned for a component a stock bag already covers.

### 5. Donor screening and eligibility

The paper "Physical/Medical Examination" + "Serological Investigations" form, done as two checks, each with its own Accept / Defer. Every decision is a physician's manual input, never automatic; the system only records it. A deferral at either check ends the donor for this case (a replacement donor is a new case donor, step 4.3).

**Decisions are required and forward only.** Each form must be saved with a decision and offers only its own two outcomes: the physical exam `accepted` → `fit` / `deferred`; serology `accepted` → `eligible` / `deferred`. No form can set `registered` again or a status of another stage, so a donor never moves backward. A form can be corrected (including switching its own Accept / Defer) only until the next stage starts: the physical exam until the donor sample is drawn, serology until the bag is collected.

1. **Physical examination (physician, one form)** → `bb_donor_screenings` (one row per `case_donor_id`): `weight_kg`, `height_cm` (optional; UI may accept feet/inches, stored as cm), `hemoglobin_g_dl`, pre-donation `pre_pulse_bpm`, `pre_bp_systolic` / `pre_bp_diastolic` (140/90 → 140 and 90), `pre_temperature_f`, `examined_by/at`. Post-donation columns stay empty until step 6. The same form records the required decision → `bb_case_donors`: `status` (`fit` = "Accepted" / `deferred`), `deferral_reason` (= "Reason for deferral", required when deferred), `assessed_by/at`. The audit log keeps old and new values of a correction.
2. **Donor sample** (only for a `fit` donor; `DONOR_NOT_FIT` otherwise) → `bb_samples` with `subject_type = donor`, `case_donor_id` set. Label printed (`sh_print_logs`).
3. **Serological investigation** (one form with step 4) → `bb_test_results` on the donor sample, one row per test (`BLOOD_GROUP`, `HBSAG`, `ANTI_HCV`, `ANTI_HIV`, `SYPHILIS`, `MALARIAL_PARASITE`), `status = entered`, `tested_by/at` = the user and server time of the save. A changed value gets a **new row** (`version + 1`, `amendment_reason` required, old row `is_current = false`); an unchanged value is not re-versioned. The blood group overwrites `bb_donors.blood_group` on every save. The sample moves to `tested`. These results are what the Compatibility Test Report later prints under "Screening Tests". There is no review step: the physician who saves the form signs the results (`tested_by`), and `reviewed_by/at` stay empty.
4. **Serology Accept / Defer** (required on the same form) → `bb_case_donors`: `status` (`eligible` / `deferred`), `deferral_reason` (replaces the earlier one; cleared on Accept), `assessed_by/at` overwritten with this decision only when the decision changes (the audit log keeps the physical-exam decision; the examining physician is also `bb_donor_screenings.examined_by`). Accept is refused while any screening result is `reactive` or the malarial parasite result is `found`.

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
| `PUT cases/{case}/donors/{caseDonor}/serology` | Save the form: `blood_group`, `hbsag`, `anti_hcv`, `anti_hiv`, `syphilis`, `malarial_parasite` (`not_found` / `found`), `decision` (required: `accepted` / `deferred`), `deferral_reason` (required when deferred, else not allowed), `amendment_reason` (required when a saved result changes). Returns the screen. |
| `GET donor-serology/pending` | Pending tab: open cases with a `fit` donor whose sample is collected (not rejected). |

Serology rules (`DonorSerologyGate`): the case is `open` and the donor belongs to it; the donor is `fit`, or already decided at serology (`eligible`, or `deferred` with saved results) for a correction; the donor sample is collected (a rejected sample does not count). Switching the own Accept / Defer needs no amendment reason. Audit: `bb.donor.serology_recorded` (first save), `bb.donor.serology_amended` (old/new values, amendment reason), on `bb.case_donor`. Error codes: `CASE_NOT_FOUND`, `DONOR_SAMPLE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_NOT_FIT` (still `registered`), `DONOR_DEFERRED` (deferred at the physical exam), `DONOR_SAMPLE_NOT_COLLECTED`, `REACTIVE_DONOR_NOT_ACCEPTABLE`, `AMENDMENT_REASON_REQUIRED`, `SEROLOGY_LOCKED` (409, bag collected).

### 6. Collection (the bag and its units, one step)

Collect draws the bag and makes it into units in the same transaction (`CollectBlood`); there is no separate preparation step. Stock is per branch: `bb_units.branch_id` comes from the case, and each branch has its own `bb_storage_locations`.

1. Draw blood from an eligible donor → `bb_collections`: `bag_no` (`B{yyMMdd}{0001}`, global, daily reset), `case_donor_id`, `collected_at/by`, `approved_by` (not set yet), `status = collected`, `notes`. One bag per case donor.
2. `bb_case_donors.status = collected`. Physical exam and serology are locked from here on.
3. The bag is made into units by its **bag plan** (`BagPlan`), chosen from what the case still needs (billed quantity in `bb_case_components` minus units already reserved or issued to the case). The first recipe that makes something still needed wins:

   | Case still needs | Patient | Storage |
   |---|---|---|
   | WB | WB | — |
   | PA | PA | — |
   | PCV (incl. TH01) | PCV | FFP |
   | FFP | FFP | PCV |
   | FP | FP | PCV |
   | PCV + FP | PCV, FP | — |
   | PLATELET | PLATELET | PCV, FFP |
   | PCV + PLATELET | PCV, PLATELET | FFP |
   | CRYO / PRP / PLASMA | that component | — |

   A component goes to the patient when the case still needs it; the rest goes to stock. Inactive or missing component types are left out. A case that needs nothing more has an empty plan: no donor row is collectable and Collect is refused (`CASE_NEEDS_NOTHING_MORE`).
4. One `bb_units` row per plan row: `unit_no` = the bag no under the component type's `unit_prefix` (`B2611020001` → `BC2611020001` for PCV; WB `BH`, PCV `BC`, FP `BP`, FFP `BF`, CRYO `BR`, PLASMA `BS`, PLATELET `BT`, PRP `BQ`, PA `BA`), `branch_id`, `collection_id`, `component_type_id`, `blood_group` (the donor's, from serology), `volume_ml` (the row's ml, optional; the only volume recorded, the bag has none), `prepared_at = collected_at`, `expires_at = collected_at + shelf life`, `parent_unit_id = null` (kept for splitting a unit later).
   - Patient row → `status = reserved`, `case_id` = the case.
   - Storage row → `status = available`, `case_id = null`, `storage_location_id` required (any active location of the branch).
   - A case that keeps its units in storage (`keeps_units_in_storage`, SWPF01) also needs a location for its patient rows.
5. One `bb_unit_movements` row per unit (`null → reserved / available`, to the location, with `case_id`). Audit `bb.blood.collected` on `bb.collection`, with the units.
6. A label per unit → `sh_print_logs` (`bb.patient_label` for a reserved unit, `bb.storage_label` for stock; `printable_type = bb.unit`), logged by Collect itself; the response carries the label commands, and the frontend sends them to the label printer. Any later print is a reprint (reason required) from the Units menu.
7. About 15 minutes after donation → fill the post-donation columns on the same `bb_donor_screenings` row: `post_pulse_bpm`, `post_bp_systolic` / `post_bp_diastolic`, `post_temperature_f`, `post_recorded_by/at`. Optionally entered on the Blood Issue form (step 9) if not recorded yet; blood can be issued without them, and recorded vitals are never overwritten.
8. Bag used up → `bb_consumable_transactions` (`type = issued`), see step 10. (Later.)
9. Every barcode scan looks up `sample_no`, `bag_no` or `unit_no`; there are no barcode columns.

Blood Collection APIs (permissions `bb.collection.view` / `bb.collection.collect`, branch scoped):

| API | Screen |
|---|---|
| `GET blood-collection?invoice_no=` or `?donor_no=` | Collection tab: an invoice scan shows the case with every donor; a donor no scan shows only that donor, on the latest open case of this branch they are on. Each row has `is_collectable` and `collection` (the bag). `bag_plan` lists the units the next bag is made into: `component_type_id`, `code`, `name`, `for_patient`, `needs_storage_location`, `default_location_type` (the frontend pre-fills the shelf from it). |
| `GET cases/{case}/blood-collection` | Same screen, opened from the Pending tab or a case. |
| `POST cases/{case}/donors/{caseDonor}/collection` | "Collect and Print": optional `notes`, `collected_at`, and `units: [{component_type_id, storage_location_id?, volume_ml?}]` (max 6; one row per plan row; ml 1–600). Returns the screen plus `collected_collection_id` and `collected_units` (each unit with `label_print` and `label_command`) (201). |
| `GET blood-collection/pending` | Pending tab: open cases with an `eligible` donor (cleared to donate, no bag yet) that still need a unit (`BagPlan::whereNeedsUnits`: a billed component not yet all held, reserved or issued; a case holding everything is left out), open cases with an `eligible` stock row (patient label not printed, step 4a), and open Therapeutic Phlebotomy cases. |
| `POST cases/{case}/donors/{caseDonor}/stock-unit` | "Print patient label" for a stock row (step 4a). |
| `GET blood-collections` | Collected tab and bag barcode scan (`filter[bag_no]`, `filter[case_no]`, `filter[donor_no]`, `filter[status]`, `filter[collected_from/to]`). |

**Therapeutic Phlebotomy** (no donor, no bag): the case is on the Blood Collection pending list, and the screen sends `can_record_phlebotomy` (open TP case) and `phlebotomy`. "Mark done" → `POST cases/{case}/therapeutic-phlebotomy` (`RecordTherapeuticPhlebotomy`, `bb.phlebotomy.record`): `volume_ml` (50–1000), optional `pre` / `post` vitals (`pulse_bpm`, `bp_systolic`, `bp_diastolic` < systolic, `temperature_f` with 1 decimal; all four or none), `notes`, `performed_at`. Writes one `bb_phlebotomies` row per case (`performed_by/at`), audit `bb.phlebotomy.recorded` on `bb.phlebotomy`, and closes the case (`via = therapeutic_phlebotomy`). Error codes: `NOT_A_PHLEBOTOMY_CASE`, `CASE_NOT_OPEN`.

Collection rules (`BloodCollectionGate`): the case is `open` and the donor belongs to it; the donor is `eligible` and has no bag (a cancelled bag does not count); the bag plan is not empty. Error codes: `CASE_NOT_FOUND`, `CASE_NOT_OPEN`, `CASE_DONOR_NOT_IN_CASE`, `DONOR_NOT_FIT` (still `registered`), `DONOR_NOT_ELIGIBLE` (still `fit`, serology pending), `DONOR_DEFERRED`, `BAG_ALREADY_COLLECTED` (409), `CASE_NEEDS_NOTHING_MORE`, `VALIDATION_FAILED` (`units`: a storage unit left out or no location; `units.{i}.component_type_id`: not made from this bag; `units.{i}.storage_location_id`: another branch's or inactive location; `collected_at` in the future).

### 7. Units (labels and the Units menu)

Units are made at collection (step 6). The **Units** menu (permissions `bb.unit.view` / `bb.unit.print_label`, branch scoped; the Blood Collection role template has both) lists them and reprints their labels.

| API | Screen |
|---|---|
| `GET units` | Units list and unit barcode scan (`filter[unit_no]`, `filter[bag_no]`, `filter[case_no]`, `filter[component_type_id]`, `filter[blood_group]`, `filter[status]`, `filter[storage_location_id]`, `filter[expires_before]`), soonest expiry first. Each unit has `bag_case_id`, the case its bag was collected for. |
| `POST cases/{case}/unit-labels` | "Label": `unit_ids` (units from this case's bags, or held / reserved / issued for this case), `reprint_reason` (required for a reprint). Returns the label content per unit with `label_command`. The Units list calls it with the unit's `bag_case_id`. |
| `GET storage-locations` | Location picker: the branch's active locations. |

Labels (`UnitLabelCommands`, GoDEX EZPL; values longer than a variable's declared length are cut). The label follows the unit's status (`PrintDocumentType::unitLabelFor`), and each is its own print document, so prints and reprints are counted per label:

- **Patient label** (`bb.patient_label`) for a unit reserved for or issued to a patient (`resources/label/bb_bag_label.ezpx`; a stock bag gets it over its storage label, step 4a): `V00` unit no, `V01` collection date, `V02` expiry date, `V03`-`V05` patient name / age / gender, `V06` donor blood group (`A+ve`), `V07` component code, `V08` donor name, `V09` referring doctor, `V10` invoice no.
- **Storage label** (`bb.storage_label`) for a stock unit, also while `held` for a case (`resources/label/bb_bg_storage_bag_label.ezpx`): `V00` unit no, `V01` donor no, `V02` donor name (24 characters), `V03` donor age (`32Y`), `V04` donor gender, `V05` blood group, `V06` component code, `V07` collection date, `V08` expiry date.

Error codes: `UNIT_NOT_IN_CASE`, `REPRINT_REASON_REQUIRED`.

### 8. Crossmatch and Compatibility Test Report

The unit is either from this case's own donor (steps 4–7) or straight from storage (another case's donor, or stock).

1. Crossmatch the patient sample against the unit → `bb_crossmatches`: `case_id`, `patient_sample_id`, `unit_id`, `major_result` and `minor_result` (`compatible`, or null for a side the component does not need: `CrossmatchScope` by component code, WB major and minor, PCV major, every other component minor; the report prints "Compatible (Major/Minor)", "Compatible (Major)" or "Compatible (Minor)"), `remarks`, `tested_by/at` (Medical Technologist), `status` (entered → reviewed), `reviewed_by/at` (Physician), `version = 1`, `is_current = true`.
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
| Screening tests, method, result | donor sample's `bb_test_results` + `bb_test_definitions.method`; every screening test is listed, with a blank result when the donor was screened before the test was added (e.g. Malarial Parasite) |
| Compatibility with donor | `bb_crossmatches.major_result` / `minor_result`: "Compatible (" + the sides recorded + ")" |
| Medical Technologist, Physician | `tested_by`, `reviewed_by` |

### 9. Assign, issue, storage, expiry

**Which unit goes to the patient:** a unit is assigned by setting `bb_units.case_id`. It must match one of the case's `bb_case_components` lines (same `component_type_id`). A unit from the case's own donor is reserved at preparation (step 6); a stock unit is held at its bag sample and reserved when its patient label is printed (step 4a). Either needs the compatible crossmatch before issue. A component line is fulfilled when the number of units assigned to the case for that component type reaches its `quantity`.

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

1. Cause of transfusion (required) → `bb_cases.transfusion_reason`, prefilled from the case; a change is audited as `bb.case.details_updated` with old and new values.
2. Patient blood group → `bb_test_results` (`BLOOD_GROUP`) on the latest non-rejected patient sample. It is required until one exists; an unchanged value is left alone, and a change is a new version with `amendment_reason`. `tested_by` is the chosen Medical Technologist. The sample moves to `tested`. `sh_patient_master.blood_group` is not touched (Shared owns it).
3. Post-donation vitals (step 6.3), optional: recorded for the donors sent, unless already recorded. Issuing without them is allowed.
4. One `bb_crossmatches` row per unit: `tested_by` = Medical Technologist, `reviewed_by` = Physician (both picked with `GET /api/staff/lookup`, not necessarily the user), `status = reviewed`. The results are not entered: the unit is recorded `compatible` on the sides its component's `CrossmatchScope` covers and null on the other (a unit found incompatible is simply not issued). A unit with an earlier current crossmatch gets a new version and needs `amendment_reason`.
5. Each unit goes `reserved → issued`; `storage_location_id` is cleared and a movement row is written.
6. `bb_cases.supplied_at = now()` (the latest issue). The case is `closed` when every `bb_case_components` line has as many issued units as its `quantity`.
7. One `sh_print_logs` row per page (`bb.compatibility_report`, `printable_type = bb.crossmatch`). The response is the PDF itself (mPDF, one page per unit, 215 × 292 mm letterhead paper: top margin 35 mm for the pre-printed header, bottom 30 mm, left/right 12.7 mm). It is rendered inside the transaction, so a failed render issues nothing.

Audit: `bb.case.details_updated` (cause of transfusion changed), `bb.patient.blood_grouped` / `bb.patient.blood_group_amended` (on `bb.sample`), `bb.donor.post_vitals_recorded` (on `bb.case_donor`), `bb.crossmatch.recorded` (on `bb.crossmatch`), `bb.blood.issued` and `bb.case.closed` (on `bb.case`).

| API | Screen |
|---|---|
| `GET blood-issue?invoice_no=` or `?donor_no=` | Scan → the whole case: `case` (with `supplied_at`, components with `reserved_count` / `issued_count` / `remaining_count`), `patient`, `invoice`, `patient_sample` with its current `blood_group`, every donor (decision, `invoice_no`, `examination` with `pre` / `post` vitals, `serology`, `bag`, `needs_post_vitals`), every unit reserved for or issued to the case (with `crossmatch_scope`, `crossmatch` (with `scope`), `compatibility_report_print_count`, `can_issue`), and `can_issue`. A donor no opens the latest case of this branch the donor is on, closed ones included (for reprints). |
| `GET cases/{case}/blood-issue` | Same screen, opened from the Pending tab or a case. |
| `GET blood-issue/pending` | Pending tab: open cases with a `reserved` unit. |
| `POST cases/{case}/blood-issue` | "Issue Blood": `technologist_id`, `physician_id`, `transfusion_reason` (required, max 1000), `units: [{unit_id, remarks?}]` (1–20), `patient_blood_group` (required until recorded), `amendment_reason`, optional `post_donation: [{case_donor_id, pulse_bpm, bp_systolic, bp_diastolic, temperature_f}]` (may be omitted or empty). Returns `application/pdf` (201). |
| `POST cases/{case}/compatibility-reports` | Reprint: `unit_ids`, `reprint_reason` (required for a reprint). Returns `application/pdf` (200) with a "REPRINT" mark. |

Error codes (JSON as usual): `CASE_NOT_OPEN`, `CASE_TAKES_NO_BLOOD` (Autologous PRP / Therapeutic Phlebotomy case: no issue, no Compatibility Test Report), `UNIT_NOT_IN_CASE`, `UNIT_NOT_RESERVED` (409), `UNIT_EXPIRED`, `PATIENT_SAMPLE_NOT_COLLECTED`, `AMENDMENT_REASON_REQUIRED`, `CASE_DONOR_NOT_IN_CASE`, `POST_DONATION_VITALS_RECORDED` (409), `CROSSMATCH_NOT_RECORDED` (reprint), `REPRINT_REASON_REQUIRED`, `VALIDATION_FAILED` (`patient_blood_group` or `transfusion_reason` missing, unknown user, vitals out of range), `FORBIDDEN`.

Not built yet: recording an incompatible crossmatch without issuing, and returns / releasing a reservation. A stock unit reaches this screen through step 4a.

### 10. Consumable stock (runs alongside everything)

- Item → `bb_consumables` (`current_balance` cached here).
- Batch / expiry (only if `tracks_batch` / `tracks_expiry`) → `bb_consumable_batches` (`batch_no`, `expires_at`, `quantity_on_hand`).
- Every movement → `bb_consumable_transactions`: `type` (received / issued / adjusted), `quantity`, `balance_after`, `reason`, `performed_by`, `occurred_at`. Append-only; the balance is derived from this ledger.

## Patient register → database

| Register column | Where |
|---|---|
| Patient details | `sh_patient_master` |
| Name of consultant | `sh_billing_referrals.doctor_id` |
| Component | `bb_case_components` (type + quantity, from the billed component items) |
| Care of | `bb_cases.care_type` (`daycare` / `out`), entered on the bill |
| Cause of transfusion | `bb_cases.transfusion_reason` |
| Supply time | `bb_cases.supplied_at` |
| Blood group | `bb_test_results` on the patient sample |
| Crossmatch | `bb_crossmatches` (patient sample × unit), printed as the Compatibility Test Report |

## Managed statuses

Every status is a PHP enum cast on the model. "Set by" is the Action that writes the value today; a transition with no Action is declared in the enum but nothing moves a record there yet.

| Table (enum) | Value | Set by | Notes |
|---|---|---|---|
| `sh_billing` (`BillingStatus`) | `active` | `CreateInvoice` | Every saved bill. |
| | `cancelled` | — | Not built. A case or donor refuses a non-active bill (`INVOICE_NOT_ACTIVE`). |
| `bb_cases` (`CaseStatus`) | `open` | `OpenCaseForInvoice` | All Blood Bank work needs an open case (`CASE_NOT_OPEN`). |
| | `closed` | `IssueBlood`, `CollectSamples`, `RecordTherapeuticPhlebotomy`, `CloseCase` (all via `CaseCloser`) | Issue: when every `bb_case_components` line has as many issued units as its `quantity`. Autologous PRP: patient sample collected. Therapeutic Phlebotomy: recorded. Close case: by hand, reason required while billed work is outstanding, refused while a unit is reserved. `closed_at/by`, `close_reason`; audit `bb.case.closed` with `via`. |
| | `cancelled` | — | Not built. |
| `bb_case_donors` (`CaseDonorStatus`) | `registered` | `AddCaseDonor` | |
| | `fit` / `deferred` | `RecordPhysicalExamination` (`ExaminationDecision`); `StockUnitIntake` at Collect (stock row, `fit` at once) | Correctable until the donor sample is drawn. A stock row is never examined. |
| | `eligible` / `deferred` | `RecordDonorSerology` (`SerologyDecision`) | Accept refused on a reactive / malaria found result. Correctable until the bag is collected (stock row: until its patient label). A stock row's unit follows (step 4a). |
| | `collected` | `CollectBlood`; `ReserveStockUnit` (stock row) | Final. Never moves backward; a deferral is final for this case. |
| `bb_case_donors` (`CaseDonorSource`) | `donor` / `stock` | `AddCaseDonor` / `CollectSamples` (`unit_no` row) | `stock` rows carry `unit_id` (step 4a). |
| `bb_samples` (`SampleStatus`) | `collected` | `CollectSamples` | |
| | `tested` | `RecordDonorSerology` (donor), `IssueBlood` (patient) | |
| | `in_testing`, `rejected` | — | Not used yet; a `rejected` sample already lets the subject be collected again. |
| `bb_test_results` (`TestResultStatus`) | `entered` | `RecordDonorSerology`, `IssueBlood` (patient blood group) | Changes are new versions (`version`, `is_current`, `amendment_reason`). |
| | `reviewed`, `rejected` | — | Review not built (Test Results menu). Donor serology has no review step by design. |
| `bb_crossmatches` (`TestResultStatus`) | `reviewed` | `IssueBlood` | Saved already reviewed: technologist = `tested_by`, physician = `reviewed_by`. Only compatible/compatible is saved. |
| | `entered`, `rejected` | — | Recording an incompatible crossmatch without issuing is not built. |
| `bb_collections` (`CollectionStatus`) | `collected` | `CollectBlood` | One bag per case donor. |
| | `cancelled` | — | Not built; a cancelled bag is already ignored by the collection gate. |
| `bb_units` (`UnitStatus`) | `reserved` / `available` | `CollectBlood` | `reserved` + `case_id` for a patient row of the bag plan, else `available` stock. |
| | `held` | `CollectSamples` (`unit_no` row), `RecordDonorSerology` (Defer → Accept) | A stock unit sampled for a case (`case_id` set) while its serology is pending; only from `available`. |
| | `held` → `reserved` | `ReserveStockUnit` | Patient label printed; the unit stays in its fridge. |
| | `held` → `available` / `quarantined` | `RecordDonorSerology` (Defer; `quarantined` on a reactive / malaria found result), `CloseCase` (`available`) | `case_id` cleared; the movement row keeps the case. |
| | `issued` | `IssueBlood` | From `reserved` only (`UNIT_NOT_RESERVED`); location cleared. |
| | `reserved` → `available`, `quarantined` → `available` / `discarded`, `expired` | — | Not built (release a reservation, returns, quarantine decision, discard, hourly expiry job). |

Each unit change also writes a `bb_unit_movements` row (`UnitMovementRecorder`); each donor / case / sample status change is audit-logged with old and new values.

## Database changes log

What each migration added after the first schema (2026-10-24), newest first. Run order matters only within a day.

| Date | Migration | Change |
|---|---|---|
| 2026-11-21 | edited in place: `create_bb_case_component_and_crossmatch_tables` | `bb_crossmatches.major_result` / `minor_result` nullable: a side outside the component's crossmatch scope is null. |
| 2026-11-21 | `add_stock_source_to_bb_case_donors_table` | `bb_case_donors.source` (`donor` / `stock`, default `donor`), `unit_id` (FK `bb_units`), check `source = stock` ⇔ `unit_id` set; `unique(case_id, donor_id)` becomes a partial index `WHERE source = 'donor'`, plus `unique(case_id, unit_id)`. `sh_print_logs.document_type` `bb.component_label` split into `bb.storage_label` / `bb.patient_label` (no data migration: dev DB fresh). |
| 2026-11-19 | edited in place: `create_bb_collection_and_unit_tables` | `bb_collections.volume_ml` removed: only each unit records a volume (optional ml per unit at Collect). |
| 2026-11-19 | `create_bb_phlebotomies_table` | One row per Therapeutic Phlebotomy case: `volume_ml`, optional pre/post vitals, `notes`, `performed_at/by`. |
| 2026-11-19 | edited in place (dev DB fresh): `create_bb_cases_table`, `create_bb_collection_and_unit_tables`, `link_bb_component_types_and_cases_to_billing`, `create_sh_print_logs_table` | `bb_cases.service`, `keeps_units_in_storage`, `closed_at`, `closed_by`, `close_reason`; `bb_billable_items` replaces `bb_component_types.test_item_*`; `sh_print_logs.copies`. |
| 2026-11-17 | `create_sh_department_rooms_table`; `sh_departments.type` and `sh_branches.lab_room_no` added in their create migrations | Room per branch for non-lab departments (Non-Lab Copy) and the branch lab room (Lab Copy); replaces the global `sh_departments.room_no`. |
| 2026-11-09 | `add_delivery_and_paid_amount_to_sh_billing_table` | `sh_billing.delivery_at` (nullable only for older bills), `paid_amount` (default 0) + check `paid_amount <= amount`. |
| 2026-11-09 | `link_bb_component_types_and_cases_to_billing` | `bb_component_types.test_item_id` (required, unique) + `test_item_code` / `test_item_name` copies; `bb_case_components.test_item_id`; `bb_cases.billing_id` required and unique. |
| 2026-11-09 | `add_category_discount_and_idempotency_to_sh_billing_tables` | `sh_billing.patient_type_id`, `subtotal_amount`, `discount_amount`, `rounding_amount`, `idempotency_key`; line `category` (per line, bills may mix), discount columns, `net_amount`; `sh_billing_discounts`. |
| 2026-11-09 | `create_sh_patient_types_and_discount_policies_tables` | `sh_patient_types`, `sh_discount_policies` (General / General 25%). |
| 2026-11-09 | `add_category_and_discount_rate_to_sh_test_items_table` | `sh_test_items.category` (`general` / `blood_bank`), `discount_rate_bp`. |
| 2026-11-08 | `add_unit_prefix_to_bb_component_types_table` | `bb_component_types.unit_prefix` (WB `BH`, PCV `BC`, FP `BP`, CRYO `BR`, PLATELET `BT`, PLASMA `BS`). |
| 2026-11-08 | `insert_bb_malarial_parasite_test_definition` | `MALARIAL_PARASITE` test definition. |
| 2026-11-08 | `add_profile_fields_to_bb_donors_table` | `father_name`, `marital_status`, `religion`, `occupation`, `is_regular_volunteer`. |
| 2026-11-03 | `set_bb_serology_test_methods` | `method` = "Immunochromatographic Test" on the screening tests. |
| 2026-11-03 | `add_branch_id_to_bb_units_and_storage_locations` | Stock and locations per branch. |
| 2026-11-02 | `insert_bb_serology_test_definitions`, `insert_sh_additional_donor_charge_test_item` | Blood group + screening test definitions; `BB-ADDL-DONOR` charge (`general`). |
| 2026-10-28 | `add_branch_id_to_bb_cases_table`, `add_branch_id_to_sh_billing_table`, `create_sh_number_sequences_table` | Branch ownership; numbering sequences. |

## History rules (nothing is overwritten)

- Test results and crossmatches: new `version` row, `is_current` flips, `amendment_reason` kept.
- Eligibility and physical examination: one row per donation; changes go through the audit log with old/new values.
- Units: every status, location and case assignment change is a `bb_unit_movements` row.
- Stock: every change is a `bb_consumable_transactions` row.
- Prints: every print and reprint is a `sh_print_logs` row.
- Cases, donors, consumables: soft deleted, never removed.

## Decisions so far

- `case_no` stays the case identifier and `invoice_no` a search key, but since 2026-11-09 every case is opened from exactly one bill with Blood Bank items (`bb_cases.billing_id` required and unique; since 2026-11-17 that bill may also carry other items). Earlier decision "a case can exist before billing" is superseded. A wrong bill can't be edited or cancelled yet; it means a new bill (and case).
- A case whose bill has no component item (only services) opens with no `bb_case_components` rows and is closed by hand ("Close case"; list filter `needs_closing`). Map every component item in `bb_billable_items`.
- One bill, one service (2026-11-19): Autologous PRP and Therapeutic Phlebotomy are billed alone; extras go with anything. PRP and CRYO / Cryo Poor Plasma give the patient their unit and store nothing; FP is given at once and labelled FP, FFP is a processed (stored) component; Platelet 4:1 is a platelet case that allows 4 donors; the Thalassemia package (TH01) is PCV; SWPF01 keeps the patient's units in storage for a later visit.
- Collect and component preparation are one step (2026-11-19): the bag is made into units by a fixed recipe (`BagPlan`, step 6) chosen from what the case still needs, so the bench never decides the split. Labels are printed at collection: the patient label (`bb_bag_label.ezpx`) for a reserved unit, the storage label (`bb_bg_storage_bag_label.ezpx`) for stock. The bag label, the Component Preparation menu and `bb.collection.print_label` / `bb.unit.prepare` are gone.
- Eligibility lives on `bb_case_donors` (no separate assessments table), decided twice: physical exam (`fit` / `deferred`), then serology (`eligible` / `deferred`). Both are manual physician inputs.
- Donor serology has its own menu (not the Donor Examination form): different queue, time and place. Blood group is one combined result, and a reactive (or malaria found) screening result blocks Accept.
- The crossmatch is patient sample × **unit**, not × donor, so units from storage (no donor brought) are covered.
- Stock blood (2026-11-21): a stock bag stands in for a donor of the case (`bb_case_donors.source = stock`) and goes through the donor steps: its bag sample holds it (`held`), the row is `fit` without a physical exam, serology is re-done on the bag sample (Accept = booked, Defer = back to stock or quarantine), the patient label reserves it, and Blood Issue is unchanged. There is no separate release: a deferred bag goes back by itself, and Close case returns held ones. The report prints the re-test. The bag is crossmatched at issue, like a donor's own unit (the earlier "crossmatch before reserving a stock unit" idea is superseded).
- Crossmatch by component (2026-11-21): the crossmatch result is not entered at issue. Whole blood is crossmatched major and minor, PCV major, every plasma or platelet component (FFP, FP, CRYO, Cryo Poor Plasma, Platelet, PRP, PA) minor (`CrossmatchScope`); the unit is recorded compatible on those sides and the report prints them. An incompatible unit is not issued (no record). Issue Blood also asks for the cause of transfusion (required) and saves it on the case.
- "Name of consultant" is the billing referral doctor; Blood Bank stores no doctor.
- Print logs are Shared (`sh_print_logs`), not Blood Bank.

## Open questions

1. **"Unit: 1" on the Compatibility Test Report.** Check old reports for a patient who got 2+ bags:
   - Unit 1, Unit 2… → which bag for the patient (current assumption, counted from the case's units).
   - Always Unit 1 → bags per report; always 1.
   - Changes only for split bags → the split number from `unit_no` (`-1`, `-2`).
2. **Returns.** The report says WB/PRBC may be returned within 30 min of issue, and Platelet/FFP can't be returned. Today a return is just a status change with a reason; nothing enforces the window or the per-component rule. Option: `is_returnable` and `return_window_minutes` on `bb_component_types`.
