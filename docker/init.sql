-- ==============================================================================
-- OMIT Shift Handover - Clean Database Schema & Initial Data for Docker
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `db_serah_terima` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_serah_terima`;

-- 1. Table: users (Master Akun Pengguna)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `no_wa` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','user') NOT NULL DEFAULT 'user',
  `jabatan` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `ttd_type` enum('barcode','upload','canvas') NOT NULL DEFAULT 'barcode',
  `ttd_file` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role_status` (`role`,`status`),
  KEY `idx_users_status_nama` (`status`,`nama_lengkap`),
  KEY `idx_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES
('1', 'Administrator Operasional', 'admin', 'admin@operasional.local', NULL, '$2y$12$cSAK9Z7JRxlW9bEJslmul.PtTZMBHnrnQRcPpJyGrKOp4vpdkoDjy', 'admin', 'System Supervisor', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('2', 'Andi Pratama', 'andi', 'andi@operasional.local', NULL, '$2y$12$St16qnflOhHEDkcF/E1rBuR9A7m99lcA4gBn3aKxBNbTyzBnb4Wma', 'user', 'Control Room Senior Operator', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('3', 'Budi Santoso', 'budi', 'budi@operasional.local', NULL, '$2y$10$hab49APB8QvJR8E9Cz1BZuaDIVV93PIZEQxnt8vtRQcAqL.jAbQTK', 'user', 'Production Shift Leader', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:19:16'),
('4', 'Citra Lestari', 'citra', 'citra@operasional.local', NULL, '$2y$12$LYxoYNUWnzOZrDrShigNrO/pDn4zaGp/bPUquYjjriDEZeV2VNwW.', 'user', 'Quality & Operations', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('5', 'Dewi Anggraini', 'dewi', 'dewi@operasional.local', NULL, '$2y$12$Yz11WM5KeiLDOh4k57NgJeFWZisv8ne6lO9.MAgTJPmlQN4nPSyy.', 'user', 'Logistics Officer', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('6', 'Eko Saputra', 'eko', 'eko@operasional.local', NULL, '$2y$12$Jlapf6Yn7bte9kLNMlWRcuZrVXC45Fxa2eomYOtlEkJoYu9XRHvPy', 'user', 'IT & Systems Tech', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('7', 'Fajar Nugroho', 'fajar', 'fajar@operasional.local', NULL, '$2y$12$ALuaC3gNmnROwtXgj8Qq6.Ry3qULezNX.HsGsc/2ZNmO3MmcXPiTO', 'user', 'Utility & Facility Tech', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:14:03', '2026-11-24 17:05:01'),
('8', 'Super Administrator', 'superadmin', 'superadmin@operasional.local', '', '$2y$12$nEGypbFePyXlbTVGMtFfsebROVvxYrxOoGISu2PvHW/Rc6oCr7sb.', 'superadmin', 'Head of Operations & Systems', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:25:10', '2026-11-24 16:31:22'),
('9', 'Operator User Demo', 'user', 'user@operasional.local', NULL, '$2y$12$Mgwm.JUIMQFxqw2gXDkc5uUzHAJqfWZyx33EyaokpE.B4OLWLaBWi', 'user', 'Field Operator', NULL, 'barcode', NULL, 'aktif', '2026-11-24 14:25:11', '2026-11-24 17:05:01');

-- 2. Table: master_shift (Master Jadwal Shift)
DROP TABLE IF EXISTS `master_shift`;
CREATE TABLE `master_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_shift` varchar(50) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `jam_serah_terima` time DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  PRIMARY KEY (`id`),
  KEY `idx_shift_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `master_shift` VALUES
('1', 'Pagi', '07:00:00', '15:00:00', '15:00:00', 'Shift 1 (07:00 - 15:00)', 'aktif'),
('2', 'Siang', '15:00:00', '23:00:00', '23:00:00', 'Shift 2 (15:00 - 23:00)', 'aktif'),
('3', 'Malam', '23:00:00', '07:00:00', '07:00:00', 'Shift 3 (23:00 - 07:00)', 'aktif'),
('4', 'Non-Shift / Lembur', '08:00:00', '17:00:00', '17:00:00', 'Reguler Operasional / Overtime', 'aktif');

-- 3. Table: master_lokasi (Master Stasiun Kerja & Unit Operasional)
DROP TABLE IF EXISTS `master_lokasi`;
CREATE TABLE `master_lokasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lokasi` varchar(100) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lokasi_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `master_lokasi` VALUES
('1', 'Control Room', 'Pusat Kendali Sistem & SCADA', 'aktif', '2026-11-24 14:14:03'),
('2', 'Area Produksi A', 'Lini Produksi Utama Unit 1', 'aktif', '2026-11-24 14:14:03'),
('3', 'Area Produksi B', 'Lini Produksi Unit 2', 'aktif', '2026-11-24 14:14:03'),
('4', 'Gudang & Logistik', 'Penyimpanan Bahan & Hasil', 'aktif', '2026-11-24 14:14:03'),
('5', 'IT & Jaringan', 'Server Room & Infrastruktur Jaringan', 'aktif', '2026-11-24 14:14:03'),
('6', 'Utility', 'Power Plant, Genset, & Kompresor', 'aktif', '2026-11-24 14:14:03');

-- 4. Table: master_kondisi (Master Status Kondisi Operasional)
DROP TABLE IF EXISTS `master_kondisi`;
CREATE TABLE `master_kondisi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kondisi` varchar(50) NOT NULL,
  `badge_color` varchar(20) NOT NULL DEFAULT 'emerald',
  `keterangan` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_kondisi` (`nama_kondisi`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `master_kondisi` VALUES
('1', 'Normal', 'emerald', 'Operasional shift berjalan stabil, aman, dan tanpa kendala signifikan.', '1', 'aktif', '1', '2026-11-24 18:59:48'),
('2', 'Perlu Pantauan', 'amber', 'Terdapat anomali minor atau parameter mesin yang memerlukan pengawasan berkala.', '0', 'aktif', '2', '2026-11-24 18:59:48'),
('3', 'Ada Gangguan', 'rose', 'Terjadi kendala operasional, gangguan mesin, atau insiden yang memerlukan penanganan.', '0', 'aktif', '3', '2026-11-24 18:59:48');

-- 5. Table: app_settings (Pengaturan Aplikasi & Konfigurasi)
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` VALUES
('app_full_name', 'Sistem Laporan Serah Terima Shift Operasional', '2026-11-24 15:43:21'),
('app_logo', '', '2026-11-24 15:43:21'),
('app_name', 'OMIT Shift Handover', '2026-11-24 15:43:21'),
('compress_enabled', '1', '2026-11-24 15:43:21'),
('compress_max_width', '1600', '2026-11-24 15:43:21'),
('compress_quality', '70', '2026-11-24 16:54:31'),
('cron_secret_key', 'f91c5ca9d00a0222988eafd1d16f4264', '2026-11-24 19:08:31'),
('discord_webhook_url', '', '2026-11-24 18:37:17'),
('last_optimized_at', '2026-11-24 19:09:42', '2026-11-24 19:09:42'),
('last_optimized_summary', '{\"last_run\":\"2026-11-24 19:09:42\",\"duration_ms\":546.98,\"tables_count\":6,\"orphan_jobs\":0,\"orphans_cleaned\":0,\"orphans_bytes\":0,\"backups_purged\":0,\"backups_bytes\":0,\"total_bytes_freed\":0,\"total_freed_format\":\"0 B\"}', '2026-11-24 19:09:42'),
('smtp_crypto', 'tls', '2026-11-24 16:03:04'),
('smtp_enabled', '1', '2026-11-24 17:25:23'),
('smtp_from_email', 'no-reply@omit.avi.id', '2026-11-24 17:25:23'),
('smtp_from_name', 'OMIT Shift Handover', '2026-11-24 16:03:04'),
('smtp_host', 'mail.avi.id', '2026-11-24 18:37:17'),
('smtp_pass', 'Aviasi2023', '2026-11-24 17:25:23'),
('smtp_port', '587', '2026-11-24 16:03:04'),
('smtp_user', 'habib@omit.avi.id', '2026-11-24 17:25:23'),
('wa_api_token', '', '2026-11-24 16:03:04'),
('wa_api_url', 'https://api.fonnte.com/send', '2026-11-24 16:03:04'),
('wa_enabled', '1', '2026-11-24 16:03:04'),
('wa_provider', 'direct', '2026-11-24 16:03:04');

-- 6. Table: laporan_serah_terima (Dokumen Berita Acara Serah Terima)
DROP TABLE IF EXISTS `laporan_serah_terima`;
CREATE TABLE `laporan_serah_terima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_laporan` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `shift_id` int(11) NOT NULL,
  `lokasi_id` int(11) NOT NULL,
  `jam_serah_terima` time NOT NULL,
  `kondisi_operasional` varchar(50) NOT NULL DEFAULT 'Normal',
  `petugas_menyerahkan_id` int(11) NOT NULL,
  `petugas_menerima_id` int(11) DEFAULT NULL,
  `catatan_penting` text DEFAULT NULL,
  `foto_1` varchar(255) DEFAULT NULL,
  `ket_foto_1` varchar(255) DEFAULT NULL,
  `foto_2` varchar(255) DEFAULT NULL,
  `ket_foto_2` varchar(255) DEFAULT NULL,
  `foto_3` varchar(255) DEFAULT NULL,
  `ket_foto_3` varchar(255) DEFAULT NULL,
  `check_lisan` enum('Ya','Belum','N/A') DEFAULT 'Ya',
  `check_tiket` enum('Ya','Belum','N/A') DEFAULT 'Ya',
  `check_risiko` enum('Ya','Belum','N/A') DEFAULT 'Ya',
  `check_kunci` enum('Ya','Belum','N/A') DEFAULT 'Ya',
  `ttd_menyerahkan` longtext DEFAULT NULL,
  `ttd_menerima` longtext DEFAULT NULL,
  `status_validasi` enum('DRAFT','MENUNGGU VALIDASI','TERVALIDASI') NOT NULL DEFAULT 'MENUNGGU VALIDASI',
  `waktu_divalidasi` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `jenis_ttd_menyerahkan` varchar(20) DEFAULT 'barcode',
  `jenis_ttd_menerima` varchar(20) DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_laporan` (`nomor_laporan`),
  KEY `petugas_menyerahkan_id` (`petugas_menyerahkan_id`),
  KEY `petugas_menerima_id` (`petugas_menerima_id`),
  KEY `idx_laporan_tgl_jam` (`tanggal`,`jam_serah_terima`),
  KEY `idx_laporan_status_val` (`status_validasi`,`tanggal`),
  KEY `idx_laporan_lokasi_tgl` (`lokasi_id`,`tanggal`),
  KEY `idx_laporan_shift_tgl` (`shift_id`,`tanggal`),
  KEY `idx_laporan_created_by` (`created_by`),
  CONSTRAINT `laporan_serah_terima_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `master_shift` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `laporan_serah_terima_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `master_lokasi` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `laporan_serah_terima_ibfk_3` FOREIGN KEY (`petugas_menyerahkan_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `laporan_serah_terima_ibfk_4` FOREIGN KEY (`petugas_menerima_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `laporan_serah_terima` VALUES
('1', 'OMIT-20261124-001', '2026-11-24', '1', '1', '15:00:00', 'Normal', '2', '3', 'Semua parameter operasional di ruang kontrol berjalan normal. Periksa suhu pendingin ruang server pukul 19:00.', NULL, NULL, NULL, NULL, NULL, NULL, 'Ya', 'Ya', 'Ya', 'Ya', NULL, NULL, 'TERVALIDASI', NULL, '2', '2026-11-24 14:14:03', '2026-11-24 14:14:03', 'barcode', NULL, NULL),
('4', 'OMIT-20261124-002', '2026-11-24', '1', '5', '20:00:00', 'Normal', '1', '8', 'aman ya', 'f1_20261124_183112_6294c543.jpeg', 'qwerghwergheh', 'f2_20261124_183112_93944f84.jpeg', 'wergefg', 'f3_20261124_183112_d3e30480.jpeg', 'dtyjhfg', 'Ya', 'Ya', 'Ya', 'Ya', 'signatures/ttd_lama_20261124_183113_af5d06.png', 'signatures/ttd_baru_20261124_183143_8ad83f.png', 'TERVALIDASI', '2026-11-24 18:31:43', '1', '2026-11-24 18:31:13', '2026-11-24 18:31:43', 'barcode', 'barcode', 'mantab'),
('5', 'OMIT-20261124-003', '2026-11-24', '1', '1', '15:00:00', 'Normal', '3', '8', 'mini pc gak stabil...', 'f1_20261124_185041_2f5fa79e.jpeg', '', 'f2_20261124_185041_12e01641.jpeg', '', 'f3_20261124_185041_25762e92.jpeg', '', 'Ya', 'Ya', 'Ya', 'Ya', NULL, 'signatures/ttd_baru_20261124_185057_3c17b0.png', 'TERVALIDASI', '2026-11-24 18:50:57', '3', '2026-11-24 18:50:41', '2026-11-24 18:50:57', 'barcode', 'barcode', ''),
('6', 'OMIT-20261124-004', '2026-11-24', '1', '1', '15:00:00', 'Normal', '8', '3', '', NULL, '', NULL, '', NULL, '', 'Ya', 'Ya', 'Ya', 'Ya', 'signatures/ttd_lama_20261124_185142_dc2516.png', 'signatures/ttd_baru_20261124_185216_bf5aee.png', 'TERVALIDASI', '2026-11-24 18:52:16', '8', '2026-11-24 18:51:42', '2026-11-24 18:52:16', 'barcode', 'barcode', '');

-- 7. Table: laporan_pekerjaan (Rincian Item Pekerjaan & Tiket)
DROP TABLE IF EXISTS `laporan_pekerjaan`;
CREATE TABLE `laporan_pekerjaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `laporan_id` int(11) NOT NULL,
  `no_tiket` varchar(50) DEFAULT NULL,
  `pekerjaan_masalah` text NOT NULL,
  `prioritas` enum('Tinggi / Kritis','Sedang','Rendah') NOT NULL DEFAULT 'Sedang',
  `status` enum('Berjalan','Belum Mulai','Tertunda','Selesai') NOT NULL DEFAULT 'Berjalan',
  `kondisi_tindak_lanjut` text NOT NULL,
  `pic` varchar(100) NOT NULL,
  `target_selesai` varchar(100) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pekerjaan_status_prio` (`status`,`prioritas`),
  KEY `idx_pekerjaan_laporan_status` (`laporan_id`,`status`),
  KEY `idx_pekerjaan_no_tiket` (`no_tiket`),
  CONSTRAINT `laporan_pekerjaan_ibfk_1` FOREIGN KEY (`laporan_id`) REFERENCES `laporan_serah_terima` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `laporan_pekerjaan` VALUES
('1', '1', 'TK-0192', 'Kalibrasi Sensor Tekanan Pompa 2B', 'Tinggi / Kritis', 'Selesai', 'Sudah dibersihkan, menunggu pembacaan stabil selama 30 menit oleh teknisi shift siang.', 'Andi Pratama', '17:00 WIB', '1'),
('2', '1', 'TK-0193', 'Pengecekan Rutin Log Server SCADA', 'Sedang', 'Selesai', 'Backup database log harian selesai tanpa error.', 'Eko Saputra', '14:30 WIB', '2'),
('3', '1', 'TK-0194', 'Inspeksi Suhu Ruang Genset Cadangan', 'Rendah', 'Selesai', 'Jadwal inspeksi berkala shift siang/malam.', 'Fajar Nugroho', '20:00 WIB', '3'),
('5', '4', 'TK-0192', 'Kalibrasi Sensor Tekanan Pompa 2B', 'Tinggi / Kritis', 'Selesai', 'Sudah dibersihkan, menunggu pembacaan stabil selama 30 menit oleh teknisi shift siang.', 'Andi Pratama', '17:00 WIB', '1'),
('6', '4', 'TK-0194', 'Inspeksi Suhu Ruang Genset Cadangan', 'Rendah', 'Selesai', 'Jadwal inspeksi berkala shift siang/malam.', 'Fajar Nugroho', '20:00 WIB', '2'),
('7', '5', 'TK-0192', 'Kalibrasi Sensor Tekanan Pompa 2B', 'Tinggi / Kritis', 'Selesai', 'Sudah dibersihkan, menunggu pembacaan stabil selama 30 menit oleh teknisi shift siang.', 'Andi Pratama', '17:00 WIB', '1'),
('8', '5', 'TK-0194', 'Inspeksi Suhu Ruang Genset Cadangan', 'Rendah', 'Selesai', 'Jadwal inspeksi berkala shift siang/malam.', 'Fajar Nugroho', '20:00 WIB', '2'),
('9', '6', 'TK-0192', 'Kalibrasi Sensor Tekanan Pompa 2B', 'Tinggi / Kritis', 'Selesai', 'Sudah dibersihkan, menunggu pembacaan stabil selama 30 menit oleh teknisi shift siang.', 'Andi Pratama', '17:00 WIB', '1'),
('10', '6', 'TK-0194', 'Inspeksi Suhu Ruang Genset Cadangan', 'Rendah', 'Selesai', 'Jadwal inspeksi berkala shift siang/malam.', 'Fajar Nugroho', '20:00 WIB', '2');

SET FOREIGN_KEY_CHECKS = 1;
