<?php

session_start();
require_once "koneksi.php";


/* =====================================================
   KONFIGURASI FONNTE
===================================================== */

$fonnteToken = "TkaoqfXfFrPGTGJ6UbLZ";

if (file_exists("fonnte.php")) {

    require_once "fonnte.php";

}


/* =====================================================
   FUNGSI KIRIM WHATSAPP FONNTE
===================================================== */

function kirimWhatsAppHapusAkun($nomor, $pesan, $token)
{

    if (
        trim($nomor) === "" ||
        trim($token) === ""
    ) {
        return false;
    }


    /* =================================================
       NORMALISASI NOMOR HP
    ================================================= */

    $nomor = preg_replace(
        '/[^0-9]/',
        '',
        $nomor
    );


    if ($nomor === "") {
        return false;
    }


    /* 08xxxxxxxxxx
       menjadi
       628xxxxxxxxxx
    */

    if (
        substr($nomor, 0, 2) === "08"
    ) {

        $nomor =
            "62" .
            substr($nomor, 1);
    }


    /* =================================================
       DATA FONNTE
    ================================================= */

    $data = [
        "target"  => $nomor,
        "message" => $pesan
    ];


    /* =================================================
       CURL
    ================================================= */

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        [

            CURLOPT_URL =>
                "https://api.fonnte.com/send",

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_POST =>
                true,

            CURLOPT_POSTFIELDS =>
                http_build_query($data),

            CURLOPT_HTTPHEADER =>
                [
                    "Authorization: " . $token
                ],

            CURLOPT_TIMEOUT =>
                20,

            CURLOPT_CONNECTTIMEOUT =>
                10

        ]
    );


    $response =
        curl_exec($curl);


    $error =
        curl_error($curl);


    curl_close($curl);


    if ($error !== "") {

        return false;
    }


    /* =================================================
       CEK RESPONSE FONNTE
    ================================================= */

    if ($response) {

        $hasil =
            json_decode(
                $response,
                true
            );


        if (
            isset($hasil["status"]) &&
            (
                $hasil["status"] === true ||
                $hasil["status"] === "true"
            )
        ) {

            return true;
        }
    }


    return false;
}


/* =====================================================
   HAPUS AKUN SISWA
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["hapus_akun"])
) {

    $idSiswaHapus =
        (int)(
            $_SESSION["siswa_id"]
            ?? 0
        );

    $emailHapus =
        $_SESSION["siswa_email"]
        ?? "";


    if ($idSiswaHapus <= 0) {

        $_SESSION["hapus_error"] =
            "ID siswa tidak ditemukan.";

        header(
            "Location: dashboard_siswa.php"
        );

        exit;
    }


    try {

        /* =================================================
           MULAI TRANSAKSI
        ================================================= */

        $conn->begin_transaction();


        /* =================================================
           AMBIL DATA SISWA SEBELUM DIHAPUS
        ================================================= */

        $namaHapus =
            "Siswa";

        $emailDatabase =
            "";

        $nomorWA =
            "";


        $stmt =
            $conn->prepare("
                SELECT
                    name,
                    email,
                    no_hp
                FROM users
                WHERE id = ?
                LIMIT 1
            ");


        if (!$stmt) {

            throw new Exception(
                "Gagal mengambil data siswa."
            );
        }


        $stmt->bind_param(
            "i",
            $idSiswaHapus
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if (
            $result &&
            $result->num_rows > 0
        ) {

            $dataUser =
                $result->fetch_assoc();


            $namaHapus =
                trim(
                    $dataUser["name"]
                    ?? "Siswa"
                );


            $emailDatabase =
                trim(
                    $dataUser["email"]
                    ?? ""
                );


            $nomorWA =
                trim(
                    $dataUser["no_hp"]
                    ?? ""
                );
        }


        $stmt->close();


        /* =================================================
           JIKA EMAIL DATABASE KOSONG
        ================================================= */

        if ($emailDatabase === "") {

            $emailDatabase =
                trim($emailHapus);
        }


        /* =================================================
           SIMPAN FOTO UNTUK DIHAPUS
        ================================================= */

        $fotoHapus = "";


        $stmt =
            $conn->prepare("
                SELECT foto
                FROM users
                WHERE id = ?
                LIMIT 1
            ");


        if ($stmt) {

            $stmt->bind_param(
                "i",
                $idSiswaHapus
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if (
                $result &&
                $result->num_rows > 0
            ) {

                $dataFoto =
                    $result->fetch_assoc();

                $fotoHapus =
                    trim(
                        $dataFoto["foto"]
                        ?? ""
                    );
            }

            $stmt->close();
        }


        /* =================================================
           KIRIM NOTIFIKASI WHATSAPP
           SEBELUM AKUN DIHAPUS
        ================================================= */

        $waBerhasil =
            false;


        if (
            $nomorWA !== "" &&
            $fonnteToken !== ""
        ) {

     $pesanHapus =
    "🌌STUDENT PORTAL\n\n" .
    "Halo, " . $namaHapus . " 👋\n\n" .
    "✅ Akun kamu berhasil dihapus.\n\n" .
    "Seluruh data akun login dan profil siswa " .
    "telah berhasil dihapus dari sistem.\n\n" .
    "📧 Email: " . $emailDatabase . "\n\n" .
    "🔒 KEAMANAN & PRIVASI\n" .
    "Data akun kamu sudah tidak tersimpan di sistem " .
    "ASTS Galaxy Student Portal.\n\n" .
    "⚠️ Jika kamu tidak melakukan penghapusan akun ini, " .
    "segera hubungi administrator sekolah untuk pemeriksaan lebih lanjut.\n\n" .
    "Terima kasih telah menggunakan layanan kami. 🙏\n\n" .
    "🚀 ASTS Galaxy Student Portal\n" .
    "SMK PGRI 2 Ponorogo";

            $waBerhasil =
                kirimWhatsAppHapusAkun(
                    $nomorWA,
                    $pesanHapus,
                    $fonnteToken
                );
        }


        /* =================================================
           HAPUS AKUN LOGIN
        ================================================= */

        if ($emailDatabase !== "") {

            $stmt =
                $conn->prepare("
                    DELETE FROM users_login
                    WHERE email = ?
                    AND role = 'siswa'
                ");


            if (!$stmt) {

                throw new Exception(
                    "Gagal menyiapkan query hapus akun login."
                );
            }


            $stmt->bind_param(
                "s",
                $emailDatabase
            );


            $stmt->execute();


            $stmt->close();
        }


        /* =================================================
           HAPUS DATA SISWA
        ================================================= */

        $stmt =
            $conn->prepare("
                DELETE FROM users
                WHERE id = ?
                LIMIT 1
            ");


        if (!$stmt) {

            throw new Exception(
                "Gagal menyiapkan query hapus data siswa."
            );
        }


        $stmt->bind_param(
            "i",
            $idSiswaHapus
        );


        $stmt->execute();


        $stmt->close();


        /* =================================================
           COMMIT
        ================================================= */

        $conn->commit();


        /* =================================================
           HAPUS FOTO SISWA
        ================================================= */

        if ($fotoHapus !== "") {

            $fotoPath =
                __DIR__ .
                "/uploads/" .
                basename($fotoHapus);


            if (
                file_exists($fotoPath) &&
                is_file($fotoPath)
            ) {

                @unlink($fotoPath);
            }
        }


        /* =================================================
           SIMPAN STATUS WHATSAPP SEBELUM SESSION DIHAPUS
        ================================================= */

        /*
         * Tidak perlu menyimpan status ke session
         * karena setelah akun dihapus session juga dihancurkan.
         */


        /* =================================================
           HAPUS SESSION
        ================================================= */

        $_SESSION = [];


        if (
            ini_get("session.use_cookies")
        ) {

            $params =
                session_get_cookie_params();


            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }


        session_destroy();


        /* =================================================
           KEMBALI KE LOGIN
        ================================================= */

        header(
            "Location: login.php?akun_dihapus=1"
        );

        exit;


    } catch (Throwable $e) {

        /* =================================================
           ROLLBACK
        ================================================= */

        try {

            $conn->rollback();

        } catch (Throwable $rollbackError) {

            // Abaikan error rollback
        }


        $_SESSION["hapus_error"] =
            "Akun gagal dihapus. Silakan coba lagi.";


        header(
            "Location: dashboard_siswa.php"
        );

        exit;
    }
}


/* =====================================================
   CEK LOGIN SISWA
===================================================== */

if (
    !isset($_SESSION["login"]) ||
    $_SESSION["login"] !== true ||
    ($_SESSION["role"] ?? "") !== "siswa"
) {

    header(
        "Location: login.php"
    );

    exit;
}


/* =====================================================
   ERROR HAPUS AKUN
===================================================== */

$hapusError =
    $_SESSION["hapus_error"]
    ?? "";


if ($hapusError !== "") {

    unset(
        $_SESSION["hapus_error"]
    );
}


/* =====================================================
   DATA SESSION
===================================================== */

$namaUser =
    $_SESSION["siswa_nama"]
    ?? $_SESSION["nama"]
    ?? "Siswa";


$usernameUser =
    $_SESSION["siswa_username"]
    ?? $_SESSION["username"]
    ?? "";


$emailUser =
    $_SESSION["siswa_email"]
    ?? $_SESSION["email"]
    ?? "";


$idSiswa =
    (int)(
        $_SESSION["siswa_id"]
        ?? 0
    );


/* =====================================================
   AMBIL DATA SISWA
===================================================== */

$siswa =
    null;


/* =====================================================
   BERDASARKAN ID
===================================================== */

if ($idSiswa > 0) {

    $stmt =
        $conn->prepare("
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

        $stmt->bind_param(
            "i",
            $idSiswa
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if (
            $result &&
            $result->num_rows > 0
        ) {

            $siswa =
                $result->fetch_assoc();
        }


        $stmt->close();
    }
}


/* =====================================================
   JIKA ID TIDAK DITEMUKAN
   CARI BERDASARKAN EMAIL
===================================================== */

if (
    !$siswa &&
    $emailUser !== ""
) {

    $stmt =
        $conn->prepare("
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

        $stmt->bind_param(
            "s",
            $emailUser
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if (
            $result &&
            $result->num_rows > 0
        ) {

            $siswa =
                $result->fetch_assoc();


            $_SESSION["siswa_id"] =
                (int)$siswa["id"];
        }


        $stmt->close();
    }
}


/* =====================================================
   JIKA DATA TIDAK DITEMUKAN
===================================================== */

if (!$siswa) {

    $siswa = [

        "id" =>
            0,

        "name" =>
            $namaUser,

        "nisn" =>
            "",

        "ttl" =>
            "",

        "gender" =>
            "",

        "email" =>
            $emailUser,

        "address" =>
            "",

        "foto" =>
            "",

        "no_hp" =>
            ""
    ];
}


/* =====================================================
   DATA SISWA
===================================================== */

$nama =
    $siswa["name"]
    ?? $namaUser;


$username =
    $usernameUser;


$nisn =
    $siswa["nisn"]
    ?? "";


$ttl =
    $siswa["ttl"]
    ?? "";


$gender =
    $siswa["gender"]
    ?? "";


$email =
    $siswa["email"]
    ?? $emailUser;


$address =
    $siswa["address"]
    ?? "";


$foto =
    trim(
        $siswa["foto"]
        ?? ""
    );


$no_hp =
    $siswa["no_hp"]
    ?? "";


/* =====================================================
   INISIAL
===================================================== */

$inisial =
    strtoupper(
        mb_substr(
            trim($nama),
            0,
            1
        )
    );


if ($inisial === "") {

    $inisial = "S";
}


/* =====================================================
   NOTIFIKASI LOGIN WHATSAPP
===================================================== */

$loginWa =
    $_SESSION["login_wa"]
    ?? null;


if ($loginWa !== null) {

    unset(
        $_SESSION["login_wa"]
    );
}


/* =====================================================
   STATUS DATA
===================================================== */

$dataLengkap =
    0;


$dataYangDiisi = [

    $nama,
    $nisn,
    $ttl,
    $gender,
    $email,
    $address

];


foreach (
    $dataYangDiisi
    as $data
) {

    if (
        trim(
            (string)$data
        ) !== ""
    ) {

        $dataLengkap++;
    }
}


$persentaseData =
    round(
        ($dataLengkap / 6) * 100
    );

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    ASTS Galaxy | Dashboard Siswa
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
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

::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #050816;
}

::-webkit-scrollbar-thumb {

    background:
        linear-gradient(
            180deg,
            #6366f1,
            #a855f7
        );

    border-radius: 20px;
}

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

    position: relative;

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

    font-size: 12px;
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

        inset 3px 0 0 #818cf8,

        0 10px 25px
        rgba(99,102,241,.08);
}

.menu.active .menu-icon {

    color: white;

    background:

        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    border-color:
        transparent;
}

.menu.logout {

    margin-top: auto;

    color: #fb7185;

    border:
        1px solid
        rgba(244,63,94,.10);
}

.menu.logout:hover {

    color: #fda4af;

    background:
        rgba(244,63,94,.08);

    border-color:
        rgba(244,63,94,.20);
}

.menu.logout .menu-icon {
    color: #fb7185;
}

.main {

    margin-left: 285px;

    padding:
        28px 32px 45px 15px;
}

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;

    margin-bottom: 25px;
}

.title-area h1 {

    color: white;

    font-size: 31px;

    line-height: 1.2;

    letter-spacing: -.8px;
}

.title-area h1 span {

    color: #818cf8;

    text-shadow:
        0 0 25px
        rgba(129,140,248,.25);
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

    display: block;

    margin-top: 3px;

    color: #71809d;

    font-size: 9px;
}

.welcome {

    position: relative;

    overflow: hidden;

    padding: 25px;

    margin-bottom: 20px;

    background:

        linear-gradient(
            135deg,
            rgba(99,102,241,.18),
            rgba(139,92,246,.08),
            rgba(15,23,42,.92)
        );

    border:
        1px solid
        rgba(129,140,248,.20);

    border-radius: 19px;

    box-shadow:
        0 25px 60px
        rgba(0,0,0,.25);
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-label {

    color: #818cf8;

    font-size: 9px;

    font-weight: 700;

    letter-spacing: 2px;

    text-transform: uppercase;
}

.welcome h2 {

    margin-top: 8px;

    color: white;

    font-size: 24px;
}

.welcome p {

    margin-top: 7px;

    color: #94a3b8;

    font-size: 10px;
}

.wa-alert {

    display: flex;

    align-items: center;

    gap: 13px;

    margin-bottom: 20px;

    padding: 15px 18px;

    border-radius: 15px;
}

.wa-alert.success {

    color: #bbf7d0;

    background:
        rgba(34,197,94,.08);

    border:
        1px solid
        rgba(34,197,94,.20);
}

.wa-alert.error {

    color: #fecaca;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.20);
}

.wa-icon {

    width: 38px;
    height: 38px;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    border-radius: 11px;

    background:
        rgba(255,255,255,.06);

    font-size: 17px;
}

.wa-alert strong {

    display: block;

    margin-bottom: 3px;

    font-size: 11px;
}

.wa-alert small {

    color: #94a3b8;

    font-size: 9px;
}

.dashboard-grid {

    display: grid;

    grid-template-columns:
        1fr 1.5fr;

    gap: 20px;

    margin-bottom: 20px;
}

.card {

    padding: 22px;

    background:

        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,26,.98)
        );

    border:
        1px solid
        rgba(129,140,248,.16);

    border-radius: 19px;

    box-shadow:
        0 25px 65px
        rgba(0,0,0,.28);
}

.card-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;
}

.card-title-icon {

    width: 34px;
    height: 34px;

    display: grid;
    place-items: center;

    color: white;

    background:

        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    border-radius: 9px;

    font-size: 13px;
}

.card-title h3 {

    color: #f8fafc;

    font-size: 14px;
}

.card-title p {

    margin-top: 3px;

    color: #64748b;

    font-size: 8px;
}

.profile-box {

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 22px;
}

.big-avatar {

    width: 85px;
    height: 85px;

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

    border-radius: 22px;

    font-size: 27px;

    font-weight: 800;
}

.big-avatar img {

    width: 100%;
    height: 100%;

    object-fit: cover;
}

.profile-box h2 {

    color: white;

    font-size: 19px;
}

.profile-box p {

    margin-top: 5px;

    color: #818cf8;

    font-size: 10px;
}

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 9px;

    padding: 5px 8px;

    color: #86efac;

    background:
        rgba(34,197,94,.07);

    border:
        1px solid
        rgba(34,197,94,.15);

    border-radius: 7px;

    font-size: 8px;

    font-weight: 700;
}

.status-dot {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #4ade80;

    box-shadow:
        0 0 8px
        #4ade80;
}

.data-list {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.data-item {

    padding: 13px;

    background:
        rgba(2,6,23,.55);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 11px;
}

.data-label {

    color: #53627c;

    font-size: 8px;

    text-transform: uppercase;

    letter-spacing: .8px;
}

.data-value {

    margin-top: 6px;

    color: #dbeafe;

    font-size: 10px;

    font-weight: 600;

    word-break: break-word;
}

.progress-box {

    margin-top: 22px;

    padding: 15px;

    background:
        rgba(2,6,23,.50);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 11px;
}

.progress-top {

    display: flex;

    justify-content: space-between;

    color: #94a3b8;

    font-size: 9px;
}

.progress-top strong {
    color: #a5b4fc;
}

.progress {

    height: 7px;

    margin-top: 9px;

    overflow: hidden;

    background:
        #172033;

    border-radius: 20px;
}

.progress-bar {

    height: 100%;

    background:

        linear-gradient(
            90deg,
            #6366f1,
            #a855f7
        );

    border-radius: 20px;
}

.quick-menu {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.quick {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 13px;

    color: #94a3b8;

    background:
        rgba(2,6,23,.55);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 11px;

    text-decoration: none;

    transition: .2s;
}

.quick:hover {

    color: white;

    background:
        rgba(99,102,241,.09);

    border-color:
        rgba(129,140,248,.25);

    transform:
        translateY(-2px);
}

.quick-icon {

    width: 34px;
    height: 34px;

    display: grid;
    place-items: center;

    color: #a5b4fc;

    background:
        rgba(99,102,241,.10);

    border-radius: 9px;

    font-size: 13px;
}

.quick strong {

    display: block;

    color: #e2e8f0;

    font-size: 9px;
}

.quick small {

    display: block;

    margin-top: 3px;

    color: #53627c;

    font-size: 7px;
}


/* =====================================================
   HAPUS AKUN
===================================================== */

.danger-account {

    margin-top: 20px;

    padding: 18px;

    background:
        rgba(127,29,29,.10);

    border:
        1px solid
        rgba(248,113,113,.18);

    border-radius: 13px;
}

.danger-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 10px;
}

.danger-title-icon {

    width: 34px;
    height: 34px;

    display: grid;
    place-items: center;

    color: #fda4af;

    background:
        rgba(244,63,94,.10);

    border:
        1px solid
        rgba(244,63,94,.15);

    border-radius: 9px;

    font-size: 14px;
}

.danger-title h3 {

    color: #fecdd3;

    font-size: 11px;
}

.danger-title p {

    margin-top: 3px;

    color: #64748b;

    font-size: 8px;
}

.danger-description {

    margin-bottom: 13px;

    color: #94a3b8;

    font-size: 9px;

    line-height: 1.6;
}

.btn-hapus-akun {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 10px 15px;

    color: #fecdd3;

    background:
        rgba(244,63,94,.10);

    border:
        1px solid
        rgba(244,63,94,.25);

    border-radius: 9px;

    font-family: inherit;

    font-size: 9px;

    font-weight: 700;

    cursor: pointer;

    transition: .25s ease;
}

.btn-hapus-akun:hover {

    color: white;

    background:
        rgba(244,63,94,.20);

    border-color:
        rgba(244,63,94,.40);

    transform:
        translateY(-2px);
}

.info-card {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 14px;
}

.info {

    padding: 18px;

    background:
        rgba(2,6,23,.50);

    border:
        1px solid
        rgba(129,140,248,.10);

    border-radius: 13px;
}

.info-icon {

    width: 34px;
    height: 34px;

    display: grid;
    place-items: center;

    margin-bottom: 11px;

    color: #a5b4fc;

    background:
        rgba(99,102,241,.09);

    border-radius: 9px;
}

.info h4 {

    color: #e2e8f0;

    font-size: 10px;
}

.info p {

    margin-top: 5px;

    color: #64748b;

    font-size: 8px;

    line-height: 1.6;
}

.footer {

    margin-top: 20px;

    color: #475569;

    text-align: center;

    font-size: 8px;

    letter-spacing: 1px;
}

.footer span {
    color: #818cf8;
}

@media (max-width: 1100px) {

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .clock {
        display: none;
    }
}

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

    .menu-icon {

        width: 32px;
        height: 32px;
    }

    .main {

        margin-left: 100px;

        padding:
            22px 16px 35px 5px;
    }

    .topbar {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .top-actions {
        width: 100%;
    }

    .info-card {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {

    .sidebar {

        left: 8px;
        top: 8px;
        bottom: 8px;
    }

    .main {

        margin-left: 88px;

        padding-right: 10px;
    }

    .title-area h1 {
        font-size: 23px;
    }

    .welcome h2 {
        font-size: 20px;
    }

    .card {
        padding: 15px;
    }

    .data-list {
        grid-template-columns: 1fr;
    }

    .quick-menu {
        grid-template-columns: 1fr;
    }

    .profile-box {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile {
        width: 100%;
    }

    .btn-hapus-akun {
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


    <a
        href="dashboard_siswa.php"
        class="menu active"
    >

        <span class="menu-icon">
            ◈
        </span>

        <span class="menu-text">
            Dashboard
        </span>

    </a>


    <a
        href="profil_siswa.php"
        class="menu"
    >

        <span class="menu-icon">
            ◎
        </span>

        <span class="menu-text">
            Profil Saya
        </span>

    </a>


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


    <div class="topbar">

        <div class="title-area">

            <h1>

                ASTS
                <span>
                    Student Portal
                </span>

            </h1>

            <p>
                Sistem Pendataan Siswa • Halaman Siswa
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

                    <?php if ($username !== ""): ?>

                        <small>
                            @<?= htmlspecialchars($username) ?>
                        </small>

                    <?php else: ?>

                        <small>
                            SISWA ASTS
                        </small>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <section class="welcome">

        <div class="welcome-content">

            <div class="welcome-label">
                Student Dashboard
            </div>

            <h2>
                Selamat datang,
                <?= htmlspecialchars($nama) ?>
                👋
            </h2>

            <p>
                Kamu berhasil login ke Student Portal.
                Silakan cek informasi profil kamu di halaman ini.
            </p>

        </div>

    </section>


    <?php if ($hapusError !== ""): ?>

        <div class="wa-alert error">

            <div class="wa-icon">
                !
            </div>

            <div>

                <strong>
                    Akun gagal dihapus
                </strong>

                <small>
                    <?= htmlspecialchars($hapusError) ?>
                </small>

            </div>

        </div>

    <?php endif; ?>


    <?php if ($loginWa !== null): ?>

        <?php

        $waStatus =
            $loginWa["status"]
            ?? false;

        $waMessage =
            $loginWa["message"]
            ?? $loginWa["response"]
            ?? "";

        ?>

        <div
            class="wa-alert
            <?= $waStatus ? 'success' : 'error' ?>"
        >

            <div class="wa-icon">

                <?= $waStatus
                    ? "✓"
                    : "!"
                ?>

            </div>


            <div>

                <strong>

                    <?= $waStatus

                        ? "Login berhasil • WhatsApp terkirim"

                        : "Login berhasil • WhatsApp tidak terkirim"

                    ?>

                </strong>


                <?php if ($waMessage !== ""): ?>

                    <small>

                        <?= htmlspecialchars(

                            is_scalar($waMessage)

                            ? (string)$waMessage

                            : json_encode($waMessage)

                        ) ?>

                    </small>

                <?php else: ?>

                    <small>
                        Status notifikasi login telah diproses oleh sistem.
                    </small>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <div class="dashboard-grid">


        <section
            class="card"
            id="profil"
        >

            <div class="card-title">

                <div class="card-title-icon">
                    ◎
                </div>

                <div>

                    <h3>
                        Profil Saya
                    </h3>

                    <p>
                        Informasi akun siswa
                    </p>

                </div>

            </div>


            <div class="profile-box">

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

                    <h2>
                        <?= htmlspecialchars($nama) ?>
                    </h2>

                    <p>

                        <?= $nisn !== ""

                            ? "NISN " .
                              htmlspecialchars($nisn)

                            : "NISN belum diisi"

                        ?>

                    </p>


                    <div class="status">

                        <span class="status-dot"></span>

                        AKUN AKTIF

                    </div>

                </div>

            </div>


            <div class="data-list">


                <div class="data-item">

                    <div class="data-label">
                        Nama
                    </div>

                    <div class="data-value">
                        <?= htmlspecialchars($nama) ?>
                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Username
                    </div>

                    <div class="data-value">

                        <?= $username !== ""

                            ? htmlspecialchars($username)

                            : "-"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        NISN
                    </div>

                    <div class="data-value">

                        <?= $nisn !== ""

                            ? htmlspecialchars($nisn)

                            : "-"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Jenis Kelamin
                    </div>

                    <div class="data-value">

                        <?= $gender !== ""

                            ? htmlspecialchars($gender)

                            : "-"

                        ?>

                    </div>

                </div>


            </div>


            <div class="progress-box">

                <div class="progress-top">

                    <span>
                        Kelengkapan Data
                    </span>

                    <strong>
                        <?= $persentaseData ?>%
                    </strong>

                </div>

                <div class="progress">

                    <div
                        class="progress-bar"
                        style="width: <?= $persentaseData ?>%;"
                    ></div>

                </div>

            </div>

        </section>


        <section class="card">

            <div class="card-title">

                <div class="card-title-icon">
                    #
                </div>

                <div>

                    <h3>
                        Informasi Siswa
                    </h3>

                    <p>
                        Data pribadi yang tersimpan di sistem
                    </p>

                </div>

            </div>


            <div class="data-list">


                <div class="data-item">

                    <div class="data-label">
                        Nama Lengkap
                    </div>

                    <div class="data-value">
                        <?= htmlspecialchars($nama) ?>
                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        NISN
                    </div>

                    <div class="data-value">

                        <?= $nisn !== ""

                            ? htmlspecialchars($nisn)

                            : "Belum diisi"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Tempat / Tanggal Lahir
                    </div>

                    <div class="data-value">

                        <?= $ttl !== ""

                            ? htmlspecialchars($ttl)

                            : "Belum diisi"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Jenis Kelamin
                    </div>

                    <div class="data-value">

                        <?= $gender !== ""

                            ? htmlspecialchars($gender)

                            : "Belum diisi"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Email
                    </div>

                    <div class="data-value">

                        <?= $email !== ""

                            ? htmlspecialchars($email)

                            : "Belum diisi"

                        ?>

                    </div>

                </div>


                <div class="data-item">

                    <div class="data-label">
                        Alamat
                    </div>

                    <div class="data-value">

                        <?= $address !== ""

                            ? htmlspecialchars($address)

                            : "Belum diisi"

                        ?>

                    </div>

                </div>


            </div>


            <div style="margin-top:20px;">

                <div class="card-title">

                    <div class="card-title-icon">
                        ⚡
                    </div>

                    <div>

                        <h3>
                            Menu Cepat
                        </h3>

                        <p>
                            Akses fitur sistem
                        </p>

                    </div>

                </div>


                <div class="quick-menu">


                    <a
                        href="profil_siswa.php"
                        class="quick"
                    >

                        <div class="quick-icon">
                            ◎
                        </div>

                        <div>

                            <strong>
                                Profil Saya
                            </strong>

                            <small>
                                Lihat dan ubah data pribadi
                            </small>

                        </div>

                    </a>


                    <a
                        href="data_json.php"
                        class="quick"
                    >

                        <div class="quick-icon">
                            { }
                        </div>

                        <div>

                            <strong>
                                Data JSON
                            </strong>

                            <small>
                                Lihat data melalui API
                            </small>

                        </div>

                    </a>


                </div>

            </div>


            <div class="danger-account">

                <div class="danger-title">

                    <div class="danger-title-icon">
                        🗑
                    </div>

                    <div>

                        <h3>
                            Hapus Akun
                        </h3>

                        <p>
                            Menghapus akun siswa secara permanen
                        </p>

                    </div>

                </div>


                <div class="danger-description">

                    Jika akun dihapus, sistem akan menghapus
                    akun login dan data profil siswa.
                    Sebelum dihapus, sistem akan mengirimkan
                    notifikasi WhatsApp ke nomor yang terdaftar.

                </div>


                <form
                    method="POST"
                    action=""
                    onsubmit="
                        return confirm(
                            'PERINGATAN!\\n\\nAkun kamu akan dihapus secara permanen dan notifikasi WhatsApp akan dikirim ke nomor yang terdaftar.\\n\\nApakah kamu benar-benar yakin ingin menghapus akun?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="hapus_akun"
                        value="1"
                    >


                    <button
                        type="submit"
                        class="btn-hapus-akun"
                    >

                        🗑 Hapus Akun Saya

                    </button>

                </form>

            </div>


        </section>

    </div>


    <section class="card">

        <div class="card-title">

            <div class="card-title-icon">
                ✦
            </div>

            <div>

                <h3>
                    Informasi Sistem
                </h3>

                <p>
                    Informasi mengenai Student Portal
                </p>

            </div>

        </div>


        <div class="info-card">


            <div class="info">

                <div class="info-icon">
                    ✓
                </div>

                <h4>
                    Akun Aktif
                </h4>

                <p>
                    Akun siswa kamu sedang aktif dan
                    dapat digunakan untuk mengakses
                    sistem ASTS.
                </p>

            </div>


            <div class="info">

                <div class="info-icon">
                    🔒
                </div>

                <h4>
                    Data Pribadi
                </h4>

                <p>
                    Data yang ditampilkan merupakan
                    data pribadi siswa yang tersimpan
                    pada database.
                </p>

            </div>


            <div class="info">

                <div class="info-icon">
                    ⚡
                </div>

                <h4>
                    Sistem Online
                </h4>

                <p>
                     Student Portal
                    terhubung dengan database
                    PHP dan MySQL.
                </p>

            </div>


        </div>

    </section>


    <div class="footer">

       STUDENT PORTAL

        <span>•</span>

        DATABASE CONNECTED

        <span>•</span>

        PHP + MYSQL

        <span>•</span>

        2026

    </div>


</main>


<script>

function updateClock() {

    const now =
        new Date();

    const jam =
        String(
            now.getHours()
        ).padStart(
            2,
            "0"
        );

    const menit =
        String(
            now.getMinutes()
        ).padStart(
            2,
            "0"
        );

    const detik =
        String(
            now.getSeconds()
        ).padStart(
            2,
            "0"
        );

    const clock =
        document.getElementById(
            "jam"
        );

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