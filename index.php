
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

$hiba = $_SESSION["hiba"] ?? "";
unset($_SESSION["hiba"]);

$uzenet = $_SESSION["uzenet"] ?? "";
unset($_SESSION["uzenet"]);

/*
|--------------------------------------------------------------------------
| AKTÍV TAGOK LEKÉRDEZÉSE
|--------------------------------------------------------------------------
*/

$aktivTagok = [];
$tagokLekerdezesiHiba = false;

/*
|--------------------------------------------------------------------------
| NYILVÁNOS HÍREK ÉS ESEMÉNYEK
|--------------------------------------------------------------------------
*/
$nyilvanosHirek = [];
$hirekLekerdezesiHiba = false;

try {
    $hirekStmt = $pdo->prepare("
        SELECT id, cim, rovid_leiras, tartalom, kep, letrehozva
        FROM hirek
        WHERE aktiv = 1
        ORDER BY letrehozva DESC, id DESC
        LIMIT 6
    ");
    $hirekStmt->execute();
    $nyilvanosHirek = $hirekStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hirekLekerdezesiHiba = true;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            teljes_nev,
            becenev,
            beosztas,
            profilkep
        FROM tagok
        WHERE aktiv = 1
          AND nyilvanos_adatlap = 1
        ORDER BY teljes_nev ASC
        LIMIT 12
    ");

    $stmt->execute();
    $aktivTagok = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $tagokLekerdezesiHiba = true;
}

function indexE($ertek): string
{
    return htmlspecialchars(
        (string)($ertek ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
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
        content="SZÖTE – Önkéntes Tűzoltó Egyesület. Segítség, közösség, önkéntesség."
    >

    <title>SZÖTE – Önkéntes Tűzoltó Egyesület</title>

    <link rel="stylesheet" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <style>
        /* =====================================================
           ALAPBEÁLLÍTÁSOK
           ===================================================== */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body.public-page {
            margin: 0;
            font-family: "Inter", Arial, sans-serif;
            background: #f5f5f3;
            color: #18181a;
        }

        body.public-page a {
            text-decoration: none;
        }

        body.public-page img {
            max-width: 100%;
        }

        /* =====================================================
           FEJLÉC
           ===================================================== */

        .public-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(13, 13, 15, 0.97);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        }

        .public-header-inner {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .public-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            font-size: 21px;
            font-weight: 900;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .public-logo-mark {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #c8102e;
            color: #ffffff;
            font-size: 14px;
            font-weight: 900;
            box-shadow: 0 5px 18px rgba(200, 16, 46, 0.35);
        }

        .public-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .public-nav a {
            color: #d8d8dc;
            padding: 10px 13px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .public-nav a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .public-nav .public-login {
            margin-left: 8px;
            background: #c8102e;
            color: #ffffff;
        }

        .public-nav .public-login:hover {
            background: #e31837;
        }

        /* =====================================================
           FŐ BEMUTATKOZÓ RÉSZ
           ===================================================== */

        .public-hero {
            position: relative;
            min-height: 650px;
            display: flex;
            align-items: center;
            overflow: hidden;
            background:
                radial-gradient(
                    circle at 85% 25%,
                    rgba(200, 16, 46, 0.35),
                    transparent 30%
                ),
                linear-gradient(
                    135deg,
                    #09090a 0%,
                    #111114 55%,
                    #1c1c20 100%
                );
        }

        .public-hero::before {
            content: "";
            position: absolute;
            width: 520px;
            height: 520px;
            right: -180px;
            bottom: -250px;
            border-radius: 50%;
            border: 80px solid rgba(200, 16, 46, 0.08);
            pointer-events: none;
        }

        .public-hero-inner {
            position: relative;
            z-index: 2;
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 70px;
            align-items: center;
            padding: 70px 0;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #ff6178;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2.5px;
            margin-bottom: 18px;
        }

        .hero-eyebrow::before {
            content: "";
            width: 32px;
            height: 3px;
            border-radius: 5px;
            background: #c8102e;
        }

        .public-hero h1 {
            margin: 0;
            color: #ffffff;
            font-size: clamp(42px, 6vw, 76px);
            line-height: 1.02;
            letter-spacing: -2.5px;
            font-weight: 900;
        }

        .public-hero h1 span {
            color: #c8102e;
        }

        .public-hero-text {
            max-width: 650px;
            margin: 25px 0 0;
            color: #c7c7cb;
            font-size: 18px;
            line-height: 1.75;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 32px;
        }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            padding: 0 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .hero-btn-red {
            background: #c8102e;
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(200, 16, 46, 0.28);
        }

        .hero-btn-red:hover {
            background: #e31837;
            transform: translateY(-2px);
        }

        .hero-btn-light {
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            background: rgba(255, 255, 255, 0.04);
        }

        .hero-btn-light:hover {
            background: rgba(255, 255, 255, 0.09);
            transform: translateY(-2px);
        }

        /* =====================================================
           FŐOLDALI GYORS ELÉRÉS
           ===================================================== */

        .hero-action-panel {
            padding: 26px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.055);
            border: 1px solid rgba(255, 255, 255, 0.11);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.22);
            backdrop-filter: blur(10px);
        }

        .hero-action-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .hero-action-subtitle {
            color: #9e9ea5;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .hero-action {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px;
            margin-top: 10px;
            border-radius: 11px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.07);
            color: #ffffff;
            transition: 0.2s ease;
        }

        .hero-action:hover {
            background: rgba(255, 255, 255, 0.09);
            transform: translateX(3px);
        }

        .hero-action-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #c8102e;
            font-size: 20px;
        }

        .hero-action strong {
            display: block;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .hero-action span {
            display: block;
            color: #9999a0;
            font-size: 12px;
        }

        /* =====================================================
           SEGÉLYHÍVÓ SÁV
           ===================================================== */

        .emergency-bar {
            background: #c8102e;
            color: #ffffff;
        }

        .emergency-inner {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .emergency-main {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
        }

        .emergency-main strong {
            font-weight: 900;
        }

        .emergency-number {
            color: #ffffff;
            font-size: 20px;
            font-weight: 900;
            white-space: nowrap;
        }

        /* =====================================================
           ÁLTALÁNOS SZEKCIÓK
           ===================================================== */

        .public-section {
            padding: 90px 0;
        }

        .public-section-dark {
            background: #0d0d0f;
            color: #ffffff;
        }

        .public-container {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
        }

        .section-heading {
            max-width: 720px;
            margin-bottom: 45px;
        }

        .section-heading.center {
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .section-eyebrow {
            display: block;
            color: #c8102e;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: clamp(30px, 4vw, 45px);
            line-height: 1.1;
            letter-spacing: -1px;
            font-weight: 900;
        }

        .section-heading p {
            margin: 18px 0 0;
            color: #707075;
            line-height: 1.75;
            font-size: 15px;
        }

        .public-section-dark .section-heading p {
            color: #a2a2a7;
        }

        /* =====================================================
           GYORS FUNKCIÓK KÁRTYÁI
           ===================================================== */

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .quick-card {
            position: relative;
            padding: 27px;
            min-height: 190px;
            border-radius: 15px;
            background: #ffffff;
            border: 1px solid #e5e5e5;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.045);
            color: #18181a;
            transition: 0.2s ease;
        }

        .quick-card:hover {
            transform: translateY(-5px);
            border-color: #d3d3d3;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.09);
        }

        .quick-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #f2f2f1;
            font-size: 23px;
            margin-bottom: 20px;
        }

        .quick-card.red .quick-icon {
            background: #fff0f2;
        }

        .quick-card h3 {
            margin: 0 0 8px;
            font-size: 17px;
        }

        .quick-card p {
            margin: 0;
            color: #707075;
            font-size: 13px;
            line-height: 1.65;
        }

        .quick-arrow {
            position: absolute;
            right: 22px;
            bottom: 20px;
            color: #c8102e;
            font-size: 20px;
        }

        /* =====================================================
           RÓLUNK
           ===================================================== */

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-text p {
            color: #55555a;
            line-height: 1.85;
            font-size: 15px;
            margin: 0 0 18px;
        }

        .about-panel {
            padding: 35px;
            border-radius: 18px;
            background: linear-gradient(135deg, #111114, #202024);
            color: #ffffff;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.12);
        }

        .about-panel-number {
            font-size: 52px;
            line-height: 1;
            font-weight: 900;
            color: #c8102e;
            margin-bottom: 12px;
        }

        .about-panel h3 {
            margin: 0 0 10px;
            font-size: 22px;
        }

        .about-panel p {
            margin: 0;
            color: #bdbdc2;
            line-height: 1.7;
            font-size: 14px;
        }

        /* =====================================================
           SZOLGÁLAT
           ===================================================== */

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .service-card {
            padding: 30px;
            border-radius: 15px;
            background: #17171a;
            border: 1px solid #29292d;
        }

        .service-icon {
            font-size: 30px;
            margin-bottom: 20px;
        }

        .service-card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }

        .service-card p {
            margin: 0;
            color: #9d9da3;
            line-height: 1.7;
            font-size: 13px;
        }

        /* =====================================================
           HÍREK ÉS ESEMÉNYEK
           ===================================================== */

        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .news-placeholder {
            padding: 30px;
            border-radius: 15px;
            background: #ffffff;
            border: 1px solid #e4e4e4;
        }

        .news-placeholder-icon {
            font-size: 30px;
            margin-bottom: 15px;
        }

        .news-placeholder h3 {
            margin: 0 0 9px;
            font-size: 17px;
        }

        .news-placeholder p {
            margin: 0;
            color: #707075;
            line-height: 1.7;
            font-size: 13px;
        }

        /* =====================================================
           AKTÍV TAGOK
           ===================================================== */

        .active-members-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .active-member-card {
            display: flex;
            color: inherit;
            text-decoration: none;
            align-items: center;
            gap: 18px;
            min-width: 0;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.045);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .active-member-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 35px rgba(0, 0, 0, 0.09);
        }

        .active-member-photo {
            width: 76px;
            height: 76px;
            min-width: 76px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f4d5da;
            background: #f2f2f1;
        }

        .active-member-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background: #c8102e;
            font-size: 28px;
        }

        .active-member-info {
            min-width: 0;
        }

        .active-member-info h3 {
            margin: 0 0 7px;
            font-size: 16px;
            overflow-wrap: anywhere;
            color: #18181a;
        }

        .active-member-role {
            margin: 0 0 9px;
            color: #68686d;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .active-member-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #187746;
            background: #eaf7ef;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .active-member-status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #219653;
        }

        .active-members-message {
            padding: 28px;
            border: 1px solid #e5e5e5;
            border-radius: 15px;
            background: #ffffff;
            color: #707075;
            line-height: 1.7;
        }

        /* =====================================================
           TÁMOGATÁS
           ===================================================== */

        .support-section {
            background: linear-gradient(135deg, #c8102e 0%, #9e0d25 100%);
            color: #ffffff;
        }

        .support-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 50px;
            align-items: center;
        }

        .support-section .section-eyebrow {
            color: #ffd9df;
        }

        .support-section h2 {
            margin: 0;
            font-size: clamp(32px, 4vw, 48px);
            line-height: 1.1;
        }

        .support-text {
            margin: 20px 0 0;
            color: #ffecef;
            line-height: 1.8;
            font-size: 15px;
            max-width: 700px;
        }

        .support-card {
            padding: 30px;
            border-radius: 16px;
            background: rgba(0, 0, 0, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .support-card h3 {
            margin: 0 0 10px;
            font-size: 19px;
        }

        .support-card p {
            margin: 0;
            color: #ffe4e9;
            font-size: 13px;
            line-height: 1.7;
        }

        .support-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 9px;
            background: #ffffff;
            color: #a30e27;
            font-size: 14px;
            font-weight: 900;
        }

        /* =====================================================
           KAPCSOLAT
           ===================================================== */

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .contact-card {
            padding: 25px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #e4e4e4;
        }

        .contact-icon {
            font-size: 25px;
            margin-bottom: 15px;
        }

        .contact-card h3 {
            margin: 0 0 7px;
            font-size: 16px;
        }

        .contact-card p {
            margin: 0;
            color: #707075;
            font-size: 13px;
            line-height: 1.6;
        }

        .contact-card a {
            color: #c8102e;
            font-weight: 700;
        }

        /* =====================================================
           LÁBLÉC
           ===================================================== */

        .public-footer {
            background: #080809;
            color: #ffffff;
            padding-top: 55px;
        }

        .footer-grid {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 45px;
            padding-bottom: 45px;
        }

        .footer-brand p {
            color: #8d8d93;
            font-size: 13px;
            line-height: 1.7;
            max-width: 350px;
        }

        .footer-column h3 {
            margin: 0 0 16px;
            font-size: 14px;
        }

        .footer-column a {
            display: block;
            color: #929297;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .footer-column a:hover {
            color: #ffffff;
        }

        .footer-bottom {
            border-top: 1px solid #202024;
            padding: 20px;
            text-align: center;
            color: #68686d;
            font-size: 12px;
        }

        /* =====================================================
           TABLET
           ===================================================== */

        @media (max-width: 950px) {
            .public-header-inner {
                flex-wrap: wrap;
                padding-top: 12px;
                padding-bottom: 12px;
            }

            .public-nav {
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .public-hero-inner {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .hero-action-panel {
                max-width: 600px;
            }

            .quick-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .about-grid,
            .support-grid {
                grid-template-columns: 1fr;
            }

            .service-grid,
            .news-grid,
            .contact-grid,
            .active-members-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* =====================================================
           MOBIL
           ===================================================== */

        @media (max-width: 650px) {
            .public-header-inner {
                min-height: 68px;
            }

            .public-logo {
                font-size: 18px;
            }

            .public-logo-mark {
                width: 38px;
                height: 38px;
            }

            .public-hero {
                min-height: auto;
            }

            .public-hero-inner {
                padding: 60px 0;
            }

            .public-hero h1 {
                letter-spacing: -1.5px;
            }

            .public-hero-text {
                font-size: 15px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-btn {
                width: 100%;
            }

            .emergency-inner {
                flex-direction: column;
                align-items: flex-start;
                padding: 13px 0;
                gap: 5px;
            }

            .quick-grid,
            .service-grid,
            .news-grid,
            .contact-grid,
            .active-members-grid {
                grid-template-columns: 1fr;
            }

            .public-section {
                padding: 65px 0;
            }

            .active-member-card {
                padding: 18px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>

</head>

<body class="public-page">

<!-- =====================================================
     FEJLÉC
     ===================================================== -->

<header class="public-header">

    <div class="public-header-inner">

        <a href="index.php" class="public-logo">
            <span class="public-logo-mark">SZ</span>
            <span>ÖTE</span>
        </a>

        <nav class="public-nav">

            <a href="#rolunk">Rólunk</a>
            <a href="#szolgalat">Szolgálat</a>
            <a href="#elerheto">Tagjaink</a>
            <a href="#hirek">Hírek</a>
            <a href="#tamogatas">Támogatás</a>
            <a href="#kapcsolat">Kapcsolat</a>

            <a href="login.php" class="public-login">
                🔐 Belépés
            </a>

        </nav>

    </div>

</header>

<!-- =====================================================
     NYITÓRÉSZ
     ===================================================== -->

<section class="public-hero">

    <div class="public-hero-inner">

        <div>

            <span class="hero-eyebrow">
                ÖNKÉNTES TŰZOLTÓ EGYESÜLET
            </span>

            <h1>
                Egy csapat.
                <br>
                Egy cél.
                <br>
                <span>Segíteni.</span>
            </h1>

            <p class="public-hero-text">
                Közösségünk önkéntes tűzoltói azért dolgoznak,
                hogy szükség esetén segítséget nyújtsanak,
                és hozzájáruljanak településünk biztonságához.
            </p>

            <div class="hero-buttons">

                <a
                    href="esemeny_bejelentes.php"
                    class="hero-btn hero-btn-red"
                >
                    🚨 Kár bejelentése
                </a>

                <a
                    href="#elerheto"
                    class="hero-btn hero-btn-light"
                >
                    👨‍🚒 Aktív tagjaink
                </a>

            </div>

        </div>

        <div class="hero-action-panel">

            <div class="hero-action-title">
                Miben segíthetünk?
            </div>

            <div class="hero-action-subtitle">
                Nyilvános felületünkön bejelentést tehetsz,
                megismerheted közösségünk munkáját, és
                tájékozódhatsz az egyesületről.
            </div>

            <a href="esemeny_bejelentes.php" class="hero-action">

                <div class="hero-action-icon">🚨</div>

                <div>
                    <strong>Kár típusa</strong>
                    <span>Válaszd ki a kár típusát.</span>
                </div>

            </a>

            <a href="#elerheto" class="hero-action">

                <div class="hero-action-icon">👨‍🚒</div>

                <div>
                    <strong>Aktív önkéntesek</strong>
                    <span>Ismerd meg csapatunk tagjait.</span>
                </div>

            </a>

            <a href="#tamogatas" class="hero-action">

                <div class="hero-action-icon">❤️</div>

                <div>
                    <strong>Támogasd munkánkat</strong>
                    <span>Segítsd az önkéntes tűzoltók munkáját.</span>
                </div>

            </a>

        </div>

    </div>

</section>

<!-- =====================================================
     VÉSZHELYZET
     ===================================================== -->

<div class="emergency-bar">

    <div class="emergency-inner">

        <div class="emergency-main">
            <span>🚨</span>
            <span>
                <strong>Vészhelyzet esetén</strong>
                mindig a 112-es segélyhívót hívd!
            </span>
        </div>

        <div class="emergency-number">112</div>

    </div>

</div>

<!-- =====================================================
     GYORS FUNKCIÓK
     ===================================================== -->

<section class="public-section">

    <div class="public-container">

        <div class="section-heading center">

            <span class="section-eyebrow">GYORS ELÉRÉS</span>

            <h2>Amit közvetlenül elérhetsz</h2>

            <p>
                A legfontosabb nyilvános funkciókat egy helyen találod.
            </p>

        </div>

        <div class="quick-grid">

            <a href="esemeny_bejelentes.php" class="quick-card red">

                <div class="quick-icon">🚨</div>

                <h3>Kár bejelentése</h3>

                <p>Kár típusa kiválasztása.</p>

                <span class="quick-arrow">→</span>

            </a>

            <a href="#elerheto" class="quick-card">

                <div class="quick-icon">👨‍🚒</div>

                <h3>Aktív tagjaink</h3>

                <p>
                    Ismerd meg az adatbázisban aktívként
                    nyilvántartott önkénteseket.
                </p>

                <span class="quick-arrow">→</span>

            </a>

            <a href="#tamogatas" class="quick-card">

                <div class="quick-icon">❤️</div>

                <h3>Támogatás</h3>

                <p>
                    Támogasd egyesületünket, és járulj hozzá
                    az önkéntes tűzoltói munka fenntartásához.
                </p>

                <span class="quick-arrow">→</span>

            </a>

            <a href="#hirek" class="quick-card">

                <div class="quick-icon">📰</div>

                <h3>Hírek és események</h3>

                <p>
                    Kövesd nyomon az egyesület életét,
                    programjait és rendezvényeit.
                </p>

                <span class="quick-arrow">→</span>

            </a>

        </div>

    </div>

</section>

<!-- =====================================================
     RÓLUNK
     ===================================================== -->

<section id="rolunk" class="public-section">

    <div class="public-container">

        <div class="about-grid">

            <div>

                <div class="section-heading">

                    <span class="section-eyebrow">RÓLUNK</span>

                    <h2>
                        Egy közösség,
                        amely számíthat egymásra.
                    </h2>

                </div>

                <div class="about-text">

                    <p>
                        Egyesületünk önkéntes tűzoltókból álló
                        közösség, amelynek célja a lakosság és
                        a település biztonságának támogatása.
                    </p>

                    <p>
                        Tagjaink szabadidejükben vállalnak
                        szolgálatot, vesznek részt gyakorlatokon,
                        képzéseken és szükség esetén káresemények
                        felszámolásában.
                    </p>

                    <p>
                        Hiszünk az összetartásban, a felkészültségben
                        és abban, hogy egy közösség akkor erős,
                        amikor számíthat egymásra.
                    </p>

                </div>

            </div>

            <div class="about-panel">

                <div class="about-panel-number">24/7</div>

                <h3>A segítségre mindig szükség lehet.</h3>

                <p>
                    Önkénteseink felkészülten, közösségünkért
                    dolgozva állnak a szükséges segítségnyújtás
                    szolgálatában.
                </p>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     SZOLGÁLAT
     ===================================================== -->

<section id="szolgalat" class="public-section public-section-dark">

    <div class="public-container">

        <div class="section-heading">

            <span class="section-eyebrow">SZOLGÁLAT</span>

            <h2>Amit csinálunk</h2>

            <p>
                Az önkéntes tűzoltói munka nem csak a káresetekről szól.
            </p>

        </div>

        <div class="service-grid">

            <div class="service-card">

                <div class="service-icon">🚒</div>

                <h3>Káresetek</h3>

                <p>
                    Szükség esetén részt veszünk tűzesetek és
                    egyéb káresemények felszámolásában.
                </p>

            </div>

            <div class="service-card">

                <div class="service-icon">🔥</div>

                <h3>Gyakorlat és felkészülés</h3>

                <p>
                    Folyamatosan fejlesztjük felkészültségünket
                    és gyakorlati tudásunkat.
                </p>

            </div>

            <div class="service-card">

                <div class="service-icon">🤝</div>

                <h3>Közösség</h3>

                <p>
                    Fontos számunkra a helyi közösség támogatása
                    és az önkéntesség értékeinek képviselete.
                </p>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     AKTÍV TAGOK – ADATBÁZISBÓL
     ===================================================== -->

<section id="elerheto" class="public-section">

    <div class="public-container">

        <div class="section-heading">

            <span class="section-eyebrow">CSAPATUNK</span>

            <h2>Aktív önkéntes tűzoltóink</h2>

            <p>
                Ismerd meg egyesületünk aktív tagjait,
                akik részt vesznek közösségünk munkájában.
            </p>

        </div>

        <?php if ($tagokLekerdezesiHiba): ?>

            <div class="active-members-message">
                Az aktív tagok adatait jelenleg nem sikerült
                betölteni. Ellenőrizd az adatbázis-kapcsolatot
                és a tagok táblájának mezőit.
            </div>

        <?php elseif (empty($aktivTagok)): ?>

            <div class="active-members-message">
                Jelenleg nincs megjeleníthető aktív tag.
                Ellenőrizd a tagok adatlapján, hogy az
                „Aktív” mező be van-e kapcsolva.
            </div>

        <?php else: ?>

            <div class="active-members-grid">

                <?php foreach ($aktivTagok as $tag): ?>

                    <?php
                    $nev = trim((string)($tag["teljes_nev"] ?? ""));
                    $beosztas = trim((string)($tag["beosztas"] ?? ""));
                    // Az adatbázis csak a fájlnevet tárolja; a képek a profilkepek mappában vannak.
                    $profilkepFajlnev = trim((string)($tag["profilkep"] ?? ""));
                    $profilkepUrl = $profilkepFajlnev !== ""
                        ? "profilkepek/" . rawurlencode(basename($profilkepFajlnev))
                        : "";
                    ?>

                    <a
                        class="active-member-card"
                        href="tagok_adatlap.php?id=<?= (int)($tag["id"] ?? 0) ?>"
                        aria-label="<?= indexE($nev !== "" ? $nev : "Tag") ?> adatlapjának megnyitása"
                    >

                        <?php if ($profilkepUrl !== ""): ?>

                            <img
                                class="active-member-photo"
                                src="<?= indexE($profilkepUrl) ?>"
                                alt="<?= indexE($nev) ?> profilképe"
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                            <div
                                class="active-member-photo active-member-placeholder"
                                style="display:none;"
                                aria-hidden="true"
                            >🚒</div>

                        <?php else: ?>

                            <div
                                class="active-member-photo active-member-placeholder"
                                aria-hidden="true"
                            >🚒</div>

                        <?php endif; ?>

                        <div class="active-member-info">

                            <h3>
                                <?= indexE($nev !== "" ? $nev : "Név nélkül") ?>
                            </h3>

                            <p class="active-member-role">
                                <?= indexE($beosztas !== "" ? $beosztas : "Önkéntes tűzoltó") ?>
                            </p>

                            <span class="active-member-status">
                                Aktív tag
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

<!-- =====================================================
     HÍREK ÉS ESEMÉNYEK
     ===================================================== -->

<section id="hirek" class="public-section">

    <div class="public-container">

        <div class="section-heading">

            <span class="section-eyebrow">HÍREK</span>

            <h2>Hírek és események</h2>

            <p>
                Az egyesület legfrissebb hírei, rendezvényei és programjai.
            </p>

            <div style="margin-top:20px;">
                <a href="admin_hirek.php" class="hero-btn hero-btn-red">📰 Hírek kezelése (admin)</a>
            </div>

        </div>

        <?php if ($hirekLekerdezesiHiba): ?>

            <div class="news-placeholder">
                <h3>A hírek jelenleg nem érhetők el.</h3>
                <p>Kérjük, próbáld meg később újra.</p>
            </div>

        <?php elseif (empty($nyilvanosHirek)): ?>

            <div class="news-placeholder">
                <div class="news-placeholder-icon">📰</div>
                <h3>Még nincs közzétett hír</h3>
                <p>Az egyesület hírei és eseményei itt jelennek meg, amint az adminisztrátor közzéteszi őket.</p>
            </div>

        <?php else: ?>

            <div class="news-grid">

                <?php foreach ($nyilvanosHirek as $hir): ?>
                    <article class="news-placeholder">

                        <?php if (!empty($hir['kep'])): ?>
                            <img
                                src="<?= indexE($hir['kep']) ?>"
                                alt="<?= indexE($hir['cim']) ?>"
                                loading="lazy"
                                style="display:block;width:100%;max-height:220px;object-fit:cover;border-radius:10px;margin-bottom:18px;"
                            >
                        <?php else: ?>
                            <div class="news-placeholder-icon" aria-hidden="true">📰</div>
                        <?php endif; ?>

                        <h3><?= indexE($hir['cim']) ?></h3>

                        <?php if (!empty($hir['letrehozva'])): ?>
                            <p style="margin-bottom:10px;font-size:12px;color:#8b8b91;">
                                <?= indexE(date('Y. m. d.', strtotime((string)$hir['letrehozva']))) ?>
                            </p>
                        <?php endif; ?>

                        <p><?= nl2br(indexE($hir['rovid_leiras'])) ?></p>

                        <?php if (!empty($hir['tartalom'])): ?>
                            <details style="margin-top:14px;">
                                <summary style="cursor:pointer;color:#c82026;font-weight:700;">Teljes hír elolvasása</summary>
                                <div style="margin-top:12px;color:#555;line-height:1.7;white-space:normal;">
                                    <?= nl2br(indexE($hir['tartalom'])) ?>
                                </div>
                            </details>
                        <?php endif; ?>

                    </article>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

<!-- =====================================================
     TÁMOGATÁS
     ===================================================== -->

<section id="tamogatas" class="public-section support-section">

    <div class="public-container">

        <div class="support-grid">

            <div>

                <span class="section-eyebrow">TÁMOGATÁS</span>

                <h2>
                    Segítsd az önkéntes tűzoltók munkáját!
                </h2>

                <p class="support-text">
                    Az önkéntes tűzoltói munka mögött egy
                    összetartó közösség, sok gyakorlás,
                    felszerelés és rengeteg szabadidő áll.
                    Támogatásoddal hozzájárulhatsz ahhoz,
                    hogy egyesületünk továbbra is felkészülten
                    szolgálhassa közösségünket.
                </p>

            </div>

            <div class="support-card">

                <h3>❤️ Támogatom az egyesületet</h3>

                <p>
                    A támogatási lehetőségek, bankszámlaszám
                    és további információk ezen az oldalon
                    lesznek elérhetők.
                </p>

                <a href="#kapcsolat" class="support-button">
                    Támogatási információk
                </a>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     KAPCSOLAT
     ===================================================== -->

<section id="kapcsolat" class="public-section">

    <div class="public-container">

        <div class="section-heading">

            <span class="section-eyebrow">KAPCSOLAT</span>

            <h2>Vedd fel velünk a kapcsolatot</h2>

            <p>
                A nyilvános kapcsolati adatok később az
                adminisztrációs felületről lesznek szerkeszthetők.
            </p>

        </div>

        <div class="contact-grid">

            <div class="contact-card">

                <div class="contact-icon">📍</div>

                <h3>Székhely</h3>

                <p>
                    Az egyesület hivatalos címe
                    itt lesz megjelenítve.
                </p>

            </div>

            <div class="contact-card">

                <div class="contact-icon">📞</div>

                <h3>Telefon</h3>

                <p>
                    A nyilvános telefonszám
                    itt lesz megjelenítve.
                </p>

            </div>

            <div class="contact-card">

                <div class="contact-icon">✉️</div>

                <h3>E-mail</h3>

                <p>
                    A hivatalos e-mail cím
                    itt lesz megjelenítve.
                </p>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     LÁBLÉC
     ===================================================== -->

<footer class="public-footer">

    <div class="footer-grid">

        <div class="footer-brand">

            <a href="index.php" class="public-logo">

                <span class="public-logo-mark">SZ</span>
                <span>SZÖTE</span>

            </a>

            <p>
                Önkéntes tűzoltó egyesület.
                Egy közösség, amely segít,
                amikor szükség van rá.
            </p>

        </div>

        <div class="footer-column">

            <h3>Gyorslinkek</h3>

            <a href="esemeny_bejelentes.php">
                🚨 Kár bejelentése
            </a>

            <a href="#elerheto">
                👨‍🚒 Aktív tagjaink
            </a>

            <a href="#hirek">📰 Hírek</a>

            <a href="#tamogatas">❤️ Támogatás</a>

        </div>

        <div class="footer-column">

            <h3>Egyesület</h3>

            <a href="#rolunk">Rólunk</a>

            <a href="#szolgalat">Szolgálat</a>

            <a href="#kapcsolat">Kapcsolat</a>

            <a href="login.php">🔐 Tag belépés</a>

        </div>

    </div>

    <div class="footer-bottom">
        © <?= date("Y") ?> SZÖTE – Minden jog fenntartva.
    </div>

</footer>

</body>
</html>