# Playbook Fix 413 (Nginx + PHP-FPM) untuk Laravel

Dokumen ini adalah template konfigurasi **siap tempel** + user guide eksekusi untuk mengatasi:

`413 Request Entity Too Large`

> Target lingkungan: Ubuntu + Nginx + PHP-FPM (Laravel), dengan kemungkinan ada reverse proxy seperti Cloudflare / Nginx Proxy Manager.

---

## 1) Nilai limit yang direkomendasikan

Untuk kasus upload CSV/manual journal skala menengah:

- `client_max_body_size`: **50M**
- `upload_max_filesize`: **50M**
- `post_max_size`: **50M**

Silakan naikkan bertahap jika memang diperlukan.

---

## 2) Template Nginx (siap tempel)

Edit file site Anda (contoh: `/etc/nginx/sites-available/accounting.conf`) lalu pastikan pola berikut ada:

```nginx
server {
    listen 80;
    server_name accounting.example.com;

    root /var/www/accounting/public;
    index index.php index.html;

    # Kunci utama error 413 di Nginx:
    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock; # sesuaikan versi PHP

        # Optional: membantu request besar/berat
        fastcgi_read_timeout 180;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Jika Anda punya banyak server block dan ingin baseline global, tambahkan juga di `http {}` pada `/etc/nginx/nginx.conf`:

```nginx
http {
    client_max_body_size 50M;
}
```

> Prioritas nilai mengikuti konteks terdekat (location/server/http). Gunakan nilai konsisten agar tidak membingungkan.

---

## 3) Template PHP-FPM (php.ini)

Buka `php.ini` untuk FPM (contoh umum):

- `/etc/php/8.2/fpm/php.ini`

Lalu set:

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 180
max_input_time = 180
memory_limit = 512M
```

Jika server Anda juga memproses via CLI untuk job import, samakan juga nilai di:

- `/etc/php/8.2/cli/php.ini`

---

## 4) Langkah eksekusi (runbook)

Jalankan perintah berikut berurutan:

```bash
# 1) Cek sintaks nginx
sudo nginx -t

# 2) Reload nginx
sudo systemctl reload nginx

# 3) Restart PHP-FPM (sesuaikan versi)
sudo systemctl restart php8.2-fpm
```

---

## 5) Verifikasi konfigurasi aktif (WAJIB)

Pastikan bukan hanya “sudah diubah”, tapi benar-benar **aktif dipakai**:

```bash
# Lihat nilai efektif dari konfigurasi nginx yang ter-load
sudo nginx -T | grep -n client_max_body_size

# Pastikan service berjalan
systemctl status nginx --no-pager
systemctl status php8.2-fpm --no-pager

# Cek php.ini yang terbaca
php -i | grep "Loaded Configuration File"
php -i | grep -E "upload_max_filesize|post_max_size"
```

Jika nilai di output belum sesuai, berarti Anda kemungkinan mengedit file yang salah atau service yang direstart bukan instance yang melayani domain tersebut.

---

## 6) Jika masih 413 setelah langkah di atas

Kemungkinan besar limit ada di layer sebelum Nginx aplikasi:

1. **Cloudflare**  
   Cek batas upload sesuai paket/plan, lalu sesuaikan.
2. **Nginx Proxy Manager / reverse proxy lain**  
   Tambahkan/naikkan `client_max_body_size` di proxy tersebut.
3. **Kubernetes ingress / load balancer**  
   Cek annotation/setting body-size di ingress.

Prinsipnya: limit upload harus cukup besar di **setiap layer**, bukan hanya di app server.

---

## 7) Checklist cepat troubleshooting

- [ ] `client_max_body_size` sudah ada di server block domain yang benar.
- [ ] `upload_max_filesize` dan `post_max_size` sudah dinaikkan di php-fpm `php.ini`.
- [ ] Nginx test config (`nginx -t`) sukses.
- [ ] Nginx reload + PHP-FPM restart sudah dijalankan.
- [ ] `nginx -T` menunjukkan nilai yang benar.
- [ ] Tidak ada proxy upstream yang masih membatasi ukuran body.

---

## 8) Rekomendasi nilai awal berdasarkan kebutuhan

- Upload kecil-menengah: `20M`
- Upload menengah-besar: `50M` (default rekomendasi)
- Upload besar: `100M` (butuh kontrol timeout, queue, dan monitoring lebih ketat)

Untuk import data besar, tetap dianjurkan memproses via queue/chunk agar stabil.
