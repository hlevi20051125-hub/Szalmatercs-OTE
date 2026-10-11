<?php

require_once 'db.php';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$hiba = '';
$siker = false;
$tag = null;
$meghivo = null;

/*
|--------------------------------------------------------------------------
| Token ellenőrzése
|--------------------------------------------------------------------------
*/

if ($token === '') {

    $hiba = 'Hiányzik a regisztrációs meghívó tokenje.';

} elseif (!preg_match('/^[a-f0-9]{64}$/i', $token)) {

    $hiba = 'Érvénytelen regisztrációs meghívó.';

} else {

    $tokenHash = hash('sha256', $token);

    $st = $pdo->prepare("
        SELECT
            rm.id AS meghivo_id,
            rm.tag_id,
            rm.lejarat,
            rm.felhasznalva,

            t.teljes_nev,
            t.becenev,
            t.email,
            t.aktiv,
            t.felhasznalo_id

        FROM regisztracios_meghivok rm

        INNER JOIN tagok t
            ON t.id = rm.tag_id

        WHERE rm.token_hash = ?

        LIMIT 1
    ");

    $st->execute([$tokenHash]);

    $adat = $st->fetch();

    if (!$adat) {

        $hiba = 'A regisztrációs meghívó nem található vagy érvénytelen.';

    } elseif (!empty($adat['felhasznalva'])) {

        $hiba = 'Ez a regisztrációs meghívó már fel lett használva.';

    } elseif (strtotime($adat['lejarat']) < time()) {

        $hiba = 'Ez a regisztrációs meghívó lejárt. Kérj új meghívót az adminisztrátortól.';

    } elseif ((int)$adat['aktiv'] !== 1) {

        $hiba = 'Ez a tag jelenleg nem aktív.';

    } elseif (!empty($adat['felhasznalo_id'])) {

        $hiba = 'Ehhez a taghoz már tartozik felhasználói fiók.';

    } else {

        $meghivo = $adat;
        $tag = $adat;
    }
}


/*
|--------------------------------------------------------------------------
| Regisztráció feldolgozása
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hiba) {

    $felhasznalonev = trim($_POST['felhasznalonev'] ?? '');
    $jelszo = $_POST['jelszo'] ?? '';
    $jelszo2 = $_POST['jelszo2'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Felhasználónév ellenőrzése
    |--------------------------------------------------------------------------
    */

    if ($felhasznalonev === '') {

        $hiba = 'A felhasználónév megadása kötelező.';

    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $felhasznalonev)) {

        $hiba = 'A felhasználónév 3–50 karakterből állhat, és csak betűt, számot, pontot, kötőjelet vagy aláhúzást tartalmazhat.';

    } elseif (strlen($jelszo) < 8) {

        $hiba = 'A jelszónak legalább 8 karakter hosszúnak kell lennie.';

    } elseif ($jelszo !== $jelszo2) {

        $hiba = 'A két jelszó nem egyezik.';

    }


    /*
    |--------------------------------------------------------------------------
    | Felhasználónév egyediségének ellenőrzése
    |--------------------------------------------------------------------------
    */

    if (!$hiba) {

        $st = $pdo->prepare("
            SELECT id
            FROM felhasznalok
            WHERE felhasznalonev = ?
            LIMIT 1
        ");

        $st->execute([$felhasznalonev]);

        if ($st->fetch()) {

            $hiba = 'Ez a felhasználónév már foglalt. Válassz másikat.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Fiók létrehozása
    |--------------------------------------------------------------------------
    */

    if (!$hiba) {

        try {

            $pdo->beginTransaction();

            /*
             * Jelszó biztonságos hash-elése.
             */
            $jelszoHash = password_hash(
                $jelszo,
                PASSWORD_DEFAULT
            );

            /*
             * Felhasználó létrehozása.
             *
             * A regisztráló tag normál tag jogosultságot kap.
             */
            $st = $pdo->prepare("
                INSERT INTO felhasznalok
                (
                    felhasznalonev,
                    jelszo,
                    nev,
                    szerepkor,
                    aktiv
                )
                VALUES
                (?, ?, ?, 'tag', 1)
            ");

            $st->execute([
                $felhasznalonev,
                $jelszoHash,
                $tag['teljes_nev']
            ]);

            $felhasznaloId = (int)$pdo->lastInsertId();


            /*
             * Tag összekapcsolása a felhasználói fiókkal.
             */
            $st = $pdo->prepare("
                UPDATE tagok
                SET felhasznalo_id = ?
                WHERE id = ?
            ");

            $st->execute([
                $felhasznaloId,
                $tag['tag_id']
            ]);


            /*
             * Meghívó felhasználása.
             */
            $st = $pdo->prepare("
                UPDATE regisztracios_meghivok
                SET felhasznalva = NOW()
                WHERE id = ?
                  AND felhasznalva IS NULL
            ");

            $st->execute([
                $tag['meghivo_id']
            ]);


            $pdo->commit();

            $siker = true;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $hiba = 'Hiba történt a regisztráció során: ' . $e->getMessage();
        }
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

    <title>Regisztráció – SZÖTE</title>

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

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>

        .register-page {
            min-height: 100vh;
            background:
                linear-gradient(
                    135deg,
                    #0d0d0f 0%,
                    #151518 55%,
                    #c8102e 180%
                );

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px 20px;
        }

        .register-box {
            width: 100%;
            max-width: 520px;

            background: #ffffff;

            border-radius: 16px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.35);

            overflow: hidden;
        }

        .register-header {
            background: #0d0d0f;
            color: #ffffff;

            padding: 28px 30px;
        }

        .register-logo {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #c8102e;
            margin-bottom: 8px;
        }

        .register-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .register-header p {
            margin: 8px 0 0;
            color: #bdbdc2;
        }

        .register-body {
            padding: 30px;
        }

        .register-person {
            background: #f5f5f3;
            border-left: 4px solid #c8102e;

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 25px;
        }

        .register-person strong {
            display: block;
            font-size: 18px;
            margin-bottom: 4px;
        }

        .register-person span {
            color: #707075;
            font-size: 14px;
        }

        .register-form-group {
            margin-bottom: 18px;
        }

        .register-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .register-form-group input {
            width: 100%;
            box-sizing: border-box;

            padding: 13px 14px;

            border: 1px solid #d8d8d8;
            border-radius: 9px;

            font-family: inherit;
            font-size: 15px;

            outline: none;
        }

        .register-form-group input:focus {
            border-color: #c8102e;

            box-shadow:
                0 0 0 3px rgba(200, 16, 46, 0.10);
        }

        .register-help {
            margin-top: 7px;

            color: #707075;

            font-size: 13px;
        }

        .register-button {
            width: 100%;
            border: 0;
            cursor: pointer;

            padding: 14px 18px;

            border-radius: 9px;

            background: #c8102e;
            color: #ffffff;

            font-family: inherit;
            font-size: 15px;
            font-weight: 700;

            transition: 0.2s;
        }

        .register-button:hover {
            background: #e31837;
        }

        .register-success {
            text-align: center;
        }

        .register-success-icon {
            font-size: 55px;
            margin-bottom: 10px;
        }

        .register-success h2 {
            margin-top: 0;
        }

        .register-login-button {
            display: inline-block;

            margin-top: 15px;

            padding: 13px 22px;

            background: #c8102e;
            color: #ffffff;

            text-decoration: none;

            border-radius: 9px;

            font-weight: 700;
        }

        .register-footer {
            text-align: center;

            padding: 0 30px 25px;

            color: #707075;

            font-size: 13px;
        }

        .register-error {
            background: #fff0f0;

            color: #9d1028;

            border: 1px solid #f2b8c0;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;
        }

    </style>

</head>

<body>

<div class="register-page">

    <div class="register-box">

        <div class="register-header">

            <div class="register-logo">
                SZÖTE
            </div>

            <h1>
                Tag regisztráció
            </h1>

            <p>
                Hozd létre saját belépésedet.
            </p>

        </div>


        <div class="register-body">

            <?php if ($hiba): ?>

                <div class="register-error">

                    <?= e($hiba) ?>

                </div>

                <div style="text-align:center;">

                    <a
                        href="index.php"
                        class="btn btn-light"
                    >
                        ← Vissza a főoldalra
                    </a>

                </div>

            <?php elseif ($siker): ?>

                <div class="register-success">

                    <div class="register-success-icon">
                        ✅
                    </div>

                    <h2>
                        Sikeres regisztráció!
                    </h2>

                    <p class="muted">

                        A felhasználói fiókod létrejött.

                        Most már be tudsz jelentkezni
                        az oldalra a választott
                        felhasználóneveddel és jelszavaddal.

                    </p>

                    <a
                        href="login.php"
                        class="register-login-button"
                    >
                        🔐 Bejelentkezés
                    </a>

                </div>

            <?php else: ?>

                <div class="register-person">

                    <strong>
                        <?= e($tag['teljes_nev']) ?>
                    </strong>

                    <?php if (!empty($tag['email'])): ?>

                        <span>
                            <?= e($tag['email']) ?>
                        </span>

                    <?php endif; ?>

                </div>


                <form method="post">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($token) ?>"
                    >


                    <div class="register-form-group">

                        <label for="felhasznalonev">
                            Felhasználónév
                        </label>

                        <input
                            type="text"
                            id="felhasznalonev"
                            name="felhasznalonev"
                            minlength="3"
                            maxlength="50"
                            autocomplete="username"
                            required
                        >

                        <div class="register-help">
                            Például: kovacs.peter
                        </div>

                    </div>


                    <div class="register-form-group">

                        <label for="jelszo">
                            Jelszó
                        </label>

                        <input
                            type="password"
                            id="jelszo"
                            name="jelszo"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <div class="register-help">
                            Legalább 8 karakter.
                        </div>

                    </div>


                    <div class="register-form-group">

                        <label for="jelszo2">
                            Jelszó ismét
                        </label>

                        <input
                            type="password"
                            id="jelszo2"
                            name="jelszo2"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="register-button"
                    >
                        🔥 Fiók létrehozása
                    </button>

                </form>

            <?php endif; ?>

        </div>


        <div class="register-footer">

            SZÖTE – Önkéntes Tűzoltó Egyesület

        </div>

    </div>

</div>

</body>

</html>