# Backend Accounting System (Laravel API) — Master Project Plan

Last updated: 2025-12-22

## Rules (Non-Negotiable)
- This file is the master roadmap & tracking.
- Status vocabulary: NOT STARTED / IN PROGRESS / COMPLETED.
- Never skip phases or steps.
- Never implement future steps prematurely.
- Read this file AND `.copilot/project-context.md` before suggesting or generating code.
- When a step starts/completes, update both files incrementally.

## Project Objective
Build a correct, auditable, and controllable accounting backend system similar to ERP accounting cores.

Core principles:
- No balance stored in chart of accounts
- All balances derived from posted journal lines
- Posted journals are immutable
- Corrections use reversal journals
- Period closing & locking enforced
- Full audit trail required

## Phase Overview
- Phase 1: Accounting Core Foundation
- Phase 2: Control, Approval & Audit
- Phase 3: AP / AR Automation
- Phase 4: Inventory & Budget Control
- Phase 5: Reporting, Hardening & Finalization

---

## Phase 1 — Accounting Core Foundation

[1] Install Laravel — COMPLETED
Output: Laravel project ready

[2] Initialize Git Repository — COMPLETED
Output: Version control ready

[3] Environment & Database Setup — COMPLETED
Output: Database connected

[4] Authentication (Sanctum) — COMPLETED
Output: API authentication working

[5] Authorization Package — COMPLETED
Output: RBAC package installed

[6] Publish & Migrate Spatie — COMPLETED
Output: Role & permission tables created

[7] Base Folder Structure — COMPLETED
Output: Clean architecture foundation

[8] Core Accounting Migrations — COMPLETED
- companies
- accounting_periods
- chart_of_accounts
Output: Core accounting tables

[9] Journal Migrations — COMPLETED
- journals
- journal_lines
Output: General Ledger tables

[10] Master Seeders — COMPLETED
- Chart of Accounts
- Sample accounting period
Output: Seeded master data

[11] Core Models — COMPLETED
- Eloquent models
- Relationships
Output: ORM layer ready

[12] Journal Service (Draft Only) — COMPLETED
- Create journal drafts
- No accounting rules yet
Output: Journal draft logic

[13] Journal API (Draft CRUD) — COMPLETED
- Create, update, delete draft journals
Output: Draft journal API

[14] Trial Balance Query — COMPLETED
- Calculate balances from journal lines
Output: Trial balance logic

[15] Phase 1 Review — COMPLETED
- Manual journal testing
Output: Phase 1 completed

---

## Phase 2 — Control, Approval & Audit

[16] User & Role Seeder — COMPLETED (2025-12-22)
- Roles: admin, supervisor, entry
- Seed development users
- Assign ONE role per user
Output: Base system actors
Verification: `php artisan db:seed` ran twice successfully (idempotent).

[17] Permission Matrix (DESIGN FIRST) — COMPLETED (2025-12-22)
- Define permissions per role
- No coding before matrix approved
Output: Permission map
Design doc: `.copilot/step-17-permission-matrix.md`

[18] Policy & Gate — COMPLETED (2025-12-22)
- Laravel Policy for authorization
Output: Access control enforced
Artifacts:
- Policies: `app/Domain/Accounting/*/*Policy.php`, `app/Domain/System/SystemPolicy.php`
- Gates: `app/Providers/AppServiceProvider.php`

[19] Journal Approval Flow — NOT STARTED
- Draft → Submitted → Posted
- Only authorized roles can approve
Output: Controlled posting

[20] Reversal Logic — NOT STARTED
- Reverse posted journals
- No edit/delete of posted data
Output: Safe correction mechanism

[21] Period Lock Logic — NOT STARTED
- Prevent posting to closed periods
Output: Period control

[22] Audit Log — NOT STARTED
- Track all critical actions
Output: Audit trail

[23] General Ledger Query — NOT STARTED
- Account ledger
- Running balance
Output: GL reporting logic

[24] Phase 2 Review — NOT STARTED
- Test approval, lock, reversal
Output: Phase 2 completed

---

## Phase 3 — AP / AR Automation

[25] Vendor & Customer Master — NOT STARTED
- vendors
- customers
Output: Business partners

[26] Purchasing Tables (AP) — NOT STARTED
- Purchase orders
- Vendor invoices
- Payments
Output: AP schema

[27] Sales Tables (AR) — NOT STARTED
- Sales invoices
- Customer payments
Output: AR schema

[28] Purchasing Service — NOT STARTED
- AP transactions → auto journal
Output: AP accounting automation

[29] Sales Service — NOT STARTED
- AR transactions → auto journal
Output: AR accounting automation

[30] Payment Service — NOT STARTED
- Cash / Bank journals
Output: Payment accounting

[31] Purchasing API — NOT STARTED
- PO, Invoice, Payment
Output: AP API

[32] Sales API — NOT STARTED
- Invoice, Payment
Output: AR API

[33] Phase 3 Review — NOT STARTED
- End-to-end AP/AR testing
Output: Phase 3 completed

---

## Phase 4 — Inventory & Budget Control

[34] Product & Warehouse Master — NOT STARTED
- products
- warehouses
Output: Inventory master data

[35] Inventory Movement Logic — NOT STARTED
- Stock IN / OUT
Output: Inventory flow

[36] Inventory Valuation — NOT STARTED
- FIFO or Average
Output: Cost calculation

[37] COGS Journal — NOT STARTED
- Sales → COGS journal
Output: Correct margins

[38] Project & Budget Tables — NOT STARTED
- project_budget
Output: Budget schema

[39] Budget Service — NOT STARTED
- Budget aggregation
Output: Budget engine

[40] Budget Control — NOT STARTED
- Hard stop or warning
Output: Budget enforcement

[41] Phase 4 Review — NOT STARTED
- Budget vs actual
Output: Phase 4 completed

---

## Phase 5 — Reporting & Finalization

[42] Financial Reports — NOT STARTED
- Profit & Loss
- Balance Sheet
Output: Core financial reports

[43] Budget Reports — NOT STARTED
- Budget vs actual pivot
Output: Budget reporting

[44] API Hardening — NOT STARTED
- Validation
- Error handling
Output: Stable API

[45] Performance Optimization — NOT STARTED
- Indexing
- Query tuning
Output: Fast system

[46] Documentation — NOT STARTED
- API documentation
- Flow documentation
Output: Project documentation

[47] Final Testing — NOT STARTED
- Business scenario testing
Output: Backend system completed
