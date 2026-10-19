# Dashboard Kurikulum UNG — AI Rules Index

## Purpose

This directory contains project-specific rules for the Dashboard Kurikulum UNG.

These rules complement the Laravel Boost guidelines in `AGENTS.md`.

Before creating or modifying application files, AI agents must:

1. Read this index.
2. Identify which rule files apply to the files being changed.
3. Read all applicable rule files.
4. Check the relevant documentation in `docs/`.
5. Follow the existing Laravel and project conventions.

## Rule Files

| Rule | Applies To | Source Documentation |
|---|---|---|
| `project.md` | Entire application | `docs/PRD.md`, `docs/DEVELOPMENT-PLAN.md` |
| `database.md` | `database/**`, Models, migrations | `docs/DATABASE.md` |
| `authorization.md` | Policies, middleware, controllers, services | `docs/PERMISSIONS.md`, `docs/TECHNICAL-SPEC.md` |
| `ui.md` | Blade, CSS, frontend components | `docs/UI-SPEC.md` |
| `workflow.md` | Curriculum workflow and services | `docs/PRD.md`, `docs/TECHNICAL-SPEC.md` |

## Mandatory Project Documentation

Before making substantial changes, consult the relevant:

- `docs/PRD.md`
- `docs/USER-FLOW.md`
- `docs/SITEMAP.md`
- `docs/PERMISSIONS.md`
- `docs/DATABASE.md`
- `docs/UI-SPEC.md`
- `docs/TECHNICAL-SPEC.md`
- `docs/API-SPEC.md`
- `docs/DEVELOPMENT-PLAN.md`

## Scope Rule

Do not introduce features outside the approved MVP without explicit approval.

If a new requirement conflicts with the existing documentation:

1. Stop implementation.
2. Identify the conflict.
3. Ask for a decision or update the relevant documentation first.
4. Only then implement the change.