
<?php
require_once __DIR__ . '/config/database.php';
$conn->set_charset("utf8mb4");

/* =========================
   FUNGSI DASAR
========================= */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function gambarWisata($foto) {
    $namaFile = basename((string)($foto ?? ''));
    $path = __DIR__ . '/images/' . $namaFile;

    if ($namaFile !== '' && is_file($path)) {
        return 'images/' . rawurlencode($namaFile);
    }

    return 'images/pantai_anyer.webp';
}

/* =========================
   PENCARIAN DAN KATEGORI
========================= */
$cari = trim($_GET['cari'] ?? '');
$kategori = trim($_GET['kategori'] ?? 'Semua');

$daftarKategori = [];
$hasilKategori = $conn->query(
    "SELECT DISTINCT kategori
     FROM wisata
     WHERE kategori IS NOT NULL AND kategori <> ''
     ORDER BY kategori ASC"
);

if ($hasilKategori) {
    while ($rowKategori = $hasilKategori->fetch_assoc()) {
        $daftarKategori[] = $rowKategori['kategori'];
    }
}

/* =========================
   AMBIL DATA WISATA
========================= */
$sql = "SELECT id, nama, lokasi, kategori, deskripsi, foto,
               aktivitas, harga_tiket, jam_buka, fasilitas
        FROM wisata
        WHERE 1=1";

$types = '';
$params = [];

if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR lokasi LIKE ? OR deskripsi LIKE ?)";
    $kataCari = '%' . $cari . '%';

    $params[] = $kataCari;
    $params[] = $kataCari;
    $params[] = $kataCari;
    $types .= 'sss';
}

if ($kategori !== '' && strtolower($kategori) !== 'semua') {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= 's';
}

$sql .= " ORDER BY id ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Gagal menyiapkan pencarian wisata.');
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$wisata = [];

while ($row = $result->fetch_assoc()) {
    $wisata[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Jelajah Banten - Wisata Alam & Budaya</title>

    <meta name="description" content="Temukan destinasi wisata alam, pantai, pulau, budaya, dan sejarah terbaik di Banten bersama Jelajah Banten.">

    <style>
        :root {
            --hijau: #087f5b;
            --hijau-tua: #075640;
            --hijau-muda: #e8f5ed;
            --krem: #f5e7bd;
            --putih: #ffffff;
            --teks: #24342d;
            --abu: #718078;
            --garis: #e6ebe7;
            --bayangan: 0 10px 35px rgba(25, 60, 43, .09);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: #fbfcfa;
            color: var(--teks);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        button,
        a,
        select {
            -webkit-tap-highlight-color: transparent;
        }

        /* WADAH UTAMA */
        .page-container {
            width: calc(100% - 48px);
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
        }

        /* HEADER */
        .site-header {
            width: 100%;
            background: rgba(255, 255, 255, .97);
            border-bottom: 1px solid #edf0ed;
        }

        .header-inner {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--hijau-muda);
            font-size: 22px;
            flex-shrink: 0;
        }

        .brand-name {
            margin: 0;
            color: #18392b;
            font-family: Georgia, serif;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.2;
        }

        .brand-subtitle {
            margin-top: 3px;
            color: var(--abu);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .portal-text {
            color: #68766f;
            font-size: 12px;
        }

        .admin-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border-radius: 12px;
            background: var(--hijau);
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: background .2s;
        }

        .admin-link:hover {
            background: var(--hijau-tua);
        }

        /* HERO */
        .hero {
            position: relative;
            min-height: 330px;
            margin-top: 18px;
            overflow: hidden;
            display: flex;
            align-items: center;
            border-radius: 24px;
            color: white;
            background:
                linear-gradient(90deg,
                    rgba(9, 35, 27, .91) 0%,
                    rgba(9, 35, 27, .73) 48%,
                    rgba(9, 35, 27, .20) 100%),
                url('images/kampung_baduy.webp') center 45% / cover no-repeat;
        }

        .hero-content {
            width: 100%;
            max-width: 690px;
            padding: 38px 5.5%;
        }

        .hero-label {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 12px;
            border: 1px solid rgba(255, 255, 255, .5);
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .6px;
        }

        .hero h1 {
            margin: 0 0 14px;
            font-size: clamp(30px, 4vw, 49px);
            line-height: 1.12;
            letter-spacing: -1.2px;
            font-weight: 800;
        }

        .hero h1 span {
            color: var(--krem);
        }

        .hero p {
            max-width: 570px;
            margin: 0 0 22px;
            color: rgba(255, 255, 255, .91);
            font-size: 13px;
            line-height: 1.8;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 13px;
        }

        .hero-button {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border-radius: 12px;
            background: var(--krem);
            color: #3c402a;
            font-size: 12px;
            font-weight: 800;
        }

        .hero-note {
            color: #f0f4ed;
            font-size: 11px;
        }

        /* KOTAK PENCARIAN */
        .search-panel {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            margin: 20px auto 38px;
            padding: 23px 26px 25px;
            border: 1px solid #edf0ed;
            border-radius: 20px;
            background: #fff;
            box-shadow: var(--bayangan);
        }

        .search-heading {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 19px;
        }

        .search-heading-icon {
            width: 29px;
            height: 29px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--hijau-muda);
            color: var(--hijau);
            font-size: 17px;
        }

        .search-heading h2 {
            margin: 0;
            font-size: 17px;
            line-height: 1.4;
            font-weight: 800;
        }

        .search-form {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(0, .8fr) auto;
            align-items: end;
            gap: 16px;
            width: 100%;
            min-width: 0;
            margin: 0;
        }

        .search-form .field {
            width: 100%;
            min-width: 0;
            margin: 0;
        }

        .search-form .field label {
            display: block;
            margin-bottom: 7px;
            color: #34443b;
            font-size: 11px;
            font-weight: 700;
        }

        .search-form .field input,
        .search-form .field select {
            display: block;
            width: 100%;
            min-width: 0;
            height: 47px;
            margin: 0;
            padding: 0 13px;
            border: 1px solid #e2e8e3;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #34443b;
            font-size: 12px;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-form .field input:focus,
        .search-form .field select:focus {
            border-color: var(--hijau);
            box-shadow: 0 0 0 3px rgba(8, 127, 91, .09);
        }

        .search-form .field input::placeholder {
            color: #9ba59e;
        }

        .search-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 9px;
            min-width: 0;
        }

        .search-button,
        .reset-button {
            min-height: 47px;
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 16px;
            border: 0;
            border-radius: 10px;
            white-space: nowrap;
            cursor: pointer;
            font-size: 11px;
            font-weight: 800;
            transition: background .2s, transform .2s;
        }

        .search-button {
            background: var(--hijau);
            color: white;
        }

        .search-button:hover {
            background: var(--hijau-tua);
            transform: translateY(-1px);
        }

        .reset-button {
            background: #f1f4f1;
            color: #59675e;
        }

        .reset-button:hover {
            background: #e5ebe6;
        }

        /* DAFTAR WISATA */
        .destinations {
            width: 100%;
            min-width: 0;
            padding-bottom: 50px;
            scroll-margin-top: 20px;
        }

        .section-top {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 23px;
        }

        .section-eyebrow {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 7px;
            color: #61766a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .section-eyebrow::before {
            content: "";
            width: 19px;
            height: 2px;
            background: var(--hijau);
        }

        .section-top h2 {
            margin: 0;
            color: #18392b;
            font-family: Georgia, serif;
            font-size: clamp(25px, 3vw, 34px);
            line-height: 1.25;
        }

        .section-description {
            margin: 8px 0 0;
            color: var(--abu);
            font-size: 12px;
        }

        .result-count {
            flex-shrink: 0;
            padding: 8px 12px;
            border-radius: 30px;
            background: #edf6ef;
            color: var(--hijau-tua);
            font-size: 11px;
            font-weight: 800;
        }

        .filter-info {
            margin: -7px 0 20px;
            color: var(--abu);
            font-size: 12px;
        }

        /* KARTU WISATA */
        .wisata-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            width: 100%;
        }

        .wisata-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid #e9eee9;
            border-radius: 17px;
            background: white;
            box-shadow: 0 6px 22px rgba(25, 60, 43, .055);
            transition: transform .25s, box-shadow .25s;
        }

        .wisata-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(25, 60, 43, .12);
        }

        .card-image-wrap {
            position: relative;
            height: 205px;
            overflow: hidden;
            background: #e9eee9;
        }

        .card-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform .35s;
        }

        .wisata-card:hover .card-image {
            transform: scale(1.04);
        }

        .category-badge {
            position: absolute;
            top: 13px;
            left: 13px;
            padding: 6px 10px;
            border-radius: 30px;
            background: rgba(255, 255, 255, .94);
            color: var(--hijau-tua);
            font-size: 10px;
            font-weight: 800;
        }

        .favorite-button {
            position: absolute;
            top: 11px;
            right: 11px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, .95);
            color: #58675e;
            font-size: 19px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .favorite-button.active {
            color: #e34c5b;
        }

        .card-body {
            padding: 18px;
        }

        .card-location {
            margin-bottom: 7px;
            color: #748278;
            font-size: 11px;
        }

        .card-location span {
            margin-right: 4px;
        }

        .card-title {
            margin: 0 0 9px;
            color: #20382b;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.35;
        }

        .card-description {
            display: -webkit-box;
            min-height: 58px;
            margin: 0 0 15px;
            overflow: hidden;
            color: #748078;
            font-size: 12px;
            line-height: 1.7;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        /* TOMBOL DETAIL: DIPERLEBAR MEMENUHI RUANG KARTU */
        .card-footer {
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            width: 100%;
            gap: 9px;
            padding-top: 14px;
            border-top: 1px solid #edf0ed;
        }

        .detail-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 55px;
            padding: 15px 12px;
            box-sizing: border-box;
            border-radius: 12px;
            background: var(--hijau);
            color: #fff;
            text-align: center;
            font-size: 15px;
            font-weight: 800;
            transition: background .2s;
        }

        .detail-button:hover {
            background: var(--hijau-tua);
        }

        .empty-state {
            padding: 48px 20px;
            border: 1px dashed #d7e2d8;
            border-radius: 18px;
            background: white;
            text-align: center;
        }

        .empty-state .empty-icon {
            margin-bottom: 10px;
            font-size: 35px;
        }

        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 19px;
        }

        .empty-state p {
            margin: 0 0 18px;
            color: var(--abu);
            font-size: 13px;
        }

        .empty-state a {
            display: inline-flex;
            padding: 10px 16px;
            border-radius: 9px;
            background: var(--hijau);
            color: white;
            font-size: 12px;
            font-weight: 700;
        }

        /* FOOTER */
        .site-footer {
            padding: 25px 0;
            border-top: 1px solid var(--garis);
            background: white;
        }

        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            color: #7a877f;
            font-size: 11px;
        }

        .footer-brand {
            color: var(--hijau-tua);
            font-weight: 800;
        }

        /* KEMBALI KE ATAS */
        .back-top {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 10;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: var(--hijau);
            color: white;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
        }

        /* TABLET */
        @media (max-width: 1000px) {
            .page-container {
                width: calc(100% - 36px);
            }

            .search-form {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 13px;
            }

            .search-actions {
                grid-column: 1 / -1;
            }

            .search-button,
            .reset-button {
                flex: 0 0 auto;
            }

            .wisata-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
            }

            .hero {
                min-height: 310px;
            }
        }

        /* HP */
        @media (max-width: 600px) {
            .page-container {
                width: calc(100% - 28px);
            }

            .header-inner {
                min-height: 68px;
                gap: 10px;
            }

            .brand-icon {
                width: 35px;
                height: 35px;
                font-size: 19px;
            }

            .brand-name {
                font-size: 17px;
            }

            .brand-subtitle {
                font-size: 8px;
                letter-spacing: 1.3px;
            }

            .header-right {
                gap: 8px;
            }

            .portal-text {
                display: none;
            }

            .admin-link {
                min-height: 35px;
                padding: 0 10px;
                border-radius: 9px;
                font-size: 10px;
            }

            .hero {
                min-height: 310px;
                margin-top: 12px;
                border-radius: 17px;
                background-position: center;
            }

            .hero-content {
                padding: 27px 22px;
            }

            .hero-label {
                margin-bottom: 13px;
                font-size: 9px;
            }

            .hero h1 {
                font-size: 34px;
            }

            .hero p {
                font-size: 12px;
            }

            .search-panel {
                width: 100%;
                margin-top: 15px;
                margin-bottom: 30px;
                padding: 18px 16px;
                border-radius: 16px;
            }

            .search-heading {
                margin-bottom: 17px;
            }

            .search-heading h2 {
                font-size: 15px;
            }

            .search-form {
                grid-template-columns: minmax(0, 1fr);
                gap: 13px;
            }

            .search-form .field input,
            .search-form .field select {
                height: 44px;
                font-size: 12px;
            }

            .search-actions {
                grid-column: auto;
                width: 100%;
                gap: 9px;
            }

            .search-button,
            .reset-button {
                height: 44px;
                min-height: 44px;
                flex: 1;
                padding: 0 10px;
                font-size: 11px;
            }

            .section-top {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .section-top h2 {
                font-size: 27px;
            }

            .section-description {
                font-size: 11px;
            }

            .wisata-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 17px;
            }

            .card-image-wrap {
                height: 220px;
            }

            .card-body {
                padding: 16px;
            }

            .card-title {
                font-size: 17px;
            }

            /* TOMBOL DETAIL DI HP */
            .card-footer {
                width: 100%;
                padding-top: 13px;
            }

            .detail-button {
                width: 100%;
                min-height: 52px;
                padding: 13px 10px;
                font-size: 14px;
            }

            .footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="page-container header-inner">
        <a class="brand" href="index.php" aria-label="Jelajah Banten beranda">
            <div class="brand-icon">🌿</div>
            <div>
                <div class="brand-name">Jelajah Banten</div>
                <div class="brand-subtitle">WISATA ALAM &amp; BUDAYA</div>
            </div>
        </a>

        <div class="header-right">
            <span class="portal-text">Portal Wisata Banten</span>
            <a class="admin-link" href="admin/index.php">♙ Login Admin</a>
        </div>
    </div>
</header>

<main class="page-container">

    <section class="hero">
        <div class="hero-content">
            <div class="hero-label">✦ JELAJAHI PESONA BANTEN</div>

            <h1>
                Temukan Keindahan
                <span>Alam Banten.</span>
            </h1>

            <p>
                Dari pantai tropis yang memukau hingga perbukitan hijau
                yang menenangkan, temukan destinasi wisata Banten dan
                rencanakan petualangan seru bersama orang tersayang.
            </p>

            <div class="hero-actions">
                <a href="#destinasi" class="hero-button">
                    Jelajahi Destinasi ↓
                </a>
                <span class="hero-note">🌱 Alam indah, pengalaman berkesan</span>
            </div>
        </div>
    </section>

    <!-- PENCARIAN WISATA -->
    <section class="search-panel" aria-labelledby="search-title">
        <div class="search-heading">
            <div class="search-heading-icon">⌕</div>
            <h2 id="search-title">Temukan Wisata Favoritmu</h2>
        </div>

        <form action="index.php" method="GET" class="search-form">
            <div class="field">
                <label for="cari">Nama, lokasi, atau wisata</label>
                <input
                    type="search"
                    id="cari"
                    name="cari"
                    placeholder="Contoh: Pantai Anyer..."
                    value="<?= e($cari) ?>"
                >
            </div>

            <div class="field">
                <label for="kategori">Kategori wisata</label>
                <select id="kategori" name="kategori">
                    <option value="Semua"
                        <?= strtolower($kategori) === 'semua' ? 'selected' : '' ?>>
                        Semua Kategori
                    </option>

                    <?php foreach ($daftarKategori as $namaKategori): ?>
                        <option
                            value="<?= e($namaKategori) ?>"
                            <?= $kategori === $namaKategori ? 'selected' : '' ?>
                        >
                            <?= e($namaKategori) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="search-actions">
                <button type="submit" class="search-button">
                    🔎 Cari Wisata
                </button>

                <a href="index.php" class="reset-button">
                    Reset
                </a>
            </div>
        </form>
    </section>

    <!-- DAFTAR WISATA -->
    <section class="destinations" id="destinasi">
        <div class="section-top">
            <div>
                <div class="section-eyebrow">Destinasi Pilihan</div>
                <h2>Jelajahi Wisata Banten</h2>
                <p class="section-description">
                    Temukan tempat menarik, simpan destinasi favoritmu,
                    dan mulai rencanakan perjalananmu.
                </p>
            </div>

            <div class="result-count">
                <?= count($wisata) ?> wisata ditemukan
            </div>
        </div>

        <?php if ($cari !== '' || (strtolower($kategori) !== 'semua' && $kategori !== '')): ?>
            <p class="filter-info">
                Hasil pencarian
                <?php if ($cari !== ''): ?>
                    untuk <strong><?= e($cari) ?></strong>
                <?php endif; ?>

                <?php if (strtolower($kategori) !== 'semua' && $kategori !== ''): ?>
                    dalam kategori <strong><?= e($kategori) ?></strong>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (count($wisata) > 0): ?>
            <div class="wisata-grid">
                <?php foreach ($wisata as $w): ?>
                    <?php
                        $idWisata = (int) $w['id'];
                        $namaWisata = (string) ($w['nama'] ?? '');
                        $lokasiWisata = (string) ($w['lokasi'] ?? '');
                        $kategoriWisata = (string) ($w['kategori'] ?? 'Wisata');
                        $deskripsiWisata = (string) ($w['deskripsi'] ?? '');
                    ?>

                    <article class="wisata-card">
                        <div class="card-image-wrap">
                            <img
                                class="card-image"
                                src="<?= e(gambarWisata($w['foto'] ?? '')) ?>"
                                alt="<?= e($namaWisata) ?>"
                                loading="lazy"
                            >

                            <span class="category-badge">
                                <?= e($kategoriWisata) ?>
                            </span>

                            <button
                                type="button"
                                class="favorite-button"
                                data-id="<?= $idWisata ?>"
                                aria-label="Simpan <?= e($namaWisata) ?> ke favorit"
                                aria-pressed="false"
                                title="Simpan ke favorit"
                            >♡</button>
                        </div>

                        <div class="card-body">
                            <div class="card-location">
                                <span>📍</span><?= e($lokasiWisata) ?>
                            </div>

                            <h3 class="card-title">
                                <?= e($namaWisata) ?>
                            </h3>

                            <p class="card-description">
                                <?= e($deskripsiWisata !== ''
                                    ? $deskripsiWisata
                                    : 'Jelajahi keindahan destinasi wisata ini di Banten.') ?>
                            </p>

                            <div class="card-footer">
                                <a
                                    class="detail-button"
                                    href="detail.php?id=<?= $idWisata ?>"
                                >
                                    Lihat Detail Wisata →
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🌿</div>
                <h3>Wisata Tidak Ditemukan</h3>
                <p>
                    Data wisata yang kamu cari belum ditemukan.
                    Coba kata pencarian atau kategori lainnya.
                </p>
                <a href="index.php">Tampilkan Semua Wisata</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <div class="page-container footer-inner">
        <div>
            <span class="footer-brand">🌿 Jelajah Banten</span>
            — Temukan keindahan alam dan budaya Banten.
        </div>
        <div>© <?= date('Y') ?> Jelajah Banten</div>
    </div>
</footer>

<button
    type="button"
    class="back-top"
    id="backTop"
    aria-label="Kembali ke atas"
    title="Kembali ke atas"
>↑</button>

<script>
/* =========================
   FITUR WISATA FAVORIT
   Disimpan di browser
========================= */
(function () {
    const storageKey = 'jelajahBantenFavorites';
    const favoriteButtons = document.querySelectorAll('.favorite-button');

    function getFavorites() {
        try {
            const data = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(data) ? data.map(String) : [];
        } catch (error) {
            return [];
        }
    }

    function saveFavorites(favorites) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(favorites));
        } catch (error) {
            console.warn('Favorit tidak dapat disimpan di browser.');
        }
    }

    function updateButton(button, active) {
        button.classList.toggle('active', active);
        button.textContent = active ? '♥' : '♡';
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.title = active ? 'Hapus dari favorit' : 'Simpan ke favorit';
    }

    let favorites = getFavorites();

    favoriteButtons.forEach(function (button) {
        const id = String(button.dataset.id);
        updateButton(button, favorites.includes(id));

        button.addEventListener('click', function () {
            favorites = getFavorites();

            if (favorites.includes(id)) {
                favorites = favorites.filter(function (item) {
                    return item !== id;
                });
                updateButton(button, false);
            } else {
                favorites.push(id);
                updateButton(button, true);
            }

            saveFavorites(favorites);
        });
    });
})();

/* =========================
   TOMBOL KEMBALI KE ATAS
========================= */
document.getElementById('backTop').addEventListener('click', function () {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
</script>

</body>
</html>
