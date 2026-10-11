<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

/*
|--------------------------------------------------------------------------
| Jogosultság
|--------------------------------------------------------------------------
|
| Bevetésre más tagot csak admin vagy parancsnok invitálhat.
|
*/

if (!jogosultsag('admin') && !jogosultsag('parancsnok')) {

    http_response_code(403);

    die('
        <div style="
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 80px auto;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            background: #f5f5f5;
        ">
            <h1>🚫 Nincs jogosultság</h1>
            <p>Nincs jogosultságod bevetési meghívó küldéséhez.</p>
            <a href="tagok.php">← Vissza a tagokhoz</a>
        </div>
    ');
}


/*
|--------------------------------------------------------------------------
| Tag azonosító
|--------------------------------------------------------------------------
*/

$tagId = (int)($_GET['tag_id'] ?? $_POST['tag_id'] ?? 0);

if ($tagId <= 0) {

    header('Location: tagok.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Bevetés azonosító
|--------------------------------------------------------------------------
|
| Később konkrét bevetéshez is tudjuk kötni.
|
*/

$bevetesId = (int)($_GET['bevetes_id'] ?? $_POST['bevetes_id'] ?? 0);

if ($bevetesId <= 0) {
    $bevetesId = null;
}


/*
|--------------------------------------------------------------------------
| Tag lekérése
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        teljes_nev,
        becenev,
        beosztas,
        telefonszam,
        email,
        aktiv
    FROM tagok
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$tagId]);

$tag = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Tag ellenőrzése
|--------------------------------------------------------------------------
*/

if (!$tag) {

    header('Location: tagok.php');
    exit;
}


if (!(int)$tag['aktiv']) {

    header('Location: tagok.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Bevetés ellenőrzése
|--------------------------------------------------------------------------
*/

$bevetes = null;

if ($bevetesId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.riasztas_id,
            r.riasztas_szam,
            r.datum,
            r.riasztasi_ido,
            r.helyszin,
            r.telepules,
            r.esemeny_tipus
        FROM bevetesek b
        LEFT JOIN riasztasok r
            ON r.id = b.riasztas_id
        WHERE b.id = ?
        LIMIT 1
    ");

    $stmt->execute([$bevetesId]);

    $bevetes = $stmt->fetch();

    if (!$bevetes) {
        $bevetesId = null;
    }
}


/*
|--------------------------------------------------------------------------
| Aktuális felhasználó
|--------------------------------------------------------------------------
*/

$felhasznalo = aktualisFelhasznalo();

$kuldoFelhasznaloId = null;

if ($felhasznalo && isset($felhasznalo['id'])) {
    $kuldoFelhasznaloId = (int)$felhasznalo['id'];
}


/*
|--------------------------------------------------------------------------
| Meghívó létrehozása
|--------------------------------------------------------------------------
*/

try {

    /*
    | Ha konkrét bevetéshez tartozik a meghívás,
    | akkor ezt írjuk bele az üzenetbe.
    */

    if ($bevetes) {

        $cim = '🚒 Bevetési meghívó';

        $uzenet =
            'Bevetésre invitáltak.' .
            "\n\n" .
            'Riasztási szám: ' . $bevetes['riasztas_szam'] .
            "\n" .
            'Dátum: ' . date('Y. m. d.', strtotime($bevetes['datum'])) .
            "\n" .
            'Helyszín: ' . $bevetes['helyszin'];

        if (!empty($bevetes['telepules'])) {

            $uzenet .=
                "\n" .
                'Település: ' . $bevetes['telepules'];
        }

        if (!empty($bevetes['esemeny_tipus'])) {

            $uzenet .=
                "\n" .
                'Esemény: ' . $bevetes['esemeny_tipus'];
        }

    } else {

        $cim = '🚒 Bevetési meghívó';

        $uzenet =
            'Bevetésre invitáltak.' .
            "\n\n" .
            'Kérjük, jelezd, hogy részt tudsz-e venni a bevetésen.';
    }


    /*
    | Megnézzük, nincs-e már ugyanilyen
    | aktív meghívó.
    */

    if ($bevetesId !== null) {

        $ellenorzo = $pdo->prepare("
            SELECT id
            FROM ertesitesek
            WHERE cimzett_tag_id = ?
              AND bevetes_id = ?
              AND tipus = 'bevetesi_meghivo'
              AND allapot IN ('uj', 'olvasott')
            LIMIT 1
        ");

        $ellenorzo->execute([
            $tagId,
            $bevetesId
        ]);

    } else {

        /*
        | Általános meghívónál az utolsó új/olvasatlan
        | meghívást ellenőrizzük.
        */

        $ellenorzo = $pdo->prepare("
            SELECT id
            FROM ertesitesek
            WHERE cimzett_tag_id = ?
              AND bevetes_id IS NULL
              AND tipus = 'bevetesi_meghivo'
              AND allapot IN ('uj', 'olvasott')
            ORDER BY id DESC
            LIMIT 1
        ");

        $ellenorzo->execute([
            $tagId
        ]);
    }


    $meglevo = $ellenorzo->fetch();


    /*
    |--------------------------------------------------------------------------
    | Ha már van aktív meghívó
    |--------------------------------------------------------------------------
    */

    if ($meglevo) {

        header(
            'Location: tagok.php?meghivo=mar_van'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Értesítés mentése
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        $tagId,
        $kuldoFelhasznaloId,
        $bevetesId,
        $cim,
        $uzenet
    ]);


    /*
    |--------------------------------------------------------------------------
    | Sikeres visszatérés
    |--------------------------------------------------------------------------
    */

    header(
        'Location: tagok.php?meghivo=sikeres'
    );

    exit;


} catch (PDOException $e) {

    /*
    | Fejlesztés alatt jelenleg jelezzük a hibát.
    | Később ezt szebb hibaoldalra lehet cserélni.
    */

    http_response_code(500);

    echo '<!DOCTYPE html>';
    echo '<html lang="hu">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Hiba</title>';
    echo '</head>';
    echo '<body style="
        font-family: Arial, sans-serif;
        background: #f5f5f5;
        padding: 40px;
    ">';

    echo '<div style="
        max-width: 700px;
        margin: auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,.1);
    ">';

    echo '<h1>❌ Hiba történt</h1>';

    echo '<p>Az értesítés létrehozása nem sikerült.</p>';

    echo '<p>';
    echo '<a href="tagok.php">← Vissza a tagokhoz</a>';
    echo '</p>';

    echo '</div>';

    echo '</body>';
    echo '</html>';

    exit;
}