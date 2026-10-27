<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$conn->set_charset("utf8mb4");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function go($message = '', $type = 'success') {
    if ($message !== '') {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: index.php');
    exit;
}

function checkCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function getWisata($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM wisata WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

function val($key) {
    return trim((string)($_POST[$key] ?? ''));
}

$isLoggedIn = !empty($_SESSION['admin_id']);
$action = $_POST['action'] ?? '';

/* PROSES FORM */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    if ($action === 'login') {
        $username = val('username');
        $password = (string)($_POST['password'] ?? '');

        $stmt = $conn->prepare(
            "SELECT id, username, password FROM admin WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        }

        go('Username atau password salah.', 'error');
    }

    if (empty($_SESSION['admin_id'])) {
        go('Silakan login terlebih dahulu.', 'error');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($action === 'tambah' || $action === 'edit') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($action === 'edit' && (!$id || !getWisata($conn, $id))) {
            go('Data wisata tidak ditemukan.', 'error');
        }

        $nama = val('nama');
        $lokasi = val('lokasi');
        $kategori = val('kategori');
        $deskripsi = val('deskripsi');
        $foto = basename(val('foto'));
        $aktivitas = val('aktivitas');
        $harga = val('harga_tiket');
        $jam = val('jam_buka');
        $fasilitas = val('fasilitas');

        if ($nama === '' || $lokasi === '' || $kategori === '' || $deskripsi === '') {
            go('Nama, lokasi, kategori, dan deskripsi wajib diisi.', 'error');
        }

        if ($action === 'tambah') {
            $stmt = $conn->prepare(
                "INSERT INTO wisata
                (nama,lokasi,kategori,deskripsi,foto,aktivitas,harga_tiket,jam_buka,fasilitas)
                VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                "sssssssss",
                $nama, $lokasi, $kategori, $deskripsi, $foto,
                $aktivitas, $harga, $jam, $fasilitas
            );
            $berhasil = $stmt->execute();
            $stmt->close();

            go(
                $berhasil ? 'Wisata berhasil ditambahkan.' : 'Gagal menambahkan wisata.',
                $berhasil ? 'success' : 'error'
            );
        }

        $stmt = $conn->prepare(
            "UPDATE wisata SET nama=?,lokasi=?,kategori=?,deskripsi=?,
            foto=?,aktivitas=?,harga_tiket=?,jam_buka=?,fasilitas=? WHERE id=?"
        );
        $stmt->bind_param(
            "sssssssssi",
            $nama, $lokasi, $kategori, $deskripsi, $foto,
            $aktivitas, $harga, $jam, $fasilitas, $id
        );
        $berhasil = $stmt->execute();
        $stmt->close();

        go(
            $berhasil ? 'Data wisata berhasil diperbarui.' : 'Gagal memperbarui data wisata.',
            $berhasil ? 'success' : 'error'
        );
    }

    if ($action === 'hapus') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $wisata = $id ? getWisata($conn, $id) : null;

        if (!$wisata) {
            go('Data wisata tidak ditemukan.', 'error');
        }

        $stmt = $conn->prepare("DELETE FROM wisata WHERE id=?");
        $stmt->bind_param("i", $id);
        $berhasil = $stmt->execute();
        $stmt->close();

        if ($berhasil) {
            $namaFoto = basename((string)($wisata['foto'] ?? ''));
            $pathFoto = __DIR__ . '/../images/' . $namaFoto;

            if ($namaFoto !== '' && is_file($pathFoto)) {
                @unlink($pathFoto);
            }

            go('Data wisata berhasil dihapus.');
        }

        go('Gagal menghapus data wisata.', 'error');
    }

    if ($action === 'password') {
        $lama = (string)($_POST['password_lama'] ?? '');
        $baru = (string)($_POST['password_baru'] ?? '');
        $konfirmasi = (string)($_POST['konfirmasi_password'] ?? '');

        if ($lama === '' || $baru === '' || $konfirmasi === '') {
            go('Semua kolom password wajib diisi.', 'error');
        }

        if (strlen($baru) < 10) {
            go('Password baru minimal 10 karakter.', 'error');
        }

        if ($baru !== $konfirmasi) {
            go('Konfirmasi password tidak cocok.', 'error');
        }

        $idAdmin = (int)$_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $idAdmin);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin || !password_verify($lama, $admin['password'])) {
            go('Password lama salah.', 'error');
        }

        $hash = password_hash($baru, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $idAdmin);
        $berhasil = $stmt->execute();
        $stmt->close();

        go(
            $berhasil ? 'Password berhasil diganti.' : 'Gagal mengganti password.',
            $berhasil ? 'success' : 'error'
        );
    }
}

/* DATA HALAMAN */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$isLoggedIn = !empty($_SESSION['admin_id']);
$page = $_GET['page'] ?? 'dashboard';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editData = null;

if ($isLoggedIn && $editId) {
    $editData = getWisata($conn, $editId);
    $page = 'edit';
}

$totalWisata = 0;
$totalKategori = 0;
$daftarWisata = [];
$cari = trim((string)($_GET['cari'] ?? ''));

if ($isLoggedIn) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM wisata");
    if ($res) {
        $totalWisata = (int)$res->fetch_assoc()['total'];
    }

    $res = $conn->query(
        "SELECT COUNT(DISTINCT kategori) AS total FROM wisata WHERE kategori <> ''"
    );
    if ($res) {
        $totalKategori = (int)$res->fetch_assoc()['total'];
    }

    if ($cari !== '') {
        $keyword = '%' . $cari . '%';
        $stmt = $conn->prepare(
            "SELECT id,nama,lokasi,kategori,foto,harga_tiket,jam_buka
            FROM wisata
            WHERE nama LIKE ? OR lokasi LIKE ? OR kategori LIKE ?
            ORDER BY id DESC"
        );
        $stmt->bind_param("sss", $keyword, $keyword, $keyword);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $daftarWisata[] = $row;
        }
        $stmt->close();
    } else {
        $result = $conn->query(
            "SELECT id,nama,lokasi,kategori,foto,harga_tiket,jam_buka
            FROM wisata ORDER BY id DESC"
        );

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $daftarWisata[] = $row;
            }
        }
    }
}

$baseUrl = '/smkjayabuana_vokasi/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Jelajah Banten</title>

<style>
:root {
    --green: #176b45;
    --dark: #104b32;
    --pale: #e9f6ee;
    --bg: #f4f7f5;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #e5e7eb;
    --red: #dc2626;
}

* {
    box-sizing: border-box;
}

html {
    width: 100%;
    max-width: 100%;
    overflow-x: clip;
}

body {
    margin: 0;
    width: 100%;
    max-width: 100%;
    overflow-x: clip;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--text);
    background: var(--bg);
}

button, input, textarea {
    font: inherit;
}

a {
    color: inherit;
    text-decoration: none;
}

img {
    max-width: 100%;
}

.topbar {
    width: 100%;
    color: white;
    background: linear-gradient(120deg, var(--dark), var(--green));
    padding: 17px 24px;
}

.topbar-inner {
    width: 100%;
    max-width: 1400px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.brand-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: #ffffff24;
    font-size: 24px;
}

.brand h1 {
    margin: 0 0 5px;
    font-size: 20px;
}

.brand p {
    margin: 0;
    font-size: 12px;
    opacity: .85;
}

.topbar-user {
    flex-shrink: 0;
    font-size: 12px;
    line-height: 1.6;
    text-align: right;
    overflow-wrap: anywhere;
}

.layout {
    width: 100%;
    max-width: 1400px;
    padding: 0 22px;
    margin: 24px auto;
    display: grid;
    grid-template-columns: 225px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
}

.sidebar {
    min-width: 0;
    position: sticky;
    top: 15px;
    background: white;
    padding: 13px;
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 5px 22px #183b2610;
}

.sidebar-label {
    font-size: 10px;
    letter-spacing: 1px;
    color: var(--muted);
    font-weight: bold;
    padding: 10px;
}

.sidebar nav {
    display: grid;
    gap: 5px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    padding: 12px 10px;
    border-radius: 10px;
    font-size: 13px;
    color: #374151;
    overflow-wrap: anywhere;
}

.nav-link:hover,
.nav-link.active {
    background: var(--pale);
    color: var(--dark);
    font-weight: bold;
}

.nav-icon {
    flex: 0 0 21px;
    text-align: center;
}

.nav-divider {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 12px 0;
}

.logout-form {
    margin: 0;
}

.logout-button {
    border: 0;
    width: 100%;
    text-align: left;
    background: #fff0f0;
    color: #b91c1c;
    cursor: pointer;
}

.main {
    min-width: 0;
    width: 100%;
}

.welcome {
    background: linear-gradient(120deg, #e3f4e7, #f8fcf8);
    border: 1px solid #d4ebda;
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 20px;
}

.welcome h2 {
    margin: 0 0 8px;
    font-size: 22px;
    color: var(--dark);
}

.welcome p {
    margin: 0;
    line-height: 1.7;
    color: #466052;
    font-size: 13px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
}

.page-heading h2 {
    margin: 0 0 7px;
    font-size: 25px;
    color: var(--dark);
}

.page-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 20px;
}

.stat-icon {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    flex: 0 0 50px;
    border-radius: 14px;
    background: var(--pale);
    font-size: 24px;
}

.stat-card small {
    display: block;
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 7px;
}

.stat-card strong {
    font-size: 26px;
    color: var(--dark);
    overflow-wrap: anywhere;
}

.panel {
    width: 100%;
    min-width: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 21px;
    margin-bottom: 20px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.panel-header h3 {
    margin: 0;
    font-size: 17px;
}

.panel-header p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 11px 14px;
    border: 0;
    border-radius: 9px;
    background: var(--green);
    color: white;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    cursor: pointer;
    overflow-wrap: anywhere;
}

.btn:hover {
    background: var(--dark);
}

.btn-secondary {
    background: #edf1ee;
    color: #374151;
}

.btn-secondary:hover {
    background: #dfe7e1;
}

.btn-danger {
    background: var(--red);
}

.btn-danger:hover {
    background: #b91c1c;
}

.btn-small {
    padding: 9px 10px;
    font-size: 11px;
}

.alert {
    border-radius: 10px;
    padding: 13px 15px;
    margin-bottom: 18px;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.alert-success {
    background: #e8f7ec;
    color: #176b37;
    border: 1px solid #b9e5c5;
}

.alert-error {
    background: #fff0f0;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

input, textarea {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    outline: none;
    background: white;
    color: var(--text);
}

input:focus, textarea:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px #176b4514;
}

textarea {
    min-height: 105px;
    resize: vertical;
    line-height: 1.6;
}

label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
}

.field {
    min-width: 0;
    margin-bottom: 17px;
}

.help {
    display: block;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
    margin-top: 7px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
}

.full-width {
    grid-column: 1 / -1;
}

.form-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.search-form {
    display: flex;
    gap: 9px;
    margin-bottom: 17px;
}

.search-form input {
    flex: 1;
    min-width: 0;
}

.table-wrap {
    width: 100%;
    min-width: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
}

thead {
    background: #f1f7f2;
}

th, td {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
    line-height: 1.5;
    overflow-wrap: anywhere;
    word-break: break-word;
}

th {
    color: #374151;
}

.pill {
    display: inline-block;
    max-width: 100%;
    padding: 5px 8px;
    border-radius: 20px;
    background: var(--pale);
    color: var(--dark);
    font-size: 10px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.table-actions form {
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 30px 12px;
    color: var(--muted);
    line-height: 1.7;
}

.empty-icon {
    font-size: 35px;
    margin-bottom: 12px;
}

.login-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 18px;
    background: linear-gradient(135deg, #edf7ef, #f8faf8);
}

.login-card {
    width: 100%;
    max-width: 420px;
    min-width: 0;
    padding: 28px;
    border-radius: 20px;
    background: white;
    border: 1px solid #e1e9e2;
    box-shadow: 0 15px 50px #143c2814;
}

.login-brand {
    text-align: center;
    margin-bottom: 25px;
}

.login-brand .brand-icon {
    margin: 0 auto 14px;
    width: 62px;
    height: 62px;
    background: var(--pale);
    font-size: 30px;
}

.login-brand h1 {
    margin: 0 0 8px;
    color: var(--dark);
    font-size: 24px;
}

.login-brand p,
.login-footer {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.login-brand p {
    margin: 0;
}

.login-card .btn {
    width: 100%;
    padding: 13px;
}

.login-footer {
    text-align: center;
    margin-top: 18px;
}

/* TABLET */
@media (max-width: 1000px) {
    .layout {
        grid-template-columns: 190px minmax(0, 1fr);
        gap: 14px;
        padding: 0 14px;
    }

    .sidebar {
        padding: 9px;
    }

    .panel {
        padding: 16px;
    }

    th, td {
        padding: 10px 6px;
        font-size: 11px;
    }
}

/* HP: SEMUA KONTEN MENYESUAIKAN LEBAR LAYAR */
@media (max-width: 760px) {
    .topbar {
        padding: 13px 12px;
    }

    .topbar-inner {
        gap: 8px;
    }

    .brand {
        gap: 8px;
    }

    .brand-icon {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
        border-radius: 10px;
        font-size: 19px;
    }

    .brand h1 {
        font-size: 15px;
    }

    .brand p {
        font-size: 10px;
    }

    .topbar-user {
        max-width: 105px;
        font-size: 10px;
    }

    .layout {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
        max-width: 100%;
        margin: 12px 0;
        padding: 0 10px;
    }

    .sidebar {
        position: static;
        width: 100%;
        padding: 8px;
        margin: 0;
        border-radius: 12px;
    }

    .sidebar-label,
    .nav-divider {
        display: none;
    }

    .sidebar nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
    }

    .nav-link {
        justify-content: flex-start;
        min-width: 0;
        min-height: 40px;
        padding: 9px 7px;
        margin: 0;
        gap: 5px;
        font-size: 11px;
    }

    .nav-icon {
        flex-basis: 17px;
        font-size: 13px;
    }

    .logout-form {
        margin-top: 6px;
    }

    .logout-button {
        justify-content: center;
    }

    .main {
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    .welcome {
        padding: 15px;
        margin-bottom: 14px;
        border-radius: 12px;
    }

    .welcome h2 {
        font-size: 18px;
        line-height: 1.4;
    }

    .welcome p {
        font-size: 12px;
    }

    .page-heading {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 14px;
    }

    .page-heading h2 {
        font-size: 21px;
    }

    .page-heading p {
        font-size: 12px;
    }

    .page-heading > .btn {
        width: 100%;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 14px;
    }

    .stat-card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 12px 10px;
        border-radius: 12px;
    }

    .stat-icon {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
        border-radius: 9px;
        font-size: 17px;
    }

    .stat-card small {
        font-size: 10px;
        line-height: 1.5;
    }

    .stat-card strong {
        font-size: 22px;
    }

    .panel {
        width: 100%;
        min-width: 0;
        padding: 13px 10px;
        margin-bottom: 14px;
        border-radius: 12px;
    }

    .panel-header {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 14px;
    }

    .panel-header .btn {
        width: 100%;
    }

    .form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 0;
    }

    .full-width {
        grid-column: auto;
    }

    input, textarea {
        font-size: 16px;
    }

    .form-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }

    .form-actions .btn {
        width: 100%;
        min-height: 42px;
    }

    .search-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }

    .search-form > * {
        width: 100%;
        min-width: 0;
    }

    /* TABEL DIUBAH MENJADI KARTU, BUKAN DIGESER */
    .table-wrap {
        width: 100%;
        max-width: 100%;
        overflow: visible;
    }

    .table-wrap table {
        display: block;
        width: 100%;
        min-width: 0;
        table-layout: fixed;
        border: 0;
    }

    .table-wrap thead {
        display: none;
    }

    .table-wrap tbody {
        display: block;
        width: 100%;
    }

    .table-wrap tbody tr {
        display: block;
        width: 100%;
        min-width: 0;
        margin: 0 0 12px;
        padding: 10px;
        border: 1px solid #e1e9e3;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 3px 12px #143c280c;
    }

    .table-wrap tbody td {
        display: grid;
        grid-template-columns: minmax(75px, 31%) minmax(0, 1fr);
        align-items: start;
        gap: 8px;
        width: 100%;
        min-width: 0;
        padding: 9px 0;
        border-bottom: 1px solid #edf0ed;
        font-size: 12px;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .table-wrap tbody td::before {
        font-weight: bold;
        color: #65736a;
        overflow-wrap: anywhere;
    }

    .table-wrap tbody td:nth-child(1)::before {
        content: "Nomor";
    }

    .table-wrap tbody td:nth-child(2)::before {
        content: "Nama wisata";
    }

    .table-wrap tbody td:nth-child(3)::before {
        content: "Lokasi";
    }

    .table-wrap tbody td:nth-child(4)::before {
        content: "Kategori";
    }

    .table-wrap tbody td:nth-child(5)::before {
        content: "Harga tiket";
    }

    .table-wrap tbody td:nth-child(6)::before {
        content: "Jam buka";
    }

    .table-wrap tbody td:nth-child(7)::before {
        content: "Aksi";
    }

    .table-wrap tbody td:last-child {
        border-bottom: 0;
        padding-top: 11px;
    }

    .table-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 7px;
        width: 100%;
    }

    .table-actions form,
    .table-actions .btn {
        width: 100%;
        min-width: 0;
    }

    .table-actions .btn {
        min-height: 38px;
    }

    .alert {
        padding: 11px;
        font-size: 12px;
    }

    .login-page {
        padding: 14px;
    }

    .login-card {
        padding: 23px 17px;
    }
}

/* HP KECIL */
@media (max-width: 380px) {
    .topbar {
        padding: 12px 9px;
    }

    .brand h1 {
        font-size: 13px;
    }

    .brand p {
        font-size: 9px;
    }

    .topbar-user {
        max-width: 82px;
        font-size: 9px;
    }

    .layout {
        padding: 0 7px;
    }

    .panel {
        padding: 11px 8px;
    }

    .nav-link {
        font-size: 10px;
        padding: 8px 5px;
    }

    .table-wrap tbody tr {
        padding: 8px;
    }

    .table-wrap tbody td {
        grid-template-columns: minmax(65px, 31%) minmax(0, 1fr);
        gap: 6px;
        font-size: 11px;
    }

    .stat-card {
        padding: 10px 8px;
    }

    .stat-card strong {
        font-size: 20px;
    }
}
</style>
</head>

<body>

<?php if (!$isLoggedIn): ?>

<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon">🌿</div>
            <h1>Jelajah Banten</h1>
            <p>Panel Admin Pengelolaan Wisata</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="login">

            <div class="field">
                <label for="username">Username Admin</label>
                <input id="username" name="username" type="text"
                    autocomplete="username" placeholder="Masukkan username" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password"
                    autocomplete="current-password" placeholder="Masukkan password" required>
            </div>

            <button class="btn" type="submit">🔐 Login Admin</button>
        </form>

        <div class="login-footer">
            Kelola informasi destinasi wisata Banten dengan mudah.
        </div>
    </div>
</div>

<?php else: ?>

<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <div class="brand-icon">🌿</div>
            <div>
                <h1>Jelajah Banten</h1>
                <p>Panel Administrasi Wisata</p>
            </div>
        </div>

       <div class="topbar-user">
    <span style="font-size:13px; color:#ffffff !important; font-weight:700; opacity:1 !important; -webkit-text-fill-color:#ffffff;">
        ADMIN DASHBOARD
    </span>
</div>

    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-label">MENU UTAMA</div>

        <nav>
            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <a class="nav-link <?= $page === 'tambah' ? 'active' : '' ?>" href="index.php?page=tambah">
                <span class="nav-icon">➕</span> Tambah Wisata
            </a>

            <a class="nav-link <?= $page === 'password' ? 'active' : '' ?>" href="index.php?page=password">
                <span class="nav-icon">🔑</span> Ganti Password
            </a>

            <a class="nav-link" href="<?= e($baseUrl) ?>" target="_blank" rel="noopener">
                <span class="nav-icon">🌐</span> Lihat Website
            </a>
        </nav>

        <hr class="nav-divider">

        <form class="logout-form" method="POST"
            onsubmit="return confirm('Yakin ingin logout?')">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="logout">

            <button type="submit" class="nav-link logout-button">
                <span class="nav-icon">🚪</span> Logout
            </button>
        </form>
    </aside>

    <main class="main">

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'tambah' || $page === 'edit'): ?>

            <?php
            $isEdit = ($page === 'edit' && $editData);
            $dataForm = $isEdit ? $editData : [
                'id' => '',
                'nama' => '',
                'lokasi' => '',
                'kategori' => '',
                'deskripsi' => '',
                'foto' => '',
                'aktivitas' => '',
                'harga_tiket' => '',
                'jam_buka' => '',
                'fasilitas' => ''
            ];

            // Membaca jam yang tersimpan agar pilihan tetap sesuai saat mengedit.
            $jamTersimpan = trim((string)($dataForm['jam_buka'] ?? ''));
            $jam24 = preg_match('/24\s*jam/i', $jamTersimpan) === 1;
            $jamMulai = '08:00';
            $jamSelesai = '17:00';

            if (preg_match('/(\d{1,2})[:.](\d{2}).*?(\d{1,2})[:.](\d{2})/', $jamTersimpan, $hasilJam)) {
                $jamMulai = sprintf('%02d:%02d', (int)$hasilJam[1], (int)$hasilJam[2]);
                $jamSelesai = sprintf('%02d:%02d', (int)$hasilJam[3], (int)$hasilJam[4]);
            }
            ?>

            <div class="page-heading">
                <div>
                    <h2><?= $isEdit ? '✏️ Edit Wisata' : '➕ Tambah Wisata' ?></h2>
                    <p>Kelola informasi destinasi wisata.</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Kembali</a>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Informasi Destinasi</h3>
                        <p>Lengkapi data wisata dengan informasi yang benar.</p>
                    </div>
                </div>

                <?php if ($page === 'edit' && !$editData): ?>
                    <div class="alert alert-error">
                        Data wisata tidak ditemukan. Silakan kembali ke dashboard.
                    </div>
                <?php else: ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'tambah' ?>">

                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= (int)$dataForm['id'] ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="field">
                            <label for="nama">Nama Wisata *</label>
                            <input id="nama" name="nama"
                                value="<?= e($dataForm['nama']) ?>"
                                placeholder="Contoh: Pantai Anyer" required>
                        </div>

                        <div class="field">
                            <label for="lokasi">Lokasi *</label>
                            <input id="lokasi" name="lokasi"
                                value="<?= e($dataForm['lokasi']) ?>"
                                placeholder="Serang, Banten" required>
                        </div>

                        <div class="field">
                            <label for="kategori">Kategori *</label>
                            <input id="kategori" name="kategori"
                                value="<?= e($dataForm['kategori']) ?>"
                                placeholder="Pantai, Pulau, Sejarah" required>
                        </div>

                        <div class="field">
                            <label for="foto">Nama File Foto</label>
                            <input id="foto" name="foto"
                                value="<?= e($dataForm['foto']) ?>"
                                placeholder="pantai_anyer.webp">
                            <span class="help">Simpan foto di folder images dan isi nama filenya saja.</span>
                        </div>

                        <div class="field">
                            <label for="harga_tiket">Harga Tiket</label>
                            <input id="harga_tiket" name="harga_tiket"
                                value="<?= e($dataForm['harga_tiket']) ?>"
                                placeholder="Rp 10.000 per orang">
                        </div>

                        <div class="field">
                            <label for="jenis_jam">Jam Operasional</label>

                            <select id="jenis_jam" style="display:block;width:100%;padding:12px;border:1px solid #d1d5db;border-radius:9px;background:white;margin-bottom:12px;color:var(--text);font-size:13px;">
                                <option value="normal" <?= !$jam24 ? 'selected' : '' ?>>Pilih Jam Buka dan Tutup</option>
                                <option value="24" <?= $jam24 ? 'selected' : '' ?>>Buka 24 Jam</option>
                            </select>

                            <div id="pilihan_jam" style="<?= $jam24 ? 'display:none;' : '' ?>">
                                <label for="jam_mulai">Jam Buka</label>
                                <input type="time" id="jam_mulai" value="<?= e($jamMulai) ?>">

                                <label for="jam_selesai" style="margin-top:12px">Jam Tutup</label>
                                <input type="time" id="jam_selesai" value="<?= e($jamSelesai) ?>">
                            </div>

                            <input type="hidden" id="jam_buka" name="jam_buka" value="<?= e($jamTersimpan) ?>">
                            <span class="help">Pilih jam buka dan tutup menggunakan pemilih waktu, atau pilih Buka 24 Jam.</span>
                        </div>

                        <div class="field full-width">
                            <label for="deskripsi">Deskripsi Wisata *</label>
                            <textarea id="deskripsi" name="deskripsi"
                                placeholder="Deskripsi destinasi wisata..." required><?= e($dataForm['deskripsi']) ?></textarea>
                        </div>

                        <div class="field full-width">
                            <label for="aktivitas">Aktivitas Wisata</label>
                            <textarea id="aktivitas" name="aktivitas"
                                placeholder="Aktivitas yang bisa dilakukan pengunjung..."><?= e($dataForm['aktivitas']) ?></textarea>
                        </div>

                        <div class="field full-width">
                            <label for="fasilitas">Fasilitas</label>
                            <textarea id="fasilitas" name="fasilitas"
                                placeholder="Area parkir, toilet, musala, dan lainnya..."><?= e($dataForm['fasilitas']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn" type="submit">
                            <?= $isEdit ? '💾 Simpan Perubahan' : '➕ Simpan Wisata' ?>
                        </button>
                        <a class="btn btn-secondary" href="index.php">Batal</a>
                    </div>
                </form>

                <?php endif; ?>
            </section>

        <?php elseif ($page === 'password'): ?>

            <div class="page-heading">
                <div>
                    <h2>🔑 Ganti Password</h2>
                    <p>Ubah password untuk menjaga keamanan akun admin.</p>
                </div>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Keamanan Akun</h3>
                        <p>Password baru harus minimal 10 karakter.</p>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="password">

                    <div class="field">
                        <label for="password_lama">Password Lama</label>
                        <input type="password" id="password_lama" name="password_lama"
                            autocomplete="current-password" required>
                    </div>

                    <div class="field">
                        <label for="password_baru">Password Baru</label>
                        <input type="password" id="password_baru" name="password_baru"
                            minlength="10" autocomplete="new-password" required>
                    </div>

                    <div class="field">
                        <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                            minlength="10" autocomplete="new-password" required>
                    </div>

                    <div class="form-actions">
                        <button class="btn" type="submit">🔐 Simpan Password Baru</button>
                    </div>
                </form>
            </section>

        <?php else: ?>

            <div class="welcome">
                <h2>Halo, y4kang👋 Ey Santay Tipis Tipis</h2>
                <p>Selamat datang di dashboard Jelajah Banten. Kelola destinasi wisata melalui panel admin ini.</p>
            </div>

            <div class="page-heading">
                <div>
                    <h2>Dashboard</h2>
                    <p>Ringkasan data destinasi wisata.</p>
                </div>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon">🏝️</div>
                    <div>
                        <small>Total Destinasi</small>
                        <strong><?= number_format($totalWisata, 0, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🗂️</div>
                    <div>
                        <small>Total Kategori</small>
                        <strong><?= number_format($totalKategori, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>📍 Data Wisata</h3>
                        <p>Cari, edit, atau hapus destinasi wisata.</p>
                    </div>
                    <a href="index.php?page=tambah" class="btn">＋ Tambah Data</a>
                </div>

                <form class="search-form" method="GET" action="index.php">
                    <input name="cari" value="<?= e($cari) ?>"
                        placeholder="Cari nama, lokasi, atau kategori...">

                    <button class="btn" type="submit">🔍 Cari</button>

                    <?php if ($cari !== ''): ?>
                        <a href="index.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($daftarWisata) > 0): ?>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nama Wisata</th>
                                    <th>Lokasi</th>
                                    <th>Kategori</th>
                                    <th>Harga Tiket</th>
                                    <th>Jam Buka</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($daftarWisata as $i => $w): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><strong><?= e($w['nama']) ?></strong></td>
                                        <td><?= e($w['lokasi']) ?></td>
                                        <td><span class="pill"><?= e($w['kategori']) ?></span></td>
                                        <td><?= e($w['harga_tiket'] ?: 'Belum diisi') ?></td>
                                        <td><?= e($w['jam_buka'] ?: 'Belum diisi') ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="btn btn-small"
                                                    href="index.php?edit=<?= (int)$w['id'] ?>">✏️ Edit</a>

                                                <form method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus wisata ini?')">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="hapus">
                                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">

                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        🗑️ Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>

                    <div class="empty-state">
                        <div class="empty-icon">🔎</div>
                        <strong>Data wisata belum ditemukan.</strong>
                        <p>
                            <?= $cari !== ''
                                ? 'Coba kata pencarian lain atau tampilkan semua data.'
                                : 'Tambahkan destinasi wisata menggunakan tombol Tambah Wisata.' ?>
                        </p>

                        <?php if ($cari !== ''): ?>
                            <a href="index.php" class="btn btn-secondary">Tampilkan Semua Wisata</a>
                        <?php else: ?>
                            <a href="index.php?page=tambah" class="btn">＋ Tambah Wisata</a>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const jenisJam = document.getElementById('jenis_jam');
    const pilihanJam = document.getElementById('pilihan_jam');
    const jamMulai = document.getElementById('jam_mulai');
    const jamSelesai = document.getElementById('jam_selesai');
    const jamBuka = document.getElementById('jam_buka');

    // Skrip hanya aktif pada halaman tambah/edit wisata.
    if (!jenisJam || !pilihanJam || !jamMulai || !jamSelesai || !jamBuka) {
        return;
    }

    function perbaruiJam() {
        if (jenisJam.value === '24') {
            pilihanJam.style.display = 'none';
            jamBuka.value = '24 Jam';
            return;
        }

        pilihanJam.style.display = 'block';
        if (jamMulai.value && jamSelesai.value) {
            jamBuka.value = jamMulai.value + ' - ' + jamSelesai.value + ' WIB';
        } else {
            jamBuka.value = '';
        }
    }

    jenisJam.addEventListener('change', perbaruiJam);
    jamMulai.addEventListener('change', perbaruiJam);
    jamSelesai.addEventListener('change', perbaruiJam);
    perbaruiJam();
});
</script>

</body>
</html>