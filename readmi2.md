# Panduan Instalasi LMS SMK Pesat

Panduan ini menjelaskan instalasi aplikasi LMS SMK Pesat pada Linux server, Laragon Windows, dan macOS. Aplikasi menggunakan Laravel 13, PHP 8.3+, Vue 3, Inertia.js, Vite, SQLite/MySQL, Laravel Reverb, queue database, dan Profiling API.

## 1. Prasyarat

Minimum yang diperlukan:

- PHP 8.3 atau lebih baru.
- Composer 2.x.
- Node.js 20 LTS atau lebih baru dan npm.
- Git.
- Ekstensi PHP: `pdo`, `pdo_sqlite` atau `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `xml`, `zip`, `tokenizer`, dan `json`.
- Port yang tersedia:
  - `8000` untuk Laravel development server.
  - `5173` untuk Vite development server.
  - `8080` untuk Laravel Reverb WebSocket.

Untuk production, gunakan Apache/Nginx dengan PHP-FPM dan proses worker yang dikelola Supervisor atau systemd.

## 2. Mengambil Source Code

```bash
git clone <URL_REPOSITORY> lms-smkpesat
cd lms-smkpesat
```

Jika source code sudah berupa folder, cukup masuk ke folder proyek:

```bash
cd /path/ke/lms-smkpesat
```

## 3. Instalasi Dependency

```bash
composer install
npm install
```

Untuk production, gunakan:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Jangan menjalankan `npm run build` dengan `sudo`. Dependency sebaiknya dimiliki oleh user deploy biasa.

## 4. Konfigurasi Environment

Salin file environment:

```bash
cp .env.example .env
php artisan key:generate
```

Pada Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Atur nilai penting di `.env`:

```dotenv
APP_NAME="SMK Pesat Learning Space"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local

PROFILING_API_URL=https://dev-profiling.smkpesat.id/api/v1
PROFILING_API_KEY=ISI_API_KEY_PROFILING
PROFILING_API_AUTH_HEADER=X-API-KEY
PROFILING_API_TIMEOUT=15
PROFILING_API_CACHE_TTL=300

REVERB_APP_ID=smkpesat-local
REVERB_APP_KEY=buat-key-acak
REVERB_APP_SECRET=buat-secret-acak
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Untuk production, ubah `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` ke domain HTTPS, dan gunakan `REVERB_SCHEME=https` dengan reverse proxy TLS.

Setelah mengubah `.env`, bersihkan cache konfigurasi:

```bash
php artisan optimize:clear
```

## 5. Database SQLite

Konfigurasi default proyek menggunakan SQLite. Buat file database jika belum ada:

Linux/macOS:

```bash
mkdir -p database
touch database/database.sqlite
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force database
New-Item -ItemType File -Force database/database.sqlite
```

Jalankan migration:

```bash
php artisan migrate --force
```

Untuk data awal demo, gunakan:

```bash
php artisan db:seed
```

Akun seed default:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@smkpesat.id` | `password` |
| Guru | `ahmad.fadli@smkpesat.id` | `password` |
| Guru | `siti.nurhaliza@smkpesat.id` | `password` |
| Siswa | `andi.pratama@smkpesat.id` | `password` |

Ganti password default sebelum aplikasi digunakan di production.

Jika ingin menghapus seluruh data lokal dan membuat ulang database, gunakan hanya pada environment development:

```bash
php artisan migrate:fresh --seed
```

## 6. Storage dan Upload File

Buat symbolic link agar file pada `storage/app/public` dapat diakses melalui `/storage`:

```bash
php artisan storage:link
```

Upload audio BGM Global Quiz Library disimpan di:

```text
storage/app/public/audio
```

Pastikan folder storage dapat ditulis oleh user web server pada Linux:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

Nama user web server dapat berbeda, misalnya `nginx`, `apache`, atau user deploy.

### Batas upload BGM 100 MB

Aplikasi Laravel memvalidasi audio maksimal 100 MB. Web server dan PHP juga harus mengizinkan ukuran tersebut. Atur minimal:

```ini
upload_max_filesize = 100M
post_max_size = 110M
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
```

Untuk Nginx, tambahkan pada blok `server`:

```nginx
client_max_body_size 110M;
```

Untuk Apache, pastikan `LimitRequestBody` tidak membatasi request. File `public/.htaccess` sudah berisi konfigurasi `php_value`, tetapi directive tersebut hanya bekerja pada Apache mod_php. Jika menggunakan PHP-FPM dan muncul HTTP 500, hapus/comment directive `php_value` dari `.htaccess` dan atur nilai pada `php.ini` atau pool PHP-FPM.

Setelah mengubah konfigurasi PHP, restart service:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

Versi PHP-FPM dapat berbeda, misalnya `php8.4-fpm`.

Pada Laragon, buka **Menu > PHP > php.ini**, ubah nilai di atas, simpan, lalu pilih **Menu > Restart All**.

Pada macOS Homebrew, lokasi PHP dapat dilihat dengan:

```bash
php --ini
```

## 7. Menjalankan Lokal

### Cara manual, direkomendasikan untuk debugging

Buka beberapa terminal pada folder proyek.

Terminal 1 — Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2 — Vite:

```bash
npm run dev
```

Terminal 3 — Queue worker:

```bash
php artisan queue:work --tries=3
```

Terminal 4 — Reverb WebSocket:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Buka aplikasi di `http://localhost:8000`.

Reverb wajib berjalan agar fitur berikut bekerja real-time:

- Lobby siswa masuk ke Host.
- Start, Pause, dan End Game.
- Live leaderboard Host.
- Update skor saat siswa menjawab.

Queue worker tetap disarankan berjalan meskipun event real-time menggunakan broadcast langsung.

### Menjalankan dengan script Composer

Proyek menyediakan script development:

```bash
composer run dev
```

Jika script tersebut tidak tersedia atau gagal pada sistem tertentu, gunakan empat terminal manual seperti di atas.

## 8. Instalasi dengan Laragon Windows

1. Instal Laragon Full dengan PHP 8.3+, Composer, Node.js, dan Git.
2. Clone/copy proyek ke:

   ```text
   C:\laragon\www\lms-smkpesat
   ```

3. Buka Terminal Laragon dan jalankan:

   ```powershell
   cd C:\laragon\www\lms-smkpesat
   composer install
   npm install
   Copy-Item .env.example .env
   php artisan key:generate
   New-Item -ItemType File -Force database\database.sqlite
   php artisan migrate --seed
   php artisan storage:link
   npm run build
   ```

4. Atur `APP_URL` sesuai URL Laragon, misalnya `http://lms-smkpesat.test`, atau gunakan `http://localhost:8000` jika menjalankan `php artisan serve`.
5. Pastikan `REVERB_HOST` dan `VITE_REVERB_HOST` sesuai host yang dibuka browser.
6. Jalankan Reverb dari Terminal Laragon:

   ```powershell
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```

7. Untuk development frontend, jalankan `npm run dev`. Untuk hasil production, jalankan `npm run build`.

## 9. Instalasi macOS

Dengan Homebrew:

```bash
brew install php composer node
```

Kemudian:

```bash
cd /path/ke/lms-smkpesat
composer install
npm install
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm run build
```

Jalankan server Laravel, Vite, queue, dan Reverb menggunakan empat terminal seperti pada bagian [Menjalankan Lokal](#7-menjalankan-lokal).

## 10. Deployment Linux Production

### Build dan optimasi aplikasi

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Document root web server harus mengarah ke folder `public`, bukan root repository.

Contoh Nginx ringkas:

```nginx
server {
    listen 80;
    server_name lms.example.id;
    root /var/www/lms-smkpesat/public;
    index index.php;
    client_max_body_size 110M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Aktifkan HTTPS sebelum production agar login, cookie, API token, dan WebSocket aman.

### Queue worker dengan Supervisor

Contoh `/etc/supervisor/conf.d/lms-worker.conf`:

```ini
[program:lms-worker]
command=php /var/www/lms-smkpesat/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/var/www/lms-smkpesat
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/lms-worker.log
```

Aktifkan:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart lms-worker
```

### Reverb dengan Supervisor

Contoh `/etc/supervisor/conf.d/lms-reverb.conf`:

```ini
[program:lms-reverb]
command=php /var/www/lms-smkpesat/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/lms-smkpesat
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/lms-reverb.log
```

Jika memakai Nginx reverse proxy untuk WebSocket, teruskan request `/app` ke `127.0.0.1:8080` dan gunakan `wss://` pada environment production.

### Scheduler

Jika nanti ada task terjadwal, jalankan scheduler setiap menit melalui cron:

```cron
* * * * * cd /var/www/lms-smkpesat && php artisan schedule:run >> /dev/null 2>&1
```

## 11. Sinkronisasi Profiling API

Isi `PROFILING_API_KEY` pada `.env`, lalu bersihkan cache konfigurasi:

```bash
php artisan optimize:clear
```

Jalankan sinkronisasi:

```bash
php artisan sync:profiling-data
```

Untuk sinkronisasi fresh dari panel Admin atau terminal:

```bash
php artisan sync:profiling-data --fresh
```

Endpoint guru yang belum tersedia diperlakukan sebagai warning dan tidak menghentikan sinkronisasi siswa. Pastikan server dapat mengakses `https://dev-profiling.smkpesat.id` melalui HTTPS.

## 12. Penggunaan Fitur Utama

1. Login sebagai Admin dan pastikan akun Guru serta Siswa tersedia.
2. Admin dapat membuka **Atur Kuis** untuk mengunggah MP3 ke Global Quiz Audio Library.
3. Guru membuat kelas, tugas, dan Game Kuis.
4. Untuk Live Game, Guru membuka Host dan membagikan PIN/QR kepada siswa.
5. Jalankan Reverb agar lobby, start game, skor, dan end game tersinkronisasi real-time.
6. Untuk Assign, Guru memilih mode Assign, tenggat waktu, batas percobaan, tipe soal, dan opsi pengacakan.
7. Siswa dapat mengerjakan soal kompleks dengan memilih lebih dari satu jawaban.
8. Admin dapat membuka API Documentation untuk token API dan panduan integrasi.

## 13. Pemeriksaan Setelah Instalasi

Jalankan perintah berikut:

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan storage:link
php artisan test
npm run build
```

Periksa juga:

- `http://localhost:8000/up` menampilkan status aplikasi sehat.
- File BGM dapat diputar melalui URL `/storage/audio/...`.
- Console browser tidak menampilkan error Echo/Reverb.
- Reverb menampilkan koneksi ketika Host dan Player membuka kuis.
- Queue worker tidak berhenti dengan error.

## 14. Troubleshooting

### `Class not found` atau dependency error

```bash
composer dump-autoload
php artisan optimize:clear
```

### Tampilan frontend tidak berubah

Development:

```bash
npm run dev
```

Production:

```bash
npm run build
php artisan optimize:clear
```

### `SQLSTATE: database is locked` pada SQLite

Pastikan hanya satu proses migration aktif, hentikan worker sementara, lalu jalankan migration kembali. Untuk traffic production tinggi, pertimbangkan MySQL/MariaDB.

### Upload BGM gagal atau `The audio failed to upload`

Periksa:

```bash
php -r "echo ini_get('upload_max_filesize').' / '.ini_get('post_max_size').PHP_EOL;"
```

Nilai efektif harus minimal `100M / 110M`. Periksa juga `client_max_body_size` Nginx, permission `storage`, dan symbolic link `public/storage`.

### Reverb tidak tersambung

1. Pastikan `php artisan reverb:start --host=0.0.0.0 --port=8080` berjalan.
2. Pastikan `REVERB_APP_KEY` sama dengan `VITE_REVERB_APP_KEY` setelah build.
3. Jalankan `php artisan optimize:clear`, lalu ulangi `npm run build`.
4. Untuk production HTTPS, gunakan `REVERB_SCHEME=https`, `wssPort=443`, dan reverse proxy WebSocket.
5. Pastikan firewall membuka port WebSocket atau gunakan proxy melalui domain HTTPS.

### Profiling API gagal

Periksa `PROFILING_API_URL`, `PROFILING_API_KEY`, dan header `X-API-KEY` pada `.env`. Lihat log:

```bash
tail -f storage/logs/laravel.log
```

Windows PowerShell:

```powershell
Get-Content storage\logs\laravel.log -Wait
```

## 15. Keamanan Production

- Jangan commit `.env`, API key, token, atau password ke repository.
- Set `APP_DEBUG=false`.
- Gunakan HTTPS.
- Ganti password akun seed.
- Batasi akses port Reverb dari publik jika menggunakan reverse proxy.
- Jalankan web server dengan user non-root.
- Backup database dan folder `storage/app/public` secara berkala.
- Setelah mengubah `.env`, jalankan `php artisan optimize:clear` lalu restart worker/Reverb.
