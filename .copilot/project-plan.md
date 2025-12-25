# Backend Accounting System (Laravel API) — Master Project Plan

Last updated: 2025-12-25

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

Note: Some Phase 2-related code may already exist from earlier iterations; treat this plan as the FINAL source of truth and refactor/remove anything that conflicts with the user-centric permission model and optional approval workflow.

[16] User, Role & Permission Foundation — COMPLETED (2025-12-24)
- Seed initial admin user
- Seed roles as OPTIONAL templates
- Seed permissions as standalone entities

[17] Permission Matrix (Atomic & Granular) — COMPLETED (2025-12-24)
- Define final permission list per module
- Ensure permissions are independent of roles

[18] User Permission Assignment & Override — COMPLETED (2025-12-25)
- Assign permissions directly to users
- Allow override even if user has a role
- Support permission copy between users

[19] Policy & Gate (Permission-Driven) — COMPLETED (2025-12-25)
- Implement Laravel Policy / Gate
- Authorization checks MUST use permission only

[20] Journal Status & Transition Rules — COMPLETED (2025-12-25)
- Define permission-based journal state transitions
- Support self-approve, auto-approve, skip-approve
- Record approval flexibility requirement: approval behavior must be configurable (e.g. auto-approve for UMKM vs separate approver) via settings, without hard-coded rigidity

[21] Journal Approval & Posting API — COMPLETED (2025-12-25)
- Implement create (draft), approve (optional), post
- Auto-approve when allowed by permission
- Validate period and budget

[22] Reversal Journal Logic — COMPLETED (2025-12-25)
- Allow reversal for POSTED journals only
- Create automatic reversing journal

[23] Period Lock (Open / Close) — COMPLETED (2025-12-25)
- Block posting & reversal when period is closed
- Allow reopen with permission & audit log

[24] Audit Log & Activity Tracking — COMPLETED (2025-12-25)
- Log create, approve, post, reverse, period open/close
- Audit is RECORDING, not PREVENTION

[25] General Ledger Query — COMPLETED (2025-12-25)
- GL is derived from POSTED journal_lines only

[26] Phase 2 Review — COMPLETED (2025-12-25)
- Validate UMKM scenario (1 user, all permissions)
- Validate multi-user scenario
- Ensure no enforced workflow rigidity

---

## Phase 3 — AP / AR Automation

[27] Vendor & Customer Master — COMPLETED (2025-12-25)
- vendors
- customers
Output: Business partners

[28] Purchasing Tables (AP) — COMPLETED (2025-12-25)
- Purchase orders
- Vendor invoices
- Payments
Output: AP schema

[29] Sales Tables (AR) — COMPLETED (2025-12-25)
- Sales invoices
- Customer payments
Output: AR schema

[30] Purchasing Service — COMPLETED (2025-12-25)
- AP transactions → auto journal
- AP business documents follow 3-stage workflow: draft -> approved -> posted
- Auto-journal is created and posted when the AP document is posted (not on approved)
- Must support configurable behavior: UMKM may auto-approve and auto-post in one action when permitted; controlled orgs may require separate approver vs poster
Output: AP accounting automation

[31] Sales Service — COMPLETED (2025-12-25)
- AR transactions → auto journal
- AR business documents follow 3-stage workflow: draft -> approved -> posted
- Auto-journal is created and posted when the AR document is posted (not on approved)
- Must support configurable behavior: UMKM may auto-approve and auto-post in one action when permitted; controlled orgs may require separate approver vs poster
Output: AR accounting automation

[32] Payment Service — COMPLETED (2025-12-25)
- Customer payments and vendor payments follow 3-stage workflow: draft -> approved -> posted
- Auto-journal is created and posted when payment is posted
- Customer receipt journal: Dr Cash/Bank, Cr AR
- Vendor payment journal: Dr AP, Cr Cash/Bank
Output: Payment accounting

[33] Purchasing API — COMPLETED (2025-12-25)
- PO, Invoice, Payment
Output: AP API

[34] Sales API — COMPLETED (2025-12-25)
- Invoice, Payment
Output: AR API

[35] Phase 3 Review — COMPLETED (2025-12-25)
- End-to-end AP/AR testing
Output: Phase 3 completed

---

## Phase 4 — Inventory & Budget Control

[36] Product & Warehouse Master — COMPLETED (2025-12-25)
- products
- warehouses
Output: Inventory master data

[37] Inventory Movement Logic — COMPLETED (2025-12-25)
- Stock IN / OUT
Output: Inventory flow

[38] Inventory Valuation — COMPLETED (2025-12-25)
- FIFO or Average
Output: Cost calculation

[39] COGS Journal — COMPLETED (2025-12-25)
- Sales → COGS journal
Output: Correct margins

[40] Project & Budget Tables — NOT STARTED
- project_budget
Output: Budget schema

[41] Budget Service — NOT STARTED
- Budget aggregation
Output: Budget engine

[42] Budget Control — NOT STARTED
- Hard stop or warning
Output: Budget enforcement

[43] Phase 4 Review — NOT STARTED
- Budget vs actual
Output: Phase 4 completed

---

## Phase 5 — Reporting & Finalization

[44] Financial Reports — NOT STARTED
- Profit & Loss
- Balance Sheet
Output: Core financial reports

[45] Budget Reports — NOT STARTED
- Budget vs actual pivot
Output: Budget reporting

[46] API Hardening — NOT STARTED
- Validation
- Error handling
Output: Stable API

[47] Performance Optimization — NOT STARTED
- Indexing
- Query tuning
Output: Fast system

[48] Documentation — NOT STARTED
- API documentation
- Flow documentation
Output: Project documentation

[49] Final Testing — NOT STARTED
- Business scenario testing
Output: Backend system completed
