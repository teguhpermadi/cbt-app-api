# QuestionSuggestion API Documentation

## Overview

Model untuk suggestion/perubahan terhadap Question. User dapat mengajukan perubahan soal dan pemilik soal dapat approve/reject.

## Model: QuestionSuggestion

**File**: `app/Models/QuestionSuggestion.php`

**Table**: `question_suggestions`

**Fillable**: `question_id`, `user_id`, `data`, `description`, `state`

**Casts**: `data` → `array`, `state` → `QuestionSuggestionStateEnum`

**Relations**:
- `question()` → BelongsTo Question
- `user()` → BelongsTo User

---

## Enum: QuestionSuggestionStateEnum

| Value | Label | Color |
|-------|-------|-------|
| `pending` | Pending | warning |
| `approved` | Approved | success |
| `rejected` | Rejected | danger |

---

## Kolom `data` Structure

Kolom `data` berfungsi menyimpan perubahan yang diajukan. Terdiri dari dua bagian:

### 1. Question Fields

```json
{
  "content": "Soal yang baru...",
  "type": "multiple_choice",
  "difficulty": "easy",
  "timer": 30,
  "score": 10,
  "hint": "Hint (opsional)",
  "is_approved": true,
  "reading_material_id": "abc123..."
}
```

Field-field yang dapat diubah:
| Field | Type | Description |
|-------|------|-------------|
| `content` | string | Konten soal |
| `type` | string | Tipe soal |
| `difficulty` | string | Tingkat kesulitan |
| `timer` | integer | Waktu (detik) |
| `score` | integer | Poin soal |
| `hint` | string | Hint/petunjuk |
| `is_approved` | boolean | Status approval |
| `reading_material_id` | string | Referensi bacaan |

### 2. Options Structure

```json
{
  "options": {
    "update": [
      {"id": "option_ulid_1", "content": "Jawaban A", "is_correct": true},
      {"id": "option_ulid_2", "content": "Jawaban B baru", "is_correct": false}
    ],
    "create": [
      {"option_key": "E", "content": "Jawaban E", "is_correct": false, "order": 4}
    ],
    "delete": ["option_ulid_3"]
  }
}
```

**Structure Details**:

| Key | Type | Required Fields | Optional Fields |
|-----|------|-----------------|----------------|
| `options.update[]` | array | `id` | `content`, `order`, `is_correct`, `metadata` |
| `options.create[]` | array | `content` | `option_key`, `order`, `is_correct`, `metadata` |
| `options.delete[]` | array | - (string IDs) | - |

### Complete Example

```json
{
  "content": "Apa ibukota Indonesia?",
  "type": "multiple_choice",
  "options": {
    "update": [
      {"id": "abc123def456", "content": "Jakarta", "is_correct": true}
    ],
    "create": [
      {"option_key": "B", "content": "Bandung", "is_correct": false, "order": 1}
    ],
    "delete": ["def456ghi789"]
  }
}
```

---

## API Endpoints

### Controller: `app/Http/Controllers/Api/V1/QuestionSuggestionController.php`

**Base URL**: `/api/v1/question-suggestions`

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/` | List all suggestions | Teacher/Admin |
| GET | `/mine` | My suggestions | User |
| POST | `/` | Create suggestion | User |
| GET | `/{id}` | Detail suggestion | User |
| PUT | `/{id}` | Update (owner, pending only) | User |
| DELETE | `/{id}` | Delete (owner or admin) | User |
| POST | `/{id}/approve` | Approve & apply changes | Question Owner |
| POST | `/{id}/reject` | Reject suggestion | Question Owner |

### Query Parameters (Index)

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | integer | 15 | Pagination |
| `state` | string | - | Filter by state |
| `question_id` | string | - | Filter by question |
| `search` | string | - | Search text |
| `sort_by` | string | created_at | Sort field |
| `order` | string | desc | Sort order (asc/desc) |

---

## Request Validation

### StoreQuestionSuggestionRequest

```php
'question_id'    => ['required', 'string', 'exists:questions,id'],
'description'  => ['required', 'string'],
'data'         => ['nullable', 'array'],

// Question fields (optional)
'data.content' => ['sometimes', 'string'],
'data.type'   => ['sometimes', 'string'],
// ... other question fields

// Options structure (optional)
'data.options' => ['sometimes', 'array'],

// Update options
'data.options.update' => ['sometimes', 'array'],
'data.options.update.*.id' => ['required', 'string'],
'data.options.update.*.content' => ['sometimes', 'string'],
'data.options.update.*.order' => ['sometimes', 'integer'],
'data.options.update.*.is_correct' => ['sometimes', 'boolean'],
'data.options.update.*.metadata' => ['sometimes', 'array'],

// Create options
'data.options.create' => ['sometimes', 'array'],
'data.options.create.*.content' => ['required', 'string'],
'data.options.create.*.option_key' => ['nullable', 'string'],
'data.options.create.*.order' => ['nullable', 'integer'],
'data.options.create.*.is_correct' => ['nullable', 'boolean'],
'data.options.create.*.metadata' => ['nullable', 'array'],

// Delete options
'data.options.delete' => ['sometimes', 'array'],
'data.options.delete.*' => ['required', 'string'],
```

### UpdateQuestionSuggestionRequest

Sama dengan Store, bedanya:
- Semua field menggunakan `sometimes` (tidak wajib ada)
- Menggunakan patch semantics

---

## Response Format (QuestionSuggestionResource)

```json
{
  "id": "01HYX...",
  "question_id": "01HYX...",
  "user": {
    "id": "01HYX...",
    "name": "John Doe"
  },
  "data": {
    "content": "Apa ibukota Indonesia?",
    "options": {
      "update": [...],
      "create": [...],
      "delete": [...]
    }
  },
  "description": "Rubah jawaban yang salah",
  "state": "pending",
  "state_label": "Pending",
  "state_color": "warning",
  "created_at": "2024-01-01T00:00:00+07:00",
  "updated_at": "2024-01-01T00:00:00+07:00"
}
```

---

## Workflow

### 1. User Membuat Suggestion

```
POST /api/v1/question-suggestions
```

**Request**:
```json
{
  "question_id": "01HYX...",
  "description": "Rubah jawaban yang salah",
  "data": {
    "content": "Apa ibukota Indonesia yang baru?",
    "options": {
      "update": [
        {"id": "01HYXA...", "is_correct": false}
      ],
      "create": [
        {"content": "Jakarta", "is_correct": true}
      ]
    }
  }
}
```

- `user_id` automatically set from `Auth::id()`
- `state` automatically set to `PENDING`

### 2. Question Owner Meninjau

**Approve**:
```
POST /api/v1/question-suggestions/{id}/approve
```

- Apply changes to Question (content, type, dll)
- Apply options changes (create/update/delete)
- Set state to `APPROVED`

**Reject**:
```
POST /api/v1/question-suggestions/{id}/reject
```

- Set state to `REJECTED`
- No changes applied

### 3. User Mengupdate/Delete Suggestion

- Owner dapat update/delete suggestion jika status masih `PENDING`
- Setelah approved/rejected, tidak dapat diubah

---

## Implementation Notes

### approve() Method Logic

```php
public function approve(string $id): JsonResponse
{
    // 1. Get suggestion
    $suggestion = QuestionSuggestion::find($id);
    
    // 2. Check authorization (must be question owner)
    // 3. Check state (must be PENDING)
    
    $question = $suggestion->question;
    $data = $suggestion->data;
    
    // 4. Apply options changes first (before question fields)
    if (isset($data['options'])) {
        $this->applyOptionsChanges($question, $data['options']);
        unset($data['options']);
    }
    
    // 5. Apply question fields
    if (!empty($data)) {
        $question->update($data);
    }
    
    // 6. Update suggestion state
    $suggestion->update(['state' => QuestionSuggestionStateEnum::APPROVED]);
}
```

### applyOptionsChanges() Logic

```php
private function applyOptionsChanges(Question $question, array $options): void
{
    // 1. Delete first (to avoid conflicts)
    if (isset($options['delete'])) {
        foreach ($options['delete'] as $optionId) {
            $option = $question->options()->find($optionId);
            $option?->delete();
        }
    }
    
    // 2. Create new options
    if (isset($options['create'])) {
        foreach ($options['create'] as $optionData) {
            $question->options()->create([...]);
        }
    }
    
    // 3. Update existing options
    if (isset($options['update'])) {
        foreach ($options['update'] as $optionData) {
            $option = $question->options()->find($optionData['id']);
            $option?->update([...]);
        }
    }
}
```

---

## Error Handling

| Scenario | Response |
|----------|----------|
| Suggestion not found | 404 `Question suggestion not found` |
| Not question owner | 403 `You are not authorized to approve/reject this suggestion` |
| Not pending | 400 `Only pending suggestions can be approved/rejected` |
| Not suggestion owner (update) | 403 `You are not authorized to update this suggestion` |

---

## Changelog

| Date | Change |
|------|--------|
| 2024-01-01 | Initial documentation |
| 2024-01-15 | Added options structure support |