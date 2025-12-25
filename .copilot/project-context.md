# Backend Accounting System (Laravel API) — Project Context

Last updated: 2025-12-24

## Tracking Files (Source of Truth)
This repository uses two tracking files:
1) `.copilot/project-context.md` → living project memory (this file)
2) `.copilot/project-plan.md` → master project roadmap & tracking

Rules:
- Read both files before suggesting or generating code
- Never skip phases or steps; never implement future steps prematurely
- Always update tracking status incrementally (NOT STARTED / IN PROGRESS / COMPLETED)

## Purpose
Build a clean, modular, and correct accounting backend system similar to professional ERP accounting cores.
Priority: accuracy, auditability, and flexible controls over speed/feature count.

## Global Design Principle (LOCKED)
The application must be flexible and scalable, usable by:
- UMKM (single user, minimal control)
- Small–medium organizations (multiple users, controlled access)

The system provides control tools (permissions, optional approval, audit), but does not enforce organizational idealism.
All operational risks (fraud, separation of duty, maker/approver discipline) remain the client's responsibility.

## Non-Negotiable Accounting Principles
- Posted journals are immutable
- No balances stored in chart of accounts
- All balances derived from posted journal lines
- Reversal is used instead of editing posted journals
- Period closing and lock must be enforced

## Authorization Model (FINAL)
- Permissions are user-centric: assigned directly to users.
- Roles are optional and act only as permission templates.
- Users may exist without any role.

Authorization checks MUST:
- Check permission only (e.g. `user->hasPermissionTo('journal.post')`).
- Never assume role hierarchy.
- Never enforce maker ≠ approver rules.

## Journal Approval & Posting (FINAL)
Journal statuses:
- draft
- approved
- posted
- reversed

Approval is optional and flexible:
- Self-approval is allowed.
- Approval may be skipped or auto-applied based on permission.

System must:
- Validate permission
- Validate period open
- Validate budget (if enabled)
- Record audit trail

System must not:
- Enforce separation of duty
- Block self-approval
- Impose organizational rules

## Roadmap Governance
The master multi-phase roadmap lives in `.copilot/project-plan.md` and is non-negotiable.
When a step starts or completes, update the status here (Phase 2 section) and in the master plan file.

## Developer Notes (Style)
- Prefer explicit, readable code
- Avoid over-engineering and “Laravel magic” unless explained
- Keep controllers thin; business logic in services only
- Seeders are data initialization only

## Architecture Rules
- Seeder = data initialization only
- Service layer = business logic only
- Controllers must stay thin
- Authorization MUST use Laravel Policy / Gate (permission-driven)
- No business logic in controllers or seeders

## Strict Do-Not Rules
- Do NOT invent features
- Do NOT skip phases
- Do NOT refactor unrelated code
- Do NOT introduce accounting logic early
- Do NOT enforce organizational workflows (maker/approver, self-approval blocks, role hierarchy)

## Progress Log

### Phase 1 — COMPLETED
- Laravel initialized
- Environment & database configured
- Sanctum authentication installed
- Spatie laravel-permission installed, published, and migrated
- Base folder structure (Domain / Service / DTO)
- Core accounting tables: companies, accounting_periods, chart_of_accounts
- Journal tables: journals, journal_lines
- Master seeders (COA & accounting period sample)
- Core Eloquent models and relationships
- Journal Service (DRAFT only)
- Journal API CRUD (draft journals only)
- Trial Balance query
- Phase 1 manual testing completed

### Phase 2 — COMPLETED (2025-12-25)

#### Phase 2 Summary
Phase 2 steps [16]–[26] are implemented and validated.

Delivered capabilities:
- User-centric permissions (roles optional templates) and direct-permission override behavior
- Permission-driven Policies/Gates (no role-name checks)
- Journal lifecycle transitions (draft → approved → posted → reversed) with optional approval and self-approval allowed
- Journal approve/post/reverse APIs and services
- Period open/close (lock) enforced for posting/reversal
- Audit logging for journal and period lifecycle events (recording only)
- General Ledger query derived from POSTED journal lines only

Tracking status source of truth remains `.copilot/project-plan.md`.

### Phase 3 — IN PROGRESS

#### Step 27 — Vendor & Customer Master
Work completed (2025-12-25):
- Added `vendors` and `customers` tables (company-scoped unique codes)
- Added `Vendor`/`Customer` models and CRUD services
- Added permission-driven gates and protected API endpoints
