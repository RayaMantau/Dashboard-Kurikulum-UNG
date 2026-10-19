# UI Rules

## Source of Truth

The UI specification is:

`docs/UI-SPEC.md`

## Design Direction

The application uses the Modern Academic Portal visual direction.

Primary brand (Phase 9): shared tokens in `public/brand.css`.

- Primary: #7A1F2B
- Dark: #5C1620
- Hover: #681A25
- Soft background: #F6E9EC
- Soft border: #E7C8CE
- On primary: #FFFFFF

Public, internal and login layouts share these tokens. Keep neutral surfaces
and independent success/warning/danger/info colors. Blue is reserved for info,
not application branding. Destructive actions must remain distinct from primary.
Font:

Inter.

Maximum content width:

1280px.

Admin sidebar width:

260px.

Admin topbar height:

72px.

## Components

Prefer reusable components for:

- Button
- Badge
- Card
- Input
- Select
- Search
- Modal
- Alert
- Empty State
- Error State
- Loading State
- Table
- Pagination
- File Upload
- Breadcrumb
- Tabs
- Timeline
- Notification Item

Check existing components before creating a new component.

## Responsive

The interface must work on:

- Desktop
- Tablet
- Mobile

Do not create desktop-only interfaces for public curriculum information.

## Language

Public and authenticated user-facing interfaces must use Bahasa Indonesia.