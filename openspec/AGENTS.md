# OpenSpec instructions for agents — recruitment-api

This file is the **local grammar contract** for this repository's Spec-Driven Development (SDD) flow:
the folder structure and delta syntax that the `spec-guard` agent validates. The team uses a **markdown
convention** (no OpenSpec CLI); `spec-guard` is the linter.

**The canonical, complete SDD guide is `~/.claude/openspec-workflow.md`** — lifecycle, human validation
gates, golden rules, brownfield policy (spec-on-touch), and cross-service coordination. This file does
not repeat it; it only fixes the syntax an agent must produce here.

> All artifacts in this repo (specs, docs, code, commits) are written in **English**.

## Repository structure

```
openspec/
├── project.md                # Project context + stack + conventions
├── AGENTS.md                 # (this file)
├── specs/                    # Current truth — what the system does today.
│   └── <capability>/spec.md  #   Empty until the first change is archived.
└── changes/                  # Proposed future work — created before writing code
    ├── <change-id>/
    │   ├── proposal.md       # Why + What changes + Impact
    │   ├── design.md         # Optional — only for non-trivial decisions
    │   ├── tasks.md          # Implementation breakdown, checkable, one item = one PR
    │   └── specs/            # Spec deltas
    │       └── <capability>/spec.md
    └── _archived/            # Closed changes (history)
```

SDD is **opt-in** (the developer decides), and `specs/` only grows from archived changes — it is never
backfilled (**spec-on-touch**). When you touch existing-but-unspecified behaviour, write an `ADDED`
delta for the **slice you touch**, not for the whole capability (a `MODIFIED` delta cannot reference a
canonical Requirement that does not exist yet). The *why* behind all of this lives in
`~/.claude/openspec-workflow.md`.

## Spec delta syntax (what `spec-guard` checks)

Each delta file starts with `# Spec delta — <capability>` and is a flat list of requirements:

```markdown
# Spec delta — <capability>

## ADDED Requirements
### Requirement: The system SHALL <do something>
The system SHALL <normative statement — MUST / SHALL / SHOULD / MAY, required>.

#### Scenario: <observable behaviour>
- **WHEN** <stimulus>
- **THEN** <observable outcome>
- **AND** <secondary outcome>

## MODIFIED Requirements
### Requirement: <exact existing title from the canonical spec>
<new full text — replaces the prior one>

#### Scenario: <…>

## REMOVED Requirements
### Requirement: <exact existing title>
**Reason:** <why it is being removed>
**Migration:** <what callers should do instead>
```

Rules:
- One `### Requirement:` H3 per requirement; no nesting. Its body is a normative statement using
  **MUST / SHALL / SHOULD / MAY** — required, not optional.
- Each requirement has >= 1 `#### Scenario:` (except under REMOVED, which uses Reason/Migration).
- Each scenario's body is bullets with at least `**WHEN**` and `**THEN**` (`**GIVEN**` / `**AND**` optional).
- All scenarios are observable from outside the system (HTTP / event / DB state); internals go in `design.md`.
- MODIFIED/REMOVED must cite the **exact title** that exists in `openspec/specs/<capability>/spec.md`.
- A delta may contain ADDED, MODIFIED and REMOVED; only the sections that apply.

## Conventions
- Capability names are kebab-case nouns (`^[a-z][a-z0-9-]+$`): `candidature-validation`, `consolidated-listing`.
- Change ids are kebab-case verb phrases (`^[a-z][a-z0-9-]+$`): `add-excel-report`, `harden-assignment-concurrency`.
- Each Scenario is covered by a `tasks.md` item, or marked `// deferred` on its `#### Scenario:` line
  (intentionally out of scope for the current change).
