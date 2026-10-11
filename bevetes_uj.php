<?php

require_once 'db.php';
require_once 'auth.php';

adminVagyParancsnok();

$oldalCim = 'Új bevetés';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$riasztasId = (int)($_GET['riasztas_id'] ?? $_POST['riasztas_id'] ?? 0);

$hiba = '';
$siker = '';

/*
|--------------------------------------------------------------------------
| Alapadatok
|--------------------------------------------------------------------------
*/

$ri = $pdo->query("
    SELECT *
    FROM riasztasok
    WHERE allapot <> 'visszavont'
    ORDER BY datum DESC, riasztasi_ido DESC
")->fetchAll();

$jarmuvek = $pdo->query("
    SELECT *
    FROM jarmuvek
    ORDER BY azonosito
")->fetchAll();

$tagok = $pdo->query("
    SELECT *
    FROM tagok
    WHERE aktiv = 1
    ORDER BY teljes_nev
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Űrlap alapértékek
|--------------------------------------------------------------------------
*/

$data = [
    'jarmu_id' => '',
    'indulas_ido' => '',
    'erkezes_ido' => '',
    'visszaindulas_ido' => '',
    'visszaerkezes_ido' => '',
    'megjegyzes' => ''
];

$kivalasztott = [];


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $riasztasId = (int)($_POST['riasztas_id'] ?? 0);

    $data['jarmu_id'] = $_POST['jarmu_id'] ?? '';

    $data['indulas_ido'] =
        trim($_POST['indulas_ido'] ?? '');

    $data['erkezes_ido'] =
        trim($_POST['erkezes_ido'] ?? '');

    $data['visszaindulas_ido'] =
        trim($_POST['visszaindulas_ido'] ?? '');

    $data['visszaerkezes_ido'] =
        trim($_POST['visszaerkezes_ido'] ?? '');

    $data['megjegyzes'] =
        trim($_POST['megjegyzes'] ?? '');

    $kivalasztott =
        $_POST['tag'] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Riasztás ellenőrzése
    |--------------------------------------------------------------------------
    */

    if (!$riasztasId) {

        $hiba = 'Válassz riasztást.';
    }


    /*
    |--------------------------------------------------------------------------
    | Időpontok ellenőrzése
    |--------------------------------------------------------------------------
    */

    foreach (
        [
            'indulas_ido',
            'erkezes_ido',
            'visszaindulas_ido',
            'visszaerkezes_ido'
        ] as $x
    ) {

        if (
            $data[$x] !== '' &&
            !preg_match('/^\d{2}:\d{2}$/', $data[$x])
        ) {

            $hiba = 'Az időpontok formátuma HH:MM legyen.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Időrendi sorrend
    |--------------------------------------------------------------------------
    */

    $vals = array_values(
        array_filter(
            [
                $data['indulas_ido'],
                $data['erkezes_ido'],
                $data['visszaindulas_ido'],
                $data['visszaerkezes_ido']
            ],
            fn($x) => $x !== ''
        )
    );

    for ($i = 1; $i < count($vals); $i++) {

        if ($vals[$i] < $vals[$i - 1]) {

            $hiba =
                'Az időpontoknak időrendben kell követniük egymást.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Mentés
    |--------------------------------------------------------------------------
    */

    if (!$hiba) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Bevetés mentése
            |--------------------------------------------------------------------------
            */

            $st = $pdo->prepare("
                INSERT INTO bevetesek (
                    riasztas_id,
                    jarmu_id,
                    indulas_ido,
                    erkezes_ido,
                    visszaindulas_ido,
                    visszaerkezes_ido,
                    megjegyzes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $st->execute([
                $riasztasId,
                $data['jarmu_id'] !== ''
                    ? (int)$data['jarmu_id']
                    : null,

                $data['indulas_ido'] !== ''
                    ? $data['indulas_ido']
                    : null,

                $data['erkezes_ido'] !== ''
                    ? $data['erkezes_ido']
                    : null,

                $data['visszaindulas_ido'] !== ''
                    ? $data['visszaindulas_ido']
                    : null,

                $data['visszaerkezes_ido'] !== ''
                    ? $data['visszaerkezes_ido']
                    : null,

                $data['megjegyzes']
            ]);


            $bid = (int)$pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Kivonult tagok mentése
            |--------------------------------------------------------------------------
            */

            $tagSt = $pdo->prepare("
                INSERT INTO bevetes_tagok (
                    bevetes_id,
                    tag_id,
                    szerep,
                    megjegyzes
                )
                VALUES (?, ?, ?, ?)
            ");


            /*
            |--------------------------------------------------------------------------
            | Értesítések előkészítése
            |--------------------------------------------------------------------------
            */

            $ertesitesSt = $pdo->prepare("
                INSERT INTO ertesitesek (
                    cimzett_tag_id,
                    kuldo_felhasznalo_id,
                    bevetes_id,
                    tipus,
                    cim,
                    uzenet,
                    allapot
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'bevetesi_meghivo',
                    ?,
                    ?,
                    'uj'
                )
            ");


            /*
            |--------------------------------------------------------------------------
            | Aktuális felhasználó
            |--------------------------------------------------------------------------
            */

            $felhasznalo = aktualisFelhasznalo();

            $kuldoFelhasznaloId = null;

            if (
                $felhasznalo &&
                isset($felhasznalo['id'])
            ) {

                $kuldoFelhasznaloId =
                    (int)$felhasznalo['id'];
            }


            /*
            |--------------------------------------------------------------------------
            | Riasztás adatai az értesítéshez
            |--------------------------------------------------------------------------
            */

            $riasztasSt = $pdo->prepare("
                SELECT
                    riasztas_szam,
                    datum,
                    riasztasi_ido,
                    helyszin,
                    telepules,
                    esemeny_tipus
                FROM riasztasok
                WHERE id = ?
                LIMIT 1
            ");

            $riasztasSt->execute([
                $riasztasId
            ]);

            $riasztas = $riasztasSt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Meghívó szövege
            |--------------------------------------------------------------------------
            */

            $meghivoCim =
                '🚒 Bevetési meghívó';


            $meghivoUzenet =
                'Bevetésre invitáltak.' .
                "\n\n";


            if ($riasztas) {

                $meghivoUzenet .=
                    'Riasztási szám: ' .
                    $riasztas['riasztas_szam'] .
                    "\n";

                if (!empty($riasztas['datum'])) {

                    $meghivoUzenet .=
                        'Dátum: ' .
                        date(
                            'Y. m. d.',
                            strtotime($riasztas['datum'])
                        ) .
                        "\n";
                }

                if (!empty($riasztas['riasztasi_ido'])) {

                    $meghivoUzenet .=
                        'Riasztási idő: ' .
                        substr(
                            $riasztas['riasztasi_ido'],
                            0,
                            5
                        ) .
                        "\n";
                }

                if (!empty($riasztas['helyszin'])) {

                    $meghivoUzenet .=
                        'Helyszín: ' .
                        $riasztas['helyszin'] .
                        "\n";
                }

                if (!empty($riasztas['telepules'])) {

                    $meghivoUzenet .=
                        'Település: ' .
                        $riasztas['telepules'] .
                        "\n";
                }

                if (!empty($riasztas['esemeny_tipus'])) {

                    $meghivoUzenet .=
                        'Esemény: ' .
                        $riasztas['esemeny_tipus'] .
                        "\n";
                }
            }

            $meghivoUzenet .=
                "\nKérjük, jelezd az értesítésben, hogy részt tudsz-e venni.";


            /*
            |--------------------------------------------------------------------------
            | Tagok mentése + meghívók
            |--------------------------------------------------------------------------
            */

            foreach ($kivalasztott as $tid => $a) {

                if (
                    !isset($a['kivalasztva']) ||
                    empty($a['kivalasztva'])
                ) {

                    continue;
                }


                $tagId = (int)$tid;

                if ($tagId <= 0) {
                    continue;
                }


                $szerep =
                    trim($a['szerep'] ?? '');

                $tagMegjegyzes =
                    trim($a['megjegyzes'] ?? '');


                /*
                | Bevetés-tag kapcsolat
                */

                $tagSt->execute([
                    $bid,
                    $tagId,
                    $szerep,
                    $tagMegjegyzes
                ]);


                /*
                | Ellenőrizzük, hogy a tag valóban aktív-e
                */

                $tagEllenorzes = $pdo->prepare("
                    SELECT id
                    FROM tagok
                    WHERE id = ?
                      AND aktiv = 1
                    LIMIT 1
                ");

                $tagEllenorzes->execute([
                    $tagId
                ]);

                if (!$tagEllenorzes->fetch()) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Ellenőrizzük, nincs-e már aktív meghívó
                |--------------------------------------------------------------------------
                */

                $duplaSt = $pdo->prepare("
                    SELECT id
                    FROM ertesitesek
                    WHERE cimzett_tag_id = ?
                      AND bevetes_id = ?
                      AND tipus = 'bevetesi_meghivo'
                      AND allapot IN ('uj', 'olvasott')
                    LIMIT 1
                ");

                $duplaSt->execute([
                    $tagId,
                    $bid
                ]);

                $marVanMeghivo =
                    $duplaSt->fetch();


                /*
                |--------------------------------------------------------------------------
                | Értesítés létrehozása
                |--------------------------------------------------------------------------
                */

                if (!$marVanMeghivo) {

                    $ertesitesSt->execute([
                        $tagId,
                        $kuldoFelhasznaloId,
                        $bid,
                        $meghivoCim,
                        $meghivoUzenet
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Riasztás állapotának frissítése
            |--------------------------------------------------------------------------
            */

            if ($data['visszaerkezes_ido'] !== '') {

                $pdo->prepare("
                    UPDATE riasztasok
                    SET
                        allapot = 'lezart',
                        lezaras_ido = ?
                    WHERE id = ?
                ")->execute([
                    $data['visszaerkezes_ido'],
                    $riasztasId
                ]);

            } else {

                $pdo->prepare("
                    UPDATE riasztasok
                    SET
                        allapot = 'folyamatban',
                        lezaras_ido = NULL
                    WHERE id = ?
                ")->execute([
                    $riasztasId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Minden rendben
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: bevetes_reszletek.php?id=' . $bid
            );

            exit;


        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $hiba =
                'A mentés nem sikerült: ' .
                $ex->getMessage();
        }
    }
}


require 'header.php';

?>

<div class="page-title">

    <div>

        <span class="eyebrow">
            BEVETÉS
        </span>

        <h1>
            Új bevetés rögzítése
        </h1>

        <p class="muted">
            A bevetés mentése automatikusan frissíti
            a riasztás állapotát.
        </p>

    </div>

    <a
        class="btn btn-light"
        href="bevetesek.php"
    >
        ← Vissza
    </a>

</div>


<div class="card form-card">

    <?php if ($hiba): ?>

        <div class="alert alert-danger">
            <?= e($hiba) ?>
        </div>

    <?php endif; ?>


    <form method="post">

        <div class="form-grid">


            <!-- RIASZTÁS -->

            <label class="full">

                Riasztás *

                <select
                    name="riasztas_id"
                    required
                >

                    <option value="">
                        -- Válassz --
                    </option>

                    <?php foreach ($ri as $r): ?>

                        <option
                            value="<?= (int)$r['id'] ?>"
                            <?= $riasztasId == $r['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= e($r['riasztas_szam']) ?>

                            –

                            <?= e($r['datum']) ?>

                            –

                            <?= e($r['helyszin']) ?>

                            (

                            <?= e($r['allapot']) ?>

                            )

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <!-- JÁRMŰ -->

            <label>

                Jármű

                <select name="jarmu_id">

                    <option value="">
                        Nincs / nem rögzítjük
                    </option>

                    <?php foreach ($jarmuvek as $j): ?>

                        <option
                            value="<?= (int)$j['id'] ?>"
                            <?= $data['jarmu_id'] == $j['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= e($j['azonosito']) ?>

                            –

                            <?= e($j['rendszam']) ?>

                            –

                            <?= e(
                                $j['marka'] . ' ' . $j['tipus']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <div></div>


            <!-- IDŐPONTOK -->

            <label>

                Indulás

                <input
                    type="time"
                    name="indulas_ido"
                    value="<?= e($data['indulas_ido']) ?>"
                >

            </label>


            <label>

                Helyszínre érkezés

                <input
                    type="time"
                    name="erkezes_ido"
                    value="<?= e($data['erkezes_ido']) ?>"
                >

            </label>


            <label>

                Visszaindulás

                <input
                    type="time"
                    name="visszaindulas_ido"
                    value="<?= e($data['visszaindulas_ido']) ?>"
                >

            </label>


            <label>

                Visszaérkezés

                <input
                    type="time"
                    name="visszaerkezes_ido"
                    value="<?= e($data['visszaerkezes_ido']) ?>"
                >

            </label>


            <!-- MEGJEGYZÉS -->

            <label class="full">

                Bevetési megjegyzés

                <textarea
                    name="megjegyzes"
                    rows="5"
                ><?= e($data['megjegyzes']) ?></textarea>

            </label>

        </div>


        <!-- =====================================================
             SZEMÉLYI ÁLLOMÁNY
        ====================================================== -->

        <h2 class="form-section-title">
            Kivonult személyi állomány
        </h2>


        <p class="muted">
            Jelöld ki azokat a tagokat, akik részt vesznek
            a bevetésen. A kijelölt tagok automatikusan
            bevetési értesítést kapnak.
        </p>


        <div class="member-select-grid">


            <?php foreach ($tagok as $t): ?>

                <?php

                $a =
                    $kivalasztott[$t['id']]
                    ?? [];

                ?>


                <div class="member-row">


                    <label class="checkbox-label">

                        <input
                            type="checkbox"
                            name="tag[<?= (int)$t['id'] ?>][kivalasztva]"
                            value="1"
                            <?= !empty($a['kivalasztva'])
                                ? 'checked'
                                : '' ?>
                        >

                        <strong>
                            <?= e($t['teljes_nev']) ?>
                        </strong>


                        <?php if (!empty($t['becenev'])): ?>

                            <span class="muted">

                                (
                                <?= e($t['becenev']) ?>
                                )

                            </span>

                        <?php endif; ?>


                        <?php if (!empty($t['beosztas'])): ?>

                            <span class="muted">

                                —
                                <?= e($t['beosztas']) ?>

                            </span>

                        <?php endif; ?>

                    </label>


                    <input
                        type="text"
                        name="tag[<?= (int)$t['id'] ?>][szerep]"
                        value="<?= e($a['szerep'] ?? '') ?>"
                        placeholder="Szerep"
                    >


                    <input
                        type="text"
                        name="tag[<?= (int)$t['id'] ?>][megjegyzes]"
                        value="<?= e($a['megjegyzes'] ?? '') ?>"
                        placeholder="Megjegyzés"
                    >

                </div>

            <?php endforeach; ?>


            <?php if (!$tagok): ?>

                <div class="empty-state">

                    Nincs aktív tag az adatbázisban.

                </div>

            <?php endif; ?>


        </div>


        <!-- =====================================================
             GOMBOK
        ====================================================== -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                🚒 Bevetés mentése
            </button>

            <a
                class="btn btn-light"
                href="bevetesek.php"
            >
                Mégse
            </a>

        </div>


    </form>

</div>


<?php require 'footer.php'; ?>