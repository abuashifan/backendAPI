# Phase 2 — Step 17 (DESIGN): Permission Matrix

Last updated: 2025-12-22

## Scope & Principles (Non-Negotiable)
- Design & documentation only (no implementation details).
- Journals are posted immediately (no approval workflow).
- Posted journals are immutable (no edit, no delete).
- Corrections are done via journal reversal.
- Audit is ex-post (review/flag), not ex-ante (approval).
- Audit status never changes balances and never hides transactions.
- Period closing is flexible; audit issues may create warnings but do not block normal posting.

## Roles (Final)
- **admin**: accounting leadership; adjustments, reversals, depreciation, period closing, reporting, user/role management.
- **accounting_staff**: daily transaction entry; journals posted immediately; may reverse for corrections; cannot close periods; cannot perform audit actions.
- **auditor**: review and audit checks; can mark checked/issue_flagged/resolved and request correction; cannot create/post/reverse; cannot edit accounting data.

---

## 1) Journal Operations — Permission Matrix

| Permission | admin | accounting_staff | auditor |
|---|:---:|:---:|:---:|
| create journal | ✅ | ✅ | ❌ |
| post journal | ✅ | ✅ | ❌ |
| reverse journal | ✅ | ✅ | ❌ |
| view journal | ✅ | ✅ | ✅ |

### Rationale (Journal Operations)
- **No approval workflow** means journal entry is an operational action: creating a journal results in it being **posted immediately**.
- **accounting_staff** can create/post because they handle daily operations. They can also reverse to correct mistakes without editing posted data.
- **auditor** is intentionally read-only for accounting data: they must not be able to originate or alter transactions.

---

## 2) Audit Operations — Permission Matrix

| Permission | admin | accounting_staff | auditor |
|---|:---:|:---:|:---:|
| audit check journal | ✅ | ❌ | ✅ |
| flag audit issue | ✅ | ❌ | ✅ |
| mark audit resolved | ✅ | ❌ | ❌ |
| view audit status | ✅ | ✅ | ✅ |

### Rationale (Audit Operations)
- Audit actions are a **separate layer** from accounting postings: they record review outcomes (unchecked/checked/issue_flagged/resolved) but **do not affect balances**.
- **auditor** can check and flag to document findings and follow-up, while remaining unable to change accounting data.
- **admin** can also perform audit actions to support internal controls and oversight.
- **accounting_staff** cannot perform audit actions (separation of duties), but can **view audit status** to understand what needs correction.

Amendment (2025-12-22): Auditors can **check** and **flag** issues, but **cannot mark resolved**. Resolution is done by **admin** to preserve separation of duties.

---

## 3) Period Operations — Permission Matrix

| Permission | admin | accounting_staff | auditor |
|---|:---:|:---:|:---:|
| view accounting period | ✅ | ✅ | ✅ |
| close accounting period | ✅ | ❌ | ❌ |

### Rationale (Period Operations)
- Period closing is a high-control action that impacts governance and reporting discipline.
- **admin** closes periods because it requires accounting judgment and responsibility.
- **accounting_staff** and **auditor** can view periods for operational awareness and audit planning, but cannot close.

---

## 4) Reporting — Permission Matrix

| Permission | admin | accounting_staff | auditor |
|---|:---:|:---:|:---:|
| trial balance | ✅ | ✅ | ✅ |
| general ledger | ✅ | ✅ | ✅ |
| financial statements | ✅ | ❌ | ✅ |

### Rationale (Reporting)
- Reports are derived from posted journal lines; they are essential for both management and audit.
- **accounting_staff** can access trial balance and general ledger for reconciliation and daily work.
- **financial statements** are restricted to **admin** (management responsibility) and **auditor** (independent review), to reduce the risk of miscommunication or premature external interpretation.

---

## 5) System Administration — Permission Matrix

| Permission | admin | accounting_staff | auditor |
|---|:---:|:---:|:---:|
| manage users | ✅ | ❌ | ❌ |
| manage roles | ✅ | ❌ | ❌ |
| manage permissions | ✅ | ❌ | ❌ |

### Rationale (System Administration)
- System administration affects access control and internal control structure.
- Only **admin** can manage users/roles/permissions to keep governance centralized and accountable.

---

## Notes on Audit Philosophy (Why auditors cannot modify data)
- Auditors must be independent from transaction entry to preserve credibility and prevent conflicts of interest.
- Allowing auditors to create/post/reverse would make audit outcomes questionable because the same role could both produce and validate evidence.
- Audit findings must remain visible and traceable over time; changing accounting data directly would break the audit trail.

## Notes on Reversal (Why reversal is used instead of editing)
- Editing posted journals destroys the historical record and makes it hard to explain changes.
- Reversal preserves a complete trail: the original posted transaction remains, and the correction is a separate posted transaction.
- Reversal supports clear accountability: who corrected, when, and why.
