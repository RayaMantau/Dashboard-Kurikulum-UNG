# Curriculum Workflow Rules

## Lifecycle

A curriculum follows:

draft
→ submitted
→ approved
→ published
→ archived

Revision flow:

submitted
→ revision
→ submitted

## Draft

Admin Prodi can create and edit draft curriculum.

## Submit

A curriculum may be submitted only when required data is complete.

Minimum validation:

- Curriculum information is complete.
- Semester numbers are exactly 1..curriculum.total_semesters before submission.
- At least one course exists.
- Curriculum total_sks must be greater than zero. Equality with attachment/semester
  sums remains deferred; Phase 7F must not add a new consistency requirement.
- At least one Document database record exists. Phase 7F does not scan storage or
  introduce an official/category flag.

Phase 7F readiness UI and submission validation share
CurriculumSubmissionService::readiness(). Metadata requirements remain nonblank
name, nonzero year, positive total_sks and total_semesters. No effective_year or
description requirement. POST must recheck fresh locked state, regardless of GET
readiness. Submission produces one audit and notifications to active same-program
Kaprodi; it does not create a CurriculumReview row. Existing history is preserved.

## Review

Kaprodi can:

- Approve
- Request revision

Revision must include a note explaining the required changes.

Phase 7G review mutations must lock and reload the actor, then Curriculum,
inside the transaction before checking role/scope/activity and submitted status.
Never authorize the final mutation using only a caller's stale model instance.
Review, status, audit and notifications remain one database transaction.
Approve ends at approved; it must not publish or archive another curriculum.
The Kaprodi review surface uses kaprodi.submissions.* and snapshot course identity.
Revision form notes are required strings up to 10,000 characters; the service
also rejects whitespace-only notes. Private review downloads reuse DocumentService
and require both submitted own-program Curriculum and matching Document ownership.

## Publication

Only an approved curriculum may be published.

Publishing must:

1. Archive the previous published curriculum for the same study program.
2. Publish the selected curriculum.
3. Record publication time.
4. Record the responsible user.
5. Create appropriate audit log entries.
6. Create appropriate notifications.

These operations should be handled transactionally.

Phase 7H publication uses CurriculumPublicationService::publish(User, Curriculum).
Lock/reload actor, then lock its StudyProgram as the common publication mutex,
then lock target and current published Curricula in deterministic ID order.
Recheck fresh target ownership and approved status before mutation. Parent locking
also covers an empty published set and different Kaprodi actors in the same Prodi.
Archive all previous published rows in that Prodi, preserving historical published_at;
publish target with published_at and clear its archived_at. Keep audit/notifications
in the same transaction. Do not modify children, snapshots, documents or review history.
GET confirmation is only a preview; never trust client previous-publication IDs.
Production routes remain kaprodi.submissions.*; public portal is not part of Phase 7H.

## Archived Curriculum

Phase 7I history is a read-only projection, never a workflow writer. Match exact
Curriculum entity_type and entity_id plus an explicit lifecycle action allowlist.
Preserve repeated submissions/reviews; do not infer missing events from current
status or timestamps. AuditLog and CurriculumReview have no reliable event FK,
so show review notes separately rather than guessing matches. Actor name/role are
current user data, not historical snapshots. Never expose raw audit JSON, paths,
IP/user-agent or credentials. Scope uses existing view Policy plus valid assignment.

Archived curricula must remain available as historical records.

Do not convert archived curriculum back to draft.

Do not directly publish an archived curriculum.

Create a new curriculum version when a new curriculum is required.
