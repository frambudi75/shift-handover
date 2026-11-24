# Panduan Pengguna Lengkap (User Manual & SOP)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Akses Sistem & Login
1. Buka browser dan arahkan ke alamat sistem:
   - **Lingkungan Docker**: `http://localhost:2032`
   - **Lingkungan XAMPP**: `http://localhost/serah-terima/`
2. Masukkan username dan password resmi:
   - **Superadmin**: `superadmin` / `password`
   - **Admin Operasional**: `admin` / `password`
   - **Operator Lapangan**: `andi` / `password`

---

### 2. Panduan Pembuatan Serah Terima Shift Baru
1. Klik tombol **Buat Serah Terima** pada bilah navigasi atas (desktop) atau ikon `+` pada bilah navigasi bawah (mobile).
2. **Bagian 1: Data Shift**:
   - Pilih jadwal shift yang selesai bertugas.
   - Jam serah terima otomatis terisi sesuai jadwal resmi shift.
   - Pilih lokasi/unit kerja operasional.
   - Tentukan **Kondisi Operasional Umum** (*Normal*, *Perlu Pantauan*, *Ada Gangguan*, atau kondisi kustom lainnya).
   - Petugas menyerahkan otomatis terisi sesuai nama akun yang sedang aktif.
   - Pilih rekan kerja yang akan menerima tugas (shift baru).
3. **Bagian 2: Rincian Pekerjaan & Masalah**:
   - Masukkan nomor tiket, rincian masalah, pilih tingkat prioritas, status progres, kondisi/tindak lanjut, PIC, dan target waktu selesai.
   - Jika terdapat tiket dari shift sebelumnya, gunakan tombol **Oper ke Shift Baru** untuk menyalin data secara instan.
4. **Bagian 3: Bukti Foto Lapangan**:
   - Unggah hingga 3 foto kondisi nyata di lapangan (area peralatan, panel instrumen, serah terima fisik).
   - Berikan keterangan singkat pada masing-masing foto.
5. **Bagian 4: Checklist & Tanda Tangan**:
   - Ceklis 4 pertanyaan konfirmasi operasional.
   - Pilih salah satu dari 3 metode tanda tangan:
     - **Canvas Pad**: Goreskan tanda tangan langsung di layar sentuh atau dengan mouse.
     - **Barcode QR Code**: Menggunakan kode verifikasi digital terenkripsi.
     - **Upload TTD Basah**: Unggah berkas gambar tanda tangan asli.
   - Klik **Simpan & Ajukan ke Shift Penerima**.

---

### 3. Panduan Persetujuan (Approval) oleh Shift Baru
1. Personil shift penerima login ke akun masing-masing.
2. Di Dashboard, klik kartu laporan yang bertanda status **MENUNGGU VALIDASI**.
3. Periksa seluruh rincian pekerjaan dan kondisi lapangan bersama personil shift lama.
4. Gulir ke bagian bawah pada kartu **Persetujuan & Tanda Tangan Penerima Shift Baru**:
   - Pilih metode tanda tangan (*Canvas*, *Barcode QR*, atau *Upload TTD Basah*).
   - Berikan catatan pengesahan jika diperlukan.
   - Klik tombol **Setujui & Sahkan Serah Terima (Approve)**.
5. Status laporan seketika menjadi **TERVALIDASI (LENGKAP)** dan dokumen resmi siap dicetak.

---

### 4. Cetak PDF Resmi Standar OMIT
1. Pada halaman detail laporan yang sudah divalidasi, klik tombol **Cetak PDF Standar OMIT**.
2. Dokumen akan terbuka dalam format siap cetak A4 dengan tata letak header navy, logo instansi, tabel 10 baris pekerjaan, 3 foto dokumentasi, dan tanda tangan kedua belah pihak yang sah.

---

### 5. Panduan Administrator: Master Data, Backup & Optimasi
- **Master Kondisi Operasional** (`master/kondisi.php`): Menambah status kondisi operasional baru, mengatur warna badge, dan menetapkan opsi default.
- **Pusat Backup Sistem** (`master/backup.php`):
  - Klik **Backup Database (.sql)** untuk mengunduh dump database langsung.
  - Klik **Backup Full Sistem (.zip)** untuk mencadangkan seluruh source code, database, dan file foto.
  - Klik **Flush Cache** untuk mengosongkan cache Redis seketika.
  - Klik **Optimalkan Sekarang** untuk menjalankan defragmentasi database dan pembersihan berkas orphan.
