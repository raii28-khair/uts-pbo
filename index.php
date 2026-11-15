<?php
// Semua kelas dimuat SEBELUM session_start()
require_once 'database.php';
require_once 'TugasKuliah.php';
require_once 'RutinitasHarian.php';
session_start();

// Pintu masuk: wajib login terlebih dahulu
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$userId      = (int) ($currentUser['id'] ?? 1);
$db          = Database::getConnection();

// ---------- Inisialisasi Data per User (Sinkron MySQL atau Session) ----------
$sessionTugasKey = "tugas_user_{$userId}";
$sessionRutinKey = "rutin_user_{$userId}";

if (!isset($_SESSION[$sessionTugasKey])) {
    $_SESSION[$sessionTugasKey] = [];
    $_SESSION["next_id_{$userId}"] = 1;

    if ($db !== null) {
        $stmt = $db->prepare("SELECT * FROM tugas_kuliah WHERE user_id = ?");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $obj = new TugasKuliah(
                (int) $row['id'],
                $row['judul'],
                $row['deskripsi'] ?? '',
                $row['tenggat'],
                $row['prioritas'],
                $row['mata_kuliah'],
                $row['dosen'] ?? '',
                $row['jenis_tugas'],
                (int) $row['bobot_nilai'],
                $row['link_pengumpulan'] ?? ''
            );
            if ((int) $row['selesai'] === 1) $obj->tandaiSelesai();
            $_SESSION[$sessionTugasKey][$obj->getId()] = $obj;
        }
    } else {
        $contoh = new TugasKuliah(
            $_SESSION["next_id_{$userId}"]++,
            'Laporan Praktikum PBO',
            'Buat laporan UTS, rekam video penjelasan OOP, lalu unggah ke YouTube.',
            date('Y-m-d', strtotime('+3 days')),
            'Tinggi',
            'Pemrograman Berorientasi Objek',
            'Prajoko, M.Kom',
            'Kelompok',
            30,
            'https://youtube.com'
        );
        $_SESSION[$sessionTugasKey][$contoh->getId()] = $contoh;
    }
}

if (!isset($_SESSION[$sessionRutinKey])) {
    $_SESSION[$sessionRutinKey] = [];
    $_SESSION["next_rutin_id_{$userId}"] = 1;

    if ($db !== null) {
        $stmt = $db->prepare("SELECT * FROM rutinitas_harian WHERE user_id = ? ORDER BY waktu ASC");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $obj = new RutinitasHarian(
                (int) $row['id'],
                $row['waktu'],
                $row['judul'],
                $row['kategori'],
                $row['deskripsi'] ?? '',
                $row['prioritas'] ?? 'Sedang'
            );
            if ((int) $row['selesai'] === 1) $obj->tandaiSelesai();
            $_SESSION[$sessionRutinKey][$obj->getId()] = $obj;
        }
    } else {
        $defaultRutin = [
            ['05:00', 'Bangun Tidur, Ibadah & Minum Air Putih', 'Pagi', 'Awali hari tanpa langsung cek HP, rapikan tempat tidur.', 'Tinggi'],
            ['06:30', 'Sarapan & Persiapan Berangkat Kampus', 'Pagi', 'Cek jadwal mata kuliah hari ini dan bawa charger.', 'Sedang'],
            ['08:00', 'Mengikuti Perkuliahan & Mencatat Materi', 'Kampus', 'Fokus menyimak dosen dan catat poin penting.', 'Tinggi'],
            ['12:30', 'ISHOMA & Makan Siang', 'Istirahat', 'Istirahat sejenak dari layar laptop.', 'Sedang'],
            ['15:30', 'Ngerjain Tugas Kuliah / Ngoding Project', 'Produktif', 'Cicil tugas sebelum mendekati deadline.', 'Tinggi'],
            ['19:30', 'Evaluasi Materi Hari Ini & Waktu Santai', 'Istirahat', 'Review catatan singkat atau me-time.', 'Rendah'],
            ['22:30', 'Skincare Malam & Tidur Cukup', 'Malam', 'Matikan gadget 15 menit sebelum tidur.', 'Tinggi'],
        ];

        foreach ($defaultRutin as $r) {
            $obj = new RutinitasHarian($_SESSION["next_rutin_id_{$userId}"]++, $r[0], $r[1], $r[2], $r[3], $r[4]);
            $_SESSION[$sessionRutinKey][$obj->getId()] = $obj;
        }
        $_SESSION[$sessionRutinKey][1]->tandaiSelesai();
        $_SESSION[$sessionRutinKey][2]->tandaiSelesai();
    }
}

// ---------- Memproses aksi dari form (Otomatis sinkron ke DB jika aktif) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $_SESSION['flash'] = null;
    $_SESSION['old']   = null;

    try {
        if ($aksi === 'tambah') {
            $newId = $_SESSION["next_id_{$userId}"]++;
            $tugasBaru = new TugasKuliah(
                $newId,
                $_POST['judul'] ?? '',
                $_POST['deskripsi'] ?? '',
                $_POST['tenggat'] ?? '',
                $_POST['prioritas'] ?? 'Sedang',
                $_POST['mata_kuliah'] ?? '',
                $_POST['dosen'] ?? '',
                $_POST['jenis'] ?? 'Individu',
                (int) ($_POST['bobot'] ?? 0),
                $_POST['link'] ?? ''
            );

            if ($db !== null) {
                $stmt = $db->prepare("INSERT INTO tugas_kuliah (user_id, judul, deskripsi, tenggat, prioritas, mata_kuliah, dosen, jenis_tugas, bobot_nilai, link_pengumpulan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId, $tugasBaru->getJudul(), $tugasBaru->getDeskripsi(), $tugasBaru->getTenggat(),
                    $tugasBaru->getPrioritas(), $tugasBaru->getMataKuliah(), $tugasBaru->getDosen(),
                    $tugasBaru->getJenisTugas(), $tugasBaru->getBobotNilai(), $tugasBaru->getLinkPengumpulan()
                ]);
                unset($_SESSION[$sessionTugasKey]);
            } else {
                $_SESSION[$sessionTugasKey][$tugasBaru->getId()] = $tugasBaru;
            }
            $_SESSION['flash'] = ['ok', 'Tugas kuliah berhasil ditambahkan!'];

        } elseif ($aksi === 'selesai') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($db !== null) {
                $db->prepare("UPDATE tugas_kuliah SET selesai = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
                unset($_SESSION[$sessionTugasKey]);
            } elseif (isset($_SESSION[$sessionTugasKey][$id])) {
                $_SESSION[$sessionTugasKey][$id]->tandaiSelesai();
            }
            $_SESSION['flash'] = ['ok', 'Mantap! Tugas kuliah ditandai selesai.'];

        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($db !== null) {
                $db->prepare("DELETE FROM tugas_kuliah WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
                unset($_SESSION[$sessionTugasKey]);
            } else {
                unset($_SESSION[$sessionTugasKey][$id]);
            }
            $_SESSION['flash'] = ['ok', 'Tugas kuliah dihapus.'];

        } elseif ($aksi === 'tambah_rutin') {
            $newId = $_SESSION["next_rutin_id_{$userId}"]++;
            $rutinBaru = new RutinitasHarian(
                $newId,
                $_POST['waktu'] ?? '',
                $_POST['judul_rutin'] ?? '',
                $_POST['kategori'] ?? 'Produktif',
                $_POST['deskripsi_rutin'] ?? ''
            );

            if ($db !== null) {
                $stmt = $db->prepare("INSERT INTO rutinitas_harian (user_id, waktu, judul, kategori, deskripsi) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $rutinBaru->getWaktu(), $rutinBaru->getJudul(), $rutinBaru->getKategori(), $rutinBaru->getDeskripsi()]);
                unset($_SESSION[$sessionRutinKey]);
            } else {
                $_SESSION[$sessionRutinKey][$rutinBaru->getId()] = $rutinBaru;
            }
            $_SESSION['flash'] = ['ok', 'Kegiatan harian baru berhasil ditambahkan!'];

        } elseif ($aksi === 'toggle_rutin') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($db !== null) {
                $db->prepare("UPDATE rutinitas_harian SET selesai = NOT selesai WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
                unset($_SESSION[$sessionRutinKey]);
            } elseif (isset($_SESSION[$sessionRutinKey][$id])) {
                $_SESSION[$sessionRutinKey][$id]->toggleSelesai();
            }

        } elseif ($aksi === 'hapus_rutin') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($db !== null) {
                $db->prepare("DELETE FROM rutinitas_harian WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
                unset($_SESSION[$sessionRutinKey]);
            } else {
                unset($_SESSION[$sessionRutinKey][$id]);
            }
            $_SESSION['flash'] = ['ok', 'Kegiatan harian dihapus.'];

        } elseif ($aksi === 'reset_rutin') {
            if ($db !== null) {
                $db->prepare("UPDATE rutinitas_harian SET selesai = 0 WHERE user_id = ?")->execute([$userId]);
                unset($_SESSION[$sessionRutinKey]);
            } else {
                foreach ($_SESSION[$sessionRutinKey] as $r) {
                    if ($r->isSelesai()) $r->toggleSelesai();
                }
            }
            $_SESSION['flash'] = ['ok', 'Semua checklist harian di-reset untuk hari baru!'];
        }
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash'] = ['err', $e->getMessage()];
        $_SESSION['old']   = $_POST;
    }

    header('Location: index.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
$old   = $_SESSION['old'] ?? [];
unset($_SESSION['flash'], $_SESSION['old']);

function e(string $teks): string { return htmlspecialchars($teks, ENT_QUOTES, 'UTF-8'); }
function lama(array $old, string $kunci, string $default = ''): string { return e((string) ($old[$kunci] ?? $default)); }

// ---------- Data Tugas Kuliah ----------
$daftar = $_SESSION[$sessionTugasKey] ?? [];
uasort($daftar, function ($a, $b) {
    if ($a->isSelesai() !== $b->isSelesai()) return $a->isSelesai() <=> $b->isSelesai();
    return strcmp($a->getTenggat(), $b->getTenggat());
});

$totalTugas   = count($daftar);
$selesaiTugas = 0;
$jmlTelat     = 0;
foreach ($daftar as $t) {
    if ($t->isSelesai())   $selesaiTugas++;
    if ($t->isTerlambat()) $jmlTelat++;
}
$persenTugas = $totalTugas > 0 ? (int) round(($selesaiTugas / $totalTugas) * 100) : 0;

// ---------- Data Rutinitas Harian ----------
$daftarRutin = $_SESSION[$sessionRutinKey] ?? [];
uasort($daftarRutin, fn($a, $b) => strcmp($a->getWaktu(), $b->getWaktu()));

$totalRutin   = count($daftarRutin);
$selesaiRutin = 0;
foreach ($daftarRutin as $r) {
    if ($r->isSelesai()) $selesaiRutin++;
}
$persenRutin = $totalRutin > 0 ? (int) round(($selesaiRutin / $totalRutin) * 100) : 0;

// ---------- Pencapaian Gabungan ----------
$totalSemua   = $totalTugas + $totalRutin;
$selesaiSemua = $selesaiTugas + $selesaiRutin;
$persenTotal  = $totalSemua > 0 ? (int) round(($selesaiSemua / $totalSemua) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mahasiswa Flow — Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --text-main: #f0f6ff;
    --text-muted: rgba(219, 234, 254, 0.72);
    --glass-border: rgba(186, 230, 253, 0.22);
    --blue-glow: #38bdf8;
    --cyan-accent: #22d3ee;
    --emerald: #34d399;
    --amber: #fbbf24;
    --rose: #fb7185;
    --radius: 24px;
  }

  * { box-sizing: border-box; }
  html { color-scheme: dark; }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--text-main);
    background: radial-gradient(circle at 15% 15%, #0f347a 0%, #061533 48%, #020814 100%);
    line-height: 1.5;
    overflow-x: hidden;
  }

  /* ===== BACKGROUND BIRU DENGAN EFEK NOISE ===== */
  .bg-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
  .noise-layer {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: 0.045;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.82' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
  }
  .orb {
    position: absolute; border-radius: 50%; filter: blur(95px); opacity: 0.65;
    animation: floatOrb 16s ease-in-out infinite alternate;
  }
  .orb-1 { width: 520px; height: 520px; background: #1d4ed8; top: -140px; left: -120px; }
  .orb-2 { width: 460px; height: 460px; background: #0284c7; bottom: -120px; right: -80px; animation-delay: -5s; }
  .orb-3 { width: 340px; height: 340px; background: #06b6d4; top: 38%; left: 48%; opacity: 0.4; animation-delay: -9s; }

  @keyframes floatOrb {
    0%   { transform: translate(0, 0) scale(1); }
    100% { transform: translate(30px, -25px) scale(1.08); }
  }

  /* ===== KOMPONEN KACA INTERAKTIF ===== */
  .glass {
    --mouse-x: 50%;
    --mouse-y: 50%;
    position: relative;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.11) 0%, rgba(255, 255, 255, 0.04) 100%);
    -webkit-backdrop-filter: blur(24px) saturate(160%);
    backdrop-filter: blur(24px) saturate(160%);
    border: 1px solid var(--glass-border);
    border-top-color: rgba(255, 255, 255, 0.35);
    border-left-color: rgba(255, 255, 255, 0.28);
    border-radius: var(--radius);
    box-shadow: 0 18px 45px rgba(2, 8, 24, 0.48), inset 0 1px 1px rgba(255, 255, 255, 0.25);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), border-color 0.3s ease, box-shadow 0.35s ease;
    overflow: hidden;
  }

  .glass::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.35s ease;
    background: radial-gradient(420px circle at var(--mouse-x) var(--mouse-y), rgba(125, 211, 252, 0.22), rgba(56, 189, 248, 0.07) 42%, transparent 72%);
    z-index: 0;
  }

  .glass:hover {
    border-color: rgba(125, 211, 252, 0.55);
    box-shadow: 0 24px 55px rgba(2, 12, 36, 0.65), 0 0 28px rgba(56, 189, 248, 0.16), inset 0 1px 1px rgba(255, 255, 255, 0.42);
  }
  .glass:hover::after { opacity: 1; }
  .glass > * { position: relative; z-index: 1; }

  .interactive-col { transform-style: preserve-3d; will-change: transform; }
  .prog-card:hover { transform: translateY(-4px) scale(1.01); }

  .container {
    position: relative;
    z-index: 1;
    max-width: 1220px;
    margin: 0 auto;
    padding: 24px 20px 72px;
  }

  /* ===== TOP NAVBAR DENGAN LOGO & PROFIL USER ===== */
  .topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 14px 22px;
    margin-bottom: 20px;
    border-radius: 20px;
  }

  .brand-mini {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: inherit;
  }

  .logo-box {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.28), rgba(34, 211, 238, 0.12));
    border: 1px solid rgba(125, 211, 252, 0.45);
    display: grid;
    place-items: center;
    box-shadow: 0 6px 18px rgba(2, 132, 199, 0.3);
  }

  .brand-mini strong {
    display: block;
    font-size: 17px;
    font-weight: 800;
    background: linear-gradient(to right, #ffffff, #7dd3fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .brand-mini small {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
  }

  .user-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .sync-pill {
    font-size: 11.5px;
    font-weight: 600;
    padding: 5px 11px;
    border-radius: 99px;
    background: rgba(56, 189, 248, 0.14);
    border: 1px solid rgba(56, 189, 248, 0.32);
    color: #bae6fd;
  }

  .user-chip {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 12px 5px 6px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.16);
    font-size: 13px;
    font-weight: 600;
  }

  .avatar-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #38bdf8, #22d3ee);
    color: #03112b;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 12px;
  }

  /* ===== HERO HEADER & RADIAL PROGRESS ===== */
  .hero {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 24px;
    align-items: center;
    padding: 26px 30px;
    margin-bottom: 24px;
  }

  .hero h1 {
    margin: 0;
    font-size: clamp(24px, 3.6vw, 38px);
    font-weight: 800;
    letter-spacing: -0.03em;
    background: linear-gradient(to right, #ffffff, #bae6fd);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .hero p {
    margin: 8px 0 0;
    color: var(--text-muted);
    max-width: 54ch;
    font-size: 14.5px;
  }

  .radial-box {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 16px 22px;
    border-radius: 20px;
    background: rgba(3, 12, 30, 0.42);
    border: 1px solid rgba(186, 230, 253, 0.22);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
  }

  .radial-box:hover {
    transform: scale(1.03);
    border-color: rgba(56, 189, 248, 0.6);
    box-shadow: 0 0 30px rgba(56, 189, 248, 0.25);
  }

  .radial-ring {
    --val: <?= $persenTotal ?>;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #38bdf8 0%, #22d3ee calc(var(--val) * 1%), rgba(255, 255, 255, 0.1) calc(var(--val) * 1%) 100%);
    display: grid;
    place-items: center;
    position: relative;
    box-shadow: 0 0 25px rgba(56, 189, 248, 0.28);
    transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .radial-box:hover .radial-ring { transform: rotate(12deg) scale(1.06); }
  .radial-ring::before { content: ""; position: absolute; inset: 9px; border-radius: 50%; background: #071836; }
  .radial-ring span { position: relative; font-size: 19px; font-weight: 800; color: #fff; }
  .radial-info strong { display: block; font-size: 15px; color: #fff; }
  .radial-info small  { display: block; color: var(--text-muted); font-size: 12.5px; margin-top: 2px; }

  /* ===== BAR PROGRESS GANDA ===== */
  .progress-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 24px;
  }

  .prog-card { padding: 18px 22px; }
  .prog-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .prog-title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; }
  .prog-percent { font-weight: 800; font-size: 18px; color: var(--cyan-accent); }

  .track {
    height: 11px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #3b82f6, #38bdf8, #22d3ee);
    box-shadow: 0 0 14px rgba(56, 189, 248, 0.55);
    transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
  }
  .fill.akademik { background: linear-gradient(90deg, #6366f1, #38bdf8, #34d399); }

  .prog-meta {
    margin-top: 8px;
    font-size: 12.5px;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
  }

  .alert {
    padding: 13px 18px;
    border-radius: 16px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 14px;
    backdrop-filter: blur(16px);
  }
  .alert.ok  { background: rgba(52, 211, 153, 0.16); border: 1px solid rgba(52, 211, 153, 0.45); color: #a7f3d0; }
  .alert.err { background: rgba(251, 113, 133, 0.18); border: 1px solid rgba(251, 113, 133, 0.5); color: #fecdd3; }

  .main-grid {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 24px;
    align-items: start;
  }

  .section-box { padding: 24px; }
  .section-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
  .section-header h2 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; }
  .section-header p { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }

  .mini-form {
    background: rgba(4, 14, 34, 0.38);
    border: 1px solid rgba(186, 230, 253, 0.16);
    border-radius: 18px;
    padding: 16px;
    margin-bottom: 20px;
    transition: border-color 0.25s ease, background 0.25s ease;
  }
  .mini-form:hover, .mini-form:focus-within { border-color: rgba(56, 189, 248, 0.45); background: rgba(4, 14, 34, 0.52); }

  .form-row { display: grid; grid-template-columns: 110px 1fr 125px; gap: 10px; }
  .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }

  input, select, textarea {
    width: 100%;
    padding: 10px 13px;
    font: inherit;
    font-size: 13.5px;
    color: #fff;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(186, 230, 253, 0.2);
    border-radius: 12px;
    transition: all 0.2s;
  }
  input::placeholder, textarea::placeholder { color: rgba(219, 234, 254, 0.42); }
  option { background: #081b3e; color: #fff; }
  input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--blue-glow);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
  }

  .btn {
    font: inherit;
    font-weight: 700;
    font-size: 13.5px;
    cursor: pointer;
    border: 0;
    border-radius: 12px;
    padding: 10px 16px;
    color: #03112b;
    background: linear-gradient(135deg, #7dd3fc, #22d3ee);
    box-shadow: 0 6px 20px rgba(34, 211, 238, 0.25);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s;
  }
  .btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 10px 26px rgba(56, 189, 248, 0.48); }

  .btn-ghost {
    color: var(--text-main);
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: none;
  }
  .btn-ghost:hover { background: rgba(255, 255, 255, 0.16); border-color: rgba(125, 211, 252, 0.45); box-shadow: none; }
  .btn-danger:hover { background: rgba(251, 113, 133, 0.25); border-color: rgba(251, 113, 133, 0.55); color: #fecdd3; }
  .btn-sm { padding: 7px 12px; font-size: 12.5px; border-radius: 10px; }

  .timeline { display: flex; flex-direction: column; gap: 10px; }

  .rutin-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 15px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(186, 230, 253, 0.15);
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), background 0.25s ease, border-color 0.25s ease, box-shadow 0.28s ease;
  }
  .rutin-item:hover {
    transform: translateX(6px) scale(1.012);
    background: rgba(255, 255, 255, 0.13);
    border-color: rgba(56, 189, 248, 0.6);
    box-shadow: -4px 8px 24px rgba(3, 15, 42, 0.45), 0 0 18px rgba(56, 189, 248, 0.18);
  }
  .rutin-item.done { opacity: 0.68; background: rgba(16, 185, 129, 0.07); border-color: rgba(52, 211, 153, 0.25); }
  .rutin-item.done:hover { opacity: 0.92; border-color: rgba(52, 211, 153, 0.55); }

  .rutin-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
  .check-btn {
    width: 26px; height: 26px; border-radius: 8px;
    border: 2px solid rgba(125, 211, 252, 0.6);
    background: rgba(255, 255, 255, 0.05);
    color: #03112b; display: grid; place-items: center; cursor: pointer; flex-shrink: 0; font-weight: 900;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s;
  }
  .check-btn:hover { transform: scale(1.16); border-color: #22d3ee; }
  .rutin-item.done .check-btn { background: linear-gradient(135deg, #34d399, #22d3ee); border-color: transparent; }

  .jam-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12.5px; font-weight: 700; padding: 4px 9px; border-radius: 8px;
    background: rgba(56, 189, 248, 0.16); color: #7dd3fc; border: 1px solid rgba(56, 189, 248, 0.28); flex-shrink: 0;
  }

  .rutin-text h4 { margin: 0; font-size: 14.5px; font-weight: 700; }
  .rutin-item.done .rutin-text h4 { text-decoration: line-through; color: var(--text-muted); }
  .rutin-text p { margin: 2px 0 0; font-size: 12px; color: var(--text-muted); }
  .kat-pill { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 99px; background: rgba(255, 255, 255, 0.1); color: #bae6fd; }

  .filter-pills { display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px; background: rgba(3, 12, 30, 0.45); border: 1px solid rgba(255, 255, 255, 0.12); }
  .filter-pills button { font: inherit; font-size: 12.5px; font-weight: 600; padding: 6px 12px; border: 0; border-radius: 9px; background: transparent; color: var(--text-muted); cursor: pointer; }
  .filter-pills button.aktif { background: rgba(56, 189, 248, 0.28); color: #fff; }

  .tugas-card {
    padding: 18px 20px; border-radius: 18px; background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(186, 230, 253, 0.18); margin-bottom: 12px; position: relative; overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), background 0.25s ease, border-color 0.25s ease, box-shadow 0.3s ease;
  }
  .tugas-card:hover {
    transform: translateY(-4px) scale(1.012);
    background: rgba(255, 255, 255, 0.13);
    border-color: rgba(56, 189, 248, 0.62);
    box-shadow: 0 14px 32px rgba(2, 10, 30, 0.55), 0 0 22px rgba(56, 189, 248, 0.18);
  }
  .tugas-card::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--amber); transition: width 0.25s ease; }
  .tugas-card:hover::before { width: 6px; }
  .tugas-card.Tinggi::before { background: var(--rose); }
  .tugas-card.Rendah::before { background: var(--emerald); }
  .tugas-card.selesai { opacity: 0.68; }
  .tugas-card.selesai h3 { text-decoration: line-through; }

  .tugas-top { display: flex; justify-content: space-between; align-items: start; gap: 10px; }
  .tugas-top h3 { margin: 0; font-size: 16.5px; font-weight: 700; }
  .prio-tag { font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 99px; color: #03112b; background: var(--amber); }
  .prio-tag.Tinggi { background: var(--rose); }
  .prio-tag.Rendah { background: var(--emerald); }

  .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
  .chips span { font-size: 11.5px; padding: 3px 10px; border-radius: 99px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.22); color: #e0f2fe; }

  .tugas-bottom { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.08); font-size: 12.5px; }

  .ripple-wave {
    position: absolute; border-radius: 50%; transform: scale(0);
    animation: rippleAnim 0.65s ease-out forwards;
    background: radial-gradient(circle, rgba(125, 211, 252, 0.35) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
  }
  @keyframes rippleAnim { to { transform: scale(3.2); opacity: 0; } }

  @media (max-width: 960px) {
    .hero { grid-template-columns: 1fr; }
    .progress-grid, .main-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<div class="bg-canvas" aria-hidden="true">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
<div class="noise-layer" aria-hidden="true"></div>

<main class="container">
  <!-- ===== NAVBAR ATAS DENGAN LOGO & AKUN ===== -->
  <nav class="glass topbar">
    <a href="index.php" class="brand-mini">
      <div class="logo-box" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="mfGradTop" x1="4" y1="4" x2="32" y2="32" gradientUnits="userSpaceOnUse">
              <stop stop-color="#E0F2FE"/>
              <stop offset="0.5" stop-color="#38BDF8"/>
              <stop offset="1" stop-color="#22D3EE"/>
            </linearGradient>
          </defs>
          <path d="M18 5L32 12L18 19L4 12L18 5Z" fill="url(#mfGradTop)"/>
          <path d="M9 17.5V23.5C9 26.2 13 28.5 18 28.5C23 28.5 27 26.2 27 23.5V17.5" stroke="url(#mfGradTop)" stroke-width="2.6" stroke-linecap="round"/>
          <path d="M14.5 22L17 24.5L22 19.5" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div>
        <strong>Mahasiswa Flow</strong>
        <small>Daily &amp; Study Workspace</small>
      </div>
    </a>

    <div class="user-actions">
      <span class="sync-pill">
        <?= $db !== null ? '🟢 MySQL Synced' : '🔵 Local Session' ?>
      </span>

      <div class="user-chip">
        <span class="avatar-circle"><?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'M', 0, 1)) ?></span>
        <span><?= e($currentUser['nama_lengkap'] ?? 'Mahasiswa') ?></span>
      </div>

      <a href="logout.php" class="btn btn-ghost btn-danger btn-sm">Keluar</a>
    </div>
  </nav>

  <!-- ===== HERO HEADER & RADIAL PROGRESS ===== -->
  <header class="glass hero">
    <div>
      <h1>Halo, <?= e(explode(' ', $currentUser['nama_lengkap'] ?? 'Mahasiswa')[0]) ?>! 👋</h1>
      <p>Kelola rutinitas harian dari bangun tidur sampai tidur lagi, sekaligus pantau deadline tugas kuliahmu hari ini.</p>
    </div>

    <div class="radial-box" title="Pencapaian Gabungan Hari Ini">
      <div class="radial-ring">
        <span><?= $persenTotal ?>%</span>
      </div>
      <div class="radial-info">
        <strong>Total Pencapaian</strong>
        <small><?= $selesaiSemua ?> dari <?= $totalSemua ?> aktivitas selesai</small>
        <small style="color: #7dd3fc;">
          <?= $persenTotal === 100 ? 'Luar biasa! Semua target tuntas.' : 'Terus jaga ritme produktifmu!' ?>
        </small>
      </div>
    </div>
  </header>

  <!-- ===== INDIKATOR PENCAPAIAN PERSENTASE TERPISAH ===== -->
  <section class="progress-grid">
    <div class="glass prog-card">
      <div class="prog-head">
        <span class="prog-title">☀️ Pencapaian Rutinitas Harian</span>
        <span class="prog-percent"><?= $persenRutin ?>%</span>
      </div>
      <div class="track" role="progressbar" aria-valuenow="<?= $persenRutin ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="fill" style="width: <?= $persenRutin ?>%;"></div>
      </div>
      <div class="prog-meta">
        <span>Bangun tidur &rarr; Tidur lagi</span>
        <span><strong><?= $selesaiRutin ?></strong> / <?= $totalRutin ?> kegiatan selesai</span>
      </div>
    </div>

    <div class="glass prog-card">
      <div class="prog-head">
        <span class="prog-title">🎓 Pencapaian Tugas Kuliah</span>
        <span class="prog-percent"><?= $persenTugas ?>%</span>
      </div>
      <div class="track" role="progressbar" aria-valuenow="<?= $persenTugas ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="fill akademik" style="width: <?= $persenTugas ?>%;"></div>
      </div>
      <div class="prog-meta">
        <span><?= $jmlTelat > 0 ? "<span style='color:#fb7185'>{$jmlTelat} tugas terlambat</span>" : "Semua deadline aman" ?></span>
        <span><strong><?= $selesaiTugas ?></strong> / <?= $totalTugas ?> tugas selesai</span>
      </div>
    </div>
  </section>

  <?php if ($flash): ?>
    <div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'err' ?>" role="status">
      <?= e($flash[1]) ?>
    </div>
  <?php endif; ?>

  <!-- ===== DUA KOLOM UTAMA ===== -->
  <div class="main-grid">
    <!-- KOLOM KIRI: RUTINITAS HARIAN -->
    <section class="glass section-box interactive-col">
      <div class="section-header">
        <div>
          <h2>🌅 Rutinitas Harian (05:00 – 23:00)</h2>
          <p>Tambahkan jadwal kegiatan harianmu secara bebas.</p>
        </div>
        <form method="post" style="margin:0;">
          <input type="hidden" name="aksi" value="reset_rutin">
          <button type="submit" class="btn btn-ghost btn-sm">Reset Hari</button>
        </form>
      </div>

      <form method="post" class="mini-form">
        <input type="hidden" name="aksi" value="tambah_rutin">
        <div class="form-row">
          <input type="time" name="waktu" value="<?= lama($old, 'waktu', '07:00') ?>" required>
          <input type="text" name="judul_rutin" placeholder="Kegiatan (misal: Olahraga pagi / Baca buku)" value="<?= lama($old, 'judul_rutin') ?>" required>
          <select name="kategori">
            <?php foreach (['Pagi', 'Kampus', 'Produktif', 'Istirahat', 'Malam'] as $kat): ?>
              <option value="<?= $kat ?>"><?= $kat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex; gap:10px; margin-top:10px;">
          <input type="text" name="deskripsi_rutin" placeholder="Catatan singkat kegiatan (opsional)..." value="<?= lama($old, 'deskripsi_rutin') ?>">
          <button type="submit" class="btn" style="white-space:nowrap;">+ Tambah</button>
        </div>
      </form>

      <div class="timeline">
        <?php if (empty($daftarRutin)): ?>
          <p style="text-align:center; color:var(--text-muted); padding:20px 0;">Belum ada jadwal harian. Yuk tambahkan kegiatan pertamamu!</p>
        <?php endif; ?>

        <?php foreach ($daftarRutin as $r): ?>
          <div class="rutin-item <?= $r->isSelesai() ? 'done' : '' ?>">
            <div class="rutin-left">
              <form method="post" style="margin:0;">
                <input type="hidden" name="aksi" value="toggle_rutin">
                <input type="hidden" name="id" value="<?= $r->getId() ?>">
                <button type="submit" class="check-btn" title="Tandai selesai / batal">
                  <?= $r->isSelesai() ? '✓' : '' ?>
                </button>
              </form>
              <span class="jam-badge"><?= e($r->getWaktu()) ?></span>
              <div class="rutin-text">
                <h4><?= e($r->getJudul()) ?></h4>
                <?php if ($r->getDeskripsi() !== ''): ?>
                  <p><?= e($r->getDeskripsi()) ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
              <span class="kat-pill"><?= e($r->getKategori()) ?></span>
              <form method="post" style="margin:0;" onsubmit="return confirm('Hapus kegiatan ini?');">
                <input type="hidden" name="aksi" value="hapus_rutin">
                <input type="hidden" name="id" value="<?= $r->getId() ?>">
                <button type="submit" class="btn btn-ghost btn-danger btn-sm" title="Hapus">✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- KOLOM KANAN: TUGAS KULIAH -->
    <section class="glass section-box interactive-col">
      <div class="section-header">
        <div>
          <h2>📚 Tugas &amp; Deadline Kuliah</h2>
          <p>Terintegrasi dengan kelas OOP <code>TugasKuliah</code>.</p>
        </div>
        <div class="filter-pills" role="group">
          <button type="button" class="aktif" data-filter="semua">Semua</button>
          <button type="button" data-filter="aktif">Aktif</button>
          <button type="button" data-filter="selesai">Selesai</button>
        </div>
      </div>

      <details class="mini-form" <?= !empty($old['judul']) ? 'open' : '' ?>>
        <summary style="cursor:pointer; font-weight:700; color:#7dd3fc; font-size:14px;">
          + Tambah Tugas Kuliah Baru
        </summary>
        <form method="post" style="margin-top:12px;">
          <input type="hidden" name="aksi" value="tambah">
          <input type="text" name="judul" placeholder="Judul tugas kuliah (Contoh: Makalah Basis Data)" value="<?= lama($old, 'judul') ?>" required>
          <textarea name="deskripsi" rows="2" placeholder="Deskripsi tugas (opsional)" style="margin-top:10px;"><?= lama($old, 'deskripsi') ?></textarea>

          <div class="form-grid-2">
            <input type="text" name="mata_kuliah" placeholder="Mata kuliah *" value="<?= lama($old, 'mata_kuliah') ?>" required>
            <input type="text" name="dosen" placeholder="Nama dosen" value="<?= lama($old, 'dosen') ?>">
            <input type="date" name="tenggat" value="<?= lama($old, 'tenggat', date('Y-m-d', strtotime('+2 days'))) ?>" required>
            <input type="number" name="bobot" min="0" max="100" placeholder="Bobot (%)" value="<?= lama($old, 'bobot', '15') ?>">
            <select name="prioritas">
              <option value="Rendah">Prioritas: Rendah</option>
              <option value="Sedang" selected>Prioritas: Sedang</option>
              <option value="Tinggi">Prioritas: Tinggi</option>
            </select>
            <select name="jenis">
              <option value="Individu">Individu</option>
              <option value="Kelompok">Kelompok</option>
            </select>
          </div>

          <div style="display:flex; gap:10px; margin-top:10px;">
            <input type="text" name="link" placeholder="Link pengumpulan (https://...)" value="<?= lama($old, 'link') ?>">
            <button type="submit" class="btn" style="white-space:nowrap;">Simpan Tugas</button>
          </div>
        </form>
      </details>

      <?php if ($totalTugas === 0): ?>
        <p style="text-align:center; color:var(--text-muted); padding:24px 0;">Belum ada tugas kuliah aktif.</p>
      <?php endif; ?>

      <?php foreach ($daftar as $t): ?>
        <?php $link = $t->getLinkPengumpulan(); ?>
        <article class="tugas-card <?= e($t->getPrioritas()) ?> <?= $t->isSelesai() ? 'selesai' : '' ?>"
                 data-status="<?= $t->isSelesai() ? 'selesai' : 'aktif' ?>">
          <div class="tugas-top">
            <h3><?= e($t->getJudul()) ?></h3>
            <span class="prio-tag <?= e($t->getPrioritas()) ?>"><?= e($t->getPrioritas()) ?></span>
          </div>

          <?php if ($t->getDeskripsi() !== ''): ?>
            <p style="margin:6px 0 0; font-size:13px; color:var(--text-muted);"><?= e($t->getDeskripsi()) ?></p>
          <?php endif; ?>

          <div class="chips">
            <span><?= e($t->getMataKuliah()) ?></span>
            <?php if ($t->getDosen() !== ''): ?><span><?= e($t->getDosen()) ?></span><?php endif; ?>
            <span><?= e($t->getJenisTugas()) ?></span>
            <span>Bobot <?= $t->getBobotNilai() ?>%</span>
          </div>

          <div class="tugas-bottom">
            <div>
              <span style="color:var(--text-muted);">Deadline:</span>
              <strong><?= e($t->getTenggat()) ?></strong>
              <span style="color:#7dd3fc;">(<?= e($t->getPengingat()) ?>)</span>
              <?php if (preg_match('#^https?://#i', $link)): ?>
                &bull; <a href="<?= e($link) ?>" target="_blank" rel="noopener" style="color:#34d399;">Link Kumpul</a>
              <?php endif; ?>
            </div>

            <div style="display:flex; gap:6px;">
              <?php if (!$t->isSelesai()): ?>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="aksi" value="selesai">
                  <input type="hidden" name="id" value="<?= $t->getId() ?>">
                  <button type="submit" class="btn btn-sm">Selesai</button>
                </form>
              <?php endif; ?>
              <form method="post" style="margin:0;" onsubmit="return confirm('Hapus tugas kuliah ini?');">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $t->getId() ?>">
                <button type="submit" class="btn btn-ghost btn-danger btn-sm">Hapus</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</main>

<script>
  document.querySelectorAll('.filter-pills button').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-filter');
      document.querySelectorAll('.filter-pills button').forEach(b => b.classList.remove('aktif'));
      btn.classList.add('aktif');
      document.querySelectorAll('.tugas-card').forEach(card => {
        const match = target === 'semua' || card.getAttribute('data-status') === target;
        card.style.display = match ? '' : 'none';
      });
    });
  });

  document.querySelectorAll('.glass').forEach(panel => {
    const updateGlow = (clientX, clientY) => {
      const rect = panel.getBoundingClientRect();
      panel.style.setProperty('--mouse-x', `${clientX - rect.left}px`);
      panel.style.setProperty('--mouse-y', `${clientY - rect.top}px`);
    };
    panel.addEventListener('mousemove', e => updateGlow(e.clientX, e.clientY));
    panel.addEventListener('touchmove', e => {
      if (e.touches[0]) updateGlow(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: true });
  });

  document.querySelectorAll('.interactive-col').forEach(col => {
    col.addEventListener('mousemove', e => {
      const rect = col.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      col.style.transform = `perspective(1100px) rotateX(${(-y * 2.2).toFixed(2)}deg) rotateY(${(x * 2.2).toFixed(2)}deg)`;
    });
    col.addEventListener('mouseleave', () => {
      col.style.transform = 'perspective(1100px) rotateX(0deg) rotateY(0deg)';
    });
  });

  document.querySelectorAll('.rutin-item, .tugas-card').forEach(card => {
    card.addEventListener('pointerdown', e => {
      const rect = card.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const ripple = document.createElement('span');
      ripple.className = 'ripple-wave';
      ripple.style.width = ripple.style.height = `${size}px`;
      ripple.style.left = `${e.clientX - rect.left - size / 2}px`;
      ripple.style.top = `${e.clientY - rect.top - size / 2}px`;
      card.appendChild(ripple);
      setTimeout(() => ripple.remove(), 650);
    });
  });
</script>
</body>
</html>
