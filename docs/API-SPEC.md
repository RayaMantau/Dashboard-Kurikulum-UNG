# API-SPEC --- Dashboard Kurikulum UNG

## 1. Status

MVP tidak menyediakan public API.

Sistem menggunakan Laravel server-rendered routes/controllers.

## 2. Internal Service Boundaries

Walaupun tidak ada public REST API, domain operations dipisahkan melalui
service layer: - CurriculumService - CurriculumSubmissionService -
CurriculumPublicationService - DocumentService - AuditLogService

## 3. Future API Direction

Jika public API diperlukan di masa depan, endpoint dapat mengikuti
pola: - `GET /api/faculties` - `GET /api/faculties/{faculty}` -
`GET /api/study-programs` - `GET /api/study-programs/{studyProgram}` -
`GET /api/curricula/{curriculum}` -
`GET /api/curricula/{curriculum}/courses` - `GET /api/courses/{course}`

API future harus: - versioned - authenticated jika data sensitif - rate
limited - documented - scoped - hanya expose published public data untuk
endpoint public

## 4. JSON Principles

Future response harus konsisten:

``` json
{
  "data": {},
  "meta": {}
}
```

Error:

``` json
{
  "message": "Pesan error yang aman ditampilkan"
}
```

## 5. Security

Jangan expose: - password - internal audit details - unpublished
curriculum - private document paths - internal user information yang
tidak diperlukan
