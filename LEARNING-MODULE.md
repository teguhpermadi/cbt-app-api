# Learning Module Documentation

Dokumentasi ini mencakup fitur-fitur yang tersedia di modul Learning yang terdiri dari:
- LearningPath
- LearningUnit
- LearningLesson
- LearningContentTypeEnum

---

## Table of Contents

1. [Overview](#overview)
2. [Models](#models)
   - [LearningPath](#learningpath)
   - [LearningUnit](#learningunit)
   - [LearningLesson](#learninglesson)
3. [Content Types](#content-types)
   - [Reading](#reading)
   - [Video](#video)
   - [Audio](#audio)
   - [Web Link](#web-link)
   - [Quiz](#quiz)
   - [Survey](#survey)
4. [API Endpoints](#api-endpoints)
5. [Usage Examples](#usage-examples)

---

## Overview

Modul Learning dirancang untuk membuat struktur pembelajaran berbasis jalur (learning path) yang terdiri dari unit-unit pembelajaran. Setiap unit dapat berisi berbagai jenis konten seperti artikel, video, audio, tautan eksternal, kuis, dan survei.

### Hierarki Struktur

```
LearningPath (Jalur Pembelajaran)
    └── LearningUnit (Unit Pembelajaran) 
            └── LearningLesson (Materi Pembelajaran)
                    └── QuestionBank (untuk Quiz/Survey)
```

---

## Models

### LearningPath

**File:** `app/Models/LearningPath.php`

LearningPath adalah level tertinggi dalam struktur pembelajaran. Mewakili satu jalur pembelajaran lengkap.

#### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| id | ULID | Unique identifier |
| subject_id | ULID | Relasi ke Subject |
| classroom_id | ULID | Relasi ke Classroom |
| user_id | ULID | Pembuat/Owner jalur pembelajaran |
| title | string | Judul jalur pembelajaran |
| description | text | Deskripsi jalur pembelajaran |
| order | integer | Urutan tampilan |
| is_published | boolean | Status publish |
| created_at | datetime | Tanggal dibuat |
| updated_at | datetime | Tanggal diperbarui |
| deleted_at | datetime | Tanggal dihapus (soft delete) |

#### Relationships

```php
// Relasi ke Subject
$learningPath->subject()

// Relasi ke Classroom
$learningPath->classroom()

// Relasi ke User (pembuat)
$learningPath->user()

// Relasi ke LearningUnit (semua unit)
$learningPath->units()
```

#### Scopes

```php
// Mengambil berdasarkan urutan
$learningPath->ordered()

// Filter by subject dan classroom
$learningPath->bySubjectAndClassroom($subjectId, $classroomId)
```

#### Usage Example

```php
// Membuat LearningPath baru
$path = LearningPath::create([
    'subject_id' => $subject->id,
    'classroom_id' => $classroom->id,
    'user_id' => auth()->id(),
    'title' => 'Matematika Dasar',
    'description' => 'Jalur pembelajaran matematika untuk tingkat dasar',
    'order' => 1,
    'is_published' => true,
]);

// Mengambil semua unit dalam jalur
$units = $path->units;

// Mengambil jalur berdasarkan subject dan classroom
$paths = LearningPath::bySubjectAndClassroom($subjectId, $classroomId)->get();
```

---

### LearningUnit

**File:** `app/Models/LearningUnit.php`

LearningUnit adalah level kedua yang berada di dalam LearningPath. Mewakili satu unit/topik pembelajaran.

#### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| id | ULID | Unique identifier |
| learning_path_id | ULID | Relasi ke LearningPath |
| title | string | Judul unit pembelajaran |
| order | integer | Urutan unit dalam path |
| xp_reward | integer | XP yang diperoleh saat menyelesaikan unit |
| is_published | boolean | Status publish |
| created_at | datetime | Tanggal dibuat |
| updated_at | datetime | Tanggal diperbarui |
| deleted_at | datetime | Tanggal dihapus (soft delete) |

#### Relationships

```php
// Relasi ke LearningPath
$unit->learningPath()

// Relasi ke LearningLesson (semua materi)
$unit->lessons()
```

#### Scopes

```php
// Mengambil unit yang sudah dipublish
$unit->published()
```

#### Usage Example

```php
// Membuat LearningUnit
$unit = LearningUnit::create([
    'learning_path_id' => $path->id,
    'title' => 'Aljabar Dasar',
    'order' => 1,
    'xp_reward' => 100,
    'is_published' => true,
]);

// Mengambil semua materi dalam unit
$lessons = $unit->lessons;
```

---

### LearningLesson

**File:** `app/Models/LearningLesson.php`

LearningLesson adalah level terkecil yang berisi konten pembelajaran sesungguhnya. Setiap lesson dapat berupa artikel, video, audio, tautan, kuis, atau survei.

#### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| id | ULID | Unique identifier |
| learning_unit_id | ULID | Relasi ke LearningUnit |
| question_bank_id | ULID | Relasi ke QuestionBank (untuk Quiz/Survey) |
| title | string | Judul materi |
| content_type | enum | Tipe konten (Reading, Video, Audio, WebLink, Quiz, Survey) |
| content_data | json | Data konten tambahan |
| order | integer | Urutan materi dalam unit |
| xp_reward | integer | XP yang diperoleh |
| is_published | boolean | Status publish |
| created_at | datetime | Tanggal dibuat |
| updated_at | datetime | Tanggal diperbarui |
| deleted_at | datetime | Tanggal dihapus (soft delete) |

#### Relationships

```php
// Relasi ke LearningUnit
$lesson->unit()

// Relasi ke QuestionBank (untuk Quiz/Survey)
$lesson->questionBank()

// Relasi ke progress pengguna
$lesson->progress()
```

#### Media Collections

LearningLesson menggunakan Spatie Media Library untuk mengelola file:

| Collection | Accepted Files | MIME Types |
|------------|---------------|------------|
| reading_files | PDF, DOC, DOCX | application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document |
| videos | MP4, MOV | video/mp4, video/quicktime |
| audios | MP3, WAV | audio/mpeg, audio/wav, audio/mp3 |

#### Scopes

```php
// Mengambil materi yang sudah dipublish
$lesson->published()
```

#### Usage Example

```php
// Membuat materi Reading
$lesson = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Pengenalan Aljabar',
    'content_type' => LearningContentTypeEnum::READING,
    'content_data' => [
        'html_content' => '<h1>Pengenalan Aljabar</h1><p>Aljabar adalah...</p>',
        'description' => 'Materi pengenalan aljabar untuk pemula',
    ],
    'xp_reward' => 10,
    'is_published' => true,
]);

// Upload file PDF
$lesson->addMedia($request->file('file'))->toMediaCollection('reading_files');

// Upload video
$lesson->addMedia($request->file('video'))->toMediaCollection('videos');

// Upload audio
$lesson->addMedia($request->file('audio'))->toMediaCollection('audios');

// Mengambil media
$pdfFiles = $lesson->getMedia('reading_files');
$videos = $lesson->getMedia('videos');
$audios = $lesson->getMedia('audios');
```

---

## Content Types

LearningContentTypeEnum (`app/Enums/LearningContentTypeEnum.php`)

```php
enum LearningContentTypeEnum: string
{
    case READING = 'reading';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case WEB_LINK = 'web_link';
    case QUIZ = 'quiz';
    case SURVEY = 'survey';
}
```

### Reading

Konten berbasis teks/artikel. Dapat berisi HTML atau file PDF/DOC.

**content_data structure:**

```json
{
  "html_content": "<p>Isi artikel...</p>",
  "description": "Deskripsi singkat materi"
}
```

**Helper Methods:**
```php
// Setter
$lesson->html_content = '<p>Isi artikel...</p>';
$lesson->description = 'Deskripsi materi';

// Getter
$html = $lesson->html_content;
$desc = $lesson->description;
```

**Media:** `reading_files` collection (PDF, DOC)

---

### Video

Konten video. Dapat berupa URL YouTube atau file video yang diupload.

**content_data structure:**

```json
{
  "youtube_url": "https://youtube.com/watch?v=xxx",
  "description": "Deskripsi video"
}
```

**Helper Methods:**
```php
// Setter
$lesson->youtube_url = 'https://youtube.com/watch?v=abc123';
$lesson->description = 'Video pembelajaran';

// Getter
$youtubeUrl = $lesson->youtube_url;
```

**Media:** `videos` collection (MP4, MOV)

---

### Audio

Konten audio yang diupload.

**content_data structure:**

```json
{
  "description": "Deskripsi audio"
}
```

**Media:** `audios` collection (MP3, WAV)

---

### Web Link

Tautan ke halaman web eksternal.

**content_data structure:**

```json
{
  "web_link": "https://example.com/article",
  "description": "Deskripsi tautan"
}
```

**Helper Methods:**
```php
// Setter
$lesson->web_link = 'https://example.com';
$lesson->description = 'Artikel menarik';

// Getter
$link = $lesson->web_link;
```

**Media:** Tidak ada

---

### Quiz

Konten kuis yang diambil dari QuestionBank.

**content_data structure:**

```json
{
  "passing_score": 70
}
```

**Helper Methods:**
```php
// Setter
$lesson->passing_score = 70;

// Getter
$passingScore = $lesson->passing_score; // default: null
```

**QuestionBank Usage:**
```php
// Hubungkan dengan QuestionBank
$lesson->question_bank_id = $questionBank->id;
$lesson->save();

// Ambil soal-soal dari QuestionBank
$questions = $lesson->questionBank->questions;
```

---

### Survey

Konten survei yang diambil dari QuestionBank dengan tipe pertanyaan SURVEY.

**content_data structure:**

```json
{
  "allow_anonymous": true,
  "show_results_after_submit": false
}
```

**Helper Methods:**
```php
// Setter
$lesson->allow_anonymous = true;
$lesson->show_results_after_submit = false;

// Getter
$allowAnonymous = $lesson->allow_anonymous; // default: false
$showResults = $lesson->show_results_after_submit; // default: false
```

**QuestionBank Usage:**
```php
// Hubungkan dengan QuestionBank
$lesson->question_bank_id = $questionBank->id;
$lesson->save();

// Question type untuk survey adalah QuestionTypeEnum::SURVEY
$questions = $lesson->questionBank->questions()
    ->where('type', QuestionTypeEnum::SURVEY)
    ->get();
```

---

## API Endpoints

### Learning Paths

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/learning-paths` | List all learning paths |
| POST | `/api/v1/learning-paths` | Create learning path |
| GET | `/api/v1/learning-paths/{id}` | Get learning path |
| PUT | `/api/v1/learning-paths/{id}` | Update learning path |
| DELETE | `/api/v1/learning-paths/{id}` | Delete learning path |
| POST | `/api/v1/learning-paths/{id}/restore` | Restore deleted path |
| DELETE | `/api/v1/learning-paths/{id}/force-delete` | Permanent delete |
| POST | `/api/v1/learning-paths/reorder` | Reorder paths |

### Learning Units

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/learning-units` | List all learning units |
| POST | `/api/v1/learning-units` | Create learning unit |
| GET | `/api/v1/learning-units/{id}` | Get learning unit |
| PUT | `/api/v1/learning-units/{id}` | Update learning unit |
| DELETE | `/api/v1/learning-units/{id}` | Delete learning unit |
| POST | `/api/v1/learning-units/reorder` | Reorder units |
| POST | `/api/v1/learning-units/bulk-delete` | Bulk delete |

### Learning Lessons

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/learning-lessons` | List all lessons |
| POST | `/api/v1/learning-lessons` | Create lesson |
| GET | `/api/v1/learning-lessons/{id}` | Get lesson |
| PUT | `/api/v1/learning-lessons/{id}` | Update lesson |
| DELETE | `/api/v1/learning-lessons/{id}` | Delete lesson |
| POST | `/api/v1/learning-lessons/reorder` | Reorder lessons |
| POST | `/api/v1/learning-lessons/bulk-delete` | Bulk delete |
| POST | `/api/v1/learning-lessons/{id}/media` | Upload media |
| POST | `/api/v1/learning-lessons/{id}/media/{mediaId}` | Replace media |
| DELETE | `/api/v1/learning-lessons/{id}/media/{mediaId}` | Delete media |

### Query Parameters

**Learning Lessons:**
- `learning_unit_id` - Filter by unit
- `is_published` - Filter by publish status
- `search` - Search by title
- `sort_by` - Sort field (default: order)
- `order` - Sort order (asc/desc)
- `per_page` - Items per page

---

## Usage Examples

### Full Flow: Membuat Learning Path dengan Semua Tipe Konten

```php
// 1. Membuat LearningPath
$path = LearningPath::create([
    'subject_id' => $subject->id,
    'classroom_id' => $classroom->id,
    'user_id' => auth()->id(),
    'title' => 'Kursus Matematika SMP',
    'description' => 'Kursus matematika untuk siswa SMP',
    'is_published' => true,
]);

// 2. Membuat LearningUnit
$unit = LearningUnit::create([
    'learning_path_id' => $path->id,
    'title' => 'Bab 1: Aljabar',
    'xp_reward' => 500,
    'is_published' => true,
]);

// 3. Membuat LearningLesson - Reading (dengan PDF)
$lesson1 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Materi: Pengenalan Aljabar',
    'content_type' => LearningContentTypeEnum::READING,
    'content_data' => [
        'html_content' => '<h1>Pengenalan Aljabar</h1><p>Aljabar adalah...</p>',
        'description' => 'Materi teori aljabar dasar',
    ],
    'xp_reward' => 10,
    'is_published' => true,
]);
$lesson1->addMedia($pdfFile)->toMediaCollection('reading_files');

// 4. Membuat LearningLesson - Video (YouTube)
$lesson2 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Video: Cara Menyelesaikan Persamaan',
    'content_type' => LearningContentTypeEnum::VIDEO,
    'content_data' => [
        'youtube_url' => 'https://youtube.com/watch?v=abc123',
        'description' => 'Video tutorial penyelesaian persamaan',
    ],
    'xp_reward' => 15,
    'is_published' => true,
]);

// 5. Membuat LearningLesson - Audio
$lesson3 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Audio: Pembahasan Rumus',
    'content_type' => LearningContentTypeEnum::AUDIO,
    'content_data' => [
        'description' => 'Audio pembahasan rumus aljabar',
    ],
    'xp_reward' => 5,
    'is_published' => true,
]);
$lesson3->addMedia($audioFile)->toMediaCollection('audios');

// 6. Membuat LearningLesson - Web Link
$lesson4 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Tautan: Artikel Tambahan',
    'content_type' => LearningContentTypeEnum::WEB_LINK,
    'content_data' => [
        'web_link' => 'https://math.isfun.com/algebra',
        'description' => 'Artikel tambahan tentang aljabar',
    ],
    'xp_reward' => 5,
    'is_published' => true,
]);

// 7. Membuat QuestionBank untuk Quiz
$questionBank = QuestionBank::create([
    'name' => 'Kuis Aljabar Dasar',
    'user_id' => auth()->id(),
    'subject_id' => $subject->id,
]);

// Tambahkan soal dengan type QUIZ
$question = Question::create([
    'user_id' => auth()->id(),
    'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
    'content' => 'Berapakah nilai x jika 2x = 10?',
    // ... other fields
]);
$questionBank->questions()->attach($question);

// 8. Membuat LearningLesson - Quiz
$lesson5 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Kuis: Aljabar Dasar',
    'content_type' => LearningContentTypeEnum::QUIZ,
    'question_bank_id' => $questionBank->id,
    'content_data' => [
        'passing_score' => 70,
    ],
    'xp_reward' => 20,
    'is_published' => true,
]);

// 9. Membuat QuestionBank untuk Survey
$surveyBank = QuestionBank::create([
    'name' => 'Survey Kepuasan Siswa',
    'user_id' => auth()->id(),
    'subject_id' => $subject->id,
]);

// Tambahkan soal dengan type SURVEY
$surveyQuestion = Question::create([
    'user_id' => auth()->id(),
    'type' => QuestionTypeEnum::SURVEY,
    'content' => 'Seberapa sulit materi ini bagi Anda?',
    // ... other fields
]);
$surveyBank->questions()->attach($surveyQuestion);

// 10. Membuat LearningLesson - Survey
$lesson6 = LearningLesson::create([
    'learning_unit_id' => $unit->id,
    'title' => 'Survey: Evaluasi Pembelajaran',
    'content_type' => LearningContentTypeEnum::SURVEY,
    'question_bank_id' => $surveyBank->id,
    'content_data' => [
        'allow_anonymous' => true,
        'show_results_after_submit' => false,
    ],
    'xp_reward' => 5,
    'is_published' => true,
]);
```

### Melihat Response JSON

```json
{
  "success": true,
  "message": "Learning lessons retrieved successfully",
  "data": {
    "data": [
      {
        "id": "01HY...",
        "learning_unit_id": "01HX...",
        "question_bank_id": "01HW...",
        "title": "Materi: Pengenalan Aljabar",
        "content_type": "reading",
        "content_data": {
          "html_content": "<h1>Pengenalan Aljabar</h1>...",
          "description": "Materi teori aljabar dasar"
        },
        "order": 0,
        "xp_reward": 10,
        "is_published": true,
        "unit": { ... },
        "question_bank": null,
        "media": {
          "reading_files": [
            {
              "id": "abc123",
              "url": "https://...",
              "name": "materi-aljabar.pdf",
              "file_name": "materi-aljabar.pdf",
              "mime_type": "application/pdf",
              "size": 1048576
            }
          ],
          "videos": [],
          "audios": []
        },
        "created_at": "2024-01-01T00:00:00+00:00",
        "updated_at": "2024-01-01T00:00:00+00:00"
      }
    ],
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

---

## Catatan

- Semua model menggunakan **ULID** sebagai primary key
- Semua model mendukung **Soft Delete**
- Media disimpan di disk `public` menggunakan Spatie Media Library
- Relasi antara LearningLesson dengan QuestionBank memungkinkan content Quiz dan Survey untuk mengambil soal dari bank soal yang sudah ada
