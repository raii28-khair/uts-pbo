<?php
require_once 'database.php';

/**
 * KELAS OOP: UserAuth
 * Menangani proses Sign In (Masuk), Sign Up (Daftar), dan penyimpanan data pengguna
 * secara otomatis ke MySQL (jika tersedia) maupun Session (mode lokal langsung jalan).
 */
class UserAuth
{
    private ?PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();

        if (!isset($_SESSION['users_store'])) {
            // Akun demo bawaan agar bisa langsung dicoba
            $_SESSION['users_store'] = [
                'mahasiswa@kampus.ac.id' => [
                    'id'            => 1,
                    'nama_lengkap'  => 'Mahasiswa Produktif',
                    'nim'           => '20260101',
                    'email'         => 'mahasiswa@kampus.ac.id',
                    'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                ]
            ];
            $_SESSION['next_user_id'] = 2;
        }
    }

    public function isDatabaseConnected(): bool
    {
        return $this->db !== null;
    }

    public function register(string $nama, string $nim, string $email, string $password, string $konfirmasi): array
    {
        $nama  = trim($nama);
        $nim   = trim($nim);
        $email = strtolower(trim($email));

        if ($nama === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException("Nama lengkap, email, dan password wajib diisi.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Format email tidak valid.");
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException("Password minimal 6 karakter.");
        }
        if ($password !== $konfirmasi) {
            throw new InvalidArgumentException("Konfirmasi password tidak cocok.");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Jika MySQL aktif, simpan ke tabel users
        if ($this->db !== null) {
            $cek = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $cek->execute([$email]);
            if ($cek->fetch()) {
                throw new InvalidArgumentException("Email sudah terdaftar di database.");
            }

            $stmt = $this->db->prepare("INSERT INTO users (nama_lengkap, nim, email, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $nim, $email, $hash]);
            $userId = (int) $this->db->lastInsertId();

            return [
                'id'           => $userId,
                'nama_lengkap' => $nama,
                'nim'          => $nim,
                'email'        => $email,
            ];
        }

        // Fallback Session
        if (isset($_SESSION['users_store'][$email])) {
            throw new InvalidArgumentException("Email sudah terdaftar. Silakan Sign In.");
        }

        $user = [
            'id'            => $_SESSION['next_user_id']++,
            'nama_lengkap'  => $nama,
            'nim'           => $nim,
            'email'         => $email,
            'password_hash' => $hash,
        ];
        $_SESSION['users_store'][$email] = $user;

        unset($user['password_hash']);
        return $user;
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException("Masukkan email dan password Anda.");
        }

        // Jika MySQL aktif, cek ke database
        if ($this->db !== null) {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new InvalidArgumentException("Email atau password salah.");
            }

            unset($user['password_hash']);
            return $user;
        }

        // Fallback Session
        if (!isset($_SESSION['users_store'][$email])) {
            throw new InvalidArgumentException("Akun tidak ditemukan. Silakan Sign Up terlebih dahulu.");
        }

        $user = $_SESSION['users_store'][$email];
        if (!password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException("Password yang Anda masukkan salah.");
        }

        unset($user['password_hash']);
        return $user;
    }
}
