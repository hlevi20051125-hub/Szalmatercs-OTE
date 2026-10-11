<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

$oldalCim = 'Profilkép';


// ============================================================
// SEGÉDFÜGGVÉNY
// ============================================================

function e($v)
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ============================================================
// BEJELENTKEZETT FELHASZNÁLÓ
// ============================================================

$felhasznalo = aktualisFelhasznalo();

if (
    !$felhasznalo ||
    empty($felhasznalo['id'])
) {
    header('Location: login.php');
    exit;
}

$felhasznaloId = (int)$felhasznalo['id'];


// ============================================================
// SAJÁT TAGPROFIL LEKÉRÉSE
// ============================================================

$st = $pdo->prepare("
    SELECT
        t.id,
        t.felhasznalo_id,
        t.teljes_nev,
        t.profilkep,
        t.aktiv
    FROM tagok t
    WHERE t.felhasznalo_id = ?
    LIMIT 1
");

$st->execute([
    $felhasznaloId
]);

$tag = $st->fetch();


// ============================================================
// HA NINCS TAGPROFIL
// ============================================================

if (!$tag) {

    require 'header.php';

    ?>

    <div class="page-title">

        <div>

            <span class="eyebrow">
                FIÓK
            </span>

            <h1>
                Profilkép
            </h1>

            <p class="muted">
                A felhasználói fiókodhoz nincs kapcsolt tagprofil.
            </p>

        </div>

    </div>


    <div class="card">

        <div class="empty-state">

            <div style="font-size:48px;">
                👤
            </div>

            <h2>
                Nincs kapcsolt tagprofil
            </h2>

            <p class="muted">
                Kérd az adminisztrátort, hogy kapcsolja össze
                a fiókodat a tagprofiloddal.
            </p>

            <a
                href="profil.php"
                class="btn btn-primary"
            >
                ← Vissza a profilhoz
            </a>

        </div>

    </div>

    <?php

    require 'footer.php';

    exit;
}


// ============================================================
// VÁLTOZÓK
// ============================================================

$hiba = '';
$siker = '';


// ============================================================
// PROFILKEP MAPPA
// ============================================================

$profilkepMappa = __DIR__ . DIRECTORY_SEPARATOR . 'profilkepek';


// ============================================================
// MAPPA LÉTREHOZÁSA, HA MÉG NINCS
// ============================================================

if (!is_dir($profilkepMappa)) {

    if (!mkdir($profilkepMappa, 0755, true)) {

        $hiba = 'A profilképek mappáját nem sikerült létrehozni.';
    }
}


// ============================================================
// RÉGI PROFILKÉP TÖRLÉSE
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['profilkep_torles'])
) {

    $regiKep = trim(
        (string)($tag['profilkep'] ?? '')
    );


    try {

        $pdo->beginTransaction();


        /*
         * Adatbázisból töröljük a kép nevét.
         */

        $st = $pdo->prepare("
            UPDATE tagok
            SET profilkep = NULL
            WHERE id = ?
              AND felhasznalo_id = ?
        ");

        $st->execute([
            $tag['id'],
            $felhasznaloId
        ]);


        /*
         * Fizikai fájl törlése.
         *
         * Csak helyi fájlt törlünk.
         */

        if (
            $regiKep !== '' &&
            !str_starts_with($regiKep, 'http://') &&
            !str_starts_with($regiKep, 'https://')
        ) {

            $regiFajl = basename($regiKep);

            $regiFajlUtvonal =
                $profilkepMappa .
                DIRECTORY_SEPARATOR .
                $regiFajl;


            if (
                is_file($regiFajlUtvonal)
            ) {

                @unlink(
                    $regiFajlUtvonal
                );
            }
        }


        $pdo->commit();


        $siker =
            'A profilképed sikeresen törölve lett.';


        /*
         * Frissítjük a lokális változót is.
         */

        $tag['profilkep'] = null;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $hiba =
            'A profilkép törlése közben hiba történt.';
    }
}


// ============================================================
// ÚJ PROFILKÉP FELTÖLTÉSE
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['profilkep_feltoltes'])
) {

    if (
        !isset($_FILES['profilkep']) ||
        !is_array($_FILES['profilkep'])
    ) {

        $hiba =
            'Nem választottál ki profilképet.';

    } else {

        $fajl = $_FILES['profilkep'];


        // ----------------------------------------------------
        // FELTÖLTÉSI HIBA
        // ----------------------------------------------------

        if (
            !isset($fajl['error']) ||
            $fajl['error'] !== UPLOAD_ERR_OK
        ) {

            switch (
                $fajl['error'] ?? UPLOAD_ERR_NO_FILE
            ) {

                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:

                    $hiba =
                        'A kép fájlmérete túl nagy.';

                    break;


                case UPLOAD_ERR_NO_FILE:

                    $hiba =
                        'Nem választottál ki képet.';

                    break;


                default:

                    $hiba =
                        'A kép feltöltése sikertelen volt.';

                    break;
            }

        } else {


            // ------------------------------------------------
            // MÉRET ELLENŐRZÉSE
            // ------------------------------------------------

            $maxMeret = 5 * 1024 * 1024;

            if (
                (int)$fajl['size'] > $maxMeret
            ) {

                $hiba =
                    'A profilkép maximum 5 MB lehet.';
            }


            // ------------------------------------------------
            // VALÓDI KÉP ELLENŐRZÉSE
            // ------------------------------------------------

            if ($hiba === '') {

                $kepInfo = @getimagesize(
                    $fajl['tmp_name']
                );


                if ($kepInfo === false) {

                    $hiba =
                        'A feltöltött fájl nem érvényes kép.';
                }
            }


            // ------------------------------------------------
            // MIME TÍPUS
            // ------------------------------------------------

            $engedelyezettMime = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];


            if ($hiba === '') {

                $finfo = new finfo(
                    FILEINFO_MIME_TYPE
                );

                $mime = $finfo->file(
                    $fajl['tmp_name']
                );


                if (
                    !isset(
                        $engedelyezettMime[$mime]
                    )
                ) {

                    $hiba =
                        'Csak JPG, PNG vagy WEBP kép tölthető fel.';
                }
            }


            // ------------------------------------------------
            // KÉPMÉRET ELLENŐRZÉSE
            // ------------------------------------------------

            if (
                $hiba === '' &&
                isset($kepInfo[0], $kepInfo[1])
            ) {

                $kepSzelesseg = (int)$kepInfo[0];
                $kepMagassag = (int)$kepInfo[1];


                /*
                 * Nagyon nagy képek kiszűrése.
                 */

                if (
                    $kepSzelesseg > 5000 ||
                    $kepMagassag > 5000
                ) {

                    $hiba =
                        'A kép túl nagy. Maximum 5000 × 5000 pixel lehet.';
                }
            }


            // ------------------------------------------------
            // FELTÖLTÉS
            // ------------------------------------------------

            if ($hiba === '') {

                $kiterjesztes =
                    $engedelyezettMime[$mime];


                /*
                 * Véletlenszerű fájlnév.
                 */

                try {

                    $ujFajlNev =
                        bin2hex(
                            random_bytes(24)
                        )
                        . '.'
                        . $kiterjesztes;

                } catch (Throwable $e) {

                    $hiba =
                        'Nem sikerült biztonságos fájlnevet létrehozni.';
                }


                if ($hiba === '') {

                    $ujFajlUtvonal =
                        $profilkepMappa .
                        DIRECTORY_SEPARATOR .
                        $ujFajlNev;


                    // ----------------------------------------
                    // FÁJL MOZGATÁSA
                    // ----------------------------------------

                    if (
                        !move_uploaded_file(
                            $fajl['tmp_name'],
                            $ujFajlUtvonal
                        )
                    ) {

                        $hiba =
                            'A feltöltött képet nem sikerült elmenteni.';
                    }


                    // ----------------------------------------
                    // ADATBÁZIS FRISSÍTÉS
                    // ----------------------------------------

                    if ($hiba === '') {

                        try {

                            $pdo->beginTransaction();


                            /*
                             * Régi kép neve.
                             */

                            $regiKep =
                                trim(
                                    (string)(
                                        $tag['profilkep']
                                        ?? ''
                                    )
                                );


                            /*
                             * Új kép mentése.
                             */

                            $st = $pdo->prepare("
                                UPDATE tagok
                                SET profilkep = ?
                                WHERE id = ?
                                  AND felhasznalo_id = ?
                            ");

                            $st->execute([
                                $ujFajlNev,
                                $tag['id'],
                                $felhasznaloId
                            ]);


                            if (
                                $st->rowCount() < 1
                                &&
                                $tag['profilkep'] !== null
                            ) {

                                /*
                                 * Nem feltétlenül hiba:
                                 * lehet, hogy ugyanaz az érték volt.
                                 */

                            }


                            $pdo->commit();


                            // --------------------------------
                            // RÉGI KÉP TÖRLÉSE
                            // --------------------------------

                            if (
                                $regiKep !== '' &&
                                !str_starts_with(
                                    $regiKep,
                                    'http://'
                                ) &&
                                !str_starts_with(
                                    $regiKep,
                                    'https://'
                                )
                            ) {

                                $regiFajl =
                                    basename(
                                        $regiKep
                                    );


                                $regiFajlUtvonal =
                                    $profilkepMappa .
                                    DIRECTORY_SEPARATOR .
                                    $regiFajl;


                                if (
                                    is_file(
                                        $regiFajlUtvonal
                                    )
                                ) {

                                    @unlink(
                                        $regiFajlUtvonal
                                    );
                                }
                            }


                            $tag['profilkep'] =
                                $ujFajlNev;


                            $siker =
                                'A profilképed sikeresen frissítve lett.';

                        } catch (Throwable $e) {

                            if (
                                $pdo->inTransaction()
                            ) {

                                $pdo->rollBack();
                            }


                            /*
                             * Ha az adatbázis mentése
                             * sikertelen, az új fájlt
                             * is töröljük.
                             */

                            if (
                                is_file(
                                    $ujFajlUtvonal
                                )
                            ) {

                                @unlink(
                                    $ujFajlUtvonal
                                );
                            }


                            $hiba =
                                'A profilkép mentése közben adatbázishiba történt.';
                        }
                    }
                }
            }
        }
    }
}


// ============================================================
// AKTUÁLIS KÉP URL
// ============================================================

$profilkep =
    trim(
        (string)($tag['profilkep'] ?? '')
    );

$profilkepUrl = '';

if ($profilkep !== '') {

    if (
        str_starts_with(
            $profilkep,
            'http://'
        ) ||
        str_starts_with(
            $profilkep,
            'https://'
        )
    ) {

        $profilkepUrl = $profilkep;

    } else {

        $profilkepUrl =
            'profilkepek/' .
            ltrim(
                $profilkep,
                '/\\'
            );
    }
}


// ============================================================
// KEZDŐBETŰK
// ============================================================

$nev =
    trim(
        (string)$tag['teljes_nev']
    );

$kezdobetuk = '';

$nevDarabok = preg_split(
    '/\s+/',
    $nev,
    -1,
    PREG_SPLIT_NO_EMPTY
);

if ($nevDarabok) {

    $kezdobetuk =
        mb_substr(
            $nevDarabok[0],
            0,
            1,
            'UTF-8'
        );


    if (
        count($nevDarabok) > 1
    ) {

        $kezdobetuk .=
            mb_substr(
                end($nevDarabok),
                0,
                1,
                'UTF-8'
            );
    }
}


// ============================================================
// OLDAL
// ============================================================

require 'header.php';

?>


<style>

/* ============================================================
   PROFILKÉP OLDAL
============================================================ */

.profile-picture-page {
    max-width: 900px;
    margin: 0 auto;
}


/* ============================================================
   FEJLÉC
============================================================ */

.picture-header {
    margin-bottom: 22px;
}


.picture-header .eyebrow {
    display: inline-block;

    font-size: 12px;

    font-weight: 800;

    letter-spacing: 2px;

    color: #c8102e;

    margin-bottom: 5px;
}


.picture-header h1 {
    margin: 0 0 8px;

    font-size: 32px;

    font-weight: 800;
}


.picture-header p {
    margin: 0;

    color: #707075;

    line-height: 1.6;
}


/* ============================================================
   FŐ KÁRTYA
============================================================ */

.picture-card {
    background: #ffffff;

    border: 1px solid #e3e3e3;

    border-radius: 15px;

    padding: 30px;

    box-shadow:
        0 10px 30px rgba(0,0,0,0.06);
}


/* ============================================================
   KÉP ELŐNÉZET
============================================================ */

.picture-preview {
    display: flex;

    justify-content: center;

    margin-bottom: 28px;
}


.picture-preview-frame {
    width: 210px;

    height: 210px;

    border-radius: 50%;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #151518,
            #c8102e
        );

    border: 6px solid #ffffff;

    box-shadow:
        0 10px 35px rgba(0,0,0,0.15);

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ffffff;

    font-size: 58px;

    font-weight: 800;
}


.picture-preview-frame img {
    width: 100%;

    height: 100%;

    object-fit: cover;
}


/* ============================================================
   FELTÖLTŐ ŰRLAP
============================================================ */

.picture-form {
    max-width: 600px;

    margin: 0 auto;
}


.picture-label {
    display: block;

    margin-bottom: 8px;

    font-size: 14px;

    font-weight: 700;

    color: #18181a;
}


.picture-file {
    display: block;

    width: 100%;

    box-sizing: border-box;

    padding: 12px;

    background: #f7f7f6;

    border: 1px solid #dddddd;

    border-radius: 9px;

    cursor: pointer;
}


.picture-file:hover {
    border-color: #c8102e;
}


.picture-help {
    margin-top: 8px;

    color: #77777c;

    font-size: 12px;

    line-height: 1.5;
}


/* ============================================================
   GOMBOK
============================================================ */

.picture-actions {
    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 20px;
}


.picture-actions form {
    margin: 0;
}


.picture-delete {
    border: 1px solid #dddddd;

    background: #ffffff;

    color: #18181a;

    cursor: pointer;
}


.picture-delete:hover {
    border-color: #c8102e;

    color: #c8102e;
}


/* ============================================================
   ÜZENETEK
============================================================ */

.picture-message {
    margin-bottom: 20px;

    padding: 14px 16px;

    border-radius: 10px;

    font-weight: 600;
}


.picture-success {
    background: #ecfdf5;

    border: 1px solid #a7f3d0;

    color: #047857;
}


.picture-error {
    background: #fff1f2;

    border: 1px solid #fecdd3;

    color: #be123c;
}


/* ============================================================
   INFORMÁCIÓS BOX
============================================================ */

.picture-info {
    margin-top: 25px;

    padding: 16px;

    background: #f7f7f6;

    border: 1px solid #e7e7e7;

    border-radius: 10px;

    color: #66666b;

    font-size: 13px;

    line-height: 1.7;
}


.picture-info strong {
    color: #18181a;
}


/* ============================================================
   MOBIL
============================================================ */

@media (max-width: 600px) {

    .picture-card {
        padding: 20px;
    }


    .picture-preview-frame {
        width: 170px;

        height: 170px;
    }


    .picture-header h1 {
        font-size: 27px;
    }


    .picture-actions {
        flex-direction: column;
    }


    .picture-actions .btn {
        width: 100%;

        box-sizing: border-box;

        text-align: center;
    }

}

</style>


<div class="profile-picture-page">


    <!-- ========================================================
         FEJLÉC
    ========================================================= -->

    <div class="picture-header">

        <span class="eyebrow">
            SAJÁT PROFIL
        </span>

        <h1>
            📷 Profilkép
        </h1>

        <p>
            Tölts fel egy saját profilképet, vagy cseréld le
            a jelenlegi képedet.
        </p>

    </div>


    <!-- ========================================================
         ÜZENETEK
    ========================================================= -->

    <?php if ($siker !== ''): ?>

        <div class="picture-message picture-success">
            ✓ <?= e($siker) ?>
        </div>

    <?php endif; ?>


    <?php if ($hiba !== ''): ?>

        <div class="picture-message picture-error">
            ⚠ <?= e($hiba) ?>
        </div>

    <?php endif; ?>


    <!-- ========================================================
         KÁRTYA
    ========================================================= -->

    <div class="picture-card">


        <!-- =====================================================
             ELŐNÉZET
        ====================================================== -->

        <div class="picture-preview">

            <div class="picture-preview-frame">

                <?php if ($profilkepUrl): ?>

                    <img
                        src="<?= e($profilkepUrl) ?>"
                        alt="<?= e($tag['teljes_nev']) ?>"
                    >

                <?php else: ?>

                    <?= e($kezdobetuk) ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- =====================================================
             FELTÖLTÉS
        ====================================================== -->

        <form
            method="post"
            enctype="multipart/form-data"
            class="picture-form"
        >

            <label
                for="profilkep"
                class="picture-label"
            >
                <?= $profilkepUrl
                    ? 'Új profilkép kiválasztása'
                    : 'Profilkép kiválasztása'
                ?>
            </label>


            <input
                type="file"
                name="profilkep"
                id="profilkep"
                class="picture-file"
                accept="image/jpeg,image/png,image/webp"
                required
            >


            <div class="picture-help">

                Engedélyezett formátumok:
                <strong>JPG, PNG, WEBP</strong>.

                Maximum fájlméret:
                <strong>5 MB</strong>.

                Maximum képméret:
                <strong>5000 × 5000 pixel</strong>.

            </div>


            <div class="picture-actions">

                <button
                    type="submit"
                    name="profilkep_feltoltes"
                    class="btn btn-primary"
                >
                    📷 Profilkép feltöltése
                </button>


                <a
                    href="profil.php"
                    class="btn btn-light"
                >
                    ← Vissza a profilhoz
                </a>

            </div>

        </form>


        <!-- =====================================================
             TÖRLÉS
        ====================================================== -->

        <?php if ($profilkepUrl): ?>

            <div
                style="
                    margin-top:25px;
                    padding-top:25px;
                    border-top:1px solid #e7e7e7;
                "
            >

                <form
                    method="post"
                    onsubmit="
                        return confirm(
                            'Biztosan törölni szeretnéd a profilképedet?'
                        );
                    "
                >

                    <button
                        type="submit"
                        name="profilkep_torles"
                        class="btn picture-delete"
                    >
                        🗑️ Profilkép törlése
                    </button>

                </form>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             INFORMÁCIÓ
        ====================================================== -->

        <div class="picture-info">

            <strong>Adatbiztonság:</strong><br>

            A feltöltött kép kizárólag a saját profilodhoz
            kerül mentésre. A rendszer biztonságos,
            véletlenszerű fájlnevet használ, és csak
            képfájlokat enged feltölteni.

        </div>


    </div>


</div>


<?php

require 'footer.php';

?>