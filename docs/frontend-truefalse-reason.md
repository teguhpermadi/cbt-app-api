# Panduan Implementasi True/False dengan Alasan Opsional

## 1. Struktur API

**Endpoint:** `POST /api/v1/students/exams/{exam_id}/answer`

### Request Body (Format Baru)

```json
{
  "question_id": "exam_result_detail_id",
  "answer": {
    "option_key": "T",
    "reason": "Alasan mengapa jawaban dipilih (opsional, bisa null)"
  }
}
```

### Request Body (Format Lama - Still Supported)

```json
{
  "question_id": "exam_result_detail_id",
  "answer": "T"
}
```

---

## 2. Komponen UI yang Diperlukan

| Elemen | Deskripsi |
|--------|-----------|
| Radio Button | Untuk memilih "Benar" atau "Salah" |
| Input Text | Alasan (opsional, muncul saat pilihan dipilih) |

---

## 3. Contoh Implementasi React

```jsx
import { useState } from 'react';

const TrueFalseQuestion = ({ detailId, onAnswer }) => {
  const [selectedOption, setSelectedOption] = useState(null);
  const [reason, setReason] = useState('');

  const handleSubmit = async () => {
    if (!selectedOption) return;

    const payload = {
      question_id: detailId,
      answer: {
        option_key: selectedOption,
        reason: reason.trim() || null
      }
    };

    try {
      const response = await fetch(`/api/v1/students/exams/${examId}/answer`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      
      if (result.success) {
        onAnswer(result.data);
      }
    } catch (error) {
      console.error('Failed to save answer:', error);
    }
  };

  return (
    <div className="true-false-question">
      <div className="options">
        <label className={`option ${selectedOption === 'T' ? 'selected' : ''}`}>
          <input
            type="radio"
            name="trueFalse"
            value="T"
            checked={selectedOption === 'T'}
            onChange={(e) => setSelectedOption(e.target.value)}
          />
          <span>Benar</span>
        </label>

        <label className={`option ${selectedOption === 'F' ? 'selected' : ''}`}>
          <input
            type="radio"
            name="trueFalse"
            value="F"
            checked={selectedOption === 'F'}
            onChange={(e) => setSelectedOption(e.target.value)}
          />
          <span>Salah</span>
        </label>
      </div>

      <div className="reason-input">
        <label htmlFor="reason">Alasan (opsional):</label>
        <textarea
          id="reason"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          placeholder="Tulis alasan mengapa Anda memilih jawaban tersebut..."
          rows={3}
        />
      </div>

      <button 
        onClick={handleSubmit}
        disabled={!selectedOption}
      >
        Simpan Jawaban
      </button>
    </div>
  );
};

export default TrueFalseQuestion;
```

---

## 4. Contoh Implementasi Vue

```vue
<template>
  <div class="true-false-question">
    <div class="options">
      <label :class="{ selected: selectedOption === 'T' }">
        <input
          type="radio"
          v-model="selectedOption"
          value="T"
        />
        <span>Benar</span>
      </label>

      <label :class="{ selected: selectedOption === 'F' }">
        <input
          type="radio"
          v-model="selectedOption"
          value="F"
        />
        <span>Salah</span>
      </label>
    </div>

    <div class="reason-input">
      <label>Alasan (opsional):</label>
      <textarea
        v-model="reason"
        placeholder="Tulis alasan mengapa Anda memilih jawaban tersebut..."
        rows="3"
      />
    </div>

    <button 
      @click="submitAnswer"
      :disabled="!selectedOption"
    >
      Simpan Jawaban
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  detailId: {
    type: String,
    required: true
  },
  examId: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['answered']);

const selectedOption = ref(null);
const reason = ref('');

const submitAnswer = async () => {
  if (!selectedOption.value) return;

  const payload = {
    question_id: props.detailId,
    answer: {
      option_key: selectedOption.value,
      reason: reason.value.trim() || null
    }
  };

  try {
    const response = await fetch(`/api/v1/students/exams/${props.examId}/answer`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    
    if (result.success) {
      emit('answered', result.data);
    }
  } catch (error) {
    console.error('Failed to save answer:', error);
  }
};
</script>
```

---

## 5. Response dari API

API akan mengembalikan:

```json
{
  "success": true,
  "message": "Answer saved.",
  "data": {
    "question_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "is_answered": true,
    "detail": {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "student_answer": {
        "option_key": "T",
        "reason": "alasan siswa"
      },
      "is_correct": true,
      "score_earned": 1
    }
  }
}
```

---

## 6. Catatan Penting

- **Backward compatible**: Jawaban lama format `"T"` atau `"F"` tetap berfungsi
- **Reason opsional**: Bisa `null` atau string kosong jika siswa tidak mengisi
- **Scoring**: Hanya `option_key` yang digunakan untuk koreksi, `reason` hanya disimpan sebagai metadata
- **Tipe data**: `option_key` menggunakan string `"T"` untuk Benar dan `"F"` untuk Salah
