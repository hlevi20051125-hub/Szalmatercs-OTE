<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

$felhasznalo = aktualisFelhasznalo();
$felhasznaloId = (int)$felhasznalo['id'];

$oldalCim = 'Jelszó módosítása';


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
// VÁLTOZÓK
// ============================================================

$hibak = [];
$sikeres = false;

$regiJelszo = '';
$ujJelszo = '';
$ujJelszoUjra = '';


// ============================================================
// FELHASZNÁLÓ ELLENŐRZÉSE
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        felhasznalonev,
        jelszo,
        nev,
        szerepkor,
        aktiv
    FROM felhasznalok
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$felhasznaloId]);

$fiók = $stmt->fetch();


if (!$fiók) {

    require_once 'header.php';
    ?>

    <div class="jelszo-oldal">

        <div class="jelszo-kartya">

            <h1>
                ❌ Fiók nem található
            </h1>

            <p>
                A bejelentkezett felhasználói fiókot nem sikerült
                megtalálni.
            </p>

            <a
                href="dashboard.php"
                class="jelszo-btn jelszo-btn-sotet"
            >
                Vissza a dashboardra
            </a>

        </div>

    </div>

    <?php
    require_once 'footer.php';
    exit;
}


// ============================================================
// JELSZÓ MÓDOSÍTÁSA
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $regiJelszo = $_POST['regi_jelszo'] ?? '';
    $ujJelszo = $_POST['uj_jelszo'] ?? '';
    $ujJelszoUjra = $_POST['uj_jelszo_ujra'] ?? '';


    // --------------------------------------------------------
    // RÉGI JELSZÓ
    // --------------------------------------------------------

    if ($regiJelszo === '') {

        $hibak[] = 'Add meg a jelenlegi jelszavadat.';

    }


    // --------------------------------------------------------
    // ÚJ JELSZÓ
    // --------------------------------------------------------

    if ($ujJelszo === '') {

        $hibak[] = 'Add meg az új jelszót.';

    } elseif (strlen($ujJelszo) < 8) {

        $hibak[] = 'Az új jelszónak legalább 8 karakter hosszúnak kell lennie.';

    }


    // --------------------------------------------------------
    // ÚJ JELSZÓ MEGERŐSÍTÉSE
    // --------------------------------------------------------

    if ($ujJelszoUjra === '') {

        $hibak[] = 'Írd be újra az új jelszót.';

    } elseif ($ujJelszo !== $ujJelszoUjra) {

        $hibak[] = 'A két új jelszó nem egyezik.';

    }


    // --------------------------------------------------------
    // RÉGI JELSZÓ ELLENŐRZÉSE
    // --------------------------------------------------------

    if (!$hibak && !password_verify($regiJelszo, $fiók['jelszo'])) {

        $hibak[] = 'A jelenlegi jelszó hibás.';

    }


    // --------------------------------------------------------
    // NE LEGYEN AZ ÚJ JELSZÓ AZONOS A RÉGIVEL
    // --------------------------------------------------------

    if (
        !$hibak &&
        password_verify($ujJelszo, $fiók['jelszo'])
    ) {

        $hibak[] = 'Az új jelszónak különböznie kell a jelenlegi jelszótól.';

    }


    // ========================================================
    // MENTÉS
    // ========================================================

    if (!$hibak) {

        try {

            $ujJelszoHash = password_hash(
                $ujJelszo,
                PASSWORD_DEFAULT
            );


            if ($ujJelszoHash === false) {

                throw new Exception(
                    'A jelszó titkosítása nem sikerült.'
                );

            }


            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                UPDATE felhasznalok
                SET jelszo = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $ujJelszoHash,
                $felhasznaloId
            ]);


            $pdo->commit();

            $sikeres = true;

            // Biztonsági okból ürítjük az űrlapmezőket.
            $regiJelszo = '';
            $ujJelszo = '';
            $ujJelszoUjra = '';


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $hibak[] = 'A jelszó módosítása közben hiba történt.';

        }

    }

}


require_once 'header.php';

?>


<style>

.jelszo-oldal {
    max-width: 760px;
    margin: 0 auto;
}

.jelszo-fejlec {
    margin-bottom: 25px;
}

.jelszo-fejlec h1 {
    margin-bottom: 8px;
}

.jelszo-fejlec p {
    color: #707075;
    margin: 0;
}

.jelszo-kartya {
    background: #ffffff;
    border: 1px solid #e3e3e3;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 12px 35px rgba(0,0,0,0.08);
}

.jelszo-kartya h2 {
    margin-top: 0;
    margin-bottom: 25px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e3e3e3;
}

.jelszo-mezo {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 20px;
}

.jelszo-mezo label {
    font-weight: 700;
    color: #18181a;
}

.jelszo-mezo input {
    width: 100%;
    box-sizing: border-box;
    padding: 13px 14px;
    border: 1px solid #d8d8d8;
    border-radius: 10px;
    font-family: inherit;
    font-size: 15px;
    background: #ffffff;
}

.jelszo-mezo input:focus {
    outline: none;
    border-color: #c8102e;
    box-shadow: 0 0 0 3px rgba(200,16,46,0.10);
}

.jelszo-segitseg {
    font-size: 13px;
    color: #777777;
    margin-top: -10px;
    margin-bottom: 20px;
}

.jelszo-hibak {
    background: #fff0f0;
    border: 1px solid #efb5b5;
    color: #9b1c1c;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
}

.jelszo-hibak ul {
    margin: 0;
    padding-left: 20px;
}

.jelszo-siker {
    background: #eaf7ee;
    border: 1px solid #b9dfc5;
    color: #216b35;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
    font-weight: 600;
}

.jelszo-info {
    background: #f7f7f7;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 25px;
}

.jelszo-info strong {
    color: #18181a;
}

.jelszo-muveletek {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
}

.jelszo-btn {
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

.jelszo-btn-piros {
    background: #c8102e;
    color: #ffffff;
}

.jelszo-btn-piros:hover {
    background: #e31837;
    color: #ffffff;
}

.jelszo-btn-sotet {
    background: #202024;
    color: #ffffff;
}

.jelszo-btn-sotet:hover {
    background: #0d0d0f;
    color: #ffffff;
}

@media (max-width: 600px) {

    .jelszo-kartya {
        padding: 20px;
    }

    .jelszo-muveletek {
        flex-direction: column-reverse;
    }

    .jelszo-btn {
        width: 100%;
    }

}

</style>


<div class="jelszo-oldal">


    <!-- ========================================================
         FEJLÉC
    ========================================================= -->

    <div class="jelszo-fejlec">

        <h1>
            🔐 Jelszó módosítása
        </h1>

        <p>
            A fiókod biztonsága érdekében itt módosíthatod a jelszavadat.
        </p>

    </div>


    <!-- ========================================================
         SIKERES ÜZENET
    ========================================================= -->

    <?php if ($sikeres): ?>

        <div class="jelszo-siker">

            ✓ A jelszavad sikeresen megváltozott.

            <br>

            A következő bejelentkezéskor már az új jelszót kell használnod.

        </div>

    <?php endif; ?>


    <!-- ========================================================
         HIBÁK
    ========================================================= -->

    <?php if ($hibak): ?>

        <div class="jelszo-hibak">

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
         FELHASZNÁLÓI INFORMÁCIÓ
    ========================================================= -->

    <div class="jelszo-kartya">

        <div class="jelszo-info">

            Bejelentkezve mint:

            <strong>
                <?= e($fiók['felhasznalonev']) ?>
            </strong>

        </div>


        <h2>
            🔑 Új jelszó beállítása
        </h2>


        <form method="post">


            <!-- RÉGI JELSZÓ -->

            <div class="jelszo-mezo">

                <label for="regi_jelszo">
                    Jelenlegi jelszó
                </label>

                <input
                    type="password"
                    id="regi_jelszo"
                    name="regi_jelszo"
                    autocomplete="current-password"
                    required
                >

            </div>


            <!-- ÚJ JELSZÓ -->

            <div class="jelszo-mezo">

                <label for="uj_jelszo">
                    Új jelszó
                </label>

                <input
                    type="password"
                    id="uj_jelszo"
                    name="uj_jelszo"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="jelszo-segitseg">
                Az új jelszó legalább 8 karakter legyen.
            </div>


            <!-- ÚJ JELSZÓ ÚJRA -->

            <div class="jelszo-mezo">

                <label for="uj_jelszo_ujra">
                    Új jelszó újra
                </label>

                <input
                    type="password"
                    id="uj_jelszo_ujra"
                    name="uj_jelszo_ujra"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <!-- GOMBOK -->

            <div class="jelszo-muveletek">

                <a
                    href="profil.php"
                    class="jelszo-btn jelszo-btn-sotet"
                >
                    Mégsem
                </a>

                <button
                    type="submit"
                    class="jelszo-btn jelszo-btn-piros"
                >
                    🔐 Jelszó módosítása
                </button>

            </div>


        </form>

    </div>


</div>


<?php

require_once 'footer.php';

?>