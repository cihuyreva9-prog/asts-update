<?php

session_start();
require_once "koneksi.php";

/* =====================================================
   CEK LOGIN SISWA
===================================================== */

if (
    !isset($_SESSION["login"]) ||
    $_SESSION["login"] !== true ||
    ($_SESSION["role"] ?? "") !== "siswa"
) {
    header("Location: login.php");
    exit;
}


/* =====================================================
   DATA SESSION
===================================================== */

$namaUser     = $_SESSION["siswa_nama"] ?? $_SESSION["nama"] ?? "Siswa";
$usernameUser = $_SESSION["siswa_username"] ?? $_SESSION["username"] ?? "";
$emailUser    = $_SESSION["siswa_email"] ?? $_SESSION["email"] ?? "";
$idSiswa      = (int)($_SESSION["siswa_id"] ?? 0);

if ($idSiswa <= 0) {
    die("Data siswa tidak ditemukan. Silakan logout dan login kembali.");
}


/* =====================================================
   VARIABEL
===================================================== */

$pesan = "";
$tipePesan = "";


/* =====================================================
   AMBIL DATA SISWA
===================================================== */

$siswa = null;

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        nisn,
        ttl,
        gender,
        email,
        address,
        foto,
        no_hp
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $idSiswa);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $siswa = $result->fetch_assoc();
    }

    $stmt->close();
}


/* =====================================================
   JIKA TIDAK DITEMUKAN BERDASARKAN ID
   CARI BERDASARKAN EMAIL
===================================================== */

if (!$siswa && $emailUser !== "") {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            nisn,
            ttl,
            gender,
            email,
            address,
            foto,
            no_hp
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("s", $emailUser);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {

            $siswa = $result->fetch_assoc();

            $idSiswa = (int)$siswa["id"];

            $_SESSION["siswa_id"] = $idSiswa;
        }

        $stmt->close();
    }
}


/* =====================================================
   JIKA DATA SISWA TIDAK ADA
===================================================== */

if (!$siswa) {

    die("
        <div style='
            font-family:Arial;
            background:#050816;
            color:white;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
        '>
            <div>
                <h2>Data siswa tidak ditemukan</h2>
                <p>Silakan logout dan login kembali.</p>
                <a href='dashboard_siswa.php'
                   style='color:#a5b4fc;'>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    ");

}


/* =====================================================
   PROSES UPDATE DATA
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name    = trim($_POST["name"] ?? "");
    $nisn    = trim($_POST["nisn"] ?? "");
    $ttl     = trim($_POST["ttl"] ?? "");
    $gender  = trim($_POST["gender"] ?? "");
    $email   = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $no_hp   = trim($_POST["no_hp"] ?? "");


    /* =================================================
       VALIDASI
    ================================================= */

    if ($name === "") {

        $pesan = "Nama lengkap wajib diisi.";
        $tipePesan = "error";

    } elseif ($email === "") {

        $pesan = "Email wajib diisi.";
        $tipePesan = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $pesan = "Format email tidak valid.";
        $tipePesan = "error";

    } elseif ($gender === "") {

        $pesan = "Jenis kelamin wajib dipilih.";
        $tipePesan = "error";

    } else {

        /* =============================================
           CEK EMAIL DUPLIKAT
        ============================================= */

        $cekEmail = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $emailDuplikat = false;

        if ($cekEmail) {

            $cekEmail->bind_param(
                "si",
                $email,
                $idSiswa
            );

            $cekEmail->execute();

            $hasilEmail = $cekEmail->get_result();

            if ($hasilEmail && $hasilEmail->num_rows > 0) {
                $emailDuplikat = true;
            }

            $cekEmail->close();
        }


        if ($emailDuplikat) {

            $pesan = "Email tersebut sudah digunakan oleh siswa lain.";
            $tipePesan = "error";

        } else {

            /* =========================================
               FOTO LAMA
            ========================================= */

            $fotoLama = trim($siswa["foto"] ?? "");
            $fotoBaru = $fotoLama;


            /* =========================================
               UPLOAD FOTO
            ========================================= */

            if (
                isset($_FILES["foto"]) &&
                $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {

                    $pesan = "Foto gagal diupload.";
                    $tipePesan = "error";

                } else {

                    $file = $_FILES["foto"];

                    $namaFile =
                        $file["name"];

                    $tmpFile =
                        $file["tmp_name"];

                    $ukuranFile =
                        $file["size"];


                    /* =================================
                       BATAS UKURAN 2 MB
                    ================================= */

                    if ($ukuranFile > 2 * 1024 * 1024) {

                        $pesan =
                            "Ukuran foto maksimal 2 MB.";

                        $tipePesan =
                            "error";

                    } else {

                        $ekstensi =
                            strtolower(
                                pathinfo(
                                    $namaFile,
                                    PATHINFO_EXTENSION
                                )
                            );


                        $ekstensiDiizinkan = [
                            "jpg",
                            "jpeg",
                            "png",
                            "webp"
                        ];


                        if (
                            !in_array(
                                $ekstensi,
                                $ekstensiDiizinkan
                            )
                        ) {

                            $pesan =
                                "Format foto harus JPG, JPEG, PNG, atau WEBP.";

                            $tipePesan =
                                "error";

                        } else {

                            /* =========================
                               BUAT FOLDER UPLOADS
                            ========================= */

                            $folderUpload =
                                __DIR__ . "/uploads";

                            if (
                                !is_dir(
                                    $folderUpload
                                )
                            ) {

                                mkdir(
                                    $folderUpload,
                                    0777,
                                    true
                                );
                            }


                            /* =========================
                               NAMA FILE BARU
                            ========================= */

                            $namaFileBaru =
                                "siswa_" .
                                $idSiswa .
                                "_" .
                                time() .
                                "." .
                                $ekstensi;


                            $tujuan =
                                $folderUpload .
                                "/" .
                                $namaFileBaru;


                            /* =========================
                               PINDAHKAN FILE
                            ========================= */

                            if (
                                move_uploaded_file(
                                    $tmpFile,
                                    $tujuan
                                )
                            ) {

                                $fotoBaru =
                                    $namaFileBaru;


                                /* =====================
                                   HAPUS FOTO LAMA
                                ===================== */

                                if (
                                    $fotoLama !== ""
                                ) {

                                    $fileLama =
                                        $folderUpload .
                                        "/" .
                                        $fotoLama;

                                    if (
                                        file_exists(
                                            $fileLama
                                        )
                                    ) {

                                        unlink(
                                            $fileLama
                                        );
                                    }
                                }

                            } else {

                                $pesan =
                                    "Foto gagal disimpan ke folder uploads.";

                                $tipePesan =
                                    "error";
                            }
                        }
                    }
                }
            }


            /* =========================================
               UPDATE DATABASE
            ========================================= */

            if ($tipePesan !== "error") {

                $stmtUpdate = $conn->prepare("
                    UPDATE users
                    SET
                        name = ?,
                        nisn = ?,
                        ttl = ?,
                        gender = ?,
                        email = ?,
                        address = ?,
                        foto = ?,
                        no_hp = ?
                    WHERE id = ?
                ");


                if ($stmtUpdate) {

                    $stmtUpdate->bind_param(
                        "ssssssssi",
                        $name,
                        $nisn,
                        $ttl,
                        $gender,
                        $email,
                        $address,
                        $fotoBaru,
                        $no_hp,
                        $idSiswa
                    );


                    if ($stmtUpdate->execute()) {

                        /* =============================
                           UPDATE SESSION
                        ============================= */

                        $_SESSION["siswa_nama"] =
                            $name;

                        $_SESSION["siswa_email"] =
                            $email;

                        $_SESSION["siswa_no_hp"] =
                            $no_hp;

                        $_SESSION["nama"] =
                            $name;

                        $_SESSION["email"] =
                            $email;

                        $_SESSION["no_hp"] =
                            $no_hp;


                        /* =============================
                           UPDATE DATA LOKAL
                        ============================= */

                        $siswa["name"] =
                            $name;

                        $siswa["nisn"] =
                            $nisn;

                        $siswa["ttl"] =
                            $ttl;

                        $siswa["gender"] =
                            $gender;

                        $siswa["email"] =
                            $email;

                        $siswa["address"] =
                            $address;

                        $siswa["foto"] =
                            $fotoBaru;

                        $siswa["no_hp"] =
                            $no_hp;


                        $pesan =
                            "Profil siswa berhasil diperbarui.";

                        $tipePesan =
                            "success";

                    } else {

                        $pesan =
                            "Data gagal diperbarui: " .
                            $stmtUpdate->error;

                        $tipePesan =
                            "error";
                    }


                    $stmtUpdate->close();

                } else {

                    $pesan =
                        "Query update tidak dapat diproses.";

                    $tipePesan =
                        "error";
                }
            }
        }
    }
}


/* =====================================================
   DATA TAMPILAN
===================================================== */

$nama =
    $siswa["name"] ?? $namaUser;

$nisn =
    $siswa["nisn"] ?? "";

$ttl =
    $siswa["ttl"] ?? "";

$gender =
    $siswa["gender"] ?? "";

$email =
    $siswa["email"] ?? $emailUser;

$address =
    $siswa["address"] ?? "";

$foto =
    trim($siswa["foto"] ?? "");

$no_hp =
    $siswa["no_hp"] ?? "";


/* =====================================================
   INISIAL
===================================================== */

$inisial = strtoupper(
    mb_substr(
        trim($nama),
        0,
        1
    )
);

if ($inisial === "") {
    $inisial = "S";
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>ASTS Galaxy | Profil Siswa</title>

<style>

/* =====================================================
   RESET
===================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    color: #eef2ff;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(99,102,241,.22),
            transparent 28%
        ),

        radial-gradient(
            circle at 85% 15%,
            rgba(168,85,247,.18),
            transparent 25%
        ),

        radial-gradient(
            circle at 70% 90%,
            rgba(14,165,233,.14),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #050816,
            #0a1024 45%,
            #050816
        );

    overflow-x: hidden;
}


/* =====================================================
   BACKGROUND
===================================================== */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    background-image:

        linear-gradient(
            rgba(129,140,248,.035) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(129,140,248,.035) 1px,
            transparent 1px
        );

    background-size: 42px 42px;

    z-index: -3;
}

body::after {

    content: "";

    position: fixed;

    width: 550px;
    height: 550px;

    right: -220px;
    top: 25%;

    border-radius: 50%;

    background:
        rgba(99,102,241,.12);

    filter: blur(110px);

    pointer-events: none;

    z-index: -2;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    left: 18px;
    top: 18px;
    bottom: 18px;

    width: 250px;

    padding: 22px 15px;

    display: flex;
    flex-direction: column;

    background:
        linear-gradient(
            180deg,
            rgba(15,23,42,.96),
            rgba(7,12,28,.98)
        );

    border:
        1px solid
        rgba(129,140,248,.20);

    border-radius: 26px;

    box-shadow:
        0 30px 80px
        rgba(0,0,0,.55),

        inset 0 1px 0
        rgba(255,255,255,.04);

    backdrop-filter: blur(25px);

    z-index: 100;
}


/* =====================================================
   LOGO
===================================================== */

.logo {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        4px 9px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.logo-icon {

    width: 48px;
    height: 48px;

    display: grid;
    place-items: center;

    border-radius: 15px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6,
            #a855f7
        );

    box-shadow:
        0 0 30px
        rgba(99,102,241,.35);

    font-size: 17px;

    font-weight: 800;
}

.logo-text strong {

    display: block;

    color: white;

    font-size: 15px;

    letter-spacing: 1px;
}

.logo-text small {

    display: block;

    margin-top: 4px;

    color: #7c89a8;

    font-size: 9px;

    letter-spacing: 1px;
}


/* =====================================================
   MENU
===================================================== */

.menu-title {

    padding:
        23px 10px 10px;

    color: #64748b;

    font-size: 9px;

    font-weight: 700;

    letter-spacing: 2px;

    text-transform: uppercase;
}

.menu {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 7px;

    padding: 12px 13px;

    color: #94a3b8;

    text-decoration: none;

    border-radius: 12px;

    font-size: 11px;

    transition: .25s ease;
}

.menu-icon {

    width: 31px;
    height: 31px;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    color: #94a3b8;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.04);

    border-radius: 9px;
}

.menu:hover {

    color: white;

    background:
        rgba(99,102,241,.10);

    transform:
        translateX(4px);
}

.menu.active {

    color: white;

    background:
        linear-gradient(
            90deg,
            rgba(99,102,241,.22),
            rgba(139,92,246,.07)
        );

    border:
        1px solid
        rgba(129,140,248,.22);

    box-shadow:
        inset 3px 0 0 #818cf8;
}

.menu.active .menu-icon {

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );
}

.menu.logout {

    margin-top: auto;

    color: #fb7185;
}

.menu.logout:hover {

    background:
        rgba(244,63,94,.08);
}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 285px;

    padding:
        28px 32px 45px 15px;
}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}

.title-area h1 {

    color: white;

    font-size: 31px;

    letter-spacing: -.8px;
}

.title-area h1 span {

    color: #818cf8;
}

.title-area p {

    margin-top: 8px;

    color: #71809d;

    font-size: 11px;
}

.top-actions {

    display: flex;

    align-items: center;

    gap: 12px;
}

.clock {

    padding:
        11px 15px;

    color: #a5b4fc;

    background:
        rgba(99,102,241,.08);

    border:
        1px solid
        rgba(129,140,248,.18);

    border-radius: 12px;

    font-size: 10px;
}

.profile {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        6px 15px 6px 6px;

    background:
        rgba(15,23,42,.82);

    border:
        1px solid
        rgba(129,140,248,.17);

    border-radius: 999px;
}

.avatar {

    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    overflow: hidden;

    border-radius: 50%;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #a855f7
        );

    font-weight: 800;
}

.avatar img {

    width: 100%;
    height: 100%;

    object-fit: cover;
}

.profile b {

    display: block;

    color: #f8fafc;

    font-size: 11px;
}

.profile small {

    color: #71809d;

    font-size: 9px;
}


/* =====================================================
   CARD
===================================================== */

.card {

    max-width: 950px;

    margin: auto;

    padding: 25px;

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,26,.98)
        );

    border:
        1px solid
        rgba(129,140,248,.16);

    border-radius: 20px;

    box-shadow:
        0 25px 65px
        rgba(0,0,0,.28);
}


/* =====================================================
   CARD HEADER
===================================================== */

.card-header {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 25px;

    padding-bottom: 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.card-icon {

    width: 45px;
    height: 45px;

    display: grid;
    place-items: center;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    border-radius: 13px;

    font-size: 18px;
}

.card-header h2 {

    color: white;

    font-size: 18px;
}

.card-header p {

    margin-top: 4px;

    color: #64748b;

    font-size: 9px;
}


/* =====================================================
   ALERT
===================================================== */

.alert {

    margin-bottom: 20px;

    padding: 14px 16px;

    border-radius: 12px;

    font-size: 10px;
}

.alert.success {

    color: #bbf7d0;

    background:
        rgba(34,197,94,.08);

    border:
        1px solid
        rgba(34,197,94,.20);
}

.alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.20);
}


/* =====================================================
   PROFILE TOP
===================================================== */

.profile-top {

    display: flex;

    align-items: center;

    gap: 20px;

    margin-bottom: 30px;

    padding: 20px;

    background:
        rgba(2,6,23,.45);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 16px;
}

.big-avatar {

    width: 90px;
    height: 90px;

    display: grid;
    place-items: center;

    overflow: hidden;

    flex-shrink: 0;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #a855f7
        );

    border:
        2px solid
        rgba(165,180,252,.30);

    border-radius: 24px;

    font-size: 28px;

    font-weight: 800;
}

.big-avatar img {

    width: 100%;
    height: 100%;

    object-fit: cover;
}

.profile-top h3 {

    color: white;

    font-size: 20px;
}

.profile-top p {

    margin-top: 5px;

    color: #818cf8;

    font-size: 10px;
}

.profile-top small {

    display: block;

    margin-top: 8px;

    color: #64748b;

    font-size: 9px;
}


/* =====================================================
   FORM
===================================================== */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;
}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {

    color: #94a3b8;

    font-size: 9px;

    font-weight: 600;

    letter-spacing: .5px;
}

.form-group input,
.form-group select,
.form-group textarea {

    width: 100%;

    padding: 12px 13px;

    color: #e2e8f0;

    background:
        rgba(2,6,23,.65);

    border:
        1px solid
        rgba(129,140,248,.13);

    border-radius: 10px;

    outline: none;

    font-family: inherit;

    font-size: 10px;

    transition: .2s;
}

.form-group textarea {

    min-height: 100px;

    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {

    border-color:
        rgba(129,140,248,.55);

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,.08);
}

.form-group input[readonly] {

    color: #64748b;

    cursor: not-allowed;

    background:
        rgba(15,23,42,.55);
}

.form-help {

    color: #53627c;

    font-size: 8px;
}


/* =====================================================
   FOTO
===================================================== */

.photo-preview {

    display: flex;

    align-items: center;

    gap: 15px;

    padding: 13px;

    background:
        rgba(2,6,23,.45);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 11px;
}

.photo-preview img {

    width: 60px;
    height: 60px;

    object-fit: cover;

    border-radius: 12px;

    border:
        1px solid
        rgba(129,140,248,.25);
}

.photo-preview span {

    color: #64748b;

    font-size: 8px;
}


/* =====================================================
   BUTTON
===================================================== */

.form-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 25px;

    padding-top: 20px;

    border-top:
        1px solid
        rgba(255,255,255,.06);
}

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 12px 20px;

    border-radius: 10px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;

    font-weight: 700;

    transition: .2s;
}

.btn-back {

    color: #94a3b8;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.08);
}

.btn-back:hover {

    color: white;

    background:
        rgba(255,255,255,.07);
}

.btn-save {

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    box-shadow:
        0 8px 25px
        rgba(99,102,241,.20);
}

.btn-save:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(99,102,241,.30);
}


/* =====================================================
   FOOTER
===================================================== */

.footer {

    margin-top: 25px;

    color: #475569;

    text-align: center;

    font-size: 8px;

    letter-spacing: 1px;
}

.footer span {
    color: #818cf8;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 950px) {

    .sidebar {

        width: 72px;

        padding:
            20px 9px;

        align-items: center;
    }

    .logo {

        padding:
            4px 0 22px;
    }

    .logo-text,
    .menu-title,
    .menu-text {

        display: none;
    }

    .menu {

        width: 48px;

        justify-content: center;

        padding: 9px;
    }

    .main {

        margin-left: 100px;

        padding:
            22px 16px 35px 5px;
    }

    .clock {
        display: none;
    }
}

@media (max-width: 650px) {

    .main {

        margin-left: 88px;

        padding-right: 10px;
    }

    .topbar {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .card {

        padding: 17px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .profile-top {

        align-items:
            flex-start;

        flex-direction: column;
    }

    .form-actions {

        flex-direction: column;
    }

    .btn {

        width: 100%;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            ✦
        </div>

        <div class="logo-text">

            <strong>
                ASTS GALAXY
            </strong>

            <small>
                STUDENT PORTAL
            </small>

        </div>

    </div>


    <div class="menu-title">
        Student Menu
    </div>


    <!-- DASHBOARD -->

    <a
        href="dashboard_siswa.php"
        class="menu"
    >

        <span class="menu-icon">
            ◈
        </span>

        <span class="menu-text">
            Dashboard
        </span>

    </a>


    <!-- PROFIL -->

    <a
        href="profil_siswa.php"
        class="menu active"
    >

        <span class="menu-icon">
            ◎
        </span>

        <span class="menu-text">
            Profil Saya
        </span>

    </a>


    <!-- DATA JSON -->

    <a
        href="data_json.php"
        class="menu"
    >

        <span class="menu-icon">
            { }
        </span>

        <span class="menu-text">
            Data JSON
        </span>

    </a>


    <!-- LOGOUT -->

    <a
        href="logout.php"
        class="menu logout"
        onclick="return confirm('Yakin ingin logout dari sistem?')"
    >

        <span class="menu-icon">
            ↪
        </span>

        <span class="menu-text">
            Logout
        </span>

    </a>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div class="title-area">

            <h1>

                ASTS
                <span>
                    Student Portal
                </span>

            </h1>

            <p>
                Sistem Pendataan Siswa • Profil Saya
            </p>

        </div>


        <div class="top-actions">

            <div class="clock">

                <span id="jam">
                    00:00:00
                </span>

            </div>


            <div class="profile">

                <div class="avatar">

                    <?php if ($foto !== ""): ?>

                        <img
                            src="uploads/<?= htmlspecialchars($foto) ?>"
                            alt="Foto <?= htmlspecialchars($nama) ?>"
                            onerror="this.style.display='none';"
                        >

                    <?php endif; ?>

                    <?= htmlspecialchars($inisial) ?>

                </div>


                <div>

                    <b>
                        <?= htmlspecialchars($nama) ?>
                    </b>

                    <small>

                        <?php if ($usernameUser !== ""): ?>

                            @<?= htmlspecialchars($usernameUser) ?>

                        <?php else: ?>

                            SISWA ASTS

                        <?php endif; ?>

                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         CARD PROFIL
    ================================================== -->

    <section class="card">


        <div class="card-header">

            <div class="card-icon">
                ◎
            </div>

            <div>

                <h2>
                    Profil Saya
                </h2>

                <p>
                    Kelola dan perbarui data pribadi kamu
                </p>

            </div>

        </div>


        <!-- =================================================
             ALERT
        ================================================== -->

        <?php if ($pesan !== ""): ?>

            <div class="alert <?= $tipePesan ?>">

                <?= htmlspecialchars($pesan) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PROFILE PREVIEW
        ================================================== -->

        <div class="profile-top">

            <div class="big-avatar">

                <?php if ($foto !== ""): ?>

                    <img
                        src="uploads/<?= htmlspecialchars($foto) ?>"
                        alt="Foto <?= htmlspecialchars($nama) ?>"
                        onerror="this.style.display='none';"
                    >

                <?php endif; ?>

                <?= htmlspecialchars($inisial) ?>

            </div>


            <div>

                <h3>
                    <?= htmlspecialchars($nama) ?>
                </h3>

                <p>

                    <?php if ($nisn !== ""): ?>

                        NISN <?= htmlspecialchars($nisn) ?>

                    <?php else: ?>

                        NISN belum diisi

                    <?php endif; ?>

                </p>

                <small>
                    Username:
                    <?= $usernameUser !== ""
                        ? "@" . htmlspecialchars($usernameUser)
                        : "-"
                    ?>
                </small>

            </div>

        </div>


        <!-- =================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-grid">


                <!-- NAMA -->

                <div class="form-group">

                    <label>
                        NAMA LENGKAP
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($nama) ?>"
                        placeholder="Masukkan nama lengkap"
                        required
                    >

                </div>


                <!-- USERNAME -->

                <div class="form-group">

                    <label>
                        USERNAME
                    </label>

                    <input
                        type="text"
                        value="<?= htmlspecialchars($usernameUser) ?>"
                        readonly
                    >

                    <div class="form-help">
                        Username login tidak dapat diubah dari halaman ini.
                    </div>

                </div>


                <!-- NISN -->

                <div class="form-group">

                    <label>
                        NISN
                    </label>

                    <input
                        type="text"
                        name="nisn"
                        value="<?= htmlspecialchars($nisn) ?>"
                        placeholder="Masukkan NISN"
                        maxlength="20"
                    >

                </div>


                <!-- TTL -->

                <div class="form-group">

                    <label>
                        TEMPAT / TANGGAL LAHIR
                    </label>

                    <input
                        type="text"
                        name="ttl"
                        value="<?= htmlspecialchars($ttl) ?>"
                        placeholder="Contoh: Ponorogo, 01 Januari 2009"
                    >

                </div>


                <!-- JENIS KELAMIN -->

                <div class="form-group">

                    <label>
                        JENIS KELAMIN
                    </label>

                    <select
                        name="gender"
                        required
                    >

                        <option value="">
                            -- Pilih Jenis Kelamin --
                        </option>

                        <option
                            value="Laki-laki"
                            <?= $gender === "Laki-laki"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Laki-laki
                        </option>

                        <option
                            value="Perempuan"
                            <?= $gender === "Perempuan"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Perempuan
                        </option>

                    </select>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        EMAIL
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="Masukkan email"
                        required
                    >

                </div>


                <!-- NO HP -->

                <div class="form-group">

                    <label>
                        NOMOR HP
                    </label>

                    <input
                        type="text"
                        name="no_hp"
                        value="<?= htmlspecialchars($no_hp) ?>"
                        placeholder="Contoh: 6285177863330"
                        maxlength="20"
                    >

                </div>


                <!-- FOTO -->

                <div class="form-group">

                    <label>
                        FOTO PROFIL
                    </label>

                    <input
                        type="file"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <div class="form-help">
                        Maksimal 2 MB. Format JPG, JPEG, PNG atau WEBP.
                    </div>

                </div>


                <!-- ALAMAT -->

                <div class="form-group full">

                    <label>
                        ALAMAT
                    </label>

                    <textarea
                        name="address"
                        placeholder="Masukkan alamat lengkap"
                    ><?= htmlspecialchars($address) ?></textarea>

                </div>


                <!-- PREVIEW FOTO -->

                <?php if ($foto !== ""): ?>

                    <div class="form-group full">

                        <label>
                            FOTO SAAT INI
                        </label>

                        <div class="photo-preview">

                            <img
                                src="uploads/<?= htmlspecialchars($foto) ?>"
                                alt="Foto siswa"
                                onerror="this.style.display='none';"
                            >

                            <span>
                                Foto akan diganti jika kamu memilih foto baru.
                            </span>

                        </div>

                    </div>

                <?php endif; ?>


            </div>


            <!-- =================================================
                 BUTTON
            ================================================== -->

            <div class="form-actions">

                <a
                    href="dashboard_siswa.php"
                    class="btn btn-back"
                >
                    ← Kembali
                </a>


                <button
                    type="submit"
                    class="btn btn-save"
                >
                    ✓ Simpan Perubahan
                </button>

            </div>

        </form>

    </section>


    <!-- FOOTER -->

    <div class="footer">

        ASTS GALAXY STUDENT PORTAL

        <span>•</span>

        DATABASE CONNECTED

        <span>•</span>

        PHP + MYSQL

        <span>•</span>

        2026

    </div>


</main>


<script>

/* =====================================================
   JAM DIGITAL
===================================================== */

function updateClock() {

    const now = new Date();

    const jam =
        String(now.getHours())
        .padStart(2, "0");

    const menit =
        String(now.getMinutes())
        .padStart(2, "0");

    const detik =
        String(now.getSeconds())
        .padStart(2, "0");

    const clock =
        document.getElementById("jam");

    if (clock) {

        clock.textContent =
            `${jam}:${menit}:${detik}`;

    }
}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>
</html>