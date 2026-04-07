# Backup & Restore Asset Files

Fitur untuk backup dan restore file gambar aset yang tersimpan di public storage.

## Overview

- **Base URL**: `/api/v1/question-banks`
- **Access**: Semua user yang terautentikasi

---

## Endpoints

### 1. Backup Assets

Mendownload seluruh file di `storage/app/public/` sebagai file zip.

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/assets/backup` | Download semua file sebagai ZIP |

**Response** (Binary):
```
Content-Type: application/zip
Content-Disposition: attachment; filename=assets_backup_2026-04-07.zip
```

**Error Responses**:
- `500`: Public storage tidak ditemukan
- `400`: Tidak ada file untuk di-backup

---

### 2. Restore Assets

Mengupload file zip dan mengekstrak ke public storage.

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/assets/restore` | Upload dan restore file |

**Request**:
```
Content-Type: multipart/form-data

Parameters:
- file: (required) File ZIP
```

**Success Response** (200):
```json
{
  "success": true,
  "message": "Berhasil merestore 150 file.",
  "data": {
    "extracted_files": 150
  }
}
```

**Error Responses**:
- `422`: File tidak ada atau bukan format zip
- `400`: Gagal membuka file zip

---

## Contoh Penggunaan

### Backup (Download)
```bash
curl -X GET "http://localhost:8000/api/v1/question-banks/assets/backup" \
  -H "Authorization: Bearer {TOKEN}" \
  -o assets_backup.zip
```

### Restore (Upload)
```bash
curl -X POST "http://localhost:8000/api/v1/question-banks/assets/restore" \
  -H "Authorization: Bearer {TOKEN}" \
  -F "file=@assets_backup.zip"
```

---

## Catatan

- Restore berfungsi untuk **menambahkan** file (tidak menghapus yang sudah ada)
- Jika file sudah ada, akan ditimpa/ditulis ulang
- File dikelompokkan berdasarkan folder ID (misal: 98/, 99/, 124/, dll)