<?php

require_once 'db.php';
require_once 'auth.php';

adminVagyParancsnok();

$tagId = (int)($_GET['tag_id'] ?? $_POST['tag_id'] ?? 0);

if ($tagId <= 0) {
    exit('Érvénytelen tag azonosító.');
}

/*
|--------------------------------------------------------------------------
| Tag lekérése
|--------------------------------------------------------------------------
*/

$st = $pdo->prepare("
    SELECT
        id,
        teljes_nev,
        email,
        aktiv,
        felhasznalo_id
    FROM tagok
    WHERE id = ?
    LIMIT 1
");

$st->execute([$tagId]);

$tag = $st->fetch();

if (!$tag) {
    exit('A tag nem található.');
}

/*
|--------------------------------------------------------------------------
| Ellenőrzések
|--------------------------------------------------------------------------
*/

if (!$tag['email']) {
    exit('Ehhez a taghoz nincs e-mail cím megadva.');
}

if ((int)$tag['aktiv'] !== 1) {
    exit('Csak aktív tag részére lehet regisztrációs meghívót küldeni.');
}

if (!empty($tag['felhasznalo_id'])) {
    exit('Ehhez a taghoz már tartozik felhasználói fiók.');
}

/*
|--------------------------------------------------------------------------
| Meghívó létrehozása
|--------------------------------------------------------------------------
*/

$hiba = '';
$siker = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
         * Korábbi, még fel nem használt meghívók érvénytelenítése.
         */
        $st = $pdo->prepare("
            UPDATE regisztracios_meghivok
            SET felhasznalva = NOW()
            WHERE tag_id = ?
              AND felhasznalva IS NULL
        ");

        $st->execute([$tagId]);

        /*
         * Biztonságos, véletlenszerű token.
         */
        $token = bin2hex(random_bytes(32));

        /*
         * Az adatbázisba csak a token hash-e kerül.
         */
        $tokenHash = hash('sha256', $token);

        /*
         * 48 órás érvényesség.
         */
        $lejarat = date('Y-m-d H:i:s', time() + (48 * 60 * 60));

        $st = $pdo->prepare("
            INSERT INTO regisztracios_meghivok
            (
                tag_id,
                token_hash,
                lejarat
            )
            VALUES
            (?, ?, ?)
        ");

        $st->execute([
            $tagId,
            $tokenHash,
            $lejarat
        ]);

        /*
         * Regisztrációs link.
         *
         * XAMPP alatt jelenleg:
         * http://localhost/tuzolto-web/
         */
        $regisztraciosLink =
            'http://localhost/tuzolto-web/regisztracio.php?token='
            . urlencode($token);

        /*
         * FONTOS:
         *
         * Egyelőre nem küldünk valódi e-mailt.
         * Először a teljes regisztrációs folyamatot készítjük el.
         *
         * Így teszteléskor látjuk a létrehozott linket.
         */

        $siker = 'A regisztrációs meghívó létrejött.';

    } catch (Throwable $e) {

        $hiba = 'Hiba történt a meghívó létrehozásakor: '
              . $e->getMessage();
    }
}

require 'header.php';

?>

<div class="page-title">

    <div>
        <span class="eyebrow">REGISZTRÁCIÓ</span>

        <h1>Regisztrációs meghívó</h1>

        <p class="muted">
            Belépési meghívó küldése a tagnak.
        </p>
    </div>

    <a href="tagok.php" class="btn btn-light">
        ← Vissza
    </a>

</div>


<div class="card form-card">

    <h2><?= htmlspecialchars($tag['teljes_nev'], ENT_QUOTES, 'UTF-8') ?></h2>

    <p class="muted">
        E-mail:
        <strong>
            <?= htmlspecialchars($tag['email'], ENT_QUOTES, 'UTF-8') ?>
        </strong>
    </p>


    <?php if ($hiba): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($hiba, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>


    <?php if ($siker): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($siker, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="card" style="margin-top:20px;">

            <h3>🔗 Tesztelési regisztrációs link</h3>

            <p class="muted">
                Ez a link jelenleg azért jelenik meg itt, mert az
                e-mail küldést a következő lépésben kötjük be.
            </p>

            <div style="
                background:#f5f5f5;
                padding:15px;
                border-radius:10px;
                word-break:break-all;
                margin:15px 0;
            ">
                <?= htmlspecialchars($regisztraciosLink, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <a
                href="<?= htmlspecialchars($regisztraciosLink, ENT_QUOTES, 'UTF-8') ?>"
                class="btn btn-primary"
            >
                🔑 Regisztráció megnyitása
            </a>

        </div>

    <?php else: ?>

        <div class="alert alert-warning">

            <strong>Figyelem!</strong>

            <p style="margin:8px 0 0;">
                A meghívó 48 óráig lesz érvényes.
                A tag a regisztráció során saját jelszót állít be.
            </p>

        </div>


        <form method="post">

            <input
                type="hidden"
                name="tag_id"
                value="<?= (int)$tag['id'] ?>"
            >

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                    onclick="return confirm('Biztosan létrehozod a regisztrációs meghívót ennek a tagnak?');"
                >
                    ✉️ Regisztrációs meghívó létrehozása
                </button>

                <a
                    href="tagok.php"
                    class="btn btn-light"
                >
                    Mégse
                </a>

            </div>

        </form>

    <?php endif; ?>

</div>


<?php require 'footer.php'; ?>