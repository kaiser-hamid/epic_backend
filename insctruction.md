# Task: Redesign Blood Bank Case Entry & Billing Workflow

Act as a Senior Software Architect. I need to redesign the existing Blood Bank billing and case-entry workflow.

**IMPORTANT RESTRICTIONS:**
- Do NOT modify, create, delete, or rename any files.
- Do NOT write or execute migrations, code changes, or commands that modify the project.
- Do NOT start implementation.
- Your responsibility is to inspect the existing codebase, understand the current architecture, identify required changes, and prepare a detailed implementation plan.
- Challenge my decisions if you find architectural problems, edge cases, or inconsistencies. Don't blindly agree with everything.
- Ask clarifying questions when business rules are ambiguous. Drill me before finalizing the plan.

## 1. Separate Billing from Case Entry

Billing will move into a dedicated module.

The Dashboard will have a separate Billing menu with the following submenus:

- **Billing (Group)**
- **New Bill Entry**
- **Bill List**

Billing should be a generic module that supports different types of billing. However, invoices must not contain a mixture of Blood Bank and non-Blood Bank test items.

## 2. Blood Bank Case Identification

Case entry will no longer have an option to create a new case manually.

Instead:
- The Case menu will display cases automatically generated from saved invoices.
- When an invoice contains one or more Blood Bank test items, it must automatically be considered a Blood Bank Case.
- Only invoices containing Blood Bank test items should appear in the Blood Bank Case list.
- Users will open an existing case and add donors to it.
- Case creation and donor registration must follow the existing domain relationships and business rules.

The invoice should be the source of the Blood Bank case.

## 3. Conditional Billing UI Behavior

Although billing is generic, its UI must enforce Blood Bank-specific invoice rules.

When creating a new bill:

**Scenario A: No Blood Bank test item selected**
- Display the normal generic billing interface.
- Do not show the Blood Bank-specific Care Of option.

**Scenario B: A Blood Bank test item is selected**
- Conditionally display the Care Of (Takeaway / Daycare) option.
- If non-Blood Bank test items were already selected, remove them from the selected items.
- Prevent users from adding any non-Blood Bank test items afterward.
- The invoice must contain Blood Bank test items only.

In other words:
- A Blood Bank invoice cannot contain non-Blood Bank test items.
- A generic/non-Blood Bank invoice cannot become a mixed invoice.
- No compound invoices are allowed.

Please evaluate whether this restriction should be enforced only in the frontend or at the backend/domain level as well. I expect proper server-side enforcement.

## 4. Billing-to-Case Integration

When saving a bill:
- Identify whether the selected test items contain Blood Bank test items.
- If Blood Bank test items are present, pass the necessary data to the Blood Bank domain to create the corresponding case.
- The saved invoice and Blood Bank case must remain correctly associated.
- Prevent duplicate case creation if the bill is updated, retried, or submitted more than once.
- Consider database transactions, data consistency, and failure scenarios.

Investigate the existing case creation workflow and recommend how it should integrate with the new billing workflow.

## 5. Discount Management

Introduce a discount system.

Initial requirement:
- Add a dedicated discount table in the database.
- Initially, there will be one "General" discount rule: **25%**.
- Add a discount field to the billing UI.
- Persist the selected/applied discount information in the database.

However, the design must be extensible because multiple discount rules and conditions will be introduced later.

Evaluate:
- Whether discount should be stored as a percentage, fixed amount, or both.
- Whether invoice items or the entire invoice should receive the discount.
- How to preserve the applied discount when discount rules change later.
- How to calculate subtotal, discount amount, and payable amount accurately.
- Whether historical invoices should retain their original discount values.

Do not assume business rules that I haven't specified. Ask me.

## 6. Codebase Investigation

Before proposing a solution, inspect the existing implementation.

Pay particular attention to:

- Existing Billing-related models, migrations, services, actions, DTOs, and API endpoints.
- Existing Blood Bank case creation flow.
- Case, patient, invoice, test item, sample, and donor relationships.
- How Blood Bank test items are currently identified.
- Existing frontend billing and case-entry components.
- Existing validation and transaction boundaries.
- Existing naming conventions and architectural patterns.

Reuse the current architecture where appropriate. Avoid introducing unnecessary abstractions or duplicating existing functionality.

## 7. Expected Deliverables

Work in the following stages:

### Stage 1 — Understand the Existing System
Explain the current billing-to-case workflow based on actual codebase findings.

Identify relevant files and explain their responsibilities.

### Stage 2 — Gap Analysis
Compare the existing implementation with the proposed workflow.

Identify:
- What needs to change.
- What can remain unchanged.
- What should be removed or deprecated.
- Potential architectural and data-integrity problems.
- Unclear business rules.

### Stage 3 — Proposed Architecture
Propose the target architecture, including:

- Billing module responsibilities.
- Blood Bank case identification and creation.
- Invoice-to-case relationship.
- Backend validation and business-rule enforcement.
- Frontend conditional behavior.
- Discount data model and calculation strategy.

Include database relationship diagrams or Mermaid diagrams where useful.

### Stage 4 — Detailed Implementation Plan
Break the work into logical implementation phases.

For each phase, specify:
- Backend changes.
- Frontend changes.
- Database changes.
- API contract changes.
- Dependencies on other phases.
- Potential risks.

Mention exact existing file paths wherever possible. Clearly distinguish existing files from files you recommend creating.

### Stage 5 — Drill Me
Before finalizing the architecture, ask me questions about unresolved business rules.

Ask questions in small, manageable groups rather than presenting a huge questionnaire.

Prioritize questions that could fundamentally change the database schema, domain boundaries, or workflow.

**Do not proceed to implementation unless I explicitly authorize it.**

## Final Instruction

Think carefully about domain boundaries, data integrity, transaction safety, extensibility, and maintainability.

I want a clean, scalable design that fits the existing Laravel + React application. I do not want generic repository-pattern boilerplate or unnecessary architectural complexity.

**For now, this is an architecture and planning exercise only.W Absolutely no file modifications.**
