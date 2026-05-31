# Vendor Payment Integration API (Bank Transfer)

Endpoint ini dipakai agar Accounting Hub dapat menerima event **Bank Payment to Vendor** dari modul Accounts Payable.

## Endpoint

- **Method**: `POST`
- **URL lokal**: `/api/integrations/vendor-payments/events`
- **Header opsional**: `X-Integration-Token: <token>` (menggunakan token integrasi AP yang sama dengan Vendor Invoice jika `INTEGRATION_VENDOR_INVOICE_TOKEN` diset)
- **Header wajib**: `Content-Type: application/json`, `Accept: application/json`

## Prinsip mapping Cash Account

Implementasi awal memakai **Opsi A**: master cash/bank menggunakan tabel `bank_accounts`.

```text
payload.cash_account_id / payload.bank_account_id
        ↓
bank_accounts.gl_account_id
        ↓
chart_of_accounts.id
        ↓
journal_lines.account_id
        ↓
General Ledger / Balance Sheet / Trial Balance
```

Line Cash/Bank pada posting rule tidak memakai COA fixed. Sistem resolve account secara dinamis dari Cash Account yang dipilih user.

## WHT sebagai user option

WHT tetap didukung di dua titik:

1. **Saat invoice**: isi `payload.amounts.withholding_tax` pada event Vendor Invoice jika WHT ingin diakui saat invoice.
2. **Saat payment**: isi `payload.amounts.withholding_tax_total` pada event Vendor Payment jika WHT ingin diakui saat bayar.

Default operasional yang disarankan adalah **WHT saat payment**. Jika WHT sudah diposting saat invoice, kirim `withholding_tax_total = 0` saat payment agar tidak double posting.

## Body JSON contoh

```json
{
  "client_key": "ACCOUNTS_PAYABLE-ABCD1234EFGH",
  "client_secret": "<client-secret>",
  "event_name": "vendor.payment.posted",
  "event_datetime": "2026-05-31T10:00:00Z",
  "idempotency_key": "VP-POSTMAN-1001",
  "source_document_type": "vendor_payment",
  "source_document_id": "VP-1001",
  "source_document_no": "VP-0001",
  "schema_version": "v1",
  "payload": {
    "transaction_type": "vendor.payment.bank_transfer",
    "currency_code": "IDR",
    "posting_date": "2026-05-31",
    "entry_date": "2026-05-31",
    "reference_no": "VP-0001",
    "description": "Vendor Payment VP-0001",
    "cash_account_id": 1,
    "amounts": {
      "invoice_payment_total": 6904000,
      "withholding_tax_total": 128000,
      "stamp_duty": 138080,
      "bank_charge": 10000,
      "freight": 100000
    },
    "invoice_lines": [
      {
        "invoice_no": "INV-1001",
        "payment_amount": 6904000,
        "withholding_tax": 128000
      }
    ]
  }
}
```

## Posting jurnal yang dihasilkan

Posting rule `AP_VENDOR_PAYMENT_BANK_TRANSFER` menghasilkan jurnal berikut untuk contoh di atas:

| Line | Transaksi | Account source | Debit | Credit |
|---:|---|---|---:|---:|
| 1 | AP Vendor | `vendor.payment.debit.ap` | 6.904.000 | - |
| 2 | Stamp Duty | `vendor.payment.debit.stamp_duty` | 138.080 | - |
| 3 | Bank Charge | `vendor.payment.debit.bank_charge` | 10.000 | - |
| 4 | Freight | `vendor.payment.debit.freight` | 100.000 | - |
| 5 | WHT Payable | `vendor.payment.credit.wht` | - | 128.000 |
| 6 | Cash/Bank Out | `bank_accounts.gl_account_id` dari `cash_account_id` | - | 7.024.080 |
|  | **Total** |  | **7.152.080** | **7.152.080** |

Rumus Cash/Bank Out:

```text
Cash Out = Invoice Payment Total - WHT + Stamp Duty + Bank Charge + Freight
         = 6.904.000 - 128.000 + 138.080 + 10.000 + 100.000
         = 7.024.080
```

Jika WHT sudah diposting saat invoice, contoh payment dikirim dengan `withholding_tax_total = 0`, dan Cash Out dihitung dari nilai AP net yang dibayar ditambah biaya tambahan.

## Command pemrosesan

```bash
php artisan integration:vendor-payment:validate --limit=100
php artisan integration:vendor-payment:post --limit=100
```
