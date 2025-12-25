# Backend Accounting System (Laravel API) — Project Context

Last updated: 2025-12-25

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
- Approval workflow behavior may be configured via settings (e.g. auto-approve vs separate approver) while keeping permission checks as the enforcement mechanism.
- Never enforce maker != approver rules by default.

## Journal Approval & Posting (FINAL)
Journal statuses:
- draft
- approved
- posted

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
- User-centric permissions (roles are optional templates)
- Permission-driven policies/gates
- Journal lifecycle with optional approval
- Period open/close (lock) enforced for posting/reversal
- Audit logging for journal and period lifecycle events
- General Ledger query derived from POSTED journal lines only

### Phase 3 — IN PROGRESS

#### Step 27 — Vendor & Customer Master
Work completed (2025-12-25):
- Added vendors and customers tables (company-scoped unique codes)
- Added vendor/customer permissions and gates
- Added protected API endpoints and feature tests

#### Step 28 — Purchasing Tables (AP)
Work completed (2025-12-25):
- Added AP schema tables: purchase_orders, purchase_order_lines, vendor_invoices, vendor_invoice_lines, vendor_payments, vendor_payment_allocations
- Schema-only artifacts (migrations); no accounting logic added

#### Step 29 — Sales Tables (AR)
Work completed (2025-12-25):
- Added AR schema tables: sales_invoices, sales_invoice_lines, customer_payments, customer_payment_allocations
- Schema-only artifacts (migrations); no services/controllers/API and no journal/ledger changes

#### Step 30 — Purchasing Service (AP)
Work completed (2025-12-25):
- Vendor invoice draft CRUD service (draft create/update/delete + line totals)
- Vendor invoice approve service (draft -> approved)
- Vendor invoice posting service creates and posts an auto-journal on document posting (approved -> posted_at)
- Purchase order draft CRUD service (draft create/update/delete + line totals)
- Purchase order approve and cancel services
- Added vendor_invoices.posted_by and vendor_invoices.posted_at

#### Step 31 — Sales Service (AR)
Work completed (2025-12-25):
- Sales invoice draft CRUD service (draft create/update/delete + line totals)
- Sales invoice approve service (draft -> approved)
- Sales invoice posting service creates and posts an auto-journal on document posting (approved -> posted_at)
- Added sales_invoices.posted_by and sales_invoices.posted_at

#### Step 32 — Payment Service
Work completed (2025-12-25):
- Added posted_by and posted_at to customer_payments and vendor_payments
- Added payment models: CustomerPayment, CustomerPaymentAllocation, VendorPayment, VendorPaymentAllocation
- Added payment draft services (create/update/delete) with allocation validation:
	- allocations require posted invoices
	- allocations must not exceed payment amount during draft
- Added payment approve services (draft -> approved) with open-period check
- Added payment posting services (approved -> posted_at) that create and post auto-journals:
	- Customer receipt: Dr Cash/Bank (1-1100 for cash, else 1-1200), Cr AR (1-1300)
	- Vendor payment: Dr AP (2-1100), Cr Cash/Bank (1-1100 for cash, else 1-1200)
	- Posting requires allocations sum == payment amount
- Added unit tests for customer/vendor payment posting (happy path, period-closed, auto-approve+post)

#### Step 33 — Purchasing API
Work completed (2025-12-25):
- Added protected API endpoints for:
	- Purchase Orders (CRUD + approve + cancel)
	- Vendor Invoices (CRUD + approve + post)
	- Vendor Payments (CRUD + approve + post)
- Added purchasing permissions and gates:
	- purchase_order.view|create|edit|delete|approve|cancel
	- vendor_invoice.view|create|edit|delete|approve|post
	- vendor_payment.view|create|edit|delete|approve|post
- Added feature tests covering permission enforcement and happy-path workflows

#### Step 34 — Sales API
Work completed (2025-12-25):
- Added protected API endpoints for:
	- Sales Invoices (CRUD + approve + post)
	- Customer Payments (CRUD + approve + post)
- Added sales permissions and gates:
	- sales_invoice.view|create|edit|delete|approve|post
	- customer_payment.view|create|edit|delete|approve|post
- Added feature tests covering permission enforcement and happy-path workflows

#### Step 35 — Phase 3 Review
Work completed (2025-12-25):
- Added end-to-end feature tests validating AP/AR flows:
	- Vendor invoice posting + vendor payment posting create posted, balanced journals with expected account codes
	- Sales invoice posting + customer payment posting create posted, balanced journals with expected account codes
- Validated key guardrails:
	- Period lock blocks posting when closed
	- Payment allocations require posted invoices

## AP/AR Document Workflow (FINAL)
AP/AR business documents (vendor invoices, customer invoices, payments) use a 3-stage workflow:
- draft -> approved -> posted

Meaning:
- approved: business approval (e.g. warehouse supervisor approval is allowed)
- posted: accounting posting event; only at this point auto-journal is created and posted

The workflow must be configurable:
- UMKM: allow auto-approve and auto-post in one action when permitted
- Controlled orgs: allow separate approver vs poster (different users), based on settings/permissions
