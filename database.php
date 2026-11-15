<?php
/**
 * KONFIGURASI DATABASE & SINKRONISASI DATA
 * Sesuaikan host, dbname, username, dan password dengan phpMyAdmin / MySQL Anda.
 */
class Database
{
    private static ?PDO $koneksi = null;

    // Ubah konfigurasi berikut jika ingin menyambungkan langsung ke MySQL
    private const DB_HOST = '127.0.0.1';
    private const DB_NAME = 'mahasiswa_flow';
    private const DB_USER = 'root';
    private const DB_PASS = '';

    /**
     * Mengembalikan koneksi PDO jika database aktif, atau null jika masih mode lokal (Session).
     */
    public static function getConnection(): ?PDO
    {
        if (self::$koneksi !== null) {
            return self::$koneksi;
        }

        try {
            $dsn = "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=utf8mb4";
            self::$koneksi = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$koneksi;
        } catch (PDOException $e) {
            // Jika database belum dibuat di phpMyAdmin, aplikasi otomatis berjalan menggunakan Session
            return null;
        }
    }
}
