# Customer Invoice Collection Integration API (Postman Guide)

Endpoint ini dipakai agar Accounting Hub dapat menerima event **Post Collection Customer Invoice** dari Postman atau integrasi modul Sales/AR.

## Endpoint

- **Method**: `POST`
- **URL lokal**: `/api/integrations/events`
- **URL production contoh**: `https://arthanusafinance.cloudcenter.work/api/integrations/events`
- **Header opsional**: `X-Integration-Token: <token>` (wajib jika `INTEGRATION_GENERIC_TOKEN` diset di `.env`)
- **Header wajib**: `Content-Type: application/json`, `Accept: application/json`

## Credential

Gunakan credential integrasi dengan `source_module = sales` atau `source_module = all`.

Contoh pembuatan credential shared untuk Postman:

```bash
php artisan integration:client:create <company_id> <branch_id> --module=all --name="Postman Shared Integration"
```

Atau khusus module Sales:

```bash
php artisan integration:client:create <company_id> <branch_id> --module=sales --name="Sales Postman Integration"
```

## Prinsip pilih Cash/Bank Account

Line Cash/Bank memakai `account_source_type = dynamic`, sehingga account tidak diambil dari `coa_mappings`. Sistem resolve Cash/Bank dengan prioritas berikut:

1. `payload.gl_account_code` → mencari `chart_of_accounts.code` aktif bertipe `asset` / `assets`.
2. `payload.cash_account_coa_id` → langsung mencari `chart_of_accounts.id` aktif bertipe `asset` / `assets`.
3. `payload.cash_account_id` atau `payload.bank_account_id` → mencari master `bank_accounts`, lalu memakai `bank_accounts.gl_account_id`.

Jika referensi Cash/Bank kosong atau tidak valid, validasi akan gagal dengan error `cash_bank_account_not_found`.

## Body JSON contoh utama (pakai `gl_account_code`)

Gunakan tab **Body** di Postman, pilih **raw**, lalu pilih format **JSON**. Ganti `client_key`, `client_secret`, dan `gl_account_code` sesuai data environment.

```json
{
  "client_key": "SALES-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "source_module": "sales",
  "event_name": "customer.invoice.collection.posted",
  "event_datetime": "2026-06-02T10:00:00+07:00",
  "idempotency_key": "CIC-POSTMAN-1001",
  "source_document_type": "customer_invoice_collection",
  "source_document_id": "COL-202606-000001",
  "source_document_no": "COL-202606-000001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "customer.invoice.collection",
    "currency_code": "IDR",
    "exchange_rate": 1,
    "posting_date": "2026-06-02",
    "entry_date": "2026-06-02",
    "reference_no": "COL-202606-000001",
    "description": "Customer invoice collection COL-202606-000001",
    "gl_account_code": "1120-020",
    "source_cash_account": {
      "id": 3,
      "code": "B-1002",
      "name": "Bank Mandiri",
      "cash_type": "BANK",
      "currency_code": "IDR"
    },
    "customer": {
      "customer_code": "CUS-0001",
      "customer_name": "Apotek Sehat Sentosa"
    },
    "amounts": {
      "invoice_total": 1000000,
      "other_charge": 50000,
      "withholding_tax_total": 20000,
      "other_deduction": 30000,
      "bank_charge": 5000
    },
    "invoice_lines": [
      {
        "invoice_no": "INV-202606-000001",
        "invoice_amount": 1000000,
        "collection_amount": 1000000,
        "withholding_tax": 20000
      }
    ],
    "deductions": [
      {
        "code": "DISC-CLAIM",
        "description": "Potongan klaim customer",
        "amount": 30000
      }
    ],
    "other_charges": [
      {
        "code": "ADMIN-FEE",
        "description": "Biaya administrasi collection",
        "amount": 50000
      }
    ]
  }
}
```

## cURL contoh

```bash
curl --location 'https://arthanusafinance.cloudcenter.work/api/integrations/events' \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "client_key": "SALES-ABCD1234EFGH",
    "client_secret": "<client-secret>",
    "source_module": "sales",
    "event_name": "customer.invoice.collection.posted",
    "event_datetime": "2026-06-02T10:00:00+07:00",
    "idempotency_key": "CIC-POSTMAN-1001",
    "source_document_type": "customer_invoice_collection",
    "source_document_id": "COL-202606-000001",
    "source_document_no": "COL-202606-000001",
    "schema_version": "v1",
    "payload": {
      "transaction_type": "customer.invoice.collection",
      "currency_code": "IDR",
      "exchange_rate": 1,
      "posting_date": "2026-06-02",
      "entry_date": "2026-06-02",
      "reference_no": "COL-202606-000001",
      "description": "Customer invoice collection COL-202606-000001",
      "gl_account_code": "1120-020",
      "amounts": {
        "invoice_total": 1000000,
        "other_charge": 50000,
        "withholding_tax_total": 20000,
        "other_deduction": 30000,
        "bank_charge": 5000
      },
      "invoice_lines": [
        {
          "invoice_no": "INV-202606-000001",
          "invoice_amount": 1000000,
          "collection_amount": 1000000,
          "withholding_tax": 20000
        }
      ]
    }
  }'
```

Jika `INTEGRATION_GENERIC_TOKEN` aktif, tambahkan header berikut pada Postman/cURL:

```http
X-Integration-Token: <integration-token>
```

## Posting jurnal yang dihasilkan

Posting rule `CUSTOMER_INVOICE_COLLECTION_POSTED` menghasilkan jurnal berikut untuk contoh utama:

| Line | Transaksi | Account source | Debit | Credit |
|---:|---|---|---:|---:|
| 1 | Cash/Bank In | COA dari `gl_account_code` | 995.000 | - |
| 2 | WHT / Prepaid Tax | `sales.collection.debit.wht` | 20.000 | - |
| 3 | Potongan Lain | `sales.collection.debit.other_deduction` | 30.000 | - |
| 4 | Bank Charge | `sales.collection.debit.bank_charge` | 5.000 | - |
| 5 | Accounts Receivable | `sales.collection.credit.ar` | - | 1.000.000 |
| 6 | Other Charge | `sales.collection.credit.other_charge` | - | 50.000 |
|  | **Total** |  | **1.050.000** | **1.050.000** |

Rumus Net Cash In yang dipakai oleh preview/jurnal:

```text
Net Cash In = Invoice Total + Other Charge - WHT - Potongan Lain - Bank Charge
            = 1.000.000 + 50.000 - 20.000 - 30.000 - 5.000
            = 995.000
```

> Catatan: `bank_charge` diposting sebagai debit expense terpisah, sehingga mengurangi nilai cash/bank yang benar-benar masuk dan tetap membuat jurnal balance.

## Variasi payload Cash/Bank

### Opsi 1 — memakai `cash_account_coa_id`

Gunakan opsi ini jika modul sumber sudah menyimpan ID COA Accounting Hub.

```json
{
  "payload": {
    "cash_account_coa_id": 12,
    "amounts": {
      "invoice_total": 1000000,
      "other_charge": 50000,
      "withholding_tax_total": 20000,
      "other_deduction": 30000,
      "bank_charge": 5000
    }
  }
}
```

### Opsi 2 — memakai `cash_account_id` / `bank_account_id` legacy

Gunakan opsi ini jika master rekening bank sudah ada di Accounting Hub pada tabel `bank_accounts`.

```json
{
  "payload": {
    "cash_account_id": 3,
    "amounts": {
      "invoice_total": 1000000,
      "other_charge": 50000,
      "withholding_tax_total": 20000,
      "other_deduction": 30000,
      "bank_charge": 5000
    }
  }
}
```

## Command pemrosesan

Setelah event masuk dengan status `received`, jalankan command berikut:

```bash
php artisan integration:customer-invoice-collection:validate --limit=100
php artisan integration:customer-invoice-collection:post --limit=100
```

Atau proses dari halaman Integration Events jika tersedia tombol validate/posting untuk event tersebut.

## Checklist troubleshooting

- Pastikan `source_module` bernilai `sales`.
- Pastikan `event_name` bernilai `customer.invoice.collection.posted`.
- Pastikan `payload.transaction_type` bernilai `customer.invoice.collection` atau dikosongkan agar default event dipakai.
- Pastikan posting rule `CUSTOMER_INVOICE_COLLECTION_POSTED` sudah ada dan aktif. Jika belum, jalankan seeder `SalesInvoicePostingRuleSeeder`.
- Pastikan mapping `sales.collection.debit.wht`, `sales.collection.debit.other_deduction`, `sales.collection.debit.bank_charge`, `sales.collection.credit.ar`, dan `sales.collection.credit.other_charge` sudah diarahkan ke COA yang sesuai.
- Pastikan `gl_account_code` / `cash_account_coa_id` / `cash_account_id` mengarah ke akun COA aktif bertipe asset/assets.
- Gunakan `idempotency_key` baru untuk setiap percobaan payload baru di Postman. Jika memakai key yang sama, API akan mengembalikan event lama sebagai duplicate.
