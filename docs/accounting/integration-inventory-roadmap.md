# Roadmap Integrasi Inventory → Accounting Hub

Dokumen ini menerjemahkan blueprint awal General Ledger menjadi rencana implementasi integrasi **Inventory Module** sebagai sumber jurnal otomatis ke accounting hub.

## 1. Prinsip desain (tetap konsisten dengan blueprint)

1. **Accounting hub sebagai posting engine tunggal**  
   Inventory tidak menulis jurnal langsung ke tabel jurnal; inventory mengirim event transaksi, accounting memvalidasi, resolve rule, lalu posting.
2. **Event-driven + idempotent**  
   Setiap event punya `idempotency_key` agar aman terhadap retry/duplikasi.
3. **Rule-based posting**  
   Mapping akun dan pola debit/kredit dikendalikan `posting_rules` + `posting_rule_lines`, bukan hardcode per endpoint.
4. **Auditability end-to-end**  
   Simpan payload sumber, log proses, error/retry, dan keterkaitan ke journal entry.
5. **Period governance**  
   Event tetap bisa diterima, tapi posting harus patuh status period/lock.

---

## 2. Target arsitektur integrasi Inventory

## 2.1 Sumber event dari inventory

Event minimum yang direkomendasikan:
- `inventory.receipt.posted` (penerimaan barang)
- `inventory.issue.posted` (barang keluar/non-sales issue)
- `inventory.transfer.posted` (antar gudang/branch)
- `inventory.adjustment.posted` (stock opname/adjustment)
- `inventory.cogs.posted` (COGS dari fulfillment/sales)

Payload standar per event:
- Header: company, branch, event datetime, doc type/id/no, currency, exchange rate.
- Detail line: item, qty, unit cost, amount, tax context (jika ada), dimensi (project/cost center/dsb).
- Metadata: source user/system, trace id, idempotency key.

## 2.2 Alur processing di accounting hub

1. Inventory publish event.
2. Accounting terima ke `integration_events` status `received`.
3. Validasi payload + periode + referensi master (COA/dimensi/currency).
4. Resolve `posting_rules` aktif sesuai `module_name=inventory`, `event_name`, `transaction_type`.
5. Bentuk draft lines dari `posting_rule_lines` + `coa_mappings`.
6. Buat `journal_entries` bertipe `auto` + link source document.
7. Post otomatis atau pending approval (sesuai governance amount/rule).
8. Tulis `integration_event_logs`; jika gagal simpan `integration_failures` untuk retry.

---

## 3. Roadmap implementasi (8–10 minggu)

## Phase 0 — Discovery & kontrak data (Minggu 1)

Deliverable:
- Event catalog final inventory + kamus payload JSON.
- Matrix event → dampak akuntansi (akun debit/kredit, dimensi wajib, periode policy).
- Keputusan mode integrasi: sync API vs async queue/webhook (disarankan async).

Checklist:
- Tentukan chart akun inventory: Inventory Asset, GRNI, COGS, Inventory Adjustment Gain/Loss, Stock Transit.
- Tetapkan aturan branch/warehouse ke dimensi accounting.
- Tetapkan kebijakan tanggal posting (`event_datetime` vs `document_date`).

## Phase 1 — Integration Inbox & Contract Validation (Minggu 2–3)

Deliverable:
- Endpoint/consumer khusus event inventory.
- JSON schema validator per event + versioning payload.
- Idempotency gate dan duplicate handling.
- Dashboard sederhana integration monitoring (received/processed/failed).

Kunci teknis:
- Simpan raw payload full (untuk forensik).
- Standard error code (validation_failed, period_locked, mapping_missing, dll).
- Dead-letter + retry policy bertahap.

## Phase 2 — Posting Rule Engine Inventory (Minggu 3–5)

Deliverable:
- Seeder/config default `posting_rules` & `posting_rule_lines` untuk event inventory utama.
- Resolver `account_source_type` (fixed/mapping/payload/dynamic) berjalan untuk kasus inventory.
- Support amount source formula untuk qty × cost + rounding policy.

Kunci bisnis:
- Versi rule by effective date (untuk perubahan kebijakan).
- Fallback rule + alert bila mapping COA belum lengkap.

## Phase 3 — Auto Journal Generation & Controls (Minggu 5–7)

Deliverable:
- Service pembentuk jurnal otomatis (`journal_type=auto`, `source_module=inventory`).
- Penegakan period lock/soft close/hard close.
- Approval path opsional berdasarkan nominal/materialitas.
- Reversal flow untuk event koreksi/cancel dari inventory.

Kualitas data:
- Strict balancing per journal.
- Cegah posting ke akun nonaktif/invalid dimension.
- Simpan `integration_key` unik per jurnal untuk trace dan idempotency lintas layer.

## Phase 4 — Reconciliation, Observability, & UAT (Minggu 7–9)

Deliverable:
- Laporan rekonsiliasi inventory movement vs GL movement (per hari/per period/per branch).
- Monitor SLA integrasi (latency, fail rate, retry success).
- UAT skenario end-to-end dengan data realistik.

Skenario wajib uji:
- Receipt normal, receipt backdated ke period closed.
- Transfer antar branch dengan nilai berbeda.
- Adjustment plus/minus.
- COGS high volume.
- Duplicate event & out-of-order event.

## Phase 5 — Go-live bertahap & Hypercare (Minggu 10)

Deliverable:
- Pilot 1–2 branch/gudang.
- Cutover plan + rollback plan.
- Hypercare dashboard 2 minggu pertama.

Go-live strategy:
- Jalankan mode shadow terlebih dahulu (generate jurnal preview tanpa posting) 3–7 hari.
- Setelah cocok, aktifkan auto-post terbatas.
- Full rollout saat mismatch di bawah threshold yang disepakati.

---

## 4. Rekomendasi desain detail (agar scalable untuk modul lain)

1. **Canonical event envelope** lintas modul (inventory sekarang, procurement/payroll nanti) agar engine posting reusable.
2. **Posting Rule Management UI** (versi, effective date, simulation test) supaya finance team tidak bergantung ke deploy code untuk update rule.
3. **Chart mapping namespace**: gunakan pola `inventory.<event>.<side>.<purpose>` (contoh: `inventory.receipt.debit.asset`).
4. **Asynchronous first** dengan queue terpisah `integration-high`/`integration-normal` untuk kontrol throughput.
5. **Outbox pattern di inventory** (jika memungkinkan) agar publish event transaksional dan tidak hilang.
6. **Replay/reprocess tooling** untuk event gagal tanpa edit DB manual.
7. **Deterministic journal number policy** untuk auto journal agar mudah audit dan tracing.
8. **Observability by design**: metric, structured logs, trace-id, alert rules.

---

## 5. KPI keberhasilan fase Inventory Integration

- **Functional accuracy**: >= 99.5% event inventory menghasilkan jurnal benar tanpa intervensi manual.
- **Reconciliation**: selisih inventory subledger vs GL < 0.1% dari nilai movement per period.
- **Reliability**: retry success rate > 95% untuk transient failure.
- **Latency**: p95 waktu dari event diterima ke journal posted < 2 menit.
- **Governance**: 100% jurnal auto memiliki source trace dan audit log.

---

## 6. Backlog teknis prioritas tinggi (shortlist implementasi)

1. Inventory integration controller/consumer + validator.
2. Service idempotency & duplicate detector.
3. Rule resolver service untuk `posting_rules`/`posting_rule_lines`.
4. Auto journal composer + balancing guard.
5. Integration monitoring page (status, error, retry action).
6. Reprocess command (`php artisan integration:reprocess --event=...`).
7. Feature tests end-to-end inventory event → journal entry.

Dokumen ini dapat dipakai sebagai baseline sprint planning dan delivery tracking lintas tim Finance, Inventory, dan Platform.
