# Rekomendasi Production: Import CSV Manual Journal

Dokumen ini berisi baseline konfigurasi production untuk import CSV manual journal, terutama untuk kasus file besar (contoh: ±1.2 MB dengan ±50.000 baris).

## Rekomendasi cepat (langsung pakai)

Jika belum ada data kapasitas server detail, gunakan nilai aman berikut:

### Aplikasi (`.env`)
```ini
MANUAL_JOURNAL_IMPORT_MAX_UPLOAD_KB=51200
MANUAL_JOURNAL_IMPORT_MAX_ROWS=50000
QUEUE_CONNECTION=database
```

### Nginx
```nginx
client_max_body_size 50M;
```

> Jika memakai reverse proxy berlapis (mis. Cloudflare, Nginx Proxy Manager, ingress), limit upload harus dinaikkan di tiap layer.

### PHP (`php.ini`)
```ini
upload_max_filesize=50M
post_max_size=50M
max_execution_time=180
max_input_time=180
memory_limit=512M
```

## Profil berdasarkan kapasitas server

| Profil server | vCPU / RAM | Upload limit | Row limit per file | Catatan |
|---|---|---:|---:|---|
| Small | 1-2 vCPU / 2-4 GB | 20 MB | 20.000 | Wajib aktifkan queue worker agar stabil |
| Medium | 2-4 vCPU / 4-8 GB | 50 MB | 50.000 | Rekomendasi default untuk kebanyakan deployment |
| Large | 4+ vCPU / 8+ GB | 100 MB | 100.000 | Tetap disarankan batch/chunk + queue |

## Praktik operasional yang disarankan

1. Jalankan worker queue secara permanen (supervisor/systemd) agar proses import tidak mengganggu request web.
2. Simpan log import (mulai, selesai, gagal) supaya mudah tracing jika ada error data.
3. Untuk file sangat besar, pecah file atau proses bertahap/chunk agar risiko timeout menurun.
4. Lakukan load test sederhana sebelum menaikkan limit lebih tinggi dari baseline.

## Command operasional

```bash
php artisan queue:work --tries=3 --timeout=180
```

> Jika traffic tinggi, jalankan lebih dari 1 worker dan atur monitoring (CPU, RAM, durasi job).

## Verifikasi jika masih muncul 413 setelah restart

```bash
sudo nginx -T | grep -n client_max_body_size
php -i | grep -E "Loaded Configuration File|upload_max_filesize|post_max_size"
```

Pastikan output menunjukkan nilai yang sama dengan target production, dan berasal dari service/container yang benar.
