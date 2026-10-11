```php
<?php

require_once "db.php";
require_once "auth.php";

bejelentkezesSzukseges();

$felhasznalo = aktualisFelhasznalo();
$szerepkor = $felhasznalo["szerepkor"] ?? "";

if (!in_array($szerepkor, ["admin", "parancsnok"], true)) {
    http_response_code(403);
    exit("Nincs jogosultságod riasztást létrehozni.");
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: esemeny_bejelentesek_admin.php");
    exit;
}

$token = $_POST["csrf_token"] ?? "";
$bejelentesId = filter_input(
    INPUT_POST,
    "bejelentes_id",
    FILTER_VALIDATE_INT
);

if (
    !is_string($token) ||
    empty($_SESSION["csrf_esemeny"]) ||
    !hash_equals($_SESSION["csrf_esemeny"], $token)
) {
    $_SESSION["hiba"] = "Érvénytelen vagy lejárt munkamenet.";
    header("Location: esemeny_bejelentesek_admin.php");
    exit;
}

if (!$bejelentesId || $bejelentesId < 1) {
    $_SESSION["hiba"] = "Érvénytelen bejelentésazonosító.";
    header("Location: esemeny_bejelentesek_admin.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // A bejelentést zároljuk a párhuzamos feldolgozás elkerülésére.
    $stmt = $pdo->prepare("
        SELECT *
        FROM esemeny_bejelentesek
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$bejelentesId]);
    $bejelentes = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bejelentes) {
        throw new RuntimeException("A bejelentés nem található.");
    }

    if ($bejelentes["allapot"] !== "elfogadva") {
        throw new RuntimeException(
            "Csak elfogadott bejelentésből készíthető riasztás."
        );
    }

    if (!empty($bejelentes["riasztas_id"])) {
        throw new RuntimeException(
            "Ehhez a bejelentéshez már tartozik hivatalos riasztás."
        );
    }

    // Egyedi riasztási szám.
    $riasztasSzam = "WEB-" . date("Ymd-His") . "-"
        . strtoupper(bin2hex(random_bytes(3)));

    $datum = date("Y-m-d");
    $ido = date("H:i:s");

    // Hivatalos riasztás létrehozása a meglévő riasztási táblában.
    $stmt = $pdo->prepare("
        INSERT INTO riasztasok (
            riasztas_szam,
            datum,
            riasztasi_ido,
            helyszin,
            telepules,
            esemeny_tipus,
            leiras,
            jelzes_modja,
            allapot,
            letrehozta
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uj', ?)
    ");

    $stmt->execute([
        $riasztasSzam,
        $datum,
        $ido,
        $bejelentes["helyszin"],
        $bejelentes["telepules"],
        $bejelentes["esemeny_tipus"],
        $bejelentes["leiras"],
        "Webes bejelentés",
        (int)$felhasznalo["id"]
    ]);

    $riasztasId = (int)$pdo->lastInsertId();

    // A bejelentés és az új riasztás összekapcsolása.
    $stmt = $pdo->prepare("
        UPDATE esemeny_bejelentesek
        SET riasztas_id = ?,
            admin_megjegyzes = ?
        WHERE id = ?
          AND riasztas_id IS NULL
    ");

    $megjegyzes = trim((string)($bejelentes["admin_megjegyzes"] ?? ""));
    $ujMegjegyzes = "Hivatalos riasztás létrehozva: " . $riasztasSzam;

    if ($megjegyzes !== "") {
        $ujMegjegyzes = $megjegyzes . "\n" . $ujMegjegyzes;
    }

    $stmt->execute([
        $riasztasId,
        $ujMegjegyzes,
        $bejelentesId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            "A bejelentés összekapcsolása nem sikerült."
        );
    }

    $pdo->commit();

    $_SESSION["uzenet"] =
        "A hivatalos riasztás létrejött. Azonosító: " . $riasztasSzam;

    header("Location: riasztasok.php");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION["hiba"] = $e instanceof RuntimeException
        ? $e->getMessage()
        : "Nem sikerült létrehozni a riasztást. Ellenőrizd a riasztások adatbázismezőit.";

    header(
        "Location: esemeny_bejelentesek_admin.php?id="
        . (int)$bejelentesId
    );
    exit;
}
```
