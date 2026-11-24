-- Schema Database Serah Terima Shift Operasional (OMIT)
CREATE DATABASE IF NOT EXISTS `db_serah_terima` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_serah_terima`;

-- Tabel Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
  `jabatan` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Master Lokasi
CREATE TABLE IF NOT EXISTS `master_lokasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lokasi` VARCHAR(100) NOT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Master Shift
CREATE TABLE IF NOT EXISTS `master_shift` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_shift` VARCHAR(50) NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `keterangan` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Laporan Serah Terima
CREATE TABLE IF NOT EXISTS `laporan_serah_terima` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nomor_laporan` VARCHAR(50) NOT NULL UNIQUE,
  `tanggal` DATE NOT NULL,
  `shift_id` INT NOT NULL,
  `lokasi_id` INT NOT NULL,
  `jam_serah_terima` TIME NOT NULL,
  `kondisi_operasional` ENUM('Normal', 'Perlu Pantauan', 'Ada Gangguan') NOT NULL DEFAULT 'Normal',
  `petugas_menyerahkan_id` INT NOT NULL,
  `petugas_menerima_id` INT DEFAULT NULL,
  `catatan_penting` TEXT DEFAULT NULL,
  
  -- Dokumentasi Foto (3 Foto)
  `foto_1` VARCHAR(255) DEFAULT NULL,
  `ket_foto_1` VARCHAR(255) DEFAULT NULL,
  `foto_2` VARCHAR(255) DEFAULT NULL,
  `ket_foto_2` VARCHAR(255) DEFAULT NULL,
  `foto_3` VARCHAR(255) DEFAULT NULL,
  `ket_foto_3` VARCHAR(255) DEFAULT NULL,
  
  -- Konfirmasi Checklist (Ya, Belum, N/A)
  `check_lisan` ENUM('Ya', 'Belum', 'N/A') DEFAULT 'Ya',
  `check_tiket` ENUM('Ya', 'Belum', 'N/A') DEFAULT 'Ya',
  `check_risiko` ENUM('Ya', 'Belum', 'N/A') DEFAULT 'Ya',
  `check_kunci` ENUM('Ya', 'Belum', 'N/A') DEFAULT 'Ya',
  
  -- Tanda Tangan Digital (Base64 data URL) & Status Validasi
  `ttd_menyerahkan` LONGTEXT DEFAULT NULL,
  `ttd_menerima` LONGTEXT DEFAULT NULL,
  `status_validasi` ENUM('DRAFT', 'MENUNGGU VALIDASI', 'TERVALIDASI') NOT NULL DEFAULT 'MENUNGGU VALIDASI',
  `waktu_divalidasi` DATETIME DEFAULT NULL,
  
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`shift_id`) REFERENCES `master_shift`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`lokasi_id`) REFERENCES `master_lokasi`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`petugas_menyerahkan_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`petugas_menerima_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Detail Pekerjaan / Tindak Lanjut
CREATE TABLE IF NOT EXISTS `laporan_pekerjaan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `laporan_id` INT NOT NULL,
  `no_tiket` VARCHAR(50) DEFAULT NULL,
  `pekerjaan_masalah` TEXT NOT NULL,
  `prioritas` ENUM('Tinggi / Kritis', 'Sedang', 'Rendah') NOT NULL DEFAULT 'Sedang',
  `status` ENUM('Berjalan', 'Belum Mulai', 'Tertunda', 'Selesai') NOT NULL DEFAULT 'Berjalan',
  `kondisi_tindak_lanjut` TEXT NOT NULL,
  `pic` VARCHAR(100) NOT NULL,
  `target_selesai` VARCHAR(100) DEFAULT NULL,
  `urutan` INT DEFAULT 0,
  
  FOREIGN KEY (`laporan_id`) REFERENCES `laporan_serah_terima`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
