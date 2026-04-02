# AI Correction Queue Status - API Documentation

## Overview

API untuk melacak progress koreksi AI pada jawaban essay siswa. Fitur ini memungkinkan frontend menampilkan estimasi waktu penyelesaian dan progress percentage kepada user.

## Base URL

```
/api/v1
```

## Authentication

Requires valid Bearer token in Authorization header.

---

## Endpoints

### 1. Trigger AI Correction

Memulai proses koreksi AI untuk soal essay dalam ujian.

**Endpoint:** `POST /api/v1/exams/{exam}/ai-correct`

**Path Parameters:**
| Parameter | Type | Description |
|------------|------|-------------|
| exam | int | Exam ID |

**Request Body:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| provider | string | No | AI provider: `gemini`, `openrouter`, `lmstudio` (default: `gemini`) |
| exam_question_id | int | No | Specific question ID to correct |
| exam_session_id | int | No | Specific session ID to correct |

**Example Request:**
```bash
curl -X POST https://api.example.com/api/v1/exams/1/ai-correct \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "lmstudio",
    "exam_question_id": null
  }'
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "AI correction jobs for 'Ujian Tengah Semester' have been dispatched.",
  "data": {
    "batch_id": "batch_abc123",
    "stats_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "total_jobs": 25,
    "correction_statuses": [
      {
        "id": 1,
        "exam_id": 1,
        "exam_question_id": 5,
        "status": "processing",
        "total_to_correct": 5,
        "corrected_count": 0
      }
    ]
  }
}
```

---

### 2. Get Correction Progress

Mengambil status progress koreksi AI untuk ujian tertentu.

**Endpoint:** `GET /api/v1/exams/{exam}/correction-progress`

**Path Parameters:**
| Parameter | Type | Description |
|------------|------|-------------|
| exam | int | Exam ID |

**Example Request:**
```bash
curl -X GET https://api.example.com/api/v1/exams/1/correction-progress \
  -H "Authorization: Bearer {token}"
```

**Success Response (200):**
```json
{
  "success": true,
  "message": null,
  "data": {
    "exam_id": 1,
    "exam_title": "Ujian Tengah Semester",
    "latest_correction": {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "provider": "lmstudio",
      "batch_id": "batch_abc123",
      "total_jobs": 25,
      "completed_jobs": 15,
      "failed_jobs": 0,
      "avg_time_per_job": 8.5,
      "status": "processing",
      "progress_percentage": 60,
      "estimated_remaining_seconds": 85,
      "started_at": "2026-04-02T10:00:00.000000Z",
      "finished_at": null
    },
    "question_progress": [
      {
        "exam_question_id": 5,
        "question_number": 1,
        "question_content": "Jelaskan pengertian photosynthesis dan berikan contoh dalam kehidupan sehari-hari",
        "score_value": 10,
        "total_to_correct": 5,
        "corrected_count": 5,
        "status": "completed",
        "progress_percentage": 100
      },
      {
        "exam_question_id": 8,
        "question_number": 2,
        "question_content": "Apa dampak positif teknologi terhadap kehidupan sehari-hari? Jelaskan minimal 3 contoh",
        "score_value": 15,
        "total_to_correct": 5,
        "corrected_count": 3,
        "status": "processing",
        "progress_percentage": 60
      },
      {
        "exam_question_id": 12,
        "question_number": 3,
        "question_content": "Tuliskan contoh penerapan teknologi ramah lingkungan dalam bidang pendidikan",
        "score_value": 20,
        "total_to_correct": 5,
        "corrected_count": 2,
        "status": "processing",
        "progress_percentage": 40
      },
      {
        "exam_question_id": 15,
        "question_number": 4,
        "question_content": "Bagaimana cara meningkatkan efisiensi energi di sekolah? Berikan minimal 5 saran",
        "score_value": 25,
        "total_to_correct": 5,
        "corrected_count": 0,
        "status": "processing",
        "progress_percentage": 0
      },
      {
        "exam_question_id": 20,
        "question_number": 5,
        "question_content": "Jelaskan peran pemerintah dalam mengatasi permasalahan lingkungan hidup di Indonesia",
        "score_value": 30,
        "total_to_correct": 5,
        "corrected_count": 5,
        "status": "completed",
        "progress_percentage": 100
      }
    ],
    "all_corrections": [
      {
        "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
        "provider": "lmstudio",
        "total_jobs": 25,
        "completed_jobs": 15,
        "status": "processing",
        "started_at": "2026-04-02T10:00:00.000000Z",
        "finished_at": null
      }
    ]
  }
}
```

**Response Fields - Latest Correction:**
| Field | Type | Description |
|-------|------|-------------|
| id | string (ULID) | Stats record ID |
| provider | string | AI provider used: `gemini`, `openrouter`, `lmstudio` |
| batch_id | string | Laravel batch ID for the job batch |
| total_jobs | int | Total number of jobs in this correction batch |
| completed_jobs | int | Number of jobs completed |
| failed_jobs | int | Number of jobs failed |
| avg_time_per_job | float | Average execution time per job in seconds |
| status | string | Status: `processing`, `completed`, `failed` |
| progress_percentage | int | Progress percentage (0-100) |
| estimated_remaining_seconds | int\|null | Estimated seconds remaining, `null` if no avg_time data |
| started_at | datetime | When the correction started |
| finished_at | datetime\|null | When the correction finished (null if still processing) |

**Response Fields - Question Progress:**
| Field | Type | Description |
|-------|------|-------------|
| exam_question_id | int | Exam question ID |
| question_number | int | Question number in the exam |
| question_content | string | Full question content text |
| score_value | float | Maximum score for this question |
| total_to_correct | int | Total answers to be corrected for this question |
| corrected_count | int | Number of answers already corrected |
| status | string | Status: `processing`, `completed`, `pending`, `failed` |
| progress_percentage | int | Progress percentage for this question (0-100) |

---

## Frontend Integration Guide

### Polling Strategy

Untuk mendapatkan real-time updates, frontend dapat melakukan polling ke endpoint `correction-progress`:

```javascript
// Example polling function
const pollCorrectionProgress = async (examId, onUpdate) => {
  const poll = async () => {
    const response = await fetch(`/api/v1/exams/${examId}/correction-progress`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    
    onUpdate(data.data);
    
    // Continue polling if still processing
    if (data.data.latest_correction.status === 'processing') {
      setTimeout(poll, 3000); // Poll every 3 seconds
    }
  };
  
  poll();
};

// Usage
pollCorrectionProgress(1, (data) => {
  console.log(`Progress: ${data.latest_correction.progress_percentage}%`);
  console.log(`Estimated time: ${data.latest_correction.estimated_remaining_seconds}s`);
});
```

### Display Guidelines

| Status | User Message | Recommended Action |
|--------|--------------|---------------------|
| `processing` | "Sedang memproses... (45/50)" | Show progress bar |
| `completed` | "Koreksi selesai!" | Show success notification |
| `failed` | "Koreksi gagal. Silakan coba lagi." | Show error with retry button |

### Estimated Time Display

```javascript
// Format estimated time for display
const formatRemainingTime = (seconds) => {
  if (seconds === null || seconds === 0) return 'Sedang dihitung...';
  
  if (seconds < 60) return `${seconds} detik`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)} menit ${seconds % 60} detik`;
  
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  return `${hours} jam ${minutes} menit`;
};
```

---

## Error Responses

### 404 - No Correction Found
```json
{
  "success": false,
  "message": "No correction jobs found for this exam.",
  "data": {
    "exam_id": 1,
    "stats": []
  }
}
```

### 422 - Invalid Provider
```json
{
  "success": false,
  "message": "Invalid provider. Supported providers are: gemini, openrouter, lmstudio",
  "data": {}
}
```

### 404 - No Essay Questions Found
```json
{
  "success": false,
  "message": "No essay questions found for this selection.",
  "data": {}
}
```

---

## Provider Information

| Provider | Description | Expected Speed |
|----------|-------------|----------------|
| `gemini` | Google Gemini API | Medium (5-15s per job) |
| `openrouter` | OpenRouter free tier | Slow (10-30s per job) |
| `lmstudio` | Local LM Studio | Fast (2-10s per job) |

---

## Notes

- Stats record secara otomatis menyimpan rata-rata execution time untuk estimasi di masa depan.
- Frontend harus berhenti polling saat status bukan `processing`.
- Jika `estimated_remaining_seconds` bernilai `null`, berarti belum ada cukup data untuk estimasi (perlu minimal 1 job selesai).
