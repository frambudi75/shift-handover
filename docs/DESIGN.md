# Panduan Desain Sistem & Antarmuka (NoteFlow Design System)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Dokumen ini mendefinisikan filosofi desain visual, palet warna, tipografi, dan standar komponen antarmuka yang digunakan di seluruh aplikasi.

---

### 1. Filosofi Desain: NoteFlow
Aplikasi mengusung konsep **NoteFlow**, memadukan elemen kartu kerja modern (*elevated dashboard cards*) dengan estetika visual yang bersih, minimalis, dan sangat mudah dipahami oleh pekerja shift industri tanpa distraksi.

- **Karakter Visual**: Sudut membulat lembut (`rounded-2xl` / `rounded-[26px]`), bayangan halus (*soft ambient shadow*), pemisahan hierarki informasi yang jelas.
- **Responsivitas Adaptif**: Elemen otomatis menyesuaikan orientasi layar (desktop, tablet, hingga smartphone).

---

### 2. Palet Warna Resmi (Brand Tokens)

| Peran Warna | Kode HEX | Tailwind Class | Penggunaan Utama |
| :--- | :--- | :--- | :--- |
| **Primary Purple** | `#6558f5` | `text-[#6558f5]` / `bg-[#6558f5]` | Warna identitas utama, tombol aksi, sidebar aktif, dan link |
| **Success Emerald** | `#68cfa3` | `text-[#68cfa3]` / `bg-emerald-500` | Status tervalidasi, kondisi Normal, tiket selesai |
| **Warning Amber** | `#f5a623` | `text-amber-500` / `bg-amber-500` | Kondisi Perlu Pantauan, prioritas Sedang, tiket berjalan |
| **Danger Rose** | `#e0536c` | `text-[#e0536c]` / `bg-rose-500` | Kondisi Ada Gangguan, prioritas Tinggi / Kritis, tombol hapus |
| **Neutral Slate** | `#181b2c` | `text-[#181b2c]` / `dark:text-white` | Tipografi utama, teks judul |
| **Light Canvas** | `#f5f5fa` | `bg-[#f5f5fa]` | Latar belakang mode terang |
| **Dark Canvas** | `#121421` | `dark:bg-[#121421]` | Latar belakang mode gelap |
| **Dark Card** | `#1b1e30` | `dark:bg-[#1b1e30]` | Latar belakang kartu pada mode gelap |

---

### 3. Tipografi & Ikonografi
- **Font Utama**: *Plus Jakarta Sans* / *Inter* dengan variasi ketebalan `font-medium`, `font-bold`, `font-extrabold`, dan `font-black`.
- **Ikonografi**: *Phosphor Icons* (`ph-fill`, `ph-bold`, `ph`). Menjamin konsistensi visual ikon di seluruh tombol, badge status, dan menu sidebar.

---

### 4. Pola Komponen Khusus
1. **Docked Sidebar (Desktop)**:
   - Sidebar ramping vertikal di tepi kiri layar dengan efek lengkungan *concave SVG curve* khas NoteFlow.
   - Tombol menu dilengkapi *tooltip hover* instan.
2. **Floating Bottom Navigation (Mobile)**:
   - Bilah navigasi melayang di bagian bawah layar ponsel dengan latar belakang kaca blur (`backdrop-blur-md`).
   - Menyediakan 5 akses cepat: Dashboard, Buat Serah Terima (+), Riwayat Laporan, Rekap Harian, dan Menu Navigasi Lengkap (Drawer).
3. **Single Mobile Dark Mode Toggle**:
   - Tombol sirkuler 32px di header mobile dengan ikon bulan/matahari yang bertransisi dinamis tanpa merusak susunan header.
