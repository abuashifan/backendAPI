# Backend Accounting API (Laravel)

A modular accounting backend designed to serve both:
- **UMKM / single-user** setups (minimal controls)
- **Small–medium organizations** (multi-user, controlled access)

This system provides control tools (permissions, optional approval, audit logging), but **does not enforce organizational idealism**. Operational risk decisions (fraud controls, separation of duty, maker/approver policies) remain the client’s responsibility.

## Architecture (High-Level)

- **Controllers**: HTTP/validation + orchestration only
- **Services**: business logic (accounting rules, transitions)
- **Models/Migrations**: persistence layer

All balances and reports are **derived from posted journal lines**; no account balances are stored in master tables.

## Authorization Model (FINAL)

### User-Centric Permissions (Locked)

- Permissions are assigned **directly to users**.
- **Roles are optional** and act only as permission templates.
- A user may exist with **no role**.

### Enforcement Rule

Authorization checks must be **permission-driven only** (e.g. `user->hasPermissionTo('journal.post')`).

- Do not assume any role hierarchy.
- Do not enforce maker ≠ approver rules.
- Do not block self-approval.

## Permission Design Rules (FINAL)

- Permissions must be **atomic** and **action-based**.
- **One permission = one real action**.

Examples:
- `journal.create`
- `journal.edit`
- `journal.delete`
- `journal.import`
- `journal.export`
- `journal.approve`
- `journal.post`
- `journal.reverse`

Permissions are **not** menu-based and **not** role-based.

## Journal Approval & Posting (FINAL)

### Journal Statuses

- `draft`
- `approved`
- `posted`
- `reversed`

### Philosophy (Flexible)

- Approval is **optional** and **permission-based**.
- A single user may **create, approve, and post** the same journal.
- Approval may be **skipped** or **auto-applied** based on permission.

### System Responsibilities

For transitions and critical actions (create/approve/post/reverse), the system must:
- Validate **permission**
- Validate **period is open**
- Validate **budget** (if budget control is enabled)
- Record an **audit trail**

The system must not:
- Enforce separation of duty
- Block self-approval
- Impose organizational workflow rules

## Roadmap / Tracking

Project planning and implementation notes live in:
- `.copilot/project-context.md`
- `.copilot/project-plan.md`

Phase 2 is focused on permission-driven controls, optional approval, posting, reversal, period lock, and audit logging.
