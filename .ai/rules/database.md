# Database Rules

## Source of Truth

The approved database design is documented in:

`docs/DATABASE.md`

Do not change the schema without updating the corresponding migration and considering the documentation.

## Migration

Every database schema change must use a Laravel migration.

Never modify an existing migration that has already been executed in a shared or production database unless there is a specific approved migration strategy.

For new schema changes, create a new migration.

## Core Tables

The MVP uses:

- users
- faculties
- study_programs
- curricula
- semesters
- courses
- curriculum_courses
- documents
- curriculum_reviews
- notifications
- audit_logs

## Relationships

Follow the relationships documented in `docs/DATABASE.md`.

Important relationships include:

Faculty → Study Programs

Study Program → Curricula

Study Program → Courses

Curriculum → Semesters

Curriculum → Curriculum Courses

Curriculum → Documents

Curriculum → Curriculum Reviews

User → Faculty

User → Study Program

## Data Integrity

Do not bypass foreign key constraints.

Do not hard-delete published or archived curricula.

Course codes must remain unique within a study program.

Only one published curriculum may exist for each study program.

The one-published-curriculum rule must be protected by application-level business logic and tests.

## Eloquent

Use Eloquent relationships rather than unnecessary manual joins.

Use explicit relationship return types.

Follow existing Laravel conventions.