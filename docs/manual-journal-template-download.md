# Manual Journal - Download Template Excel

## Menu yang benar

Template import Manual Journal harus di-download dari halaman:

1. Buka menu **Apps > Manual Journal**.
2. Klik tombol toolbar **Download Template Excel (.xlsx)** di sebelah tombol **Import Excel/CSV**.
3. File yang benar bernama `manual-journal-import-template.xlsx`.

Jika file yang terbuka di Excel menampilkan baris seperti `<!DOCTYPE html>` atau `<html lang="en">`, berarti file tersebut bukan template Excel. Itu adalah halaman aplikasi/login yang tersimpan dari asset atau session lama.


## Catatan penyebab HTML pada template

Endpoint download template tidak membutuhkan company aktif. Jika file masih berisi HTML, biasanya server/browser masih memakai kode atau asset lama yang melakukan redirect ke halaman aplikasi. Pastikan kode terbaru sudah ter-deploy dan cache Laravel/browser sudah dibersihkan dengan langkah di bawah.

## Instruksi build/refresh setelah deploy

Jalankan langkah berikut di server setelah pull perubahan terbaru:

```bash
git pull
composer install --no-dev --prefer-dist --no-interaction
npm ci
npm run build
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

Setelah itu, di browser user:

1. Tutup file Excel lama yang berisi HTML.
2. Hapus file lama bernama `manual-journal-import-template` atau `manual-journal-import-template.csv` dari folder Downloads.
3. Hard refresh halaman Manual Journal dengan **Ctrl+F5** atau **Cmd+Shift+R**.
4. Klik ulang **Download Template Excel (.xlsx)**.

## Cara verifikasi cepat

- Nama file harus berakhiran `.xlsx`.
- Jika dibuka di Excel, baris pertama harus berisi header seperti `journal_no`, `entry_date`, `posting_date`, dan `credit`.
- Jika respons download masih HTML, frontend akan menampilkan pesan error dan tidak menyimpan file tersebut.
