<?php
require_once __DIR__ . '/config/database.php';

$conn->set_charset("utf8mb4");

function e($teks) {
    return htmlspecialchars((string)($teks ?? ''), ENT_QUOTES, 'UTF-8');
}

function gambarDetail($foto) {
    $namaFile = basename((string)($foto ?? ''));
    $lokal = __DIR__ . '/images/' . $namaFile;

    if ($namaFile !== '' && is_file($lokal)) {
        return 'images/' . rawurlencode($namaFile);
    }

    return '';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    $wisata = null;
} else {
    $stmt = $conn->prepare("SELECT * FROM wisata WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $hasil = $stmt->get_result();
    $wisata = $hasil->fetch_assoc();
    $stmt->close();

    if (!$wisata) {
        http_response_code(404);
    }
}

$nama = $wisata['nama'] ?? '';
$lokasi = $wisata['lokasi'] ?? '';
$kategori = $wisata['kategori'] ?? '';
$deskripsi = $wisata['deskripsi'] ?? '';
$foto = gambarDetail($wisata['foto'] ?? '');

$aktivitas = trim((string)($wisata['aktivitas'] ?? ''));
$harga = trim((string)($wisata['harga_tiket'] ?? ''));
$jam = trim((string)($wisata['jam_buka'] ?? ''));
$fasilitas = trim((string)($wisata['fasilitas'] ?? ''));

$queryGoogle = urlencode($nama . ' ' . $lokasi);
$linkMaps = 'https://www.google.com/maps/search/?api=1&query=' . $queryGoogle;
$linkCari = 'https://www.google.com/search?q=' . $queryGoogle;

$pesanWhatsApp = "Yuk, kunjungi {$nama} yang berlokasi di {$lokasi}!\n\n"
    . "Kategori: {$kategori}\n"
    . "Informasi wisata ini bisa kamu lihat di website Jelajah Banten.\n\n"
    . "Ayo temukan destinasi wisata menarik lainnya di Banten!";

$linkWhatsApp = 'https://wa.me/?text=' . urlencode($pesanWhatsApp);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $wisata ? e($nama) . ' - Jelajah Banten' : 'Wisata Tidak Ditemukan - Jelajah Banten' ?>
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8f4;
            color: #26352b;
            line-height: 1.7;
        }

        header {
            background: #176b3a;
            color: white;
            padding: 18px 6%;
        }

        .logo {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }

        .logo a {
            color: white;
            text-decoration: none;
        }

        .wadah {
            max-width: 1000px;
            width: 92%;
            margin: 30px auto 50px;
        }

        .kartu {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
        }

        .foto-wisata {
            display: block;
            width: 100%;
            height: 420px;
            object-fit: cover;
            background: #e1e9e2;
        }

        .tanpa-foto {
            height: 260px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #e1e9e2;
            color: #607465;
        }

        .isi {
            padding: 28px;
        }

        .kategori {
            display: inline-block;
            padding: 5px 13px;
            background: #e6f4e9;
            color: #176b3a;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
        }

        h1 {
            margin: 12px 0 5px;
            color: #174d2d;
            font-size: 32px;
            line-height: 1.3;
        }

        .lokasi {
            color: #68766c;
            margin-top: 5px;
        }

        .judul-bagian {
            margin: 28px 0 10px;
            color: #176b3a;
            font-size: 20px;
        }

        .deskripsi {
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .grid-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info {
            padding: 17px;
            background: #f5f9f5;
            border: 1px solid #e2ece3;
            border-radius: 12px;
            min-width: 0;
        }

        .info h3 {
            margin: 0 0 7px;
            font-size: 15px;
            color: #176b3a;
        }

        .info p {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .tombol-aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .tombol {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 17px;
            min-height: 46px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .tombol:hover {
            transform: translateY(-2px);
            filter: brightness(0.96);
        }

        .tombol-kembali {
            background: #edf3ee;
            color: #245c37;
            border: 1px solid #d7e5d9;
        }

        .tombol-maps {
            background: #2563eb;
            color: white;
        }

        .tombol-cari {
            background: #e6f4e9;
            color: #176b3a;
            border: 1px solid #cde6d2;
        }

        .tombol-whatsapp {
            background: #25d366;
            color: white;
        }

        .tombol-whatsapp svg {
            width: 21px;
            height: 21px;
            flex-shrink: 0;
        }

        .tidak-ditemukan {
            text-align: center;
            background: white;
            padding: 40px 22px;
            border-radius: 16px;
        }

        .tidak-ditemukan h1 {
            font-size: 26px;
        }

        footer {
            padding: 22px;
            background: #174d2d;
            color: white;
            text-align: center;
        }

        @media (max-width: 650px) {
            header {
                padding: 16px 5%;
            }

            .logo {
                font-size: 21px;
            }

            .wadah {
                margin-top: 20px;
            }

            .foto-wisata {
                height: 250px;
            }

            .isi {
                padding: 20px;
            }

            h1 {
                font-size: 26px;
            }

            .grid-info {
                grid-template-columns: 1fr;
            }

            .tombol-aksi {
                flex-direction: column;
            }

            .tombol {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<header>
    <p class="logo">
        <a href="index.php">🌿 Jelajah Banten</a>
    </p>
</header>

<main class="wadah">

    <?php if (!$wisata): ?>

        <section class="tidak-ditemukan">
            <h1>Wisata Tidak Ditemukan</h1>
            <p>Data wisata tidak tersedia atau mungkin sudah dihapus.</p>
            <a href="index.php" class="tombol tombol-kembali">
                ← Kembali ke Daftar Wisata
            </a>
        </section>

    <?php else: ?>

        <article class="kartu">

            <?php if ($foto !== ''): ?>
                <img
                    src="<?= e($foto) ?>"
                    alt="<?= e($nama) ?>"
                    class="foto-wisata"
                >
            <?php else: ?>
                <div class="tanpa-foto">
                    Foto wisata belum tersedia
                </div>
            <?php endif; ?>

            <div class="isi">

                <span class="kategori"><?= e($kategori ?: 'Wisata') ?></span>

                <h1><?= e($nama) ?></h1>

                <div class="lokasi">
                    📍 <?= e($lokasi ?: 'Lokasi belum tersedia') ?>
                </div>

                <h2 class="judul-bagian">Tentang Wisata</h2>
                <div class="deskripsi"><?= e($deskripsi ?: 'Deskripsi wisata belum tersedia.') ?></div>

                <h2 class="judul-bagian">Informasi Wisata</h2>

                <div class="grid-info">

                    <section class="info">
                        <h3>🎟️ Harga Tiket</h3>
                        <p><?= e($harga !== '' ? $harga : 'Informasi harga tiket belum tersedia.') ?></p>
                    </section>

                    <section class="info">
                        <h3>🕒 Jam Buka</h3>
                        <p><?= e($jam !== '' ? $jam : 'Informasi jam buka belum tersedia.') ?></p>
                    </section>

                    <section class="info">
                        <h3>🏃 Aktivitas</h3>
                        <p><?= nl2br(e($aktivitas !== '' ? $aktivitas : 'Informasi aktivitas belum tersedia.')) ?></p>
                    </section>

                    <section class="info">
                        <h3>🏕️ Fasilitas</h3>
                        <p><?= nl2br(e($fasilitas !== '' ? $fasilitas : 'Informasi fasilitas belum tersedia.')) ?></p>
                    </section>

                </div>

                <div class="tombol-aksi">

                    <a href="index.php" class="tombol tombol-kembali">
                        ← Kembali ke Daftar Wisata
                    </a>

                    <a
                        href="<?= e($linkMaps) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="tombol tombol-maps"
                    >
                        📍 Buka Google Maps
                    </a>

                    <a
                        href="<?= e($linkCari) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="tombol tombol-cari"
                    >
                        🔎 Cari Penelusuran
                    </a>

                    <a
                        href="<?= e($linkWhatsApp) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="tombol tombol-whatsapp"
                    >
                        <svg viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                            <path d="M16 .8A15.1 15.1 0 0 0 3.1 23.7L1 31l7.5-2A15.2 15.2 0 1 0 16 .8zm0 27.6a12.4 12.4 0 0 1-6.3-1.7l-.5-.3-4.5 1.2 1.2-4.4-.3-.5A12.4 12.4 0 1 1 16 28.4zm6.8-9.3c-.4-.2-2.1-1-2.4-1.1-.3-.1-.6-.2-.8.2s-.9 1.1-1.1 1.3c-.2.2-.4.3-.8.1a10 10 0 0 1-3-1.9 11.2 11.2 0 0 1-2.1-2.6c-.2-.4 0-.6.2-.8l.6-.7.4-.6c.1-.2 0-.5 0-.7l-1.1-2.7c-.3-.7-.6-.6-.8-.6h-.7c-.3 0-.7.1-1 .5s-1.3 1.3-1.3 3.1 1.3 3.5 1.5 3.7c.2.2 2.6 4 6.3 5.5.9.4 1.6.6 2.1.7.9.3 1.7.2 2.3.1.7-.1 2.1-.9 2.4-1.8.3-.9.3-1.7.2-1.8-.1-.2-.3-.3-.7-.5z"/>
                        </svg>
                        Bagikan WhatsApp
                    </a>

                </div>

            </div>
        </article>

    <?php endif; ?>

</main>

<footer>
    <p>© <?= date('Y') ?> Jelajah Banten — Temukan Keindahan Alam Banten 🌿</p>
</footer>

</body>
</html>