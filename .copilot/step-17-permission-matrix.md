# Phase 2 — Step 17 (DESIGN): Permission Matrix (Atomic & Granular)

Last updated: 2025-12-24

## Scope & Principles (FINAL)
- Design & documentation only (no implementation details).
- Permissions are **user-centric** (assigned directly to users).
- Roles are **optional templates** only; permissions must be defined independent of roles.
- Permissions must be **atomic** and **action-based**.

## Naming Convention
- Format: `<resource>.<action>`
- Examples: `journal.create`, `journal.approve`, `period.close`, `user.permission.assign`

## Journal Lifecycle Context (FINAL)

Journal statuses:
- `draft`
- `approved`
- `posted`
- `reversed`

Approval is optional and flexible:
- Self-approval is allowed
- Approval may be skipped or auto-applied depending on permission

---

## Final Permission List (by Module)

Note: this list defines the canonical permission entities. Roles (if used) may reference these permissions, but authorization checks must not depend on role.

### Journals
- `journal.view`
- `journal.create` (create draft)
- `journal.edit` (edit draft)
- `journal.delete` (delete draft)
- `journal.import`
- `journal.export`
- `journal.approve` (move draft → approved OR mark approved where applicable)
- `journal.post` (move draft/approved → posted, depending on transition rules)
- `journal.reverse` (reverse posted journal)

### Accounting Periods
- `period.view`
- `period.close`
- `period.reopen`

### Audit / Activity
- `audit.view`
- `audit.log.view`

### Reporting
- `report.trial_balance.view`
- `report.general_ledger.view`

### System / Access Control
- `user.view`
- `user.create`
- `user.edit`
- `user.deactivate`
- `role.view`
- `role.create`
- `role.edit`
- `role.delete`
- `permission.view`
- `permission.assign` (assign permissions to a user)
- `permission.copy` (copy permissions from one user to another)

## Notes
- This permission list is intentionally granular so organizations can choose strict controls, while UMKM can simply grant all permissions to one user.
- Separation-of-duty rules (maker/approver) are explicitly out of scope for enforcement.
