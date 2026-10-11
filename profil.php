<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

$oldalCim = 'Profil';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function datumFormaz($datum)
{
    if (!$datum) {
        return '—';
    }

    return date('Y. m. d.', strtotime($datum));
}

/*
|--------------------------------------------------------------------------
| Bejelentkezett felhasználó
|--------------------------------------------------------------------------
*/

$felhasznalo = aktualisFelhasznalo();

if (!$felhasznalo || empty($felhasznalo['id'])) {
    header('Location: login.php');
    exit;
}

$felhasznaloId = (int)$felhasznalo['id'];


/*
|--------------------------------------------------------------------------
| A hozzá tartozó tag lekérése
|--------------------------------------------------------------------------
*/

$st = $pdo->prepare("
    SELECT
        t.id,
        t.felhasznalo_id,
        t.teljes_nev,
        t.becenev,
        t.telefonszam,
        t.email,
        t.lakhely,
        t.szuletesi_datum,
        t.belepes_datum,
        t.beosztas,
        t.kepzettseg,
        t.aktiv,
        t.bemutatkozas,
        t.profilkep,
        t.letrehozva,
        t.modositva,

        f.felhasznalonev,
        f.szerepkor,
        f.aktiv AS fiok_aktiv,
        f.letrehozva AS fiok_letrehozva

    FROM tagok t

    INNER JOIN felhasznalok f
        ON f.id = t.felhasznalo_id

    WHERE t.felhasznalo_id = ?

    LIMIT 1
");

$st->execute([$felhasznaloId]);

$tag = $st->fetch();


/*
|--------------------------------------------------------------------------
| Ha nincs még összekapcsolt tagprofil
|--------------------------------------------------------------------------
*/

if (!$tag) {

    require 'header.php';

    ?>

    <div class="page-title">

        <div>

            <span class="eyebrow">
                FIÓK
            </span>

            <h1>
                Profil
            </h1>

            <p class="muted">
                A felhasználói fiókodhoz még nincs tagprofil kapcsolva.
            </p>

        </div>

    </div>


    <div class="card">

        <div class="empty-state">

            <div style="font-size:48px;margin-bottom:15px;">
                👤
            </div>

            <h2>
                Nincs kapcsolt tagprofil
            </h2>

            <p class="muted">
                Kérd az adminisztrátort, hogy kapcsolja össze
                a felhasználói fiókodat a tagi adatlapoddal.
            </p>

            <a
                href="dashboard.php"
                class="btn btn-primary"
            >
                ← Vissza a vezérlőpultra
            </a>

        </div>

    </div>

    <?php

    require 'footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Bevetések száma
|--------------------------------------------------------------------------
*/

$st = $pdo->prepare("
    SELECT COUNT(DISTINCT bt.bevetes_id)
    FROM bevetes_tagok bt
    WHERE bt.tag_id = ?
");

$st->execute([
    $tag['id']
]);

$bevetesDb = (int)$st->fetchColumn();


/*
|--------------------------------------------------------------------------
| Értesítések száma
|--------------------------------------------------------------------------
*/

$ertesitesDb = 0;

try {

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM ertesitesek
        WHERE cimzett_tag_id = ?
          AND allapot = 'uj'
    ");

    $st->execute([
        $tag['id']
    ]);

    $ertesitesDb = (int)$st->fetchColumn();

} catch (Throwable $e) {

    /*
     * Ha az értesítések tábla valamiért nincs,
     * a profil ettől még működjön.
     */

    $ertesitesDb = 0;
}


/*
|--------------------------------------------------------------------------
| Profilkép
|--------------------------------------------------------------------------
*/

$profilkep = trim((string)($tag['profilkep'] ?? ''));

$profilkepUrl = '';

if ($profilkep !== '') {

    /*
     * Ha a profilkép már teljes URL,
     * azt használjuk.
     */

    if (
        str_starts_with($profilkep, 'http://')
        || str_starts_with($profilkep, 'https://')
    ) {

        $profilkepUrl = $profilkep;

    } else {

        /*
         * Ha csak fájlnév van megadva,
         * a profilkep mappából próbáljuk meg.
         */

        $profilkepUrl = 'profilkepek/' . ltrim(
            $profilkep,
            '/\\'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Kezdőbetűk
|--------------------------------------------------------------------------
*/

$nev = trim((string)$tag['teljes_nev']);

$kezdobetuk = '';

$nevDarabok = preg_split(
    '/\s+/',
    $nev,
    -1,
    PREG_SPLIT_NO_EMPTY
);

if ($nevDarabok) {

    $kezdobetuk .= mb_substr(
        $nevDarabok[0],
        0,
        1,
        'UTF-8'
    );

    if (count($nevDarabok) > 1) {

        $kezdobetuk .= mb_substr(
            end($nevDarabok),
            0,
            1,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Beosztás
|--------------------------------------------------------------------------
*/

$beosztas = trim((string)($tag['beosztas'] ?? ''));

if ($beosztas === '') {
    $beosztas = 'Önkéntes tűzoltó';
}

/*
|--------------------------------------------------------------------------
| Hírek kezelése gomb jogosultsága
|--------------------------------------------------------------------------
| A gomb csak admin jogosultságú fiókhoz vagy admin/adminisztrátor
| beosztású tag profilján jelenik meg.
*/
$szerepkorNormalizalt = mb_strtolower(trim((string)($tag['szerepkor'] ?? '')), 'UTF-8');
$beosztasNormalizalt = mb_strtolower(trim((string)($tag['beosztas'] ?? '')), 'UTF-8');
$adminProfil = ($szerepkorNormalizalt === 'admin')
    || str_contains($beosztasNormalizalt, 'admin');


/*
|--------------------------------------------------------------------------
| Oldal
|--------------------------------------------------------------------------
*/

require 'header.php';

?>

<style>

.profile-page {
    max-width: 1150px;
    margin: 0 auto;
}

.profile-hero {
    background:
        linear-gradient(
            135deg,
            #0d0d0f 0%,
            #151518 65%,
            #c8102e 180%
        );

    border-radius: 16px;

    padding: 35px;

    color: #ffffff;

    display: flex;

    align-items: center;

    gap: 28px;

    box-shadow:
        0 15px 40px rgba(0,0,0,0.16);

    margin-bottom: 22px;
}

.profile-avatar {
    width: 125px;
    height: 125px;

    min-width: 125px;

    border-radius: 50%;

    overflow: hidden;

    background: #ffffff;

    display: flex;

    align-items: center;
    justify-content: center;

    color: #c8102e;

    font-size: 38px;

    font-weight: 800;

    border: 5px solid rgba(255,255,255,0.9);

    box-shadow:
        0 8px 25px rgba(0,0,0,0.25);
}

.profile-avatar img {
    width: 100%;
    height: 100%;

    object-fit: cover;
}

.profile-main {
    flex: 1;
}

.profile-eyebrow {
    font-size: 12px;

    letter-spacing: 2px;

    font-weight: 800;

    color: #ff5a73;

    margin-bottom: 5px;
}

.profile-name {
    font-size: 34px;

    font-weight: 800;

    margin: 0 0 5px;
}

.profile-nickname {
    color: #cfcfd3;

    font-size: 15px;

    margin-bottom: 14px;
}

.profile-role {
    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 7px 12px;

    border-radius: 999px;

    background: rgba(255,255,255,0.10);

    border: 1px solid rgba(255,255,255,0.12);

    font-size: 13px;

    font-weight: 600;
}

.profile-status {
    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-left: 8px;

    padding: 7px 12px;

    border-radius: 999px;

    background: rgba(52,211,153,0.14);

    color: #7ff0c2;

    border: 1px solid rgba(52,211,153,0.18);

    font-size: 13px;

    font-weight: 600;
}

.profile-status.inactive {
    background: rgba(255,255,255,0.08);

    color: #cfcfd3;
}

.profile-actions {
    margin-left: auto;

    display: flex;

    flex-direction: column;

    gap: 10px;

    align-items: stretch;
}

.profile-actions .btn {
    white-space: nowrap;
}

.profile-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.4fr)
        minmax(280px, 0.8fr);

    gap: 22px;

    margin-bottom: 22px;
}

.profile-card {
    background: #ffffff;

    border: 1px solid #e3e3e3;

    border-radius: 14px;

    box-shadow:
        0 8px 25px rgba(0,0,0,0.05);

    padding: 25px;
}

.profile-card h2 {
    margin: 0 0 20px;

    font-size: 19px;
}

.profile-card h2::after {
    content: "";

    display: block;

    width: 42px;

    height: 3px;

    background: #c8102e;

    margin-top: 9px;

    border-radius: 3px;
}

.profile-info-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 18px;
}

.profile-info-item {
    padding: 14px;

    background: #f7f7f6;

    border-radius: 10px;

    border: 1px solid #ededed;
}

.profile-info-label {
    color: #707075;

    font-size: 12px;

    font-weight: 600;

    margin-bottom: 5px;
}

.profile-info-value {
    color: #18181a;

    font-size: 15px;

    font-weight: 600;

    word-break: break-word;
}

.profile-info-value a {
    color: #c8102e;

    text-decoration: none;
}

.profile-info-value a:hover {
    text-decoration: underline;
}

.profile-about {
    color: #444449;

    line-height: 1.75;

    white-space: pre-line;

    font-size: 15px;
}

.profile-about.empty {
    color: #909095;

    font-style: italic;
}

.profile-stats {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 14px;
}

.profile-stat {
    padding: 20px;

    border-radius: 12px;

    background: #f7f7f6;

    border: 1px solid #e8e8e8;

    text-align: center;
}

.profile-stat strong {
    display: block;

    font-size: 30px;

    color: #c8102e;

    margin-bottom: 4px;
}

.profile-stat span {
    color: #707075;

    font-size: 13px;
}

.profile-menu {
    display: flex;

    flex-direction: column;

    gap: 10px;
}

.profile-menu a {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding: 13px 14px;

    border-radius: 9px;

    background: #f7f7f6;

    border: 1px solid #e7e7e7;

    color: #18181a;

    text-decoration: none;

    font-weight: 600;

    transition: 0.2s;
}

.profile-menu a:hover {
    background: #eeeeed;

    border-color: #d8d8d8;

    transform: translateX(2px);
}

.profile-menu .menu-left {
    display: flex;

    align-items: center;

    gap: 10px;
}

.profile-menu .menu-arrow {
    color: #c8102e;

    font-size: 18px;
}

.profile-footer-info {
    color: #85858a;

    font-size: 12px;

    margin-top: 20px;

    text-align: right;
}

@media (max-width: 850px) {

    .profile-hero {
        flex-direction: column;

        align-items: flex-start;

        padding: 25px;
    }

    .profile-actions {
        margin-left: 0;

        width: 100%;
    }

    .profile-actions .btn {
        width: 100%;

        box-sizing: border-box;

        text-align: center;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .profile-info-grid {
        grid-template-columns: 1fr;
    }

    .profile-name {
        font-size: 27px;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;

        min-width: 100px;
    }

    .profile-status {
        margin-left: 0;

        margin-top: 8px;
    }

}

</style>


<div class="profile-page">


    <!-- =========================================================
         PROFIL FEJLÉC
    ========================================================== -->

    <div class="profile-hero">


        <div class="profile-avatar">

            <?php if ($profilkepUrl): ?>

                <img
                    src="<?= e($profilkepUrl) ?>"
                    alt="<?= e($tag['teljes_nev']) ?>"
                >

            <?php else: ?>

                <?= e($kezdobetuk) ?>

            <?php endif; ?>

        </div>


        <div class="profile-main">

            <div class="profile-eyebrow">
                SZÖTE TAGI PROFIL
            </div>

            <h1 class="profile-name">
                <?= e($tag['teljes_nev']) ?>
            </h1>


            <?php if (!empty($tag['becenev'])): ?>

                <div class="profile-nickname">
                    „<?= e($tag['becenev']) ?>”
                </div>

            <?php endif; ?>


            <span class="profile-role">
                🚒 <?= e($beosztas) ?>
            </span>


            <?php if ((int)$tag['aktiv'] === 1): ?>

                <span class="profile-status">
                    ● Aktív tag
                </span>

            <?php else: ?>

                <span class="profile-status inactive">
                    ● Inaktív tag
                </span>

            <?php endif; ?>

        </div>


        <div class="profile-actions">

            <a
                href="profil_szerkeszt.php"
                class="btn btn-primary"
            >
                ✏️ Profil szerkesztése
            </a>

            <a
                href="profil_nyilvanossag.php"
                class="btn btn-light"
                title="A nyilvános tagi adatlap és a kapcsolati adatok engedélyezése"
            >
                🔐 Nyilvánossági beállítások
            </a>

            <?php if ($ertesitesDb > 0): ?>

                <a
                    href="ertesites.php"
                    class="btn btn-light"
                >
                    🔔 Értesítések
                    (<?= $ertesitesDb ?>)
                </a>

            <?php else: ?>

                <a
                    href="ertesites.php"
                    class="btn btn-light"
                >
                    🔔 Értesítéseim
                </a>

            <?php endif; ?>

            <?php if ($adminProfil): ?>
                <a
                    href="admin_hirek.php"
                    class="btn btn-primary"
                    title="Hírek és események kezelése"
                >
                    📰 Hírek és események kezelése
                </a>
            <?php endif; ?>

        </div>

    </div>


    <!-- =========================================================
         FŐ TARTALOM
    ========================================================== -->

    <div class="profile-grid">


        <!-- =====================================================
             SZEMÉLYES ADATOK
        ====================================================== -->

        <div class="profile-card">

            <h2>
                Személyes adatok
            </h2>


            <div class="profile-info-grid">


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Teljes név
                    </div>

                    <div class="profile-info-value">
                        <?= e($tag['teljes_nev']) ?>
                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Becenév
                    </div>

                    <div class="profile-info-value">
                        <?= e($tag['becenev'] ?: '—') ?>
                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Telefonszám
                    </div>

                    <div class="profile-info-value">

                        <?php if (!empty($tag['telefonszam'])): ?>

                            <a
                                href="tel:<?= e($tag['telefonszam']) ?>"
                            >
                                📞 <?= e($tag['telefonszam']) ?>
                            </a>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        E-mail
                    </div>

                    <div class="profile-info-value">

                        <?php if (!empty($tag['email'])): ?>

                            <a
                                href="mailto:<?= e($tag['email']) ?>"
                            >
                                ✉️ <?= e($tag['email']) ?>
                            </a>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Lakhely
                    </div>

                    <div class="profile-info-value">
                        <?= e($tag['lakhely'] ?: '—') ?>
                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Születési dátum
                    </div>

                    <div class="profile-info-value">
                        <?= datumFormaz($tag['szuletesi_datum']) ?>
                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Egyesületi belépés
                    </div>

                    <div class="profile-info-value">
                        <?= datumFormaz($tag['belepes_datum']) ?>
                    </div>

                </div>


                <div class="profile-info-item">

                    <div class="profile-info-label">
                        Felhasználónév
                    </div>

                    <div class="profile-info-value">
                        <?= e($tag['felhasznalonev']) ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             STATISZTIKÁK / MENÜ
        ====================================================== -->

        <div class="profile-card">

            <h2>
                Saját felület
            </h2>


            <div class="profile-stats">

                <div class="profile-stat">

                    <strong>
                        <?= $bevetesDb ?>
                    </strong>

                    <span>
                        Bevetés
                    </span>

                </div>


                <div class="profile-stat">

                    <strong>
                        <?= $ertesitesDb ?>
                    </strong>

                    <span>
                        Új értesítés
                    </span>

                </div>

            </div>


            <div style="height:18px;"></div>


            <div class="profile-menu">


                <a href="ertesites.php">

                    <span class="menu-left">
                        🔔
                        <span>
                            Értesítéseim
                        </span>
                    </span>

                    <span class="menu-arrow">
                        →
                    </span>

                </a>


                <a href="bevetesek.php">

                    <span class="menu-left">
                        🚒
                        <span>
                            Bevetések
                        </span>
                    </span>

                    <span class="menu-arrow">
                        →
                    </span>

                </a>


                <a href="profil_szerkeszt.php">

                    <span class="menu-left">
                        ✏️
                        <span>
                            Profil szerkesztése
                        </span>
                    </span>

                    <span class="menu-arrow">
                        →
                    </span>

                </a>
                    <a href="jelszo_modositas.php" class="profil-btn profil-btn-sotet">
                            🔐 Jelszó módosítása
                </a>

            </div>

        </div>

    </div>


    <!-- =========================================================
         KÉPZETTSÉG
    ========================================================== -->

    <div class="profile-card" style="margin-bottom:22px;">

        <h2>
            🎓 Képzettség
        </h2>


        <?php if (!empty($tag['kepzettseg'])): ?>

            <div class="profile-about">
                <?= e($tag['kepzettseg']) ?>
            </div>

        <?php else: ?>

            <div class="profile-about empty">
                Nincs megadott képzettség.
            </div>

        <?php endif; ?>

    </div>


    <!-- =========================================================
         BEMUTATKOZÁS
    ========================================================== -->

    <div class="profile-card">

        <h2>
            👤 Bemutatkozás
        </h2>


        <?php if (!empty($tag['bemutatkozas'])): ?>

            <div class="profile-about">
                <?= e($tag['bemutatkozas']) ?>
            </div>

        <?php else: ?>

            <div class="profile-about empty">
                A tag még nem adott meg bemutatkozást.
            </div>

        <?php endif; ?>


        <div class="profile-footer-info">

            Felhasználónév:
            <?= e($tag['felhasznalonev']) ?>

        </div>

    </div>

</div>


<?php require 'footer.php'; ?>