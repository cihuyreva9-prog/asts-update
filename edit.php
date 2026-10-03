<?php

session_start();

require_once "koneksi.php";
require_once "fonnte.php";


/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    header("Location: login.php");
    exit;
}


/* =====================================================
   CEK ID
===================================================== */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET["id"];


/* =====================================================
   VARIABEL
===================================================== */

$name = "";
$nisn = "";
$ttl = "";
$gender = "";
$email = "";
$address = "";
$no_hp = "";

$success = "";
$error = "";


/* =====================================================
   AMBIL DATA LAMA
===================================================== */

$stmt = $conn->prepare("
    SELECT
        name,
        nisn,
        ttl,
        gender,
        email,
        address,
        no_hp
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    header("Location: index.php");
    exit;
}

$data = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   ISI DATA KE FORM
===================================================== */

$name = $data["name"];
$nisn = $data["nisn"];
$ttl = $data["ttl"];
$gender = $data["gender"];
$email = $data["email"];
$address = $data["address"];
$no_hp = $data["no_hp"];


/* =====================================================
   PROSES UPDATE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $nisn = trim($_POST["nisn"] ?? "");
    $ttl = trim($_POST["ttl"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");


    /* =================================================
       VALIDASI DATA
    ================================================= */

    if (
        $name === "" ||
        $nisn === "" ||
        $ttl === "" ||
        $gender === "" ||
        $email === "" ||
        $address === "" ||
        $no_hp === ""
    ) {

        $error = "Semua data wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } else {


        /* =============================================
           NORMALISASI NOMOR WHATSAPP
        ============================================= */

        $no_hp = preg_replace('/[^0-9]/', '', $no_hp);

        if (substr($no_hp, 0, 1) === "0") {

            $no_hp = "62" . substr($no_hp, 1);

        } elseif (substr($no_hp, 0, 1) === "8") {

            $no_hp = "62" . $no_hp;
        }


        /* =============================================
           VALIDASI NOMOR WHATSAPP
        ============================================= */

        if (!preg_match('/^62[0-9]{9,13}$/', $no_hp)) {

            $error = "Nomor WhatsApp tidak valid. Contoh: 628123456789";

        } else {


            /* =========================================
               CEK NISN MILIK SISWA LAIN
            ========================================= */

            $cek = $conn->prepare("
                SELECT id
                FROM users
                WHERE nisn = ?
                AND id != ?
            ");

            $cek->bind_param("si", $nisn, $id);
            $cek->execute();

            $hasilCek = $cek->get_result();

            if ($hasilCek->num_rows > 0) {

                $error = "NISN tersebut sudah digunakan siswa lain.";

                $cek->close();

            } else {

                $cek->close();


                /* =====================================
                   UPDATE DATA
                ===================================== */

                $sql = "
                    UPDATE users SET
                        name = ?,
                        nisn = ?,
                        ttl = ?,
                        gender = ?,
                        email = ?,
                        address = ?,
                        no_hp = ?
                    WHERE id = ?
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    $error = "Query database gagal: " . $conn->error;

                } else {

                    $stmt->bind_param(
                        "sssssssi",
                        $name,
                        $nisn,
                        $ttl,
                        $gender,
                        $email,
                        $address,
                        $no_hp,
                        $id
                    );


                    /* =================================
                       EKSEKUSI UPDATE
                    ================================= */

                    if ($stmt->execute()) {


                        /* =================================
                           PESAN WHATSAPP
                        ================================= */

                        $pesan = "🔄 *DATA BERHASIL DIPERBARUI!* 🔄\n\n";

                        $pesan .= "Halo, *" . $name . "* 👋\n";
                        $pesan .= "Data kamu telah berhasil diperbarui melalui *Sistem Pendataan Siswa ASTS*.\n\n";

                        $pesan .= "╭───────────────╮\n";
                        $pesan .= "   📋 *DATA TERBARU*\n";
                        $pesan .= "╰───────────────╯\n\n";

                        $pesan .= "👤 *Nama:* " . $name . "\n";
                        $pesan .= "🆔 *NISN:* " . $nisn . "\n";
                        $pesan .= "🎂 *TTL:* " . $ttl . "\n";
                        $pesan .= "⚧️ *Jenis Kelamin:* " . $gender . "\n";
                        $pesan .= "📧 *Email:* " . $email . "\n";
                        $pesan .= "📱 *WhatsApp:* " . $no_hp . "\n";
                        $pesan .= "📍 *Alamat:* " . $address . "\n\n";

                        $pesan .= "✅ *Status: Data berhasil diperbarui*\n\n";

                        $pesan .= "📌 Pastikan data di atas sudah sesuai dengan data kamu.\n";
                        $pesan .= "Jika ada kesalahan, silakan hubungi admin untuk melakukan perubahan kembali.\n\n";

                        $pesan .= "━━━━━━━━━━━━━━━━━━\n";
                        $pesan .= "🏫 *SISTEM PENDATAAN SISWA ASTS*\n";
                        $pesan .= "📱 Notifikasi Otomatis\n";
                        $pesan .= "━━━━━━━━━━━━━━━━━━\n\n";

                        $pesan .= "Terima kasih sudah memperbarui data. 🙏\n";
                        $pesan .= "Semoga proses ASTS kamu berjalan lancar! ✨";


                        /* =================================
                           KIRIM WHATSAPP
                        ================================= */

                        $hasilWA = kirimWhatsApp(
                            $no_hp,
                            $pesan
                        );


                        /* =================================
                           CEK HASIL WHATSAPP
                        ================================= */

                        if (
                            isset($hasilWA["status"]) &&
                            $hasilWA["status"] === true
                        ) {

                            $success = "Data berhasil diperbarui dan WhatsApp berhasil dikirim.";

                        } else {

                            $success = "Data berhasil diperbarui, tetapi WhatsApp gagal dikirim.";

                        }


                    } else {

                        $error = "Data gagal diperbarui: " . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
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

    <title>Edit Data | ASTS Galaxy</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            min-height: 100vh;

            font-family:
                "Courier New",
                Consolas,
                monospace;

            color: #f5edff;

            padding: 25px;

            background:

                radial-gradient(
                    circle at 15% 15%,
                    rgba(124, 58, 237, .25),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 85% 85%,
                    rgba(168, 85, 247, .20),
                    transparent 30%
                ),

                linear-gradient(
                    135deg,
                    #08030f,
                    #12051f,
                    #06020b
                );

            position: relative;

            overflow-x: hidden;
        }


        /* GRID */

        body::before {

            content: "";

            position: fixed;

            inset: 0;

            background-image:

                linear-gradient(
                    rgba(168, 85, 247, .035) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(168, 85, 247, .035) 1px,
                    transparent 1px
                );

            background-size: 40px 40px;

            pointer-events: none;

            z-index: 0;
        }


        /* BINTANG */

        body::after {

            content:
                "✦   ·   ✧   ·   ✦   ·   ✧   ·   ✦";

            position: fixed;

            inset: 0;

            color:
                rgba(
                    216,
                    180,
                    254,
                    .08
                );

            font-size: 22px;

            line-height: 100px;

            word-spacing: 40px;

            pointer-events: none;

            z-index: 0;
        }


        /* CONTAINER */

        .container {

            width: 100%;

            max-width: 850px;

            margin: 20px auto;

            position: relative;

            z-index: 1;

            background:

                linear-gradient(
                    145deg,
                    rgba(28, 10, 45, .98),
                    rgba(10, 3, 18, .98)
                );

            border:
                1px solid #6d28d9;

            border-top:
                4px solid #a855f7;

            padding: 30px;

            box-shadow:

                0 0 40px
                rgba(
                    168,
                    85,
                    247,
                    .18
                );
        }


        /* HEADER */

        .system-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding-bottom: 15px;

            margin-bottom: 25px;

            border-bottom:
                1px solid
                rgba(
                    168,
                    85,
                    247,
                    .20
                );

            font-size: 10px;

            letter-spacing: 1px;
        }


        .system-name {

            color: #c084fc;

            font-weight: bold;
        }


        .system-status {

            color: #a78bfa;
        }


        /* BACK */

        .back {

            display: inline-block;

            margin-bottom: 20px;

            padding: 9px 13px;

            text-decoration: none;

            color: #d8b4fe;

            background:
                rgba(
                    168,
                    85,
                    247,
                    .08
                );

            border:
                1px solid
                #6d28d9;

            font-size: 11px;

            transition: .2s;
        }


        .back:hover {

            color: white;

            border-color: #c084fc;

            box-shadow:
                0 0 12px
                rgba(
                    168,
                    85,
                    247,
                    .25
                );
        }


        /* TITLE */

        h2 {

            color: #d8b4fe;

            font-size: 24px;

            margin-bottom: 8px;

            text-shadow:
                0 0 15px
                rgba(
                    192,
                    132,
                    252,
                    .55
                );
        }


        .subtitle {

            color: #8b72a8;

            font-size: 11px;

            margin-bottom: 25px;
        }


        /* PATH */

        .path {

            background: #07020c;

            border:
                1px solid
                #3b176b;

            color: #a78bfa;

            padding: 10px 12px;

            font-size: 10px;

            margin-bottom: 22px;
        }


        .path span {

            color: #c084fc;
        }


        /* SUCCESS */

        .success {

            background:
                rgba(
                    22,
                    101,
                    52,
                    .18
                );

            border:
                1px solid
                #4ade80;

            color:
                #bbf7d0;

            padding: 12px;

            margin-bottom: 20px;

            font-size: 11px;

            line-height: 1.5;
        }


        /* ERROR */

        .error {

            background:
                rgba(
                    127,
                    29,
                    29,
                    .20
                );

            border:
                1px solid
                #ef4444;

            color:
                #fecaca;

            padding: 12px;

            margin-bottom: 20px;

            font-size: 11px;

            line-height: 1.5;
        }


        /* FORM */

        .form-group {

            margin-bottom: 18px;
        }


        label {

            display: block;

            margin-bottom: 7px;

            color: #d8b4fe;

            font-size: 11px;

            font-weight: bold;

            letter-spacing: .5px;
        }


        label::before {

            content: "> ";

            color: #a855f7;
        }


        input,
        select,
        textarea {

            width: 100%;

            padding: 12px;

            background:
                #08030f;

            border:
                1px solid
                #4c1d95;

            color: #f5edff;

            outline: none;

            border-radius: 3px;

            font-family:
                "Courier New",
                Consolas,
                monospace;

            font-size: 12px;

            transition: .2s;
        }


        input::placeholder,
        textarea::placeholder {

            color: #654b7d;
        }


        input:focus,
        select:focus,
        textarea:focus {

            border-color:
                #a855f7;

            box-shadow:

                0 0 0 2px
                rgba(
                    168,
                    85,
                    247,
                    .08
                ),

                0 0 15px
                rgba(
                    168,
                    85,
                    247,
                    .12
                );
        }


        select option {

            background: #160822;

            color: white;
        }


        textarea {

            resize: vertical;

            min-height: 110px;
        }


        /* INFO */

        .info {

            font-size: 10px;

            color: #9f7bbd;

            margin-top: 6px;
        }


        /* BUTTON */

        button {

            width: 100%;

            padding: 14px;

            border: none;

            border-radius: 3px;

            background:
                linear-gradient(
                    135deg,
                    #7c3aed,
                    #a855f7
                );

            border:
                1px solid
                #d8b4fe;

            color: white;

            font-family:
                "Courier New",
                Consolas,
                monospace;

            font-size: 12px;

            font-weight: bold;

            cursor: pointer;

            box-shadow:

                0 0 18px
                rgba(
                    168,
                    85,
                    247,
                    .25
                );

            transition: .2s;
        }


        button:hover {

            transform:
                translateY(-2px);

            box-shadow:

                0 0 28px
                rgba(
                    168,
                    85,
                    247,
                    .45
                );

            background:
                linear-gradient(
                    135deg,
                    #8b5cf6,
                    #c084fc
                );
        }


        /* FOOTER */

        .footer {

            text-align: center;

            margin-top: 25px;

            padding-top: 15px;

            border-top:
                1px solid
                rgba(
                    168,
                    85,
                    247,
                    .12
                );

            color: #4d3761;

            font-size: 9px;

            letter-spacing: .5px;
        }


        /* RESPONSIVE */

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .container {
                padding: 22px 18px;
                margin: 10px auto;
            }

            h2 {
                font-size: 20px;
            }

            .system-header {
                font-size: 8px;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="system-header">

        <div class="system-name">
            ASTS // STUDENT DATA
        </div>

        <div class="system-status">
            ● SYSTEM ONLINE
        </div>

    </div>


    <!-- BACK -->

    <a href="index.php" class="back">
        ← BACK TO DASHBOARD
    </a>


    <!-- TITLE -->

    <h2>
        + EDIT STUDENT DATA
    </h2>

    <div class="subtitle">
        Update informasi data siswa ASTS
    </div>


    <!-- PATH -->

    <div class="path">

        <span>root@asts</span>:~/system/student/edit

    </div>


    <!-- SUCCESS -->

    <?php if ($success !== ""): ?>

        <div class="success">

            ✓ SUCCESS:

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ""): ?>

        <div class="error">

            ⚠ ERROR:

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </div>

    <?php endif; ?>


    <!-- FORM -->

    <form method="POST" action="">


        <div class="form-group">

            <label for="name">
                NAMA LENGKAP
            </label>

            <input
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars(
                    $name,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="Masukkan nama lengkap..."
                required
            >

        </div>


        <div class="form-group">

            <label for="nisn">
                NISN
            </label>

            <input
                type="text"
                id="nisn"
                name="nisn"
                value="<?= htmlspecialchars(
                    $nisn,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="Masukkan NISN..."
                maxlength="10"
                required
            >

        </div>


        <div class="form-group">

            <label for="ttl">
                TEMPAT / TANGGAL LAHIR
            </label>

            <input
                type="text"
                id="ttl"
                name="ttl"
                value="<?= htmlspecialchars(
                    $ttl,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="Contoh: Ponorogo, 10 Januari 2008"
                required
            >

        </div>


        <div class="form-group">

            <label for="gender">
                JENIS KELAMIN
            </label>

            <select
                id="gender"
                name="gender"
                required
            >

                <option value="">
                    -- PILIH JENIS KELAMIN --
                </option>

                <option
                    value="Laki-laki"
                    <?= $gender === "Laki-laki"
                        ? "selected"
                        : ""; ?>
                >
                    Laki-laki
                </option>

                <option
                    value="Perempuan"
                    <?= $gender === "Perempuan"
                        ? "selected"
                        : ""; ?>
                >
                    Perempuan
                </option>

            </select>

        </div>


        <div class="form-group">

            <label for="email">
                EMAIL
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="Masukkan email..."
                required
            >

        </div>


        <div class="form-group">

            <label for="address">
                ALAMAT
            </label>

            <textarea
                id="address"
                name="address"
                placeholder="Masukkan alamat lengkap..."
                required
            ><?= htmlspecialchars(
                $address,
                ENT_QUOTES,
                "UTF-8"
            ); ?></textarea>

        </div>


        <div class="form-group">

            <label for="no_hp">
                NOMOR WHATSAPP
            </label>

            <input
                type="text"
                id="no_hp"
                name="no_hp"
                value="<?= htmlspecialchars(
                    $no_hp,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="628123456789"
                required
            >

            <div class="info">

                Format WhatsApp:
                628123456789

            </div>

        </div>


        <button type="submit">

            [ ✓ ] SIMPAN PERUBAHAN & KIRIM WHATSAPP

        </button>


    </form>


    <!-- FOOTER -->

    <div class="footer">

        ASTS GALAXY SYSTEM // STUDENT DATA MANAGEMENT PANEL

    </div>


</div>


</body>

</html>