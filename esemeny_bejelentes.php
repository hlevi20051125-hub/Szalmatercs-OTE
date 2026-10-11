<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$hiba = "";
$siker = "";

$esemenyTipus = "";
$helyszin = "";
$telepules = "";
$leiras = "";
$bejelentoNev = "";
$bejelentoTelefon = "";
$bejelentoEmail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $esemenyTipus = trim($_POST["esemeny_tipus"] ?? "");

    $helyszin = trim($_POST["helyszin"] ?? "");

    $telepules = trim($_POST["telepules"] ?? "");

    $leiras = trim($_POST["leiras"] ?? "");

    $bejelentoNev = trim($_POST["bejelento_nev"] ?? "");

    $bejelentoTelefon = trim($_POST["bejelento_telefon"] ?? "");

    $bejelentoEmail = trim($_POST["bejelento_email"] ?? "");

    /*

     * Alapértelmezett ellenőrzés

     */

    if ($esemenyTipus === "") {

        $hiba = "Kérjük, válaszd ki az esemény típusát.";

    } elseif ($helyszin === "") {

        $hiba = "Kérjük, add meg az esemény helyszínét.";

    } elseif ($telepules === "") {

        $hiba = "Kérjük, add meg a települést.";

    }

    /*

     * E-mail ellenőrzése, ha megadták

     */

    if (

        $hiba === "" &&

        $bejelentoEmail !== "" &&

        !filter_var($bejelentoEmail, FILTER_VALIDATE_EMAIL)

    ) {

        $hiba = "A megadott e-mail cím nem megfelelő.";

    }

    /*

     * Hosszkorlátok

     */

    if ($hiba === "" && mb_strlen($esemenyTipus, "UTF-8") > 100) {

        $hiba = "Az esemény típusa túl hosszú.";

    }

    if ($hiba === "" && mb_strlen($helyszin, "UTF-8") > 255) {

        $hiba = "A helyszín túl hosszú.";

    }

    if ($hiba === "" && mb_strlen($telepules, "UTF-8") > 100) {

        $hiba = "A település neve túl hosszú.";

    }

    if ($hiba === "" && mb_strlen($bejelentoNev, "UTF-8") > 100) {

        $hiba = "A név túl hosszú.";

    }

    if ($hiba === "" && mb_strlen($bejelentoTelefon, "UTF-8") > 30) {

        $hiba = "A telefonszám túl hosszú.";

    }

    if ($hiba === "" && mb_strlen($bejelentoEmail, "UTF-8") > 150) {

        $hiba = "Az e-mail cím túl hosszú.";

    }

    /*

     * Mentés

     */

    if ($hiba === "") {

        try {

            $sql = "

                INSERT INTO esemeny_bejelentesek (

                    esemeny_tipus,

                    helyszin,

                    telepules,

                    leiras,

                    bejelento_nev,

                    bejelento_telefon,

                    bejelento_email,

                    allapot

                )

                VALUES (?, ?, ?, ?, ?, ?, ?, 'uj')

            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                $esemenyTipus,

                $helyszin,

                $telepules,

                $leiras !== "" ? $leiras : null,

                $bejelentoNev !== "" ? $bejelentoNev : null,

                $bejelentoTelefon !== "" ? $bejelentoTelefon : null,

                $bejelentoEmail !== "" ? $bejelentoEmail : null

            ]);

            $siker = "Köszönjük! A bejelentésed sikeresen megérkezett az egyesülethez.";

            /*

             * Mezők ürítése sikeres beküldés után

             */

            $esemenyTipus = "";

            $helyszin = "";

            $telepules = "";

            $leiras = "";

            $bejelentoNev = "";

            $bejelentoTelefon = "";

            $bejelentoEmail = "";

        } catch (PDOException $e) {

            $hiba = "A bejelentés mentése közben hiba történt. Kérjük, próbáld meg később újra.";

        }

    }

}

?>

<!DOCTYPE html>

<html lang="hu">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"

          content="width=device-width, initial-scale=1.0">

    <meta

        name="description"

        content="Esemény vagy baleset bejelentése – SZÖTE"

    >

    <title>Esemény bejelentése – SZÖTE</title>

    <link

        rel="stylesheet"

        href="style.css"

    >

    <link

        rel="preconnect"

        href="https://fonts.googleapis.com"

    >

    <link

        rel="preconnect"

        href="https://fonts.gstatic.com"

        crossorigin

    >

    <link

        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"

        rel="stylesheet"

    >

    <style>

        * {

            box-sizing: border-box;

        }

        body {

            margin: 0;

            font-family: Inter, Arial, sans-serif;

            background: #f5f5f3;

            color: #18181a;

        }

        .bejelentes-header {

            background: #0d0d0f;

            color: #fff;

            border-bottom: 3px solid #c8102e;

        }

        .bejelentes-header-inner {

            width: min(1180px, calc(100% - 32px));

            margin: 0 auto;

            min-height: 76px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }

        .bejelentes-logo {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #fff;

            text-decoration: none;

            font-size: 21px;

            font-weight: 800;

        }

        .bejelentes-logo-mark {

            width: 42px;

            height: 42px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #c8102e;

            color: #fff;

            font-size: 14px;

            font-weight: 800;

        }

        .bejelentes-back {

            color: #fff;

            text-decoration: none;

            padding: 11px 16px;

            border: 1px solid rgba(255,255,255,.18);

            border-radius: 8px;

            font-size: 14px;

            font-weight: 600;

            transition: .2s ease;

        }

        .bejelentes-back:hover {

            background: rgba(255,255,255,.08);

        }

        .bejelentes-main {

            width: min(900px, calc(100% - 32px));

            margin: 50px auto 80px;

        }

        .bejelentes-intro {

            text-align: center;

            margin-bottom: 32px;

        }

        .bejelentes-icon {

            width: 72px;

            height: 72px;

            margin: 0 auto 18px;

            border-radius: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #c8102e;

            color: #fff;

            font-size: 34px;

            box-shadow: 0 12px 30px rgba(200,16,46,.22);

        }

        .bejelentes-intro h1 {

            margin: 0 0 12px;

            font-size: clamp(28px, 5vw, 42px);

            line-height: 1.1;

            font-weight: 800;

        }

        .bejelentes-intro p {

            max-width: 680px;

            margin: 0 auto;

            color: #707075;

            line-height: 1.7;

        }

        .figyelmeztetes {

            display: flex;

            gap: 14px;

            align-items: flex-start;

            margin-bottom: 24px;

            padding: 18px 20px;

            background: #fff4f4;

            border: 1px solid #f2c7cd;

            border-left: 5px solid #c8102e;

            border-radius: 10px;

        }

        .figyelmeztetes-icon {

            font-size: 24px;

            line-height: 1;

        }

        .figyelmeztetes strong {

            display: block;

            margin-bottom: 5px;

        }

        .figyelmeztetes p {

            margin: 0;

            color: #555;

            line-height: 1.55;

            font-size: 14px;

        }

        .bejelentes-card {

            background: #fff;

            border: 1px solid #e3e3e3;

            border-radius: 16px;

            box-shadow: 0 12px 35px rgba(0,0,0,.08);

            padding: 32px;

        }

        .section-title {

            margin: 0 0 20px;

            font-size: 20px;

            font-weight: 800;

            padding-bottom: 12px;

            border-bottom: 1px solid #e7e7e7;

        }

        .form-grid {

            display: grid;

            grid-template-columns: repeat(2, minmax(0, 1fr));

            gap: 20px;

        }

        .form-group {

            display: flex;

            flex-direction: column;

            gap: 8px;

        }

        .form-group.full {

            grid-column: 1 / -1;

        }

        .form-group label {

            font-size: 14px;

            font-weight: 700;

        }

        .required {

            color: #c8102e;

        }

        .form-control {

            width: 100%;

            border: 1px solid #d8d8d8;

            border-radius: 9px;

            padding: 13px 14px;

            background: #fff;

            color: #18181a;

            font-family: inherit;

            font-size: 15px;

            outline: none;

            transition: border-color .2s ease,

                        box-shadow .2s ease;

        }

        .form-control:focus {

            border-color: #c8102e;

            box-shadow: 0 0 0 3px rgba(200,16,46,.10);

        }

        textarea.form-control {

            min-height: 150px;

            resize: vertical;

        }

        select.form-control {

            cursor: pointer;

        }

        .form-help {

            font-size: 12px;

            color: #777;

            line-height: 1.45;

        }

        .hibauzenet {

            margin-bottom: 24px;

            padding: 15px 18px;

            background: #fff0f0;

            border: 1px solid #edb8c0;

            border-radius: 9px;

            color: #a10c24;

            font-weight: 600;

            line-height: 1.5;

        }

        .sikeruzenet {

            margin-bottom: 24px;

            padding: 18px 20px;

            background: #eef9f1;

            border: 1px solid #b8dfc2;

            border-radius: 9px;

            color: #17652c;

            line-height: 1.6;

        }

        .sikeruzenet strong {

            display: block;

            margin-bottom: 5px;

        }

        .form-divider {

            height: 1px;

            background: #e8e8e8;

            margin: 32px 0;

        }

        .form-note {

            margin-top: 25px;

            color: #777;

            font-size: 13px;

            line-height: 1.6;

        }

        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 28px;

        }

        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 46px;

            padding: 0 20px;

            border-radius: 9px;

            font-family: inherit;

            font-size: 14px;

            font-weight: 700;

            text-decoration: none;

            cursor: pointer;

            transition: .2s ease;

        }

        .btn-secondary {

            background: #fff;

            border: 1px solid #d8d8d8;

            color: #333;

        }

        .btn-secondary:hover {

            background: #f3f3f3;

        }

        .btn-primary {

            border: 1px solid #c8102e;

            background: #c8102e;

            color: #fff;

            box-shadow: 0 8px 20px rgba(200,16,46,.18);

        }

        .btn-primary:hover {

            background: #e31837;

            border-color: #e31837;

            transform: translateY(-1px);

        }

        .bejelentes-footer {

            background: #0d0d0f;

            color: #aaa;

            padding: 30px 20px;

            text-align: center;

            font-size: 13px;

        }

        .bejelentes-footer strong {

            color: #fff;

        }

        @media (max-width: 700px) {

            .bejelentes-header-inner {

                min-height: 68px;

            }

            .bejelentes-back {

                padding: 9px 11px;

                font-size: 13px;

            }

            .bejelentes-main {

                margin-top: 30px;

            }

            .bejelentes-card {

                padding: 22px 18px;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .form-group.full {

                grid-column: auto;

            }

            .form-actions {

                flex-direction: column-reverse;

            }

            .btn {

                width: 100%;

            }

        }

    </style>

</head>

<body>

<header class="bejelentes-header">

    <div class="bejelentes-header-inner">

        <a

            href="index.php"

            class="bejelentes-logo"

        >

            <span class="bejelentes-logo-mark">SZ</span>

            <span>SZÖTE</span>

        </a>

        <a

            href="index.php"

            class="bejelentes-back"

        >

            ← Vissza a főoldalra

        </a>

    </div>

</header>

<main class="bejelentes-main">

    <div class="bejelentes-intro">

        <div class="bejelentes-icon">

            🚨

        </div>

        <h1>Esemény bejelentése</h1>

        <p>

            Ha olyan eseményt észleltél, amelyről szeretnéd

            értesíteni az önkéntes tűzoltó egyesületet,

            az alábbi űrlapon elküldheted a részleteket.

        </p>

    </div>

    <div class="figyelmeztetes">

        <div class="figyelmeztetes-icon">

            ⚠️

        </div>

        <div>

            <strong>Vészhelyzet esetén mindig a 112-t hívd!</strong>

            <p>

                Ez az űrlap nem helyettesíti a segélyhívót.

                Közvetlen veszély, tűz, baleset vagy életveszély

                esetén azonnal hívd a 112-es segélyhívó számot.

            </p>

        </div>

    </div>

    <?php if ($hiba !== ""): ?>

        <div class="hibauzenet">

            <?= htmlspecialchars($hiba, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>

    <?php if ($siker !== ""): ?>

        <div class="sikeruzenet">

            <strong>✓ Bejelentés elküldve</strong>

            <?= htmlspecialchars($siker, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>

    <div class="bejelentes-card">

        <form

            method="post"

            action=""

            autocomplete="off"

        >

            <h2 class="section-title">

                Az esemény adatai

            </h2>

            <div class="form-grid">

                <div class="form-group">

                    <label for="esemeny_tipus">

                        Esemény típusa

                        <span class="required">*</span>

                    </label>

                    <select

                        name="esemeny_tipus"

                        id="esemeny_tipus"

                        class="form-control"

                        required

                    >

                        <option value="">

                            – Válassz eseménytípust –

                        </option>

                        <option

                            value="Tűzeset"

                            <?= $esemenyTipus === "Tűzeset" ? "selected" : "" ?>

                        >

                            🔥 Tűzeset

                        </option>

                        <option

                            value="Közlekedési baleset"

                            <?= $esemenyTipus === "Közlekedési baleset" ? "selected" : "" ?>

                        >

                            🚗 Közlekedési baleset

                        </option>

                        <option

                            value="Műszaki mentés"

                            <?= $esemenyTipus === "Műszaki mentés" ? "selected" : "" ?>

                        >

                            🛠️ Műszaki mentés

                        </option>

                        <option

                            value="Viharkár"

                            <?= $esemenyTipus === "Viharkár" ? "selected" : "" ?>

                        >

                            🌩️ Viharkár

                        </option>

                        <option

                            value="Egyéb"

                            <?= $esemenyTipus === "Egyéb" ? "selected" : "" ?>

                        >

                            ℹ️ Egyéb esemény

                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="telepules">

                        Település

                        <span class="required">*</span>

                    </label>

                    <input

                        type="text"

                        name="telepules"

                        id="telepules"

                        class="form-control"

                        maxlength="100"

                        required

                        value="<?= htmlspecialchars($telepules, ENT_QUOTES, "UTF-8") ?>"

                        placeholder="Pl. Pásztó"

                    >

                </div>

                <div class="form-group full">

                    <label for="helyszin">

                        Pontos helyszín

                        <span class="required">*</span>

                    </label>

                    <input

                        type="text"

                        name="helyszin"

                        id="helyszin"

                        class="form-control"

                        maxlength="255"

                        required

                        value="<?= htmlspecialchars($helyszin, ENT_QUOTES, "UTF-8") ?>"

                        placeholder="Pl. Kossuth utca 12. vagy 21-es főút 50. km"

                    >

                    <div class="form-help">

                        Minél pontosabban add meg a helyszínt, annál könnyebb

                        az esemény beazonosítása.

                    </div>

                </div>

                <div class="form-group full">

                    <label for="leiras">

                        Az esemény rövid leírása

                    </label>

                    <textarea

                        name="leiras"

                        id="leiras"

                        class="form-control"

                        maxlength="5000"

                        placeholder="Írd le röviden, hogy mi történt, mit láttál, van-e sérült, milyen veszély áll fenn stb."

                    ><?= htmlspecialchars($leiras, ENT_QUOTES, "UTF-8") ?></textarea>

                </div>

            </div>

            <div class="form-divider"></div>

            <h2 class="section-title">

                Kapcsolattartási adatok

            </h2>

            <div class="form-grid">

                <div class="form-group">

                    <label for="bejelento_nev">

                        Neved

                    </label>

                    <input

                        type="text"

                        name="bejelento_nev"

                        id="bejelento_nev"

                        class="form-control"

                        maxlength="100"

                        value="<?= htmlspecialchars($bejelentoNev, ENT_QUOTES, "UTF-8") ?>"

                        placeholder="Opcionális"

                    >

                </div>

                <div class="form-group">

                    <label for="bejelento_telefon">

                        Telefonszám

                    </label>

                    <input

                        type="tel"

                        name="bejelento_telefon"

                        id="bejelento_telefon"

                        class="form-control"

                        maxlength="30"

                        value="<?= htmlspecialchars($bejelentoTelefon, ENT_QUOTES, "UTF-8") ?>"

                        placeholder="Opcionális"

                    >

                </div>

                <div class="form-group full">

                    <label for="bejelento_email">

                        E-mail cím

                    </label>

                    <input

                        type="email"

                        name="bejelento_email"

                        id="bejelento_email"

                        class="form-control"

                        maxlength="150"

                        value="<?= htmlspecialchars($bejelentoEmail, ENT_QUOTES, "UTF-8") ?>"

                        placeholder="Opcionális"

                    >

                    <div class="form-help">

                        A kapcsolattartási adatok megadása nem kötelező.

                        Ha megadod, szükség esetén fel tudjuk venni veled a kapcsolatot.

                    </div>

                </div>

            </div>

            <div class="form-note">

                A beküldött adatokat az egyesület az esemény

                ellenőrzéséhez és kezeléséhez használja.

                A bejelentés elküldése nem jelenti azt, hogy az esemény

                automatikusan hivatalos riasztássá válik.

            </div>

            <div class="form-actions">

                <a

                    href="index.php"

                    class="btn btn-secondary"

                >

                    Mégsem

                </a>

                <button

                    type="submit"

                    class="btn btn-primary"

                >

                    🚨 Bejelentés elküldése

                </button>

            </div>

        </form>

    </div>

</main>

<footer class="bejelentes-footer">

    <strong>SZÖTE</strong>

    – Önkéntes Tűzoltó Egyesület

    <br><br>

    Vészhelyzetben mindig a <strong>112</strong>-t hívd!

</footer>

</body>

</html>