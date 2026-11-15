-- Impor file ini ke phpMyAdmin untuk menyinkronkan Login, Sign Up, Rutinitas, dan Tugas Kuliah
CREATE DATABASE IF NOT EXISTS mahasiswa_flow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mahasiswa_flow;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    nim VARCHAR(30) DEFAULT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rutinitas_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    waktu VARCHAR(5) NOT NULL,
    judul VARCHAR(150) NOT NULL,
    kategori VARCHAR(30) NOT NULL DEFAULT 'Produktif',
    deskripsi TEXT,
    prioritas VARCHAR(20) DEFAULT 'Sedang',
    selesai TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tugas_kuliah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    tenggat DATE NOT NULL,
    prioritas VARCHAR(20) DEFAULT 'Sedang',
    mata_kuliah VARCHAR(100) NOT NULL,
    dosen VARCHAR(100) DEFAULT '',
    jenis_tugas VARCHAR(20) DEFAULT 'Individu',
    bobot_nilai INT DEFAULT 0,
    link_pengumpulan VARCHAR(255) DEFAULT '',
    selesai TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
