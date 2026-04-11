# Update Student Answer API

## Overview
Fitur ini memungkinkan guru/admin untuk mengupdate jawaban siswa pada soal tertentu dalam sesi ujian.

## Endpoint

```
PUT /api/v1/exams/{exam}/sessions/{examSession}/details/{examResultDetail}/answer
```

**Route Name:** `api.v1.exams.correction.update-answer`

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `exam` | integer | Yes | ID Exam (path parameter) |
| `examSession` | integer | Yes | ID Exam Session (path parameter) |
| `examResultDetail` | integer | Yes | ID Exam Result Detail (path parameter) |
| `student_answer` | string/array | Yes | Jawaban siswa (JSON atau string) |

## Request Example

```json
PUT /api/v1/exams/1/sessions/5/details/123/answer

{
  "student_answer": "Jawaban baru untuk soal ini"
}
```

Untuk soal dengan multiple selection:
```json
{
  "student_answer": [1, 3, 5]
}
```

## Authorization

- **Allowed:** Admin, Teacher (user_type !== STUDENT)
- **Forbidden:** Student - akan menerima error 403

## Response

**Success (200):**
```json
{
  "success": true,
  "message": "Student answer updated successfully",
  "data": {
    "id": 123,
    "exam_session_id": 5,
    "exam_question_id": 10,
    "student_answer": "Jawaban baru untuk soal ini",
    "answered_at": "2026-04-11T10:30:00Z",
    ...
  }
}
```

**Error (403 - Student):**
```json
{
  "success": false,
  "message": "You do not have permission to update student answers.",
  "errors": []
}
```

**Error (404 - Not Found):**
```json
{
  "success": false,
  "message": "Session not found for this exam."
}
```

## Supported Question Types

Semua tipe soal support update jawaban:
- `multiple_choice`
- `multiple_selection`
- `true_false`
- `short_answer`
- `essay`
- `math_input`
- `matching`
- `sequence`
- `arrange_words`
- `categorization`
- `arabic_response`
- `javanese_response`
- `survey`

## Catatan

- Method ini **hanya mengupdate jawaban siswa**, tidak recalculate score
- Setelah mengupdate jawaban, perlu memanggil endpoint `recalculate` atau `bulk-correction` jika ingin recalculate score
- Field `answered_at` akan diupdate ke waktu sekarang saat jawaban diubah

## Implementation Details

- **Controller:** `app/Http/Controllers/Api/V1/ExamCorrectionController.php`
- **Method:** `updateAnswer()`
- **Route:** `routes/api/v1.php`

## Changelog

| Version | Date | Description |
|---------|------|-------------|
| 1.0.0 | 2026-04-11 | Initial release |