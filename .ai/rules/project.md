# Project Rules

## Product

Product name: Dashboard Kurikulum UNG.

The system is a centralized curriculum information platform for Universitas Negeri Gorontalo.

## Users

Public users:

- Calon mahasiswa
- Mahasiswa
- Dosen

Authenticated internal roles:

- Admin Universitas
- Admin Fakultas
- Admin Prodi
- Kaprodi

Public users do not need an account to browse curriculum information.

## MVP Principles

The MVP uses manual data management.

Do not implement scraping or external API integration unless explicitly approved.

Do not add curriculum comparison to the MVP.

Do not add features outside the approved product documentation.

## Curriculum Ownership

Admin Prodi manages curriculum content for their own study program.

Kaprodi reviews and approves or returns curriculum for revision.

Admin Fakultas monitors curriculum within their faculty.

Admin Universitas manages global master data, users, monitoring, audit, and settings.

Admin Universitas is not the primary curriculum content editor in the MVP.

## Curriculum Lifecycle

Valid lifecycle:

draft → submitted
submitted → approved
submitted → revision
revision → submitted
approved → published
published → archived

Do not allow invalid direct transitions.

## Versioning

Curriculum versions are separate records.

Do not overwrite historical curriculum versions.

Only one published curriculum may exist for a study program at a time.

When a new curriculum is published, the previous published curriculum must be archived.

## Public Documents

Curriculum documents are public when their curriculum is published.

Archived curriculum documents remain available as historical documents.

Draft, submitted, revision, and approved curriculum documents are not public.

## Language

User-facing UI must use Bahasa Indonesia unless a technical reason requires otherwise.