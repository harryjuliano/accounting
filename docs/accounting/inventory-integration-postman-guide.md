# Inventory Integration API (Postman Guide)

Endpoint ini dipakai agar accounting hub dapat menerima payload event inventory dari Postman/integrasi sistem operasional.

## Endpoint

- **Method**: `POST`
- **URL**: `/api/integrations/inventory/events`
- **Header opsional**: `X-Integration-Token: <token>` (wajib jika `INTEGRATION_INVENTORY_TOKEN` diset di `.env`)

## Body JSON (contoh)

```json
{
  "company_id": 1,
  "event_name": "inventory.receipt.posted",
  "event_datetime": "2026-03-28T10:00:00Z",
  "idempotency_key": "INV-RECEIPT-1001",
  "source_document_type": "goods_receipt",
  "source_document_id": "GR-1001",
  "source_document_no": "GRN-1001",
  "schema_version": "v1",
  "payload": {
    "branch_code": "JKT",
    "warehouse_code": "WH-01",
    "lines": [
      {
        "item_code": "SKU-1",
        "qty": 10,
        "unit_cost": 25000
      }
    ]
  }
}
```

## Success response

- **201 Created** untuk event baru.
- **200 OK** untuk duplicate (`idempotency_key` sama pada company yang sama).

Contoh response:

```json
{
  "message": "Inventory event received.",
  "data": {
    "integration_event_id": 10,
    "processing_status": "received",
    "is_duplicate": false
  }
}
```

## Catatan implementasi

- Event masuk disimpan ke tabel `integration_events` dengan `processing_status=received`.
- Idempotency dijaga oleh kombinasi `company_id + idempotency_key`.
- Event duplicate tidak dibuatkan row baru.

## Menjalankan Phase 2 (Rule Validation)

Setelah event diterima (status `received`), jalankan command berikut untuk validasi rule-based posting preview:

```bash
php artisan integration:inventory:validate --limit=100
```

Hasil command:
- event dengan rule valid berubah ke `validated` dan payload menyimpan `_posting_rule` + `_posting_preview`;
- event tanpa rule/mapping akan menjadi `failed` dengan `error_message`.

> ⚠️ Jalankan command Artisan dari terminal server (bash), **bukan** dari prompt MariaDB (`MariaDB [db]>`).
>
> Contoh urutan benar:
> 1. `exit` dari MariaDB.
> 2. Di shell project Laravel, jalankan `php artisan integration:inventory:validate --limit=100`.


## Menjalankan Phase 3 (Auto Journal Posting)

Setelah event berstatus `validated`, jalankan command berikut untuk membuat auto journal ke `journal_entries` dan `journal_lines`:

```bash
php artisan integration:inventory:post --limit=100
```

Hasil command:
- event valid akan menjadi `processed` dan payload menyimpan `_journal_entry_id` + `_journal_no`;
- jika periode tidak `open` atau akun invalid, event akan menjadi `failed` dengan `error_message`.

Catatan payload amount untuk rule default `INV_RECEIPT_BASIC`:
- sistem akan baca nilai total dari salah satu field berikut (urut fallback): `payload.amounts.total`, `payload.total_amount`, `payload.amount`;
- jika semua field amount tidak ada, sistem akan hitung otomatis dari `sum(lines.qty * lines.unit_cost)`.


## Menjalankan Phase 3.1 (Hardening: Logs, Failures, Retry)

Sistem sekarang menulis jejak proses ke tabel berikut:
- `integration_event_logs` untuk log lifecycle event,
- `integration_failures` untuk error stage `validation` / `posting`.

Untuk requeue event gagal:

```bash
php artisan integration:inventory:retry-failed --stage=all --limit=100
```

`--stage=validate` akan mengembalikan event gagal validasi ke `received`.  
`--stage=post` akan mengembalikan event gagal posting ke `validated`.
