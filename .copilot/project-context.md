# Backend Accounting System (Laravel API) — Project Context

Last updated: 2025-12-22

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
Priority: accuracy, auditability, and control over speed/feature count.

## Non-Negotiable Accounting Principles
- Posted journals are immutable
- No balances stored in chart of accounts
- All balances derived from posted journal lines
- Reversal is used instead of editing posted journals
- Period closing and lock must be enforced

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
- Authorization MUST use Laravel Policy (later phase)
- No business logic in controllers or seeders

## Strict Do-Not Rules
- Do NOT invent features
- Do NOT skip phases
- Do NOT refactor unrelated code
- Do NOT introduce accounting logic early
- Do NOT assume permissions or approval behavior

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

### Phase 2 — IN PROGRESS

#### Step 16 — User & Role Seeder — COMPLETED (2025-12-22)
Scope:
- Seed ONLY base roles: admin, supervisor, entry
- Seed development users
- Assign exactly ONE role per user
- DO NOT create permissions
- DO NOT create policies
- DO NOT touch journal logic

Result:
- Verified `php artisan db:seed` runs twice (idempotent) with SQLite; roles=3, users=3; each seeded user has exactly 1 role.

Upcoming Phase 2 steps (DO NOT IMPLEMENT YET):
- Step 17: Permission Matrix (design first)
- Step 18: Policy & Gate
- Step 19: Journal Approval
- Step 20: Reversal Logic
- Step 21: Period Lock
- Step 22: Audit Log
- Step 23: General Ledger Query
