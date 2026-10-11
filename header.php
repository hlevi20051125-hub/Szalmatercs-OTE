<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$felhasznalo = $_SESSION['felhasznalo'] ?? null;
$oldalCim = $oldalCim ?? 'SZÖTE';

$fejlecProfilkepUrl = '';
$fejlecFelhasznaloId = (int)($felhasznalo['id'] ?? 0);

/*
 * A bejelentkezett felhasználóhoz tartozó profilkép lekérése.
 * A header.php más oldalakon is szerepelhet, ezért csak akkor
 * töltjük be az adatbázis-kapcsolatot, ha még nincs $pdo változó.
 */
if ($felhasznalo && $fejlecFelhasznaloId > 0) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once __DIR__ . '/db.php';
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            $fejlecSt = $pdo->prepare("
                SELECT profilkep
                FROM tagok
                WHERE felhasznalo_id = ?
                LIMIT 1
            ");
            $fejlecSt->execute([$fejlecFelhasznaloId]);
            $fejlecProfilkep = trim((string)($fejlecSt->fetchColumn() ?: ''));

            if ($fejlecProfilkep !== '') {
                if (
                    str_starts_with($fejlecProfilkep, 'https://')
                    || str_starts_with($fejlecProfilkep, 'http://')
                ) {
                    $fejlecProfilkepUrl = $fejlecProfilkep;
                } else {
                    // Az adatbázisban tárolt fájlnév a profilkepek mappában van.
                    $fejlecProfilkepUrl = 'profilkepek/' . rawurlencode(basename($fejlecProfilkep));
                }
            }
        }
    } catch (Throwable $e) {
        // Ha a profilkép lekérése nem sikerül, az oldal többi része működjön tovább.
        $fejlecProfilkepUrl = '';
    }
}

?>
<!DOCTYPE html>
<html lang="hu">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="SZÖTE – Önkéntes Tűzoltó Egyesület"
    >

    <title>
        <?= htmlspecialchars($oldalCim, ENT_QUOTES, 'UTF-8') ?> – SZÖTE
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>
        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
        }

        .user-pill-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #c8102e;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
        }

        .user-pill-icon img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>

</head>


<body class="app-page">


<header class="app-header">

    <div class="app-header-inner">


        <!-- =====================================================
             LOGÓ
        ====================================================== -->

        <a
            class="logo"
            href="<?= $felhasznalo ? 'dashboard.php' : 'index.php' ?>"
        >

            <span class="logo-mark">
                SZ
            </span>

            <span>
                ÖTE
            </span>

        </a>


        <?php if ($felhasznalo): ?>


            <!-- =================================================
                 NAVIGÁCIÓ
            ================================================== -->

            <nav class="app-nav">

                <a href="dashboard.php">
                    Vezérlőpult
                </a>

                <a href="tagok.php">
                    Tagok
                </a>

                <a href="riasztasok.php">
                    Riasztások
                </a>

                <a href="bevetesek.php">
                    Bevetések
                </a>


                <?php if (
                    ($felhasznalo['szerepkor'] ?? '') === 'admin'
                ): ?>

                    <a href="admin.php">
                        Admin
                    </a>

                <?php endif; ?>


                <a
                    class="nav-login"
                    href="logout.php"
                >
                    Kilépés
                </a>

            </nav>


            <!-- =================================================
                 FELHASZNÁLÓ / PROFIL
            ================================================== -->

            <a
                class="user-pill"
                href="profil.php"
                title="Saját profil megnyitása"
            >

                <span class="user-pill-icon">
                    <?php if ($fejlecProfilkepUrl !== ''): ?>
                        <img
                            src="<?= htmlspecialchars($fejlecProfilkepUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Profilkép"
                        >
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </span>

                <span>
                    <?= htmlspecialchars(
                        $felhasznalo['felhasznalonev'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

            </a>


        <?php else: ?>


            <!-- =================================================
                 KIJELENTKEZETT NAVIGÁCIÓ
            ================================================== -->

            <nav class="app-nav">

                <a href="index.php">
                    Főoldal
                </a>

                <a
                    href="login.php"
                    class="nav-login"
                >
                    Bejelentkezés
                </a>

            </nav>


        <?php endif; ?>


    </div>

</header>


<main class="page-main">

    <div class="page-container">
