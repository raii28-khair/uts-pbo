<?php
require_once 'UserAuth.php';
session_start();

// Jika sudah login, langsung arahkan ke dashboard utama
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$auth  = new UserAuth();
$error = null;
$mode  = $_GET['mode'] ?? 'signin'; // signin | signup
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? 'signin';
    $old  = $_POST;

    try {
        if ($aksi === 'signup') {
            $mode = 'signup';
            $user = $auth->register(
                $_POST['nama_lengkap'] ?? '',
                $_POST['nim'] ?? '',
                $_POST['email'] ?? '',
                $_POST['password'] ?? '',
                $_POST['konfirmasi_password'] ?? ''
            );
            $_SESSION['user'] = $user;
            $_SESSION['flash'] = ['ok', 'Selamat datang, ' . $user['nama_lengkap'] . '! Akun berhasil dibuat.'];
            header('Location: index.php');
            exit;
        } else {
            $mode = 'signin';
            $user = $auth->login(
                $_POST['email'] ?? '',
                $_POST['password'] ?? ''
            );
            $_SESSION['user'] = $user;
            $_SESSION['flash'] = ['ok', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!'];
            header('Location: index.php');
            exit;
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — Mahasiswa Flow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --text-main: #f0f6ff;
    --text-muted: rgba(219, 234, 254, 0.72);
    --glass-border: rgba(186, 230, 253, 0.24);
    --blue-glow: #38bdf8;
    --cyan-accent: #22d3ee;
    --radius: 26px;
  }

  * { box-sizing: border-box; }
  html { color-scheme: dark; }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--text-main);
    background: radial-gradient(circle at 18% 18%, #0f347a 0%, #061533 48%, #020814 100%);
    display: grid;
    place-items: center;
    padding: 24px 16px;
    overflow-x: hidden;
  }

  /* ===== BACKGROUND BIRU + NOISE ===== */
  .bg-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
  .noise-layer {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: 0.045;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.82' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
  }
  .orb {
    position: absolute; border-radius: 50%; filter: blur(95px); opacity: 0.65;
    animation: floatOrb 15s ease-in-out infinite alternate;
  }
  .orb-1 { width: 520px; height: 520px; background: #1d4ed8; top: -150px; left: -120px; }
  .orb-2 { width: 460px; height: 460px; background: #0284c7; bottom: -140px; right: -90px; animation-delay: -5s; }
  .orb-3 { width: 320px; height: 320px; background: #06b6d4; top: 45%; left: 55%; opacity: 0.38; animation-delay: -9s; }

  @keyframes floatOrb {
    0%   { transform: translate(0, 0) scale(1); }
    100% { transform: translate(28px, -22px) scale(1.07); }
  }

  /* ===== KARTU PINTU MASUK GLASSMORPHISM ===== */
  .auth-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 960px;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    border-radius: var(--radius);
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.04) 100%);
    -webkit-backdrop-filter: blur(26px) saturate(165%);
    backdrop-filter: blur(26px) saturate(165%);
    border: 1px solid var(--glass-border);
    border-top-color: rgba(255, 255, 255, 0.38);
    border-left-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 25px 60px rgba(2, 8, 24, 0.6), inset 0 1px 1px rgba(255, 255, 255, 0.25);
    overflow: hidden;
    --mouse-x: 50%;
    --mouse-y: 50%;
  }

  .auth-wrapper::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.35s ease;
    background: radial-gradient(
      460px circle at var(--mouse-x) var(--mouse-y),
      rgba(125, 211, 252, 0.2),
      transparent 70%
    );
  }
  .auth-wrapper:hover::after { opacity: 1; }

  /* Sisi Kiri: Brand Identity & Logo Mahasiswa Flow */
  .brand-panel {
    padding: 42px 38px;
    background: linear-gradient(160deg, rgba(14, 52, 122, 0.45) 0%, rgba(3, 12, 30, 0.55) 100%);
    border-right: 1px solid rgba(186, 230, 253, 0.16);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    z-index: 1;
  }

  .logo-lockup {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .logo-badge {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.28), rgba(34, 211, 238, 0.12));
    border: 1px solid rgba(125, 211, 252, 0.45);
    display: grid;
    place-items: center;
    box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.4);
  }

  .logo-text strong {
    display: block;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.02em;
    background: linear-gradient(to right, #ffffff, #7dd3fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .logo-text span {
    font-size: 12px;
    color: #7dd3fc;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .brand-copy {
    margin: 32px 0;
  }

  .brand-copy h1 {
    margin: 0 0 12px;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.03em;
  }

  .brand-copy p {
    margin: 0;
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.6;
  }

  .feature-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .feat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(186, 230, 253, 0.14);
    font-size: 13px;
    color: #e0f2fe;
    transition: transform 0.25s ease, border-color 0.25s ease;
  }

  .feat-item:hover {
    transform: translateX(5px);
    border-color: rgba(56, 189, 248, 0.45);
  }

  .db-status {
    margin-top: 24px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 99px;
    background: rgba(3, 12, 30, 0.5);
    border: 1px solid rgba(186, 230, 253, 0.18);
    color: var(--text-muted);
    width: fit-content;
  }

  .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 10px #34d399;
  }
  .dot.local { background: #38bdf8; box-shadow: 0 0 10px #38bdf8; }

  /* Sisi Kanan: Form Sign In & Sign Up */
  .form-panel {
    padding: 42px 38px;
    position: relative;
    z-index: 1;
  }

  .tab-switch {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    padding: 5px;
    border-radius: 14px;
    background: rgba(3, 12, 30, 0.5);
    border: 1px solid rgba(186, 230, 253, 0.16);
    margin-bottom: 24px;
  }

  .tab-btn {
    padding: 10px;
    border: 0;
    border-radius: 10px;
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-muted);
    background: transparent;
    cursor: pointer;
    transition: all 0.25s ease;
  }

  .tab-btn.active {
    color: #03112b;
    background: linear-gradient(135deg, #7dd3fc, #22d3ee);
    box-shadow: 0 4px 16px rgba(34, 211, 238, 0.35);
  }

  .form-title {
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 800;
  }

  .form-sub {
    margin: 0 0 20px;
    font-size: 13.5px;
    color: var(--text-muted);
  }

  .alert-err {
    padding: 11px 14px;
    border-radius: 12px;
    background: rgba(251, 113, 133, 0.18);
    border: 1px solid rgba(251, 113, 133, 0.5);
    color: #fecdd3;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
  }

  .field { margin-bottom: 14px; }
  .field label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
  }

  .field input {
    width: 100%;
    padding: 11px 14px;
    font: inherit;
    font-size: 14px;
    color: #fff;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(186, 230, 253, 0.22);
    border-radius: 12px;
    transition: all 0.2s ease;
  }

  .field input::placeholder { color: rgba(219, 234, 254, 0.38); }
  .field input:focus {
    outline: none;
    border-color: var(--blue-glow);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
  }

  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .btn-submit {
    width: 100%;
    margin-top: 8px;
    padding: 13px;
    border: 0;
    border-radius: 13px;
    font: inherit;
    font-size: 14.5px;
    font-weight: 800;
    color: #03112b;
    background: linear-gradient(135deg, #7dd3fc, #22d3ee);
    box-shadow: 0 8px 24px rgba(34, 211, 238, 0.3);
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s;
  }

  .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(56, 189, 248, 0.5);
  }

  .demo-hint {
    margin-top: 16px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(56, 189, 248, 0.1);
    border: 1px dashed rgba(56, 189, 248, 0.35);
    font-size: 12px;
    color: #bae6fd;
    text-align: center;
  }

  .hidden { display: none; }

  @media (max-width: 820px) {
    .auth-wrapper { grid-template-columns: 1fr; }
    .brand-panel { border-right: 0; border-bottom: 1px solid rgba(186, 230, 253, 0.16); padding: 28px 24px; }
    .form-panel { padding: 28px 24px; }
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

<div class="auth-wrapper" id="authCard">
  <!-- PANEL KIRI: LOGO & IDENTITAS APLIKASI -->
  <div class="brand-panel">
    <div>
      <div class="logo-lockup">
        <div class="logo-badge" aria-hidden="true">
          <!-- Custom SVG Logo Mahasiswa Flow -->
          <svg width="32" height="32" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="mfGrad" x1="4" y1="4" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                <stop stop-color="#E0F2FE"/>
                <stop offset="0.5" stop-color="#38BDF8"/>
                <stop offset="1" stop-color="#22D3EE"/>
              </linearGradient>
            </defs>
            <!-- Topi Toga / Kristal Akademik -->
            <path d="M18 5L32 12L18 19L4 12L18 5Z" fill="url(#mfGrad)"/>
            <!-- Aliran Gelombang Produktivitas (Flow & Checklist) -->
            <path d="M9 17.5V23.5C9 26.2 13 28.5 18 28.5C23 28.5 27 26.2 27 23.5V17.5" stroke="url(#mfGrad)" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M14.5 22L17 24.5L22 19.5" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="logo-text">
          <strong>Mahasiswa Flow</strong>
          <span>Student Productivity OS</span>
        </div>
      </div>

      <div class="brand-copy">
        <h1>Atur Hari &amp; Tugas Kuliah dalam Satu Aliran.</h1>
        <p>Mulai dari bangun tidur hingga tidur kembali, pantau persentase target harian dan deadline akademik Anda secara real-time.</p>
      </div>

      <div class="feature-list">
        <div class="feat-item">☀️ Rutinitas harian terstruktur dari bangun s/d tidur</div>
        <div class="feat-item">🎓 Manajemen tugas kuliah berbasis OOP PHP</div>
        <div class="feat-item">🗄️ Siap sinkronisasi langsung dengan MySQL Database</div>
      </div>
    </div>

    <div class="db-status">
      <span class="dot <?= $auth->isDatabaseConnected() ? '' : 'local' ?>"></span>
      <span>
        <?= $auth->isDatabaseConnected()
            ? 'Database MySQL Terhubung (Sinkron)'
            : 'Mode Session Lokal Aktif (Siap dihubungkan ke MySQL)' ?>
      </span>
    </div>
  </div>

  <!-- PANEL KANAN: SIGN IN & SIGN UP -->
  <div class="form-panel">
    <div class="tab-switch">
      <button type="button" class="tab-btn <?= $mode === 'signin' ? 'active' : '' ?>" onclick="switchTab('signin')">Sign In</button>
      <button type="button" class="tab-btn <?= $mode === 'signup' ? 'active' : '' ?>" onclick="switchTab('signup')">Sign Up</button>
    </div>

    <?php if ($error): ?>
      <div class="alert-err" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- FORM SIGN IN -->
    <form method="post" id="formSignin" class="<?= $mode === 'signin' ? '' : 'hidden' ?>">
      <input type="hidden" name="aksi" value="signin">
      <h2 class="form-title">Selamat Datang Kembali</h2>
      <p class="form-sub">Masuk untuk melanjutkan progres harian dan tugas kuliahmu.</p>

      <div class="field">
        <label>Email Mahasiswa</label>
        <input type="email" name="email" placeholder="mahasiswa@kampus.ac.id" value="<?= e($old['email'] ?? 'mahasiswa@kampus.ac.id') ?>" required>
      </div>

      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" value="123456" required>
      </div>

      <button type="submit" class="btn-submit">Masuk ke Dashboard &rarr;</button>

      <div class="demo-hint">
        Akun Demo: <strong>mahasiswa@kampus.ac.id</strong> &bull; Pass: <strong>123456</strong>
      </div>
    </form>

    <!-- FORM SIGN UP -->
    <form method="post" id="formSignup" class="<?= $mode === 'signup' ? '' : 'hidden' ?>">
      <input type="hidden" name="aksi" value="signup">
      <h2 class="form-title">Buat Akun Baru</h2>
      <p class="form-sub">Daftarkan akun untuk menyimpan jadwal &amp; tugasmu.</p>

      <div class="grid-2">
        <div class="field">
          <label>Nama Lengkap</label>
          <input type="text" name="nama_lengkap" placeholder="Contoh: Aria Alfath" value="<?= e($old['nama_lengkap'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>NIM (Opsional)</label>
          <input type="text" name="nim" placeholder="2026xxxx" value="<?= e($old['nim'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="nama@kampus.ac.id" value="<?= e($old['email'] ?? '') ?>" required>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min. 6 karakter" required>
        </div>
        <div class="field">
          <label>Konfirmasi Password</label>
          <input type="password" name="konfirmasi_password" placeholder="Ulangi password" required>
        </div>
      </div>

      <button type="submit" class="btn-submit">Daftar &amp; Mulai Sekarang &rarr;</button>
    </form>
  </div>
</div>

<script>
  function switchTab(target) {
    const isSignIn = target === 'signin';
    document.getElementById('formSignin').classList.toggle('hidden', !isSignIn);
    document.getElementById('formSignup').classList.toggle('hidden', isSignIn);
    document.querySelectorAll('.tab-btn').forEach((btn, idx) => {
      btn.classList.toggle('active', (idx === 0 && isSignIn) || (idx === 1 && !isSignIn));
    });
  }

  // Efek kilau kaca mengikuti kursor
  const card = document.getElementById('authCard');
  card.addEventListener('mousemove', e => {
    const rect = card.getBoundingClientRect();
    card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
    card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
  });
</script>
</body>
</html>
