# Alur Kerja Sistem (Flowchart & Business Process)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Diagram Alur Serah Terima & Persetujuan (Handover Workflow)

```mermaid
flowchart TD
    Start([Personil Shift Lama Selesai Bertugas]) --> BukaForm[Buka Menu Buat Serah Terima Baru]
    BukaForm --> IsiBag1[Isi Bagian 1: Waktu, Lokasi, Pilihan Kondisi Operasional]
    IsiBag1 --> CekTiketLama{Ada Tiket Menggantung / Kritis?}
    CekTiketLama -- Ya --> AutoImport[Sistem Menampilkan Opsi 'Oper ke Shift Baru']
    AutoImport --> IsiBag2[Isi & Lengkapi Rincian Pekerjaan & Tindak Lanjut]
    CekTiketLama -- Tidak --> IsiBag2
    
    IsiBag2 --> UploadFoto[Unggah 3 Foto Dokumentasi Lapangan + Keterangan]
    UploadFoto --> Checklist[Ceklis 4 Butir Konfirmasi Operasional]
    Checklist --> PilihTTD1[Pilih Metode TTD: Canvas / Barcode QR / Upload Basah]
    PilihTTD1 --> Simpan[Simpan Berita Acara: Status MENUNGGU VALIDASI]
    
    Simpan --> Notifikasi[Notifikasi Masuk ke Dashboard, Email SMTP & Discord]
    Notifikasi --> BukaPenerima[Personil Shift Baru Membuka Detail Laporan]
    BukaPenerima --> ReviewLapangan[Verifikasi Fisik Lapangan & Kesesuaian Catatan]
    
    ReviewLapangan --> ApprovalBox[Buka Kotak Persetujuan Penerima]
    ApprovalBox --> PilihTTD2[Tandatangani Penerimaan: Canvas / Barcode QR / Basah]
    PilihTTD2 --> Sahkan[Klik Setujui & Sahkan]
    
    Sahkan --> ValidatedStatus[Status Laporan Berubah: TERVALIDASI LENGKAP]
    ValidatedStatus --> GeneratePDF[Cetak PDF Standar OMIT Resmi A4]
    GeneratePDF --> Done([Selesai: Shift Baru Siap Beroperasi])
```

---

### 2. Diagram Alur Eskalasi Tiket Pekerjaan (Escalation Engine)

```mermaid
flowchart LR
    Shift1[Shift Pagi: Tiket TK-0192 Dibuat Status: Berjalan] --> Handover1[Serah Terima ke Shift Siang]
    Handover1 --> Unfinished{Tiket Selesai?}
    Unfinished -- Belum --> CarryOver[Otomatis Muncul di Tindak Lanjut Aktif Shift Siang]
    CarryOver --> Shift2[Shift Siang Menangani Masalah]
    Shift2 --> Handover2[Serah Terima ke Shift Malam]
    Handover2 --> Unfinished2{Tiket Selesai?}
    Unfinished2 -- Belum --> CarryOver2[Otomatis Terbawa ke Shift Malam]
    Unfinished2 -- Ya: Status Selesai --> Resolved[Tiket Selesai: Otomatis Hilang dari Daftar Kritis Aktif]
```

---

### 3. Diagram Alur Caching & Kinerja (Redis Cache Flow)

```mermaid
flowchart TD
    Req[Permintaan Halaman Web dari User] --> CheckCache{Data Tersedia di Redis?}
    CheckCache -- Hit (Ya) --> ReturnCache[Kembalikan Data dari Redis: Waktu Respon < 20ms]
    CheckCache -- Miss (Tidak) --> QueryDB[Kueri ke MySQL Database]
    QueryDB --> StoreCache[Simpan Hasil Kueri ke Redis dengan TTL]
    StoreCache --> ReturnData[Kirimkan Halaman ke Browser User]
    
    AdminUpdate[Admin Mengubah Master / Pengaturan] --> Invalidate[Panggil cache_delete]
    Invalidate --> FlushKey[Kunci Cache Dihapus: Data Baru Segera Terbaca]
```
