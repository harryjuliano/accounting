## RILT-Starter (REACT INERTIA LARVEL TAILWIND)
Project ini merupakan sebuah starter-kit atau project base dengan spesifikasi sebagai berikut:

TECH :
- Larvel 11
- Inertia.js
- React.Js
- Tailwind Css

FITUR :
- Theme Switcher (Dark & Light)
- Bulk Delete Data
- Responsive Design
- Navigation by Roles & Permissions
- SPA (Single Page Application)

## INSTALASI PROJECT

Pastikan `git` sudah terinstall, kemudian jalankan semua perintah dibawah ini :
```
1. clone repository
2. copy .env.example rename menjadi .env kemudian atur database di .env
3. composer install
4. php artisan key:generate
5. npm install 
6. npm run dev (pastikan selalu dijalankan diterminal)
7. php artisan migrate --seed
8. php artisan serve (pastikan selalu dijalankan diterminal)
```

## AKUN SEEDER

```
email : raf@dev.com
password : password
```

## TROUBLESHOOTING IMPORT CSV (ERROR 413 REQUEST ENTITY TOO LARGE)

Jika muncul halaman `413 Request Entity Too Large` saat import CSV (misalnya file ±1.2 MB), biasanya request ditolak di layer web server (Nginx), bukan di validasi Laravel.

Penyebab umum:
- `client_max_body_size` Nginx masih default kecil (sering `1m`).
- `upload_max_filesize` / `post_max_size` PHP masih lebih kecil dari file upload.

Contoh pengaturan yang disarankan:

### Nginx
```nginx
server {
    client_max_body_size 20M;
}
```

### PHP (`php.ini`)
```ini
upload_max_filesize=20M
post_max_size=20M
max_execution_time=120
max_input_time=120
memory_limit=512M
```

Setelah perubahan:
1. Reload/restart Nginx.
2. Restart PHP-FPM.
3. Coba upload ulang file CSV.

Catatan: untuk import dengan jumlah baris sangat besar (contoh 50.000 baris), pertimbangkan proses bertahap/chunk atau queue agar tidak timeout.

Parameter aplikasi yang bisa diatur via `.env`:
```ini
MANUAL_JOURNAL_IMPORT_MAX_UPLOAD_KB=20480
MANUAL_JOURNAL_IMPORT_MAX_ROWS=50000
```

Panduan rekomendasi production (profil server + nilai siap pakai): `docs/manual-journal-import-production.md`.

## OVERVIEW APLIKASI
<table>
  <tr>
        <td> 
            <img src="https://imgur.com/lGLU18q.png" alt="dashboard-light">
        </td>
        <td> 
            <img src="https://imgur.com/0iD1Cna.png" alt="dashboard-dark">
        </td>
   </tr>
    <tr>    
        <td>
            <img src="https://imgur.com/k7YXFL7.png" alt="sidebar-light-close">
        </td>
        <td>
            <img src="https://imgur.com/GFD8QwI.png" alt="sidebar-dropdown-link-light">
        </td>
    </tr>
   <tr>
        <td>
             <img src="https://imgur.com/YPWeFco.png" alt="mobile-light-modal">
        </td>
        <td> 
            <img src="https://imgur.com/yRBmDxZ.png" alt="mobile-light-sidebar">
        </td>
   </tr>    
   <tr>
       <td>
           <img src="https://imgur.com/4QEzppS.png" alt="notification-dark">
       </td>
        <td>
           <img src="https://imgur.com/mJ7dXu4.png" alt="mobile-notification-dark">
       </td>
   </tr>
</table>


## LISENSI
Aplikasi ini bersifat open source dapat digunakan oleh siapa pun dengan syarat tidak untuk di perjual belikan.
