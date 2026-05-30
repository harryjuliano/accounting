# Inventory Integration API (Postman Guide)

Endpoint ini dipakai agar accounting hub dapat menerima payload event inventory dari Postman/integrasi sistem operasional.

## Endpoint

- **Method**: `POST`
- **URL**: `/api/integrations/inventory/events`
- **Header opsional**: `X-Integration-Token: <token>` (wajib jika `INTEGRATION_INVENTORY_TOKEN` diset di `.env`)
- **Header wajib**: `Content-Type: application/json`, `Accept: application/json`

## Transaction type receipt yang didukung seeder awal

Untuk implementasi awal, Finance Hub tetap memakai event inventory yang sama, tetapi membedakan posting rule berdasarkan `payload.transaction_type`:

| Kebutuhan | `event_name` | `payload.transaction_type` | Posting rule |
|---|---|---|---|
| Receipt dari pembelian | `inventory.receipt.posted` | `inventory.receipt.purchase` | `INV_RECEIPT_PURCHASE` |
| Receipt dari retur pembelian | `inventory.receipt.posted` | `inventory.receipt.purchase_return` | `INV_RECEIPT_PURCHASE_RETURN` |

> Catatan: `payload.transaction_type` wajib dikirim agar rule yang benar terpilih. Rule legacy `INV_RECEIPT_BASIC` dinonaktifkan oleh seeder baru jika masih ada dari seed sebelumnya.

## Body JSON - contoh receipt pembelian

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.receipt.posted",
  "event_datetime": "2026-03-28T10:00:00Z",
  "idempotency_key": "INV-RECEIPT-PURCHASE-1001",
  "source_document_type": "goods_receipt",
  "source_document_id": "GR-PO-1001",
  "source_document_no": "GRN-PO-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.receipt.purchase",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "GRN-PO-1001",
    "description": "Inventory receipt from purchase",
    "total_amount": 500000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "receipt_source": "purchase",
    "lines": [
      {
        "item_code": "SKU-TEST",
        "qty": 20,
        "unit_cost": 25000
      }
    ]
  }
}
```

### cURL - receipt pembelian

```bash
curl --location 'https://arthanusafinance.cloudcenter.work/api/integrations/inventory/events' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "client_key": "INVENTORY-ABCD1234EFGH",
    "client_secret": "<client-secret>",
    "event_name": "inventory.receipt.posted",
    "event_datetime": "2026-03-28T10:00:00Z",
    "idempotency_key": "INV-RECEIPT-PURCHASE-1001",
    "source_document_type": "goods_receipt",
    "source_document_id": "GR-PO-1001",
    "source_document_no": "GRN-PO-1001",
    "schema_version": "v1",
    "payload": {
      "transaction_type": "inventory.receipt.purchase",
      "posting_date": "2026-03-28",
      "entry_date": "2026-03-28",
      "currency_code": "IDR",
      "exchange_rate": 1,
      "reference_no": "GRN-PO-1001",
      "description": "Inventory receipt from purchase",
      "total_amount": 500000,
      "branch_code": "MAIN",
      "warehouse_code": "WH-01",
      "receipt_source": "purchase",
      "lines": [
        {
          "item_code": "SKU-TEST",
          "qty": 20,
          "unit_cost": 25000
        }
      ]
    }
  }'
```

## Body JSON - contoh receipt retur pembelian

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.receipt.posted",
  "event_datetime": "2026-03-28T10:15:00Z",
  "idempotency_key": "INV-RECEIPT-PURCHASE-RETURN-1001",
  "source_document_type": "purchase_return_receipt",
  "source_document_id": "PRR-1001",
  "source_document_no": "PRR-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.receipt.purchase_return",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "PRR-1001",
    "description": "Inventory receipt from purchase return replacement",
    "total_amount": 250000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "receipt_source": "purchase_return",
    "lines": [
      {
        "item_code": "SKU-RET-TEST",
        "qty": 10,
        "unit_cost": 25000
      }
    ]
  }
}
```

### cURL - receipt retur pembelian

```bash
curl --location 'https://arthanusafinance.cloudcenter.work/api/integrations/inventory/events' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "client_key": "INVENTORY-ABCD1234EFGH",
    "client_secret": "<client-secret>",
    "event_name": "inventory.receipt.posted",
    "event_datetime": "2026-03-28T10:15:00Z",
    "idempotency_key": "INV-RECEIPT-PURCHASE-RETURN-1001",
    "source_document_type": "purchase_return_receipt",
    "source_document_id": "PRR-1001",
    "source_document_no": "PRR-1001",
    "schema_version": "v1",
    "payload": {
      "transaction_type": "inventory.receipt.purchase_return",
      "posting_date": "2026-03-28",
      "entry_date": "2026-03-28",
      "currency_code": "IDR",
      "exchange_rate": 1,
      "reference_no": "PRR-1001",
      "description": "Inventory receipt from purchase return replacement",
      "total_amount": 250000,
      "branch_code": "MAIN",
      "warehouse_code": "WH-01",
      "receipt_source": "purchase_return",
      "lines": [
        {
          "item_code": "SKU-RET-TEST",
          "qty": 10,
          "unit_cost": 25000
        }
      ]
    }
  }'
```

## Posting rule dan COA mapping default dari seeder

Seeder `InventoryPostingRuleSeeder` membuat dua rule aktif:

### `INV_RECEIPT_PURCHASE`

| Line | Side | Mapping key | Amount source |
|---:|---|---|---|
| 1 | Debit | `inventory.receipt.purchase.debit.inventory` | `payload_total` |
| 2 | Credit | `inventory.receipt.purchase.credit.grni` | `payload_total` |

Default COA mapping dibuat jika akun default tersedia:

| Mapping key | Default COA code |
|---|---|
| `inventory.receipt.purchase.debit.inventory` | `1150` Inventory |
| `inventory.receipt.purchase.credit.grni` | `2120` Accrued Expenses / GRNI clearing |

### `INV_RECEIPT_PURCHASE_RETURN`

| Line | Side | Mapping key | Amount source |
|---:|---|---|---|
| 1 | Debit | `inventory.receipt.purchase_return.debit.inventory` | `payload_total` |
| 2 | Credit | `inventory.receipt.purchase_return.credit.clearing` | `payload_total` |

Default COA mapping dibuat jika akun default tersedia:

| Mapping key | Default COA code |
|---|---|
| `inventory.receipt.purchase_return.debit.inventory` | `1150` Inventory |
| `inventory.receipt.purchase_return.credit.clearing` | `2110` Accounts Payable / vendor clearing |

Silakan review COA mapping default tersebut setelah seeding jika perusahaan memiliki akun GRNI atau clearing khusus.

## Provisioning credential client

Buat credential sekali per modul + company + branch dengan command:

```bash
php artisan integration:client:create <company_id> <branch_id> --module=inventory --name="Inventory WMS"
```

Output command akan menampilkan:
- `client_key`
- `client_secret` (ditampilkan sekali, simpan di secret manager)

Accounting Hub menyimpan `client_secret` dalam bentuk hash SHA-256 (`client_secret_hash`) dan melakukan verifikasi saat request masuk.

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
    "is_duplicate": false,
    "company_id": 1,
    "branch_id": 2
  }
}
```

## Catatan implementasi

- Event masuk disimpan ke tabel `integration_events` dengan `processing_status=received`.
- Idempotency dijaga oleh kombinasi `company_id (hasil resolusi dari client_key) + idempotency_key`.
- `company_id` dan `branch_id` **tidak perlu dikirim** oleh client; Accounting Hub mengambil otomatis dari credential.
- Event duplicate tidak dibuatkan row baru.
- Tidak ada tax line di receipt fase awal; nominal posting memakai `payload_total`.

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

Catatan payload amount untuk rule receipt:
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
