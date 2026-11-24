# Dokumentasi Aturan Bisnis & Validasi (Business Rules)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Aturan Validasi Pengisian Serah Terima
1. **Pola Nomor Dokumen Laporan**:
   - Dihasilkan secara otomatis oleh sistem dengan pola berurutan harian: `OMIT-YYYYMMDD-XXX` (contoh: `OMIT-20261124-001`).
   - Nomor laporan dijamin unik secara kriptografis dan tidak dapat diduplikasi (*unique key constraint*).
2. **Kondisi Operasional Umum Dinamis**:
   - Wajib dipilih dari daftar kondisi operasional yang aktif di database (`master_kondisi`).
   - Opsi default (*pre-selected*) ditentukan oleh konfigurasi `is_default = 1`.
   - Warna badge visual (*Emerald, Amber, Rose, Indigo, Purple, Slate*) otomatis menyesuaikan pengaturan master.
3. **Kalkulasi Metrik Integritas**:
   - `Pekerjaan Aktif`: Dihitung dari seluruh tiket dengan status selain `Selesai` (`Berjalan`, `Belum Mulai`, `Tertunda`).
   - `Pekerjaan Kritis / Butuh Eskalasi`: Dihitung dari pekerjaan dengan prioritas `Tinggi / Kritis` yang status terakhirnya belum selesai.
4. **Dokumentasi Foto**:
   - Format yang diterima: `JPG`, `JPEG`, `PNG`, `WEBP`.
   - Batas ukuran upload: Maksimal 15 MB per foto, otomatis dikompresi ke resolusi optimal (80% quality, max width 1600px).
   - Jika foto tidak diunggah, pada hasil cetak PDF akan menampilkan kotak penampung (*placeholder*) bertuliskan `[Tempel / Insert Foto di sini]`.

---

### 2. Aturan Handover & Persetujuan Dua Belah Pihak
1. **Dua Tahap Pengesahan Bertingkat**:
   - **Tahap 1 (Shift Lama)**: Personil menyerahkan menandatangani laporan saat pembuatan. Status menjadi `MENUNGGU VALIDASI`.
   - **Tahap 2 (Shift Baru)**: Personil penerima wajib memeriksa fisik lapangan dan memberikan tanda tangan persetujuan. Status berubah menjadi `TERVALIDASI (LENGKAP)`.
2. **Integritas Rekam Jejak Audit**:
   - Petugas menyerahkan otomatis terkunci ke akun user yang sedang login untuk mencegah manipulasi identitas pembuat.
   - Laporan yang telah berstatus `TERVALIDASI` tidak dapat diubah oleh operator lapangan demi menjaga integritas data audit operasional.

---

### 3. Aturan Eskalasi & Tiket Berkesinambungan
1. Tiket pekerjaan yang memiliki nomor tiket (`no_tiket`) dan belum berstatus `Selesai` akan selalu muncul pada widget **Tindak Lanjut Aktif** di dashboard.
2. Ketika shift selanjutnya menangani dan menandai status tiket tersebut menjadi `Selesai`, tiket tersebut otomatis **hilang dari daftar aktif/kritis** pada shift-shift berikutnya.

---

### 4. Aturan Pemeliharaan & Retensi Cadangan
1. Berkas cadangan otomatis dirotasi sesuai konfigurasi retensi hari (default: 14 hari). Berkas yang melebihi batas waktu akan dibersihkan secara otomatis.
2. Proses `OPTIMIZE TABLE` dan pembersihan file orphan dijalankan secara aman di latar belakang tanpa mengunci tabel operasional (*non-blocking*).
