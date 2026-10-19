# Authorization Rules

## Source of Truth

Authorization requirements are documented in:

- `docs/PERMISSIONS.md`
- `docs/TECHNICAL-SPEC.md`

Use Laravel Policies and appropriate middleware.

Do not rely on hidden UI elements for security.

## Scope

### Admin Prodi

Can access only data belonging to their assigned study program.

### Kaprodi

Can review curriculum only for their assigned study program.

### Admin Fakultas

Can view and monitor study programs and curricula within their assigned faculty.

### Admin Universitas

Has global administrative access according to the approved permission matrix.

Global access does not automatically mean permission to edit curriculum content.

## Security Order

For protected operations, follow:

1. Authentication
2. Authorization
3. Scope validation
4. Input validation
5. File validation
6. Business-rule validation
7. Audit logging

Never skip authorization because a route or UI element appears inaccessible.

## Ownership

Never trust a `study_program_id` or `faculty_id` supplied by the client.

Determine the user's permitted scope from the authenticated user and server-side authorization rules.