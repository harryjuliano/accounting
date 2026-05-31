# Vendor Invoice Integration API (Postman Guide)

Endpoint ini dipakai agar Accounting Hub dapat menerima payload event **Vendor Invoice** dari Postman atau integrasi sistem Accounts Payable.

## Endpoint

- **Method**: `POST`
- **URL lokal**: `/api/integrations/vendor-invoices/events`
- **URL production contoh**: `https://arthanusafinance.cloudcenter.work/api/integrations/vendor-invoices/events`
- **Header opsional**: `X-Integration-Token: <token>` (wajib jika `INTEGRATION_VENDOR_INVOICE_TOKEN` diset di `.env`)
- **Header wajib**: `Content-Type: application/json`, `Accept: application/json`

## Body JSON untuk Postman

Gunakan tab **Body** di Postman, pilih **raw**, lalu pilih format **JSON**. Ganti `client_key` dan `client_secret` sesuai credential yang dibuat di Accounting Hub.

```json
{
  "client_key": "ACCOUNTS_PAYABLE-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "vendor.invoice.posted",
  "event_datetime": "2026-05-30T10:00:00Z",
  "idempotency_key": "VI-POSTMAN-1001",
  "source_document_type": "vendor_invoice",
  "source_document_id": "VI-1001",
  "source_document_no": "VI-0001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "vendor.invoice.standard",
    "currency_code": "IDR",
    "posting_date": "2026-05-30",
    "entry_date": "2026-05-30",
    "reference_no": "VI-0001",
    "description": "Vendor Invoice VI-0001",
    "amounts": {
      "invoice": 6400000,
      "tax": 704000,
      "freight": 100000,
      "withholding_tax": 128000,
      "purchase_discount": 200000,
      "payable_total": 6876000
    }
  }
}
```

## cURL contoh

```bash
curl --location 'https://arthanusafinance.cloudcenter.work/api/integrations/vendor-invoices/events' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "client_key": "ACCOUNTS_PAYABLE-ABCD1234EFGH",
    "client_secret": "<client-secret>",
    "event_name": "vendor.invoice.posted",
    "event_datetime": "2026-05-30T10:00:00Z",
    "idempotency_key": "VI-POSTMAN-1001",
    "source_document_type": "vendor_invoice",
    "source_document_id": "VI-1001",
    "source_document_no": "VI-0001",
    "schema_version": "v1",
    "payload": {
      "transaction_type": "vendor.invoice.standard",
      "currency_code": "IDR",
      "posting_date": "2026-05-30",
      "entry_date": "2026-05-30",
      "reference_no": "VI-0001",
      "description": "Vendor Invoice VI-0001",
      "amounts": {
        "invoice": 6400000,
        "tax": 704000,
        "freight": 100000,
        "withholding_tax": 128000,
        "purchase_discount": 200000,
        "payable_total": 6876000
      }
    }
  }'
```

Jika `INTEGRATION_VENDOR_INVOICE_TOKEN` aktif, tambahkan header berikut pada Postman/cURL:

```http
X-Integration-Token: <integration-token>
```

## Perhitungan jurnal yang diharapkan

Posting rule `AP_VENDOR_INVOICE_STANDARD` akan membuat 6 line jurnal dari contoh payload di atas:

| Line | Transaksi | Amount source | Debit | Credit |
|---:|---|---|---:|---:|
| 1 | DPP | `amounts.invoice` | 6.400.000 | - |
| 2 | PPN | `amounts.tax` | 704.000 | - |
| 3 | Freight | `amounts.freight` | 100.000 | - |
| 4 | PPh | `amounts.withholding_tax` | - | 128.000 |
| 5 | Diskon Pembelian | `amounts.purchase_discount` | - | 200.000 |
| 6 | Hutang Vendor/AP | `amounts.payable_total` | - | 6.876.000 |
|  | **Total** |  | **7.204.000** | **7.204.000** |

Rumus balance dari contoh:

```text
Debit  = DPP + PPN + Freight
       = 6.400.000 + 704.000 + 100.000
       = 7.204.000

Credit = PPh + Diskon Pembelian + Hutang Vendor/AP
       = 128.000 + 200.000 + 6.876.000
       = 7.204.000
```

> Catatan: jangan gunakan `payable_total` 7.076.000 untuk contoh dengan diskon pembelian 200.000, karena jurnal akan tidak balance. Nilai payable yang benar untuk contoh ini adalah 6.876.000.

## Success response

- **201 Created** untuk event baru.
- **200 OK** untuk duplicate (`idempotency_key` sama pada company yang sama).

Contoh response event baru:

```json
{
  "message": "Vendor invoice event received.",
  "data": {
    "integration_event_id": 10,
    "processing_status": "received",
    "is_duplicate": false,
    "company_id": 1,
    "branch_id": 2
  }
}
```

## Provisioning credential client

Buat credential sekali per module + company + branch dengan command:

```bash
php artisan integration:client:create <company_id> <branch_id> --module=accounts_payable --name="Accounts Payable"
```

Output command akan menampilkan:

- `client_key`
- `client_secret` (ditampilkan sekali, simpan di secret manager)

Accounting Hub menyimpan `client_secret` dalam bentuk hash SHA-256 (`client_secret_hash`) dan melakukan verifikasi saat request masuk.
