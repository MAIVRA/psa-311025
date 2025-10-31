-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 31, 2025 at 11:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pranatasuperapps`
--

-- --------------------------------------------------------

--
-- Table structure for table `board_members`
--

CREATE TABLE `board_members` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `deed_id_pengangkatan` int(11) DEFAULT NULL COMMENT 'FK ke tabel deeds.id (Akta Pengangkatan)',
  `nama_lengkap` varchar(255) NOT NULL,
  `no_ktp` varchar(50) DEFAULT NULL,
  `file_ktp_path` varchar(255) DEFAULT NULL COMMENT 'Path ke file KTP/Identitas',
  `npwp` varchar(50) DEFAULT NULL,
  `file_npwp_path` varchar(255) DEFAULT NULL COMMENT 'Path ke file NPWP',
  `alamat` text DEFAULT NULL,
  `telepon` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `jabatan` enum('Komisaris Utama','Komisaris','Direktur Utama','Direktur') NOT NULL,
  `masa_jabatan_mulai` date DEFAULT NULL,
  `masa_jabatan_akhir` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collective_leave`
--

CREATE TABLE `collective_leave` (
  `id` int(11) NOT NULL,
  `tanggal_cuti` date NOT NULL,
  `tahun` year(4) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `proposed_by_id` int(11) NOT NULL,
  `processed_by_id` int(11) DEFAULT NULL COMMENT 'ID Direktur yg Approve/Reject',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collective_leave`
--

INSERT INTO `collective_leave` (`id`, `tanggal_cuti`, `tahun`, `keterangan`, `status`, `proposed_by_id`, `processed_by_id`, `created_at`, `updated_at`) VALUES
(1, '2025-12-26', '2025', 'Cuti Bersama Natal', 'Approved', 8, 6, '2025-10-30 11:32:38', '2025-10-30 11:37:40');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `nama_perusahaan` varchar(255) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL COMMENT 'Path relatif ke file logo perusahaan',
  `tanggal_pendirian` date DEFAULT NULL,
  `akta_pendirian` varchar(255) DEFAULT NULL COMMENT 'Nomor Akta Pendirian',
  `tanggal_akta_pendirian` date DEFAULT NULL COMMENT 'Tanggal Akta Pendirian',
  `sk_ahu_pendirian` varchar(255) DEFAULT NULL,
  `tanggal_sk_ahu_pendirian` date DEFAULT NULL COMMENT 'Tanggal SK AHU Pendirian',
  `notaris_pendirian` varchar(255) DEFAULT NULL COMMENT 'Nama Notaris Akta Pendirian',
  `domisili_notaris_pendirian` varchar(255) DEFAULT NULL COMMENT 'Domisili Notaris Akta Pendirian',
  `id_akta_terakhir` int(11) UNSIGNED DEFAULT NULL COMMENT 'FK ke tabel deeds.id',
  `modal_dasar` bigint(20) DEFAULT 0,
  `modal_disetor` bigint(20) DEFAULT 0 COMMENT 'Akan di-update dari total tabel shareholders',
  `nilai_nominal_saham` bigint(20) DEFAULT 0,
  `tempat_kedudukan` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL COMMENT 'Alamat Lengkap Perusahaan',
  `nib` varchar(13) DEFAULT NULL COMMENT 'Nomor Induk Berusaha (13 digit)',
  `npwp` varchar(20) DEFAULT NULL COMMENT 'NPWP Perusahaan (format bisa bervariasi)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `nama_perusahaan`, `logo_path`, `tanggal_pendirian`, `akta_pendirian`, `tanggal_akta_pendirian`, `sk_ahu_pendirian`, `tanggal_sk_ahu_pendirian`, `notaris_pendirian`, `domisili_notaris_pendirian`, `id_akta_terakhir`, `modal_dasar`, `modal_disetor`, `nilai_nominal_saham`, `tempat_kedudukan`, `alamat`, `nib`, `npwp`, `created_at`) VALUES
(1, 'PT PUTRA NATUR UTAMA', 'uploads/logos/logo_6901d5a45ed294.59300064.png', '2016-05-30', '154', '2016-05-30', 'AHU-0027208.AH.01.01.TAHUN 2016', '2016-06-03', 'SITARESMI PUSPADEWI SUBIANTO, SH.,M.KN.', 'Surabaya', 4, 20000000000, 0, 1000000, 'Surabaya', 'Jl. Raya Arjuna No. 40-42, RT 003/ RW 006, Kelurahan/Desa Sawahan, Kecamatan Sawahan, Kota Surabaya, Jawa Timur', '8120008851161', '76.335.061.8-613.000', '2025-10-29 08:51:48');

-- --------------------------------------------------------

--
-- Table structure for table `company_kbli`
--

CREATE TABLE `company_kbli` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `kode_kbli` varchar(10) NOT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_licenses`
--

CREATE TABLE `company_licenses` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nama_izin` varchar(255) NOT NULL,
  `nomor_izin` varchar(255) DEFAULT NULL,
  `tanggal_izin` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `penerbit_izin` varchar(255) DEFAULT NULL,
  `tanggal_expired` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deeds`
--

CREATE TABLE `deeds` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) NOT NULL,
  `nomor_akta` varchar(100) NOT NULL,
  `tanggal_akta` date NOT NULL,
  `nama_notaris` varchar(255) NOT NULL,
  `domisili_notaris` varchar(255) DEFAULT NULL,
  `nomor_sk_ahu` varchar(100) DEFAULT NULL,
  `tanggal_sk_ahu` date DEFAULT NULL,
  `isi_akta_summary` text DEFAULT NULL,
  `tipe_akta` enum('Pendirian','Perubahan') DEFAULT NULL COMMENT 'Jenis akta: Pendirian atau Perubahan',
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deeds`
--

INSERT INTO `deeds` (`id`, `company_id`, `nomor_akta`, `tanggal_akta`, `nama_notaris`, `domisili_notaris`, `nomor_sk_ahu`, `tanggal_sk_ahu`, `isi_akta_summary`, `tipe_akta`, `file_path`, `created_at`) VALUES
(1, 1, '154', '2016-05-30', 'SITARESMI PUSPADEWI SUBIANTO, SH.,M.KN.', 'Surabaya', 'AHU-0027208.AH.01.01.TAHUN 2016', '2016-06-03', 'Akta Pendirian Perusahaan PT PUTRA NATUR UTAMA', 'Pendirian', 'uploads/deeds/deed_6901d5a4625bc.pdf', '2025-10-29 08:51:48'),
(2, 1, '02', '2016-08-22', 'MASWATI HALIM, S.H.', 'Jakarta Pusat', 'AHU-AH.01.03-0073732', '2016-08-23', 'Pemberian persetujuan penerbitan 5.043 Lembar saham dalam simpanan, senilai Rp 5.043.000.000,- seluruhnya diambil bagian oleh Bapak Budi Iskandar Dinata', 'Perubahan', 'uploads/companies/1/deeds/deed_1_1761728102.pdf', '2025-10-29 08:55:02'),
(3, 1, '55', '2016-12-16', 'SITAREMIS PUSPADEWI SUBIANTO, SH.,M.KN.', 'Surabaya', 'AHU-AH.01.03-0112514', '2016-12-27', 'Pemberian persetujuan tindakan penyertaan modal ke dalam PT DAYA SENTOSA REKAYASA dan PT PABRIK MESIN GUNTUR', 'Perubahan', 'uploads/companies/1/deeds/deed_1_1761728226.pdf', '2025-10-29 08:57:06'),
(4, 1, '33', '2018-03-19', 'SITAREMIS PUSPADEWI SUBIANTO, SH.,M.KN.', 'Surabaya', 'AHU-AH.01.03-0120085', '2018-03-22', 'Perubahan pengurus perseroan', 'Perubahan', 'uploads/companies/1/deeds/deed_1_1761728567.pdf', '2025-10-29 09:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `departemen`
--

CREATE TABLE `departemen` (
  `id` int(11) NOT NULL,
  `id_divisi` int(11) NOT NULL,
  `nama_departemen` varchar(255) NOT NULL,
  `kode_departemen` varchar(2) DEFAULT NULL COMMENT '2 digit kode unik departemen',
  `nomor_urut_terakhir` int(3) NOT NULL DEFAULT 0 COMMENT 'Nomor urut terakhir yg digunakan',
  `id_pimpinan` int(11) DEFAULT NULL COMMENT 'FK ke users.id (Manager)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departemen`
--

INSERT INTO `departemen` (`id`, `id_divisi`, `nama_departemen`, `kode_departemen`, `nomor_urut_terakhir`, `id_pimpinan`) VALUES
(3, 4, 'HUMAN CAPITAL & GENERAL SERVICES', NULL, 0, 8),
(4, 4, 'LEGAL & CORPORATE SECRETARY', NULL, 0, NULL),
(5, 4, 'INFORMATION TECHNOLOGY', NULL, 0, NULL),
(6, 3, 'FINANCE ACCOUNTING & TAX', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `direktorat`
--

CREATE TABLE `direktorat` (
  `id` int(11) NOT NULL,
  `nama_direktorat` varchar(255) NOT NULL,
  `id_pimpinan` int(11) DEFAULT NULL COMMENT 'FK ke users.id (Direktur)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direktorat`
--

INSERT INTO `direktorat` (`id`, `nama_direktorat`, `id_pimpinan`) VALUES
(5, 'HUMAN CAPITAL & BUSINESS SUPPORT', 6),
(6, 'UTAMA', 5),
(7, 'FINANCE, ACCOUNTING & TAX', 5),
(8, 'BUSINESS DEVELOPMENT & STRATEGY', 7);

-- --------------------------------------------------------

--
-- Table structure for table `divisi`
--

CREATE TABLE `divisi` (
  `id` int(11) NOT NULL,
  `id_direktorat` int(11) NOT NULL,
  `nama_divisi` varchar(255) NOT NULL,
  `id_pimpinan` int(11) DEFAULT NULL COMMENT 'FK ke users.id (GM, bisa N/A)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `divisi`
--

INSERT INTO `divisi` (`id`, `id_direktorat`, `nama_divisi`, `id_pimpinan`) VALUES
(3, 7, 'FINANCE, ACCOUNTING & TAX', 5),
(4, 5, 'HUMAN CAPITAL & BUSINESS SUPPORT', 6),
(5, 8, 'BUSINESS DEVELOPMENT & STRATEGY', 7);

-- --------------------------------------------------------

--
-- Table structure for table `document_codes`
--

CREATE TABLE `document_codes` (
  `id` int(11) NOT NULL,
  `kode_surat` varchar(10) NOT NULL,
  `deskripsi` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_codes`
--

INSERT INTO `document_codes` (`id`, `kode_surat`, `deskripsi`) VALUES
(1, 'EXT', 'Surat Keluar'),
(2, 'SK', 'Surat Keputusan'),
(3, 'PO', 'Purchase Order'),
(4, 'PK', 'Perjanjian Kerja'),
(5, 'PKS', 'Perjanjian Kerja Sama'),
(6, 'SEWA', 'Perjanjian Sewa'),
(7, 'SKK', 'Surat Ketetapan Kepegawaian'),
(8, 'PKG', 'Surat Keterangan Kerja/Paklaring');

-- --------------------------------------------------------

--
-- Table structure for table `document_registry`
--

CREATE TABLE `document_registry` (
  `id` int(11) NOT NULL,
  `document_code_id` int(11) NOT NULL,
  `nomor_urut` int(5) NOT NULL,
  `bulan` int(2) NOT NULL,
  `tahun` year(4) NOT NULL,
  `nomor_lengkap` varchar(100) NOT NULL COMMENT 'Contoh: LPNU-04/EXT/X/2025',
  `tanggal_surat` date NOT NULL,
  `perihal` varchar(255) DEFAULT NULL COMMENT 'Perihal atau Judul Surat',
  `penandatangan` varchar(255) DEFAULT NULL,
  `ditujukan_kepada` varchar(255) DEFAULT NULL,
  `isi_ringkas` text DEFAULT NULL COMMENT 'Summary isi surat',
  `created_by_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL COMMENT 'Path relatif ke file scan PDF surat',
  `tipe_dokumen` enum('Rahasia','Terbatas','Umum') NOT NULL DEFAULT 'Umum' COMMENT 'Klasifikasi kerahasiaan dokumen',
  `akses_dokumen` enum('Dilarang','Terbatas','Semua') NOT NULL DEFAULT 'Semua' COMMENT 'Siapa saja yg boleh akses file',
  `akses_terbatas_level` enum('Direksi','Manager','Karyawan') DEFAULT NULL COMMENT 'Level akses jika akses_dokumen=Terbatas',
  `akses_karyawan_ids` text DEFAULT NULL COMMENT 'JSON array atau CSV dari user_id yg diizinkan jika level=Karyawan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `jenis_cuti` varchar(50) DEFAULT NULL COMMENT 'Jenis cuti yg diajukan',
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `jumlah_hari` int(3) NOT NULL,
  `keterangan` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by_id` int(11) DEFAULT NULL COMMENT 'FK ke users.id (atasan/admin)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_bupot_status`
--

CREATE TABLE `payroll_bupot_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tahun` year(4) NOT NULL,
  `status` enum('Pending','Sent') NOT NULL DEFAULT 'Pending',
  `sent_by_id` int(11) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Melacak status kirim Bupot 1721-A1 tahunan';

--
-- Dumping data for table `payroll_bupot_status`
--

INSERT INTO `payroll_bupot_status` (`id`, `user_id`, `tahun`, `status`, `sent_by_id`, `sent_at`, `created_at`) VALUES
(1, 9, '2025', 'Sent', 8, '2025-10-31 06:56:41', '2025-10-31 06:56:41');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_history`
--

CREATE TABLE `payroll_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `periode_tahun` year(4) NOT NULL,
  `periode_bulan` int(2) NOT NULL,
  `tanggal_mulai_periode` date NOT NULL,
  `tanggal_selesai_periode` date NOT NULL,
  `jumlah_hari_kerja_periode` int(2) NOT NULL,
  `jumlah_kehadiran_aktual` int(2) NOT NULL,
  `gaji_pokok_final` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Gaji pokok setelah pro-rata (jika absen)',
  `total_tunjangan_tetap` bigint(20) NOT NULL DEFAULT 0,
  `total_tunjangan_tidak_tetap` bigint(20) NOT NULL DEFAULT 0,
  `total_tunjangan_lain` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Misal: Tunj. Komunikasi (Reimburse)',
  `total_gross_income` bigint(20) NOT NULL DEFAULT 0,
  `total_potongan_bpjs` bigint(20) NOT NULL DEFAULT 0,
  `total_potongan_pph21` bigint(20) NOT NULL DEFAULT 0,
  `total_potongan_lainnya` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Dari tabel payroll_potongan_lain',
  `take_home_pay` bigint(20) NOT NULL DEFAULT 0,
  `status` enum('Calculated','Locked','Paid') NOT NULL DEFAULT 'Calculated',
  `calculated_by_id` int(11) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Menyimpan hasil kalkulasi gaji per periode';

--
-- Dumping data for table `payroll_history`
--

INSERT INTO `payroll_history` (`id`, `user_id`, `periode_tahun`, `periode_bulan`, `tanggal_mulai_periode`, `tanggal_selesai_periode`, `jumlah_hari_kerja_periode`, `jumlah_kehadiran_aktual`, `gaji_pokok_final`, `total_tunjangan_tetap`, `total_tunjangan_tidak_tetap`, `total_tunjangan_lain`, `total_gross_income`, `total_potongan_bpjs`, `total_potongan_pph21`, `total_potongan_lainnya`, `take_home_pay`, `status`, `calculated_by_id`, `calculated_at`) VALUES
(1, 9, '2025', 9, '2025-08-21', '2025-09-20', 23, 1, 491304, 1213000, 0, 150000, 1704304, 29739, 0, 0, 1674565, 'Calculated', 8, '2025-10-30 20:19:49'),
(2, 9, '2025', 10, '2025-09-21', '2025-10-20', 23, 23, 11300000, 1213000, 850500, 150000, 13363500, 354000, 534540, 0, 12474960, 'Paid', 8, '2025-10-31 05:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_history_detail`
--

CREATE TABLE `payroll_history_detail` (
  `id` int(11) NOT NULL,
  `payroll_history_id` int(11) NOT NULL COMMENT 'FK ke payroll_history.id',
  `komponen` varchar(255) NOT NULL COMMENT 'Misal: Gaji Pokok, Pot. BPJS JHT Karyawan, PPh 21 TER',
  `deskripsi` varchar(255) DEFAULT NULL COMMENT 'Misal: (Dasar: 5.000.000) Hadir 20/22 hari, 1% x 5.000.000',
  `tipe` enum('Pendapatan','Potongan') NOT NULL,
  `jumlah` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Menyimpan rincian kalkulasi untuk modal detail';

--
-- Dumping data for table `payroll_history_detail`
--

INSERT INTO `payroll_history_detail` (`id`, `payroll_history_id`, `komponen`, `deskripsi`, `tipe`, `jumlah`) VALUES
(98, 2, 'Gaji Pokok', 'Gaji Pokok Master', 'Pendapatan', 11300000),
(99, 2, 'Tunjangan Jabatan', 'Tunjangan Tetap', 'Pendapatan', 500000),
(100, 2, 'Tunjangan Kesehatan (Asuransi)', 'Tunjangan Tetap', 'Pendapatan', 713000),
(101, 2, 'Tunjangan Makan', '21 hari (WFO/WFH/Sakit) x 22,500', 'Pendapatan', 472500),
(102, 2, 'Tunjangan Transportasi', '21 hari (WFO) x 18,000', 'Pendapatan', 378000),
(103, 2, 'Tunjangan Rumah', 'Tunjangan Tidak Tetap (Bulanan)', 'Pendapatan', 0),
(104, 2, 'Tunjangan Pendidikan', 'Tunjangan Tidak Tetap (Bulanan)', 'Pendapatan', 0),
(105, 2, 'Tunjangan Komunikasi (Fasilitas)', 'Tunjangan Lainnya (Non-THP/Reimburse)', 'Pendapatan', 150000),
(106, 2, 'Pot. BPJS Kesehatan (Karyawan)', '1% x 11,800,000', 'Potongan', 118000),
(107, 2, 'Pot. JHT (Karyawan)', '2% x 11,800,000', 'Potongan', 236000),
(108, 2, 'Pot. Jaminan Pensiun (Karyawan)', '0% x 6,000,000', 'Potongan', 0),
(109, 2, 'Potongan PPh 21 (TER)', 'Kategori A (TK/0) - Tarif 4.00% x 13,363,500', 'Potongan', 534540),
(110, 1, 'Gaji Pokok', 'Gaji Pokok Master', 'Pendapatan', 11300000),
(111, 1, 'Potongan Pro-rata', 'Absen/Sakit non-note: 22 hari. (1 / 23)', 'Potongan', 10808696),
(112, 1, 'Tunjangan Jabatan', 'Tunjangan Tetap', 'Pendapatan', 500000),
(113, 1, 'Tunjangan Kesehatan (Asuransi)', 'Tunjangan Tetap', 'Pendapatan', 713000),
(114, 1, 'Tunjangan Makan', '0 hari (WFO/WFH/Sakit) x 22,500', 'Pendapatan', 0),
(115, 1, 'Tunjangan Transportasi', '0 hari (WFO) x 18,000', 'Pendapatan', 0),
(116, 1, 'Tunjangan Rumah', 'Tunjangan Tidak Tetap (Bulanan)', 'Pendapatan', 0),
(117, 1, 'Tunjangan Pendidikan', 'Tunjangan Tidak Tetap (Bulanan)', 'Pendapatan', 0),
(118, 1, 'Tunjangan Komunikasi (Fasilitas)', 'Tunjangan Lainnya (Non-THP/Reimburse)', 'Pendapatan', 150000),
(119, 1, 'Pot. BPJS Kesehatan (Karyawan)', '1% x 991,304', 'Potongan', 9913),
(120, 1, 'Pot. JHT (Karyawan)', '2% x 991,304', 'Potongan', 19826),
(121, 1, 'Pot. Jaminan Pensiun (Karyawan)', '0% x 991,304', 'Potongan', 0),
(122, 1, 'Potongan PPh 21 (TER)', 'Kategori A (TK/0) - Tarif 0.00% x 1,704,304', 'Potongan', 0);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_master_gaji`
--

CREATE TABLE `payroll_master_gaji` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'FK ke users.id',
  `gaji_pokok` bigint(20) NOT NULL DEFAULT 0,
  `tunj_jabatan` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tetap',
  `tunj_kesehatan` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tetap (Asuransi)',
  `tunj_transport` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tidak Tetap',
  `tunj_makan` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tidak Tetap',
  `tunj_rumah` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tidak Tetap',
  `tunj_pendidikan` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tidak Tetap',
  `tunj_komunikasi` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tunjangan Tidak Tetap',
  `status_ptkp` varchar(10) DEFAULT NULL COMMENT 'Status PTKP (Misal: TK/0, K/1)',
  `pot_pph` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Potongan Wajib (0=Tidak, 1=Ya)',
  `pot_bpjs_kesehatan` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Potongan Wajib (0=Tidak, 1=Ya)',
  `pot_bpjs_ketenagakerjaan` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Potongan Wajib (0=Tidak, 1=Ya)',
  `updated_by_id` int(11) DEFAULT NULL COMMENT 'ID HR/Admin yg terakhir update',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_master_gaji`
--

INSERT INTO `payroll_master_gaji` (`id`, `user_id`, `gaji_pokok`, `tunj_jabatan`, `tunj_kesehatan`, `tunj_transport`, `tunj_makan`, `tunj_rumah`, `tunj_pendidikan`, `tunj_komunikasi`, `status_ptkp`, `pot_pph`, `pot_bpjs_kesehatan`, `pot_bpjs_ketenagakerjaan`, `updated_by_id`, `updated_at`) VALUES
(1, 9, 11300000, 500000, 713000, 18000, 22500, 0, 0, 150000, 'TK/0', 1, 1, 1, 8, '2025-10-30 16:55:28');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_potongan_lain`
--

CREATE TABLE `payroll_potongan_lain` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'FK ke users.id',
  `periode_tahun` year(4) NOT NULL,
  `periode_bulan` int(2) NOT NULL COMMENT 'Bulan (1-12)',
  `jenis_potongan` varchar(255) NOT NULL COMMENT 'Misal: Koperasi, Utang Karyawan',
  `jumlah` bigint(20) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_by_id` int(11) NOT NULL COMMENT 'HR/Admin yg input',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Potongan manual yg diinput HR per periode';

-- --------------------------------------------------------

--
-- Table structure for table `payroll_settings`
--

CREATE TABLE `payroll_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_settings`
--

INSERT INTO `payroll_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'periode_mulai', '21', '2025-10-30 13:29:28'),
(2, 'periode_akhir', '20', '2025-10-30 13:29:28'),
(3, 'jumlah_hari_kerja', '23', '2025-10-30 13:29:28'),
(4, 'bpjs_kes_perusahaan_pct', '4', '2025-10-30 15:30:05'),
(5, 'bpjs_kes_karyawan_pct', '1', '2025-10-30 15:30:05'),
(6, 'bpjs_kes_max_upah', '12000000', '2025-10-30 15:30:05'),
(7, 'jht_perusahaan_pct', '3.7', '2025-10-30 15:30:05'),
(8, 'jht_karyawan_pct', '2', '2025-10-30 15:30:05'),
(9, 'jp_perusahaan_pct', '3', '2025-10-30 17:29:55'),
(10, 'jp_karyawan_pct', '0', '2025-10-30 17:29:55'),
(11, 'jp_max_upah', '6000000', '2025-10-30 15:30:05'),
(12, 'jkk_perusahaan_pct', '0.24', '2025-10-30 15:30:05'),
(13, 'jkm_perusahaan_pct', '0.3', '2025-10-30 15:30:05'),
(14, 'jkp_perusahaan_pct', '0.46', '2025-10-30 15:30:05'),
(15, 'pph21_metode', 'GROSS', '2025-10-30 15:30:05');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_settings_ptkp`
--

CREATE TABLE `payroll_settings_ptkp` (
  `id` int(11) NOT NULL,
  `kode_ptkp` varchar(10) NOT NULL COMMENT 'Misal: TK/0, K/1',
  `deskripsi` varchar(255) DEFAULT NULL,
  `nilai_ptkp_tahunan` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_settings_ptkp`
--

INSERT INTO `payroll_settings_ptkp` (`id`, `kode_ptkp`, `deskripsi`, `nilai_ptkp_tahunan`, `updated_at`) VALUES
(1, 'TK/0', 'Tidak Kawin, Tanpa Tanggungan', 54000000, '2025-10-30 15:30:05'),
(2, 'TK/1', 'Tidak Kawin, 1 Tanggungan', 58500000, '2025-10-30 15:30:05'),
(3, 'TK/2', 'Tidak Kawin, 2 Tanggungan', 63000000, '2025-10-30 15:30:05'),
(4, 'TK/3', 'Tidak Kawin, 3 Tanggungan', 67500000, '2025-10-30 15:30:05'),
(5, 'K/0', 'Kawin, Tanpa Tanggungan', 58500000, '2025-10-30 15:30:05'),
(6, 'K/1', 'Kawin, 1 Tanggungan', 63000000, '2025-10-30 15:30:05'),
(7, 'K/2', 'Kawin, 2 Tanggungan', 67500000, '2025-10-30 15:30:05'),
(8, 'K/3', 'Kawin, 3 Tanggungan', 72000000, '2025-10-30 15:30:05');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_settings_ter`
--

CREATE TABLE `payroll_settings_ter` (
  `id` int(11) NOT NULL,
  `kategori` enum('A','B','C') NOT NULL,
  `penghasilan_bruto_min` bigint(20) NOT NULL DEFAULT 0,
  `penghasilan_bruto_max` bigint(20) NOT NULL DEFAULT 0,
  `tarif_ter` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Persentase, misal: 1.50 untuk 1.5%'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_settings_ter`
--

INSERT INTO `payroll_settings_ter` (`id`, `kategori`, `penghasilan_bruto_min`, `penghasilan_bruto_max`, `tarif_ter`) VALUES
(1, 'A', 0, 5400000, 0.00),
(2, 'A', 5400001, 5650000, 0.25),
(3, 'A', 5650001, 5950000, 0.50),
(4, 'A', 5950001, 6300000, 0.75),
(5, 'A', 6300001, 6750000, 1.00),
(6, 'A', 6750001, 7500000, 1.25),
(7, 'A', 7500001, 8550000, 1.50),
(8, 'A', 8550001, 9650000, 1.75),
(9, 'A', 9650001, 10700000, 2.00),
(10, 'A', 10700001, 11600000, 2.25),
(11, 'A', 11600001, 12500000, 2.50),
(12, 'A', 12500001, 13200000, 3.00),
(13, 'A', 13200001, 14050000, 4.00),
(14, 'A', 14050001, 15050000, 5.00),
(15, 'A', 15050001, 16250000, 6.00),
(16, 'A', 16250001, 17650000, 7.00),
(17, 'A', 17650001, 19350000, 8.00),
(18, 'A', 19350001, 21250000, 9.00),
(19, 'A', 21250001, 23400000, 10.00),
(20, 'A', 23400001, 25850000, 11.00),
(21, 'A', 25850001, 28600000, 12.00),
(22, 'A', 28600001, 31700000, 13.00),
(23, 'A', 31700001, 35200000, 14.00),
(24, 'A', 35200001, 39200000, 15.00),
(25, 'A', 39200001, 43850000, 16.00),
(26, 'A', 43850001, 49150000, 17.00),
(27, 'A', 49150001, 55300000, 18.00),
(28, 'A', 55300001, 62400000, 19.00),
(29, 'A', 62400001, 70600000, 20.00),
(30, 'A', 70600001, 80050000, 21.00),
(31, 'A', 80050001, 91050000, 22.00),
(32, 'A', 91050001, 103850000, 23.00),
(33, 'A', 103850001, 119050000, 24.00),
(34, 'A', 119050001, 137350000, 25.00),
(35, 'A', 137350001, 159650000, 26.00),
(36, 'A', 159650001, 187050000, 27.00),
(37, 'A', 187050001, 221050000, 28.00),
(38, 'A', 221050001, 264050000, 29.00),
(39, 'A', 264050001, 319250000, 30.00),
(40, 'A', 319250001, 391950000, 31.00),
(41, 'A', 391950001, 492050000, 32.00),
(42, 'A', 492050001, 9999999999, 33.00),
(43, 'B', 0, 6200000, 0.00),
(44, 'B', 6200001, 6500000, 0.25),
(45, 'B', 6500001, 6850000, 0.50),
(46, 'B', 6850001, 7300000, 0.75),
(47, 'B', 7300001, 8100000, 1.00),
(48, 'B', 8100001, 9250000, 1.25),
(49, 'B', 9250001, 10350000, 1.50),
(50, 'B', 10350001, 11250000, 1.75),
(51, 'B', 11250001, 12350000, 2.00),
(52, 'B', 12350001, 13300000, 2.25),
(53, 'B', 13300001, 14200000, 2.50),
(54, 'B', 14200001, 15100000, 3.00),
(55, 'B', 15100001, 16100000, 4.00),
(56, 'B', 16100001, 17250000, 5.00),
(57, 'B', 17250001, 18600000, 6.00),
(58, 'B', 18600001, 20150000, 7.00),
(59, 'B', 20150001, 21950000, 8.00),
(60, 'B', 21950001, 24000000, 9.00),
(61, 'B', 24000001, 26300000, 10.00),
(62, 'B', 26300001, 28900000, 11.00),
(63, 'B', 28900001, 31800000, 12.00),
(64, 'B', 31800001, 35100000, 13.00),
(65, 'B', 35100001, 38800000, 14.00),
(66, 'B', 38800001, 43000000, 15.00),
(67, 'B', 43000001, 47800000, 16.00),
(68, 'B', 47800001, 53400000, 17.00),
(69, 'B', 53400001, 59700000, 18.00),
(70, 'B', 59700001, 66800000, 19.00),
(71, 'B', 66800001, 75200000, 20.00),
(72, 'B', 75200001, 84800000, 21.00),
(73, 'B', 84800001, 95800000, 22.00),
(74, 'B', 95800001, 108600000, 23.00),
(75, 'B', 108600001, 124000000, 24.00),
(76, 'B', 124000001, 142800000, 25.00),
(77, 'B', 142800001, 165600000, 26.00),
(78, 'B', 165600001, 194000000, 27.00),
(79, 'B', 194000001, 229200000, 28.00),
(80, 'B', 229200001, 273600000, 29.00),
(81, 'B', 273600001, 330600000, 30.00),
(82, 'B', 330600001, 405600000, 31.00),
(83, 'B', 405600001, 508200000, 32.00),
(84, 'B', 508200001, 1011600000, 33.00),
(85, 'B', 1011600001, 9999999999, 34.00),
(86, 'C', 0, 6650000, 0.00),
(87, 'C', 6650001, 6950000, 0.25),
(88, 'C', 6950001, 7350000, 0.50),
(89, 'C', 7350001, 7800000, 0.75),
(90, 'C', 7800001, 8650000, 1.00),
(91, 'C', 8650001, 9800000, 1.25),
(92, 'C', 9800001, 10950000, 1.50),
(93, 'C', 10950001, 11950000, 1.75),
(94, 'C', 11950001, 12950000, 2.00),
(95, 'C', 12950001, 13950000, 2.25),
(96, 'C', 13950001, 14950000, 2.50),
(97, 'C', 14950001, 15950000, 3.00),
(98, 'C', 15950001, 16950000, 4.00),
(99, 'C', 16950001, 18150000, 5.00),
(100, 'C', 18150001, 19500000, 6.00),
(101, 'C', 19500001, 21050000, 7.00),
(102, 'C', 21050001, 22950000, 8.00),
(103, 'C', 22950001, 24950000, 9.00),
(104, 'C', 24950001, 27300000, 10.00),
(105, 'C', 27300001, 29900000, 11.00),
(106, 'C', 29900001, 32800000, 12.00),
(107, 'C', 32800001, 36100000, 13.00),
(108, 'C', 36100001, 39900000, 14.00),
(109, 'C', 39900001, 44100000, 15.00),
(110, 'C', 44100001, 48900000, 16.00),
(111, 'C', 48900001, 54600000, 17.00),
(112, 'C', 54600001, 60900000, 18.00),
(113, 'C', 60900001, 68000000, 19.00),
(114, 'C', 68000001, 76500000, 20.00),
(115, 'C', 76500001, 86200000, 21.00),
(116, 'C', 86200001, 97500000, 22.00),
(117, 'C', 97500001, 110600000, 23.00),
(118, 'C', 110600001, 126400000, 24.00),
(119, 'C', 126400001, 145200000, 25.00),
(120, 'C', 145200001, 168400000, 26.00),
(121, 'C', 168400001, 197400000, 27.00),
(122, 'C', 197400001, 233400000, 28.00),
(123, 'C', 233400001, 278400000, 29.00),
(124, 'C', 278400001, 336600000, 30.00),
(125, 'C', 336600001, 412800000, 31.00),
(126, 'C', 412800001, 516600000, 32.00),
(127, 'C', 516600001, 1023000000, 33.00),
(128, 'C', 1023000001, 9999999999, 34.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_struktur_upah`
--

CREATE TABLE `payroll_struktur_upah` (
  `id` int(11) NOT NULL,
  `jabatan` enum('Staf','Supervisor','Manager','Direksi','Komisaris','Pemegang Saham') NOT NULL COMMENT 'Diambil dari enum tier tabel users (kecuali Admin)',
  `level` varchar(100) DEFAULT NULL,
  `grade` varchar(50) DEFAULT NULL,
  `gaji_pokok_min` bigint(20) NOT NULL DEFAULT 0,
  `gaji_pokok_max` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_tetap` bigint(20) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel master untuk struktur dan skala upah';

-- --------------------------------------------------------

--
-- Table structure for table `payroll_thr_history`
--

CREATE TABLE `payroll_thr_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'FK ke users.id',
  `tahun_thr` year(4) NOT NULL COMMENT 'Tahun THR (Misal: 2025)',
  `tanggal_acuan` date NOT NULL COMMENT 'Tanggal acuan pembayaran (H-7 Lebaran)',
  `tanggal_masuk_karyawan` date NOT NULL COMMENT 'Snapshot Tgl Masuk Karyawan',
  `masa_kerja_bulan` int(3) NOT NULL COMMENT 'Masa kerja (pembulatan) dlm bulan',
  `basis_perhitungan_desc` varchar(255) DEFAULT NULL COMMENT 'Misal: Gaji Pokok, GP+Tunjab',
  `basis_perhitungan_nominal` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Nominal basis gaji yg dipakai',
  `keterangan` varchar(255) DEFAULT NULL COMMENT 'Misal: Penuh (1x Gaji), Pro-rata (8/12)',
  `nominal_thr` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Nominal THR final',
  `status` enum('Calculated','Approved','Paid') NOT NULL DEFAULT 'Calculated' COMMENT 'Status kalkulasi',
  `calculated_by_id` int(11) NOT NULL COMMENT 'FK ke users.id (HR/Admin yg hitung)',
  `approved_by_id` int(11) DEFAULT NULL COMMENT 'FK ke users.id (Manajemen yg approve)',
  `payroll_history_id_payout` int(11) DEFAULT NULL COMMENT 'FK ke payroll_history.id (Payslip saat dibayarkan)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Menyimpan hasil kalkulasi THR tahunan';

--
-- Dumping data for table `payroll_thr_history`
--

INSERT INTO `payroll_thr_history` (`id`, `user_id`, `tahun_thr`, `tanggal_acuan`, `tanggal_masuk_karyawan`, `masa_kerja_bulan`, `basis_perhitungan_desc`, `basis_perhitungan_nominal`, `keterangan`, `nominal_thr`, `status`, `calculated_by_id`, `approved_by_id`, `payroll_history_id_payout`, `created_at`, `updated_at`) VALUES
(1, 5, '2025', '2025-03-24', '2005-01-01', 242, '0', 0, '0', 0, 'Calculated', 8, NULL, NULL, '2025-10-30 23:05:18', '2025-10-30 23:05:18'),
(2, 6, '2025', '2025-03-24', '2015-05-05', 118, '0', 0, '0', 0, 'Calculated', 8, NULL, NULL, '2025-10-30 23:05:18', '2025-10-30 23:05:18'),
(3, 7, '2025', '2025-03-24', '2005-01-01', 242, '0', 0, '0', 0, 'Calculated', 8, NULL, NULL, '2025-10-30 23:05:18', '2025-10-30 23:05:18'),
(4, 8, '2025', '2025-03-24', '2018-01-01', 86, '0', 0, '0', 0, 'Calculated', 8, NULL, NULL, '2025-10-30 23:05:18', '2025-10-30 23:05:18'),
(5, 9, '2025', '2025-03-24', '2020-03-13', 60, '0', 11800000, '0', 11800000, 'Calculated', 8, NULL, NULL, '2025-10-30 23:05:18', '2025-10-30 23:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

CREATE TABLE `presensi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_presensi` date NOT NULL COMMENT 'Tanggal melakukan presensi',
  `waktu_presensi` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu tombol presensi ditekan',
  `status_kerja` enum('WFO','Sakit','WFH','Dinas') NOT NULL COMMENT 'Keterangan Kerja Pilihan User',
  `lokasi_kerja` varchar(255) DEFAULT NULL COMMENT 'Lokasi WFO Pilihan User',
  `lokasi_wfh` text DEFAULT NULL COMMENT 'Lokasi WFH yang diinput User',
  `file_surat_sakit_path` varchar(255) DEFAULT NULL COMMENT 'Path relatif ke file surat sakit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel untuk mencatat presensi harian karyawan';

--
-- Dumping data for table `presensi`
--

INSERT INTO `presensi` (`id`, `user_id`, `tanggal_presensi`, `waktu_presensi`, `status_kerja`, `lokasi_kerja`, `lokasi_wfh`, `file_surat_sakit_path`) VALUES
(1, 9, '2025-10-29', '2025-10-29 23:19:30', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(2, 8, '2025-10-30', '2025-10-30 15:31:07', 'WFO', 'TRD/BMS Shipyard Lamongan', NULL, NULL),
(3, 9, '2025-10-30', '2025-10-30 16:17:31', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(4, 1, '2025-10-30', '2025-10-30 17:30:57', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(5, 6, '2025-10-30', '2025-10-30 17:35:12', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(6, 9, '2025-09-22', '2025-09-22 07:41:52', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(7, 9, '2025-09-23', '2025-09-23 08:09:46', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(8, 9, '2025-09-24', '2025-09-24 08:12:27', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(9, 9, '2025-09-25', '2025-09-25 08:42:44', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(10, 9, '2025-09-26', '2025-09-26 07:58:17', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(11, 9, '2025-09-29', '2025-09-29 07:53:57', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(12, 9, '2025-09-30', '2025-09-30 07:57:36', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(13, 9, '2025-10-01', '2025-10-01 07:50:13', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(14, 9, '2025-10-02', '2025-10-02 08:48:42', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(15, 9, '2025-10-03', '2025-10-03 08:06:47', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(16, 9, '2025-10-06', '2025-10-06 08:43:48', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(17, 9, '2025-10-07', '2025-10-07 07:52:18', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(18, 9, '2025-10-08', '2025-10-08 08:50:12', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(19, 9, '2025-10-09', '2025-10-09 08:47:04', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(20, 9, '2025-10-10', '2025-10-10 08:42:45', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(21, 9, '2025-10-13', '2025-10-13 07:05:26', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(22, 9, '2025-10-14', '2025-10-14 07:23:06', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(23, 9, '2025-10-15', '2025-10-15 07:52:50', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(24, 9, '2025-10-16', '2025-10-16 07:00:53', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(25, 9, '2025-10-17', '2025-10-17 07:37:09', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(26, 9, '2025-10-20', '2025-10-20 08:07:42', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(27, 9, '2025-10-21', '2025-10-21 08:29:29', 'WFO', 'PRANATA/Rutan Office (Ikado Surabaya)', NULL, NULL),
(28, 9, '2025-10-31', '2025-10-31 15:11:24', 'WFH', NULL, 'Rumah', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `saldo_cuti_tahunan`
--

CREATE TABLE `saldo_cuti_tahunan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tahun` year(4) NOT NULL,
  `sisa_cuti_aktual` int(3) NOT NULL DEFAULT 0 COMMENT 'Sisa cuti yg bisa dipakai tahun ini (sudah dikurangi cuti bersama/massal)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `id` int(11) NOT NULL,
  `id_departemen` int(11) NOT NULL,
  `nama_section` varchar(255) NOT NULL,
  `id_pimpinan` int(11) DEFAULT NULL COMMENT 'FK ke users.id (Supervisor)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`id`, `id_departemen`, `nama_section`, `id_pimpinan`) VALUES
(1, 4, 'LEGAL & CORPORATE SECRETARY', 9);

-- --------------------------------------------------------

--
-- Table structure for table `shareholders`
--

CREATE TABLE `shareholders` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nama_pemegang` varchar(255) NOT NULL,
  `nomor_identitas` varchar(100) NOT NULL COMMENT 'KTP atau NIB',
  `file_identitas_path` varchar(255) DEFAULT NULL COMMENT 'Path ke file KTP/NIB',
  `npwp` varchar(50) DEFAULT NULL,
  `file_npwp_path` varchar(255) DEFAULT NULL COMMENT 'Path ke file NPWP',
  `jumlah_saham` bigint(20) NOT NULL DEFAULT 0,
  `persentase_kepemilikan` decimal(5,2) DEFAULT 0.00 COMMENT 'Jumlah Suara',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nik` varchar(50) NOT NULL COMMENT 'Nomor Induk Karyawan',
  `password` varchar(255) NOT NULL COMMENT 'Gunakan password_hash() di PHP',
  `nama_lengkap` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(30) DEFAULT NULL,
  `no_ktp` varchar(50) DEFAULT NULL,
  `npwp` varchar(50) DEFAULT NULL,
  `status_perkawinan` varchar(20) DEFAULT NULL COMMENT 'Misal: TK/0, K/1, K/2, K/3',
  `pendidikan_terakhir` enum('Tidak Sekolah','SD','SMP','SMA/SMK','D1','D2','D3','S1','S2','S3') DEFAULT NULL,
  `jumlah_tanggungan` int(2) NOT NULL DEFAULT 0,
  `nama_jabatan` varchar(100) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `id_direktorat` int(11) DEFAULT NULL,
  `id_divisi` int(11) DEFAULT NULL,
  `id_departemen` int(11) DEFAULT NULL,
  `id_section` int(11) DEFAULT NULL,
  `atasan_id` int(11) DEFAULT NULL COMMENT 'FK ke users.id (Atasan Langsung)',
  `tanggal_masuk` date DEFAULT NULL,
  `jumlah_cuti` int(5) DEFAULT 12,
  `penempatan_kerja` varchar(100) DEFAULT NULL,
  `status_karyawan` enum('PKWT','PKWTT','BOD','BOC','Freelance','OS','Magang','KHL') NOT NULL DEFAULT 'PKWT',
  `terakhir_cuti` date DEFAULT NULL,
  `expired_pkwt` date DEFAULT NULL,
  `bank_nama` varchar(100) DEFAULT NULL,
  `bank_rekening` varchar(100) DEFAULT NULL,
  `bank_atas_nama` varchar(255) DEFAULT NULL,
  `kontak_darurat_nama` varchar(255) DEFAULT NULL,
  `kontak_darurat_hubungan` varchar(100) DEFAULT NULL,
  `kontak_darurat_telepon` varchar(30) DEFAULT NULL,
  `tier` enum('Admin','Staf','Supervisor','Manager','Direksi','Komisaris','Pemegang Saham') NOT NULL,
  `app_akses` enum('Admin','HR','Legal','Finance','Top Management','Karyawan') NOT NULL DEFAULT 'Karyawan',
  `foto_profile_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nik`, `password`, `nama_lengkap`, `email`, `jenis_kelamin`, `alamat`, `telepon`, `no_ktp`, `npwp`, `status_perkawinan`, `pendidikan_terakhir`, `jumlah_tanggungan`, `nama_jabatan`, `pic`, `id_direktorat`, `id_divisi`, `id_departemen`, `id_section`, `atasan_id`, `tanggal_masuk`, `jumlah_cuti`, `penempatan_kerja`, `status_karyawan`, `terakhir_cuti`, `expired_pkwt`, `bank_nama`, `bank_rekening`, `bank_atas_nama`, `kontak_darurat_nama`, `kontak_darurat_hubungan`, `kontak_darurat_telepon`, `tier`, `app_akses`, `foto_profile_path`, `created_at`) VALUES
(1, 'ADMIN001', '$2y$10$X7tG2g7aET5Oyq922aZ6dO2dJArK0RxT0i/EEiP6x/yFapHSWfeo.', 'Admin Utama', 'psa@corp-rutan.co.id', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'System Administrator', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12, NULL, 'PKWTT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Admin', 'Admin', NULL, '2025-10-28 03:17:26'),
(5, '050101002', '$2y$10$94VTdAFcuSenIHlpKgKKWuw1.8bkHSKK1BeTLxkFMRAwcUFPZI6vy', 'ANDREW ISKANDAR BUDIMAN', 'andrew.budiman@corp-rutan.co.id', NULL, 'Graha Famili I-58, RT 004/ RW 002, Kelurahan/Desa Pradahkalikendal, Kecamatan Dukuh Pakis, Kota Surabaya, Jawa Timur', '08123015031', '3578060312820003', '09.753.694.0-614.000', NULL, NULL, 0, 'Direktur Utama', NULL, 7, NULL, NULL, NULL, NULL, '2005-01-01', 12, 'Surabaya', 'BOD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Direksi', 'Top Management', NULL, '2025-10-29 05:41:06'),
(6, '150501001', '$2y$10$HrMDpHWkvtkNjmHGRkwaOu3o9mT7.1mp/TTfWeefqz2GmH2rc6c1i', 'ARDANNY PRATAMA GUNAWAN', 'ardanny.pratama@corp-rutan.co.id', NULL, 'Jl. Jajar Tunggal Timur L-4, RT 004/RW 005, Kelurahan/Desa Jajartunggal, Kecamatan Wiyung, Kota Surabaya, Jawa Timur', '082135098897', '3302241005900002', '79.624.832.6-521.000', NULL, NULL, 0, 'Direktur', NULL, 5, NULL, NULL, NULL, 5, '2015-05-05', 12, 'Surabaya', 'BOD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Direksi', 'Top Management', NULL, '2025-10-29 06:02:33'),
(7, '050101003', '$2y$10$aKzNvcdAzefZ6lqKoRwOguAh.kxnoZEu06VpFEINd/WK3VrPGRJfi', 'RONALD ISKANDAR BUDIMAN', 'ronald.budiman@corp-rutan.co.id', NULL, 'Jl. Raya Arjuna No. 40-42, RT 003/ RW 006, Kelurahan/Desa Sawahan, Kecamatan Sawahan, Kota Surabaya, Jawa Timur', '0811359037', '3578060306860001', '34.585.214.9-614.000', NULL, NULL, 0, 'Direktur', NULL, 8, NULL, NULL, NULL, 5, '2005-01-01', 12, 'Surabaya', 'BOD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Direksi', 'Top Management', NULL, '2025-10-29 06:44:56'),
(8, '170202001', '$2y$10$McWNqCljHPYGI.X3uvM02eFxneuDcRvtwqXtFX2z0R9XLc5l3OLtm', 'YOHANES HENDRO HUSODO', 'yohanes.hendro@corp-rutan.co.id', NULL, 'Jalan Bangau 1 / 2 RT 02 RW 04, Kel Mangunharjo, Kec Tembalang, Kota Semarang', '082138898848', '3374100304890001', '', NULL, NULL, 0, 'Departement Head Human Capital & General Services', NULL, 5, 4, 3, NULL, 6, '2018-01-01', 12, 'Surabaya', 'PKWTT', NULL, NULL, '', '', '', '', '', '', 'Manager', 'HR', 'uploads/profile_pics/user_8_1761855283.jpeg', '2025-10-29 06:53:35'),
(9, '200303004', '$2y$10$KfauoM2OAZkSdLBpV5ASdee.8uOSAvaZpF/ZWkdXe15Qrq6iloFji', 'YACOBUS BAYU HERKUNCAHYO', 'bayu.herkuncahyo@corp-rutan.co.id', 'Laki-laki', 'Apt. Klaska Residence, Tower Azure Unit 2608, Kelurahan Jagir, Kecamatan Wonokromo, Surabaya, Jawa Timur', '082154026647', '3401071803890002', '73.450.159.6-544.000', 'TK/0', 'S1', 0, 'Section Head Legal & Corporate Secretary', NULL, 5, 4, 4, 1, 6, '2020-03-13', 12, 'Surabaya', 'PKWTT', NULL, NULL, 'BCA', '2582464058', 'YACOBUS BAYU HERKUNCAHYO', NULL, NULL, NULL, 'Supervisor', 'Legal', NULL, '2025-10-29 07:18:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `board_members`
--
ALTER TABLE `board_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `fk_board_deed` (`deed_id_pengangkatan`);

--
-- Indexes for table `collective_leave`
--
ALTER TABLE `collective_leave`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tanggal_unik` (`tanggal_cuti`),
  ADD KEY `idx_tahun_status` (`tahun`,`status`),
  ADD KEY `fk_collective_proposed` (`proposed_by_id`),
  ADD KEY `fk_collective_processed` (`processed_by_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_perusahaan` (`nama_perusahaan`),
  ADD KEY `idx_id_akta_terakhir` (`id_akta_terakhir`);

--
-- Indexes for table `company_kbli`
--
ALTER TABLE `company_kbli`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `company_licenses`
--
ALTER TABLE `company_licenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `deeds`
--
ALTER TABLE `deeds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `departemen`
--
ALTER TABLE `departemen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_departemen_unik` (`kode_departemen`),
  ADD KEY `id_divisi` (`id_divisi`),
  ADD KEY `id_pimpinan` (`id_pimpinan`);

--
-- Indexes for table `direktorat`
--
ALTER TABLE `direktorat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_direktorat` (`nama_direktorat`),
  ADD KEY `id_pimpinan` (`id_pimpinan`);

--
-- Indexes for table `divisi`
--
ALTER TABLE `divisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_direktorat` (`id_direktorat`),
  ADD KEY `id_pimpinan` (`id_pimpinan`);

--
-- Indexes for table `document_codes`
--
ALTER TABLE `document_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_surat` (`kode_surat`);

--
-- Indexes for table `document_registry`
--
ALTER TABLE `document_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_lengkap` (`nomor_lengkap`),
  ADD UNIQUE KEY `nomor_unik_per_tahun` (`document_code_id`,`nomor_urut`,`tahun`),
  ADD KEY `created_by_id` (`created_by_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll_bupot_status`
--
ALTER TABLE `payroll_bupot_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_tahun_unik` (`user_id`,`tahun`),
  ADD KEY `fk_bupot_user` (`user_id`),
  ADD KEY `fk_bupot_admin` (`sent_by_id`);

--
-- Indexes for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_unik_per_karyawan` (`user_id`,`periode_tahun`,`periode_bulan`),
  ADD KEY `fk_payroll_history_user` (`user_id`),
  ADD KEY `fk_payroll_history_admin` (`calculated_by_id`);

--
-- Indexes for table `payroll_history_detail`
--
ALTER TABLE `payroll_history_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payroll_history_id` (`payroll_history_id`);

--
-- Indexes for table `payroll_master_gaji`
--
ALTER TABLE `payroll_master_gaji`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_unik` (`user_id`),
  ADD KEY `fk_master_gaji_user` (`user_id`),
  ADD KEY `fk_master_gaji_admin` (`updated_by_id`);

--
-- Indexes for table `payroll_potongan_lain`
--
ALTER TABLE `payroll_potongan_lain`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `potongan_unik_per_periode` (`user_id`,`periode_tahun`,`periode_bulan`,`jenis_potongan`),
  ADD KEY `fk_potongan_lain_user` (`user_id`),
  ADD KEY `fk_potongan_lain_admin` (`created_by_id`);

--
-- Indexes for table `payroll_settings`
--
ALTER TABLE `payroll_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key_unik` (`setting_key`);

--
-- Indexes for table `payroll_settings_ptkp`
--
ALTER TABLE `payroll_settings_ptkp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_ptkp_unik` (`kode_ptkp`);

--
-- Indexes for table `payroll_settings_ter`
--
ALTER TABLE `payroll_settings_ter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kategori_bruto` (`kategori`,`penghasilan_bruto_min`,`penghasilan_bruto_max`);

--
-- Indexes for table `payroll_struktur_upah`
--
ALTER TABLE `payroll_struktur_upah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jabatan_level_grade_unik` (`jabatan`,`level`,`grade`) COMMENT 'Kombinasi harus unik';

--
-- Indexes for table `payroll_thr_history`
--
ALTER TABLE `payroll_thr_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_tahun_thr_unik` (`user_id`,`tahun_thr`) COMMENT 'Hanya boleh 1 record THR per user per tahun',
  ADD KEY `idx_tahun_status` (`tahun_thr`,`status`),
  ADD KEY `fk_thr_calculated_by` (`calculated_by_id`),
  ADD KEY `fk_thr_approved_by` (`approved_by_id`),
  ADD KEY `fk_thr_payroll_history` (`payroll_history_id_payout`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_per_tanggal` (`user_id`,`tanggal_presensi`) COMMENT 'Hanya boleh 1 presensi per user per hari',
  ADD KEY `idx_tanggal` (`tanggal_presensi`);

--
-- Indexes for table `saldo_cuti_tahunan`
--
ALTER TABLE `saldo_cuti_tahunan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_tahun` (`user_id`,`tahun`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_departemen` (`id_departemen`),
  ADD KEY `id_pimpinan` (`id_pimpinan`);

--
-- Indexes for table `shareholders`
--
ALTER TABLE `shareholders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `tier` (`tier`),
  ADD KEY `fk_users_direktorat` (`id_direktorat`),
  ADD KEY `fk_users_divisi` (`id_divisi`),
  ADD KEY `fk_users_departemen` (`id_departemen`),
  ADD KEY `fk_users_atasan` (`atasan_id`),
  ADD KEY `fk_users_section` (`id_section`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `board_members`
--
ALTER TABLE `board_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collective_leave`
--
ALTER TABLE `collective_leave`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company_kbli`
--
ALTER TABLE `company_kbli`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_licenses`
--
ALTER TABLE `company_licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deeds`
--
ALTER TABLE `deeds`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departemen`
--
ALTER TABLE `departemen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `direktorat`
--
ALTER TABLE `direktorat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `divisi`
--
ALTER TABLE `divisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_codes`
--
ALTER TABLE `document_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `document_registry`
--
ALTER TABLE `document_registry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_bupot_status`
--
ALTER TABLE `payroll_bupot_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payroll_history`
--
ALTER TABLE `payroll_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payroll_history_detail`
--
ALTER TABLE `payroll_history_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `payroll_master_gaji`
--
ALTER TABLE `payroll_master_gaji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payroll_potongan_lain`
--
ALTER TABLE `payroll_potongan_lain`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_settings`
--
ALTER TABLE `payroll_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `payroll_settings_ptkp`
--
ALTER TABLE `payroll_settings_ptkp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payroll_settings_ter`
--
ALTER TABLE `payroll_settings_ter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `payroll_struktur_upah`
--
ALTER TABLE `payroll_struktur_upah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_thr_history`
--
ALTER TABLE `payroll_thr_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `saldo_cuti_tahunan`
--
ALTER TABLE `saldo_cuti_tahunan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shareholders`
--
ALTER TABLE `shareholders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `board_members`
--
ALTER TABLE `board_members`
  ADD CONSTRAINT `board_members_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collective_leave`
--
ALTER TABLE `collective_leave`
  ADD CONSTRAINT `fk_collective_processed` FOREIGN KEY (`processed_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_collective_proposed` FOREIGN KEY (`proposed_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`id_akta_terakhir`) REFERENCES `deeds` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `company_kbli`
--
ALTER TABLE `company_kbli`
  ADD CONSTRAINT `company_kbli_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_licenses`
--
ALTER TABLE `company_licenses`
  ADD CONSTRAINT `company_licenses_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deeds`
--
ALTER TABLE `deeds`
  ADD CONSTRAINT `deeds_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departemen`
--
ALTER TABLE `departemen`
  ADD CONSTRAINT `fk_departemen_divisi` FOREIGN KEY (`id_divisi`) REFERENCES `divisi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_departemen_pimpinan` FOREIGN KEY (`id_pimpinan`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `direktorat`
--
ALTER TABLE `direktorat`
  ADD CONSTRAINT `fk_direktorat_pimpinan` FOREIGN KEY (`id_pimpinan`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `divisi`
--
ALTER TABLE `divisi`
  ADD CONSTRAINT `fk_divisi_direktorat` FOREIGN KEY (`id_direktorat`) REFERENCES `direktorat` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_divisi_pimpinan` FOREIGN KEY (`id_pimpinan`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_registry`
--
ALTER TABLE `document_registry`
  ADD CONSTRAINT `document_registry_ibfk_1` FOREIGN KEY (`document_code_id`) REFERENCES `document_codes` (`id`),
  ADD CONSTRAINT `document_registry_ibfk_2` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_bupot_status`
--
ALTER TABLE `payroll_bupot_status`
  ADD CONSTRAINT `fk_bupot_admin` FOREIGN KEY (`sent_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bupot_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD CONSTRAINT `fk_payroll_history_admin_users` FOREIGN KEY (`calculated_by_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_payroll_history_user_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_history_detail`
--
ALTER TABLE `payroll_history_detail`
  ADD CONSTRAINT `fk_payroll_detail_to_history` FOREIGN KEY (`payroll_history_id`) REFERENCES `payroll_history` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_master_gaji`
--
ALTER TABLE `payroll_master_gaji`
  ADD CONSTRAINT `fk_master_gaji_admin` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_master_gaji_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_potongan_lain`
--
ALTER TABLE `payroll_potongan_lain`
  ADD CONSTRAINT `fk_potongan_lain_admin_users` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_potongan_lain_user_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_thr_history`
--
ALTER TABLE `payroll_thr_history`
  ADD CONSTRAINT `fk_thr_approved_by` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_thr_calculated_by` FOREIGN KEY (`calculated_by_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_thr_payroll_history` FOREIGN KEY (`payroll_history_id_payout`) REFERENCES `payroll_history` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_thr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `fk_presensi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `saldo_cuti_tahunan`
--
ALTER TABLE `saldo_cuti_tahunan`
  ADD CONSTRAINT `saldo_cuti_tahunan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `section`
--
ALTER TABLE `section`
  ADD CONSTRAINT `fk_section_departemen` FOREIGN KEY (`id_departemen`) REFERENCES `departemen` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_section_pimpinan` FOREIGN KEY (`id_pimpinan`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shareholders`
--
ALTER TABLE `shareholders`
  ADD CONSTRAINT `shareholders_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_atasan` FOREIGN KEY (`atasan_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_departemen` FOREIGN KEY (`id_departemen`) REFERENCES `departemen` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_direktorat` FOREIGN KEY (`id_direktorat`) REFERENCES `direktorat` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_divisi` FOREIGN KEY (`id_divisi`) REFERENCES `divisi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_section` FOREIGN KEY (`id_section`) REFERENCES `section` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
