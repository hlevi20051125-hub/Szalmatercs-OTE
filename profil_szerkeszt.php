<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

$felhasznalo = aktualisFelhasznalo();
$felhasznaloId = (int)$felhasznalo['id'];

$oldalCim = 'Profil szerkesztése';


// ============================================================
// SEGÉDFÜGGVÉNY
// ============================================================

function e($ertek)
{
    return htmlspecialchars(
        (string)$ertek,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ============================================================
// SAJÁT TAGPROFIL BETÖLTÉSE
// ============================================================

$stmt = $pdo->prepare("
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
        f.aktiv AS fiok_aktiv

    FROM tagok t

    INNER JOIN felhasznalok f
        ON f.id = t.felhasznalo_id

    WHERE t.felhasznalo_id = ?

    LIMIT 1
");

$stmt->execute([$felhasznaloId]);

$profil = $stmt->fetch();


// ============================================================
// HA NINCS PROFIL
// ============================================================

if (!$profil) {

    require_once 'header.php';

    ?>

    <div class="card" style="max-width:800px;margin:40px auto;">

        <h1>Profil nem található</h1>

        <p>
            A bejelentkezett felhasználóhoz nem tartozik tagprofil.
        </p>

        <a
            href="dashboard.php"
            class="btn btn-primary"
        >
            Vissza a dashboardra
        </a>

    </div>

    <?php

    require_once 'footer.php';

    exit;
}


// ============================================================
// ŰRLAP ADATOK
// ============================================================

$hibak = [];
$sikeres = false;

$becenev = $profil['becenev'] ?? '';
$telefonszam = $profil['telefonszam'] ?? '';
$email = $profil['email'] ?? '';
$lakhely = $profil['lakhely'] ?? '';
$kepzettseg = $profil['kepzettseg'] ?? '';
$bemutatkozas = $profil['bemutatkozas'] ?? '';


// ============================================================
// MENTÉS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $becenev = trim($_POST['becenev'] ?? '');
    $telefonszam = trim($_POST['telefonszam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $lakhely = trim($_POST['lakhely'] ?? '');
    $kepzettseg = trim($_POST['kepzettseg'] ?? '');
    $bemutatkozas = trim($_POST['bemutatkozas'] ?? '');


    // --------------------------------------------------------
    // E-MAIL ELLENŐRZÉS
    // --------------------------------------------------------

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $hibak[] = 'Az e-mail cím formátuma nem megfelelő.';

    }


    // --------------------------------------------------------
    // HOSSZ ELLENŐRZÉSEK
    // --------------------------------------------------------

    if (mb_strlen($becenev) > 50) {

        $hibak[] = 'A becenév legfeljebb 50 karakter lehet.';

    }

    if (mb_strlen($telefonszam) > 30) {

        $hibak[] = 'A telefonszám legfeljebb 30 karakter lehet.';

    }

    if (mb_strlen($email) > 150) {

        $hibak[] = 'Az e-mail cím legfeljebb 150 karakter lehet.';

    }

    if (mb_strlen($lakhely) > 150) {

        $hibak[] = 'A lakhely legfeljebb 150 karakter lehet.';

    }


    // ========================================================
    // MENTÉS
    // ========================================================

    if (!$hibak) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                UPDATE tagok
                SET
                    becenev = ?,
                    telefonszam = ?,
                    email = ?,
                    lakhely = ?,
                    kepzettseg = ?,
                    bemutatkozas = ?,
                    modositva = NOW()

                WHERE id = ?
                  AND felhasznalo_id = ?

                LIMIT 1
            ");

            $stmt->execute([
                $becenev !== '' ? $becenev : null,
                $telefonszam !== '' ? $telefonszam : null,
                $email !== '' ? $email : null,
                $lakhely !== '' ? $lakhely : null,
                $kepzettseg !== '' ? $kepzettseg : null,
                $bemutatkozas !== '' ? $bemutatkozas : null,
                (int)$profil['id'],
                $felhasznaloId
            ]);


            $pdo->commit();

            $sikeres = true;


            // Frissített adatok visszaolvasása

            $stmt = $pdo->prepare("
                SELECT
                    t.*,
                    f.felhasznalonev,
                    f.szerepkor,
                    f.aktiv AS fiok_aktiv
                FROM tagok t
                INNER JOIN felhasznalok f
                    ON f.id = t.felhasznalo_id
                WHERE t.felhasznalo_id = ?
                LIMIT 1
            ");

            $stmt->execute([$felhasznaloId]);

            $profil = $stmt->fetch();


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $hibak[] = 'A profil mentése közben hiba történt.';

        }

    }

}


require_once 'header.php';

?>

<style>

.profil-szerkesztes-oldal {
    max-width: 1000px;
    margin: 0 auto;
}

.profil-szerkesztes-fejlec {
    margin-bottom: 25px;
}

.profil-szerkesztes-fejlec h1 {
    margin-bottom: 8px;
}

.profil-szerkesztes-fejlec p {
    color: #707075;
    margin: 0;
}

.profil-szerkesztes-kartya {
    background: #ffffff;
    border: 1px solid #e3e3e3;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 12px 35px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.profil-szerkesztes-kartya h2 {
    margin-top: 0;
    margin-bottom: 22px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e3e3e3;
}

.profil-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.profil-mezo {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.profil-mezo.teljes {
    grid-column: 1 / -1;
}

.profil-mezo label {
    font-weight: 700;
    color: #18181a;
}

.profil-mezo input,
.profil-mezo textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #d8d8d8;
    border-radius: 10px;
    font-family: inherit;
    font-size: 15px;
    background: #ffffff;
}

.profil-mezo input:focus,
.profil-mezo textarea:focus {
    outline: none;
    border-color: #c8102e;
    box-shadow: 0 0 0 3px rgba(200,16,46,0.10);
}

.profil-mezo textarea {
    min-height: 140px;
    resize: vertical;
}

.profil-mezo input[readonly] {
    background: #f3f3f3;
    color: #666666;
}

.profil-segitseg {
    font-size: 13px;
    color: #777777;
}

.profil-siker {
    background: #eaf7ee;
    border: 1px solid #b9dfc5;
    color: #216b35;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
    font-weight: 600;
}

.profil-hibak {
    background: #fff0f0;
    border: 1px solid #efb5b5;
    color: #9b1c1c;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
}

.profil-hibak ul {
    margin: 0;
    padding-left: 20px;
}

.profil-muveletek {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 25px;
}

.profil-info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.profil-info {
    background: #f7f7f7;
    border-radius: 10px;
    padding: 15px;
}

.profil-info-cim {
    font-size: 12px;
    color: #777777;
    margin-bottom: 5px;
}

.profil-info-ertek {
    font-weight: 700;
    color: #18181a;
}

.profil-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 20px;
    border-radius: 9px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-weight: 700;
    font-size: 14px;
}

.profil-btn-piros {
    background: #c8102e;
    color: #ffffff;
}

.profil-btn-piros:hover {
    background: #e31837;
    color: #ffffff;
}

.profil-btn-sotet {
    background: #202024;
    color: #ffffff;
}

.profil-btn-sotet:hover {
    background: #0d0d0f;
    color: #ffffff;
}

@media (max-width: 700px) {

    .profil-grid {
        grid-template-columns: 1fr;
    }

    .profil-mezo.teljes {
        grid-column: auto;
    }

    .profil-info-grid {
        grid-template-columns: 1fr;
    }

    .profil-szerkesztes-kartya {
        padding: 20px;
    }

    .profil-muveletek {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .profil-btn {
        width: 100%;
    }

}

</style>


<div class="profil-szerkesztes-oldal">


    <!-- ========================================================
         FEJLÉC
    ========================================================= -->

    <div class="profil-szerkesztes-fejlec">

        <h1>
            ✏️ Profil szerkesztése
        </h1>

        <p>
            Itt módosíthatod a saját profilodhoz tartozó adatokat.
        </p>

    </div>


    <!-- ========================================================
         ÜZENETEK
    ========================================================= -->

    <?php if ($sikeres): ?>

        <div class="profil-siker">
            ✓ A profilod sikeresen frissítve lett.
        </div>

    <?php endif; ?>


    <?php if ($hibak): ?>

        <div class="profil-hibak">

            <ul>

                <?php foreach ($hibak as $hiba): ?>

                    <li>
                        <?= e($hiba) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         ALAPADATOK
    ========================================================= -->

    <div class="profil-szerkesztes-kartya">

        <h2>
            👤 Alapadatok
        </h2>


        <div class="profil-info-grid">

            <div class="profil-info">

                <div class="profil-info-cim">
                    Teljes név
                </div>

                <div class="profil-info-ertek">
                    <?= e($profil['teljes_nev']) ?>
                </div>

            </div>


            <div class="profil-info">

                <div class="profil-info-cim">
                    Felhasználónév
                </div>

                <div class="profil-info-ertek">
                    <?= e($profil['felhasznalonev']) ?>
                </div>

            </div>


            <div class="profil-info">

                <div class="profil-info-cim">
                    Beosztás
                </div>

                <div class="profil-info-ertek">

                    <?= e(
                        $profil['beosztas']
                        ?: 'Nincs megadva'
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================================
         SZERKESZTHETŐ ADATOK
    ========================================================= -->

    <form method="post" class="profil-szerkesztes-kartya">

        <h2>
            📝 Személyes adatok
        </h2>


        <div class="profil-grid">


            <!-- BECENÉV -->

            <div class="profil-mezo">

                <label for="becenev">
                    Becenév
                </label>

                <input
                    type="text"
                    id="becenev"
                    name="becenev"
                    maxlength="50"
                    value="<?= e($becenev) ?>"
                    placeholder="Pl. Pisti"
                >

            </div>


            <!-- TELEFONSZÁM -->

            <div class="profil-mezo">

                <label for="telefonszam">
                    Telefonszám
                </label>

                <input
                    type="text"
                    id="telefonszam"
                    name="telefonszam"
                    maxlength="30"
                    value="<?= e($telefonszam) ?>"
                    placeholder="Pl. +36 30 123 4567"
                >

            </div>


            <!-- EMAIL -->

            <div class="profil-mezo">

                <label for="email">
                    E-mail cím
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= e($email) ?>"
                    placeholder="pelda@email.hu"
                >

            </div>


            <!-- LAKHELY -->

            <div class="profil-mezo">

                <label for="lakhely">
                    Lakhely
                </label>

                <input
                    type="text"
                    id="lakhely"
                    name="lakhely"
                    maxlength="150"
                    value="<?= e($lakhely) ?>"
                    placeholder="Pl. Salgótarján"
                >

            </div>


            <!-- KÉPZETTSÉG -->

            <div class="profil-mezo teljes">

                <label for="kepzettseg">
                    Képzettség
                </label>

                <textarea
                    id="kepzettseg"
                    name="kepzettseg"
                    placeholder="Pl. gépjárművezető, tűzoltó alaptanfolyam, elsősegély..."
                ><?= e($kepzettseg) ?></textarea>

                <div class="profil-segitseg">
                    Több képzettséget külön sorban is megadhatsz.
                </div>

            </div>


            <!-- BEMUTATKOZÁS -->

            <div class="profil-mezo teljes">

                <label for="bemutatkozas">
                    Bemutatkozás
                </label>

                <textarea
                    id="bemutatkozas"
                    name="bemutatkozas"
                    placeholder="Írj néhány mondatot magadról..."
                ><?= e($bemutatkozas) ?></textarea>

            </div>

        </div>


        <!-- GOMBOK -->

        <div class="profil-muveletek">

            <a
                href="profil.php"
                class="profil-btn profil-btn-sotet"
            >
                Mégsem
            </a>

            <button
                type="submit"
                class="profil-btn profil-btn-piros"
            >
                💾 Profil mentése
            </button>

        </div>

    </form>


    <!-- ========================================================
         FIGYELEM
    ========================================================= -->

    <div class="profil-szerkesztes-kartya">

        <h2>
            🔐 Fiók és biztonság
        </h2>

        <p>
            A felhasználónevedet és a jelszavadat ezen az oldalon
            nem módosítjuk.
        </p>

        <p>
            A következő lépésben külön készíthetünk egy
            <strong>„Jelszó módosítása”</strong> oldalt, ahol a régi
            jelszó ellenőrzése után biztonságosan beállítható az új jelszó.
        </p>

    </div>


</div>


<?php

require_once 'footer.php';

?>