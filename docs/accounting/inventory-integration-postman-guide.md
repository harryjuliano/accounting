# Inventory Integration API (Postman Guide)

Endpoint ini dipakai agar accounting hub dapat menerima payload event inventory dari Postman/integrasi sistem operasional.

## Endpoint

- **Method**: `POST`
- **URL**: `/api/integrations/inventory/events`
- **Header opsional**: `X-Integration-Token: <token>` (wajib jika `INTEGRATION_INVENTORY_TOKEN` diset di `.env`)
- **Header wajib**: `Content-Type: application/json`, `Accept: application/json`

## Transaction type yang didukung seeder

Finance Hub memakai endpoint inventory yang sama, tetapi membedakan posting rule berdasarkan `payload.transaction_type`:

| Kebutuhan | `event_name` | `payload.transaction_type` | Posting rule |
|---|---|---|---|
| Receipt dari pembelian | `inventory.receipt.posted` | `inventory.receipt.purchase` | `INV_RECEIPT_PURCHASE` |
| Receipt dari retur pembelian | `inventory.receipt.posted` | `inventory.receipt.purchase_return` | `INV_RECEIPT_PURCHASE_RETURN` |
| Inventory keluar - penjualan/COGS | `inventory.issue.posted` | `inventory.issue.sales` | `INV_ISSUE_SALES` |
| Inventory keluar - damaged/write-off | `inventory.issue.posted` | `inventory.issue.damaged` | `INV_ISSUE_DAMAGED` |
| Inventory keluar - sample/promotion | `inventory.issue.posted` | `inventory.issue.sample` | `INV_ISSUE_SAMPLE` |
| Inventory keluar - internal use | `inventory.issue.posted` | `inventory.issue.internal_use` | `INV_ISSUE_INTERNAL_USE` |

> Catatan: `payload.transaction_type` wajib dikirim agar rule yang benar terpilih. Rule legacy `INV_RECEIPT_BASIC` dinonaktifkan oleh seeder baru jika masih ada dari seed sebelumnya.

## Import collection Postman untuk inventory out

Collection Postman siap import tersedia di:

```text
docs/accounting/postman/inventory-out-events.postman_collection.json
```

Setelah import, isi collection variables berikut:

| Variable | Contoh | Keterangan |
|---|---|---|
| `base_url` | `https://arthanusafinance.cloudcenter.work` | Base URL Accounting Hub |
| `client_key` | `INVENTORY-ABCD1234EFGH` | Credential integration module `inventory` atau `all` |
| `client_secret` | `<client-secret>` | Secret yang tampil saat credential dibuat |
| `integration_token` | kosong / token `.env` | Aktifkan header `X-Integration-Token` hanya jika `INTEGRATION_INVENTORY_TOKEN` diset |

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

## Body JSON - contoh inventory keluar penjualan / COGS

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.issue.posted",
  "event_datetime": "2026-03-28T11:00:00Z",
  "idempotency_key": "INV-ISSUE-SALES-POSTMAN-1001",
  "source_document_type": "sales_order_issue",
  "source_document_id": "SO-ISS-1001",
  "source_document_no": "SO-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.issue.sales",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "SO-1001",
    "description": "Inventory out for sales order SO-1001",
    "total_amount": 750000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "customer_code": "CUST-001",
    "lines": [
      {
        "item_code": "SKU-SALES-001",
        "item_name": "Produk Penjualan A",
        "qty": 10,
        "unit_cost": 75000,
        "uom": "PCS"
      }
    ]
  }
}
```

Jurnal yang diharapkan setelah validate + post:

```text
Dr COGS / HPP
    Cr Inventory / Persediaan
```

## Body JSON - contoh inventory keluar damaged / write-off

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.issue.posted",
  "event_datetime": "2026-03-28T11:10:00Z",
  "idempotency_key": "INV-ISSUE-DAMAGED-POSTMAN-1001",
  "source_document_type": "inventory_write_off",
  "source_document_id": "DMG-1001",
  "source_document_no": "DMG-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.issue.damaged",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "DMG-1001",
    "description": "Inventory damaged write-off DMG-1001",
    "total_amount": 180000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "damage_reason": "broken_in_warehouse",
    "lines": [
      {
        "item_code": "SKU-DMG-001",
        "item_name": "Produk Rusak A",
        "qty": 3,
        "unit_cost": 60000,
        "uom": "PCS"
      }
    ]
  }
}
```

Jurnal yang diharapkan setelah validate + post:

```text
Dr Inventory Loss / Write-off
    Cr Inventory / Persediaan
```

## Body JSON - contoh inventory keluar sample / promotion

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.issue.posted",
  "event_datetime": "2026-03-28T11:20:00Z",
  "idempotency_key": "INV-ISSUE-SAMPLE-POSTMAN-1001",
  "source_document_type": "sample_issue",
  "source_document_id": "SMP-1001",
  "source_document_no": "SMP-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.issue.sample",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "SMP-1001",
    "description": "Inventory sample issued to prospect",
    "total_amount": 125000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "recipient_name": "PT Prospect Baru",
    "lines": [
      {
        "item_code": "SKU-SMP-001",
        "item_name": "Sample Produk A",
        "qty": 5,
        "unit_cost": 25000,
        "uom": "PCS"
      }
    ]
  }
}
```

Jurnal yang diharapkan setelah validate + post:

```text
Dr Promotion / Sample Expense
    Cr Inventory / Persediaan
```

## Body JSON - contoh inventory keluar internal use

```json
{
  "client_key": "INVENTORY-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "inventory.issue.posted",
  "event_datetime": "2026-03-28T11:30:00Z",
  "idempotency_key": "INV-ISSUE-INTERNAL-POSTMAN-1001",
  "source_document_type": "internal_use_issue",
  "source_document_id": "INT-1001",
  "source_document_no": "INT-1001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "inventory.issue.internal_use",
    "posting_date": "2026-03-28",
    "entry_date": "2026-03-28",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "reference_no": "INT-1001",
    "description": "Inventory issued for internal use",
    "total_amount": 90000,
    "branch_code": "MAIN",
    "warehouse_code": "WH-01",
    "department_code": "OPS",
    "lines": [
      {
        "item_code": "SKU-INT-001",
        "item_name": "Barang Operasional A",
        "qty": 2,
        "unit_cost": 45000,
        "uom": "PCS"
      }
    ]
  }
}
```

Jurnal yang diharapkan setelah validate + post:

```text
Dr Internal Use Expense
    Cr Inventory / Persediaan
```

## Posting rule dan COA mapping default dari seeder

Seeder `InventoryPostingRuleSeeder` membuat rule aktif untuk receipt dan issue:

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

### Rule inventory keluar

| Posting rule | Debit mapping key | Credit mapping key | Default debit COA | Default credit COA |
|---|---|---|---|---|
| `INV_ISSUE_SALES` | `inventory.issue.sales.debit.cogs` | `inventory.issue.sales.credit.inventory` | `5120` COGS | `1150` Inventory |
| `INV_ISSUE_DAMAGED` | `inventory.issue.damaged.debit.loss` | `inventory.issue.damaged.credit.inventory` | `8100` Loss/write-off | `1150` Inventory |
| `INV_ISSUE_SAMPLE` | `inventory.issue.sample.debit.promotion` | `inventory.issue.sample.credit.inventory` | `7100` Promotion/sample expense | `1150` Inventory |
| `INV_ISSUE_INTERNAL_USE` | `inventory.issue.internal_use.debit.expense` | `inventory.issue.internal_use.credit.inventory` | `7100` Internal use expense | `1150` Inventory |

Silakan review COA mapping default tersebut setelah seeding jika perusahaan memiliki akun GRNI, clearing, COGS, write-off, promotion, atau internal-use khusus.

## Provisioning credential client

Buat credential sekali per modul + company + branch dengan command. Jika ingin satu credential dipakai semua module, gunakan `--module=all`:

```bash
php artisan integration:client:create <company_id> <branch_id> --module=all --name="ERP Shared Integration"
```

Output command akan menampilkan:
- `client_key`
- `client_secret` (ditampilkan sekali, simpan di secret manager)

Accounting Hub menyimpan `client_secret` dalam bentuk hash SHA-256 (`client_secret_hash`) dan melakukan verifikasi saat request masuk. Credential `--module=all` dapat dipakai oleh endpoint inventory dan module integrasi lain; gunakan `--module=inventory` jika ingin membatasi credential hanya untuk inventory.

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
- Tidak ada tax line di receipt/issue fase awal; nominal posting memakai `payload_total`.

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
- inventory out (`inventory.issue.*`) juga membuat row pending di `integration_outboxes` dengan `destination_system=finance_hub` dan `event_name=finance_hub.inventory_out.posted`;
- jika periode tidak `open` atau akun invalid, event akan menjadi `failed` dengan `error_message`.

Catatan payload amount untuk rule receipt dan issue:
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
