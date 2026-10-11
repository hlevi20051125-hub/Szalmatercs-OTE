
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Ellenőrzi, hogy van-e bejelentkezett felhasználó.
 */
function bejelentkezve(): bool
{
    return !empty($_SESSION['felhasznalo'])
        && is_array($_SESSION['felhasznalo']);
}

/**
 * Visszaadja a bejelentkezett felhasználó adatait.
 */
function aktualisFelhasznalo(): ?array
{
    return bejelentkezve()
        ? $_SESSION['felhasznalo']
        : null;
}

/**
 * Megvizsgálja, hogy a tag beosztása alapján admin-e.
 *
 * A tagok táblában a felhasznalo_id kapcsolja össze
 * a tagot a felhasznalok táblában szereplő felhasználóval.
 */
function beosztasAlapjanAdmin(array $felhasznalo): bool
{
    // Ha a beosztás már szerepel a munkamenetben.
    $beosztas = trim((string)($felhasznalo['beosztas'] ?? ''));

    if ($beosztas !== '' && stripos($beosztas, 'admin') !== false) {
        return true;
    }

    // A bejelentkezéskor elmentett felhasználói azonosító.
    $felhasznaloId = (int)($felhasznalo['id'] ?? 0);

    if ($felhasznaloId <= 0) {
        return false;
    }

    try {
        // A db.php betöltése, ha a kapcsolat még nem elérhető.
        if (
            !isset($GLOBALS['pdo'])
            || !($GLOBALS['pdo'] instanceof PDO)
        ) {
            require_once __DIR__ . '/db.php';
        }

        // A db.php-ban létrehozott PDO-kapcsolat használata.
        $kapcsolat = $GLOBALS['pdo'] ?? ($pdo ?? null);

        if (!($kapcsolat instanceof PDO)) {
            return false;
        }

        $lekerdezes = $kapcsolat->prepare(
            'SELECT beosztas
             FROM tagok
             WHERE felhasznalo_id = :felhasznalo_id
             LIMIT 1'
        );

        $lekerdezes->execute([
            'felhasznalo_id' => $felhasznaloId
        ]);

        $beosztas = (string)($lekerdezes->fetchColumn() ?: '');

        // Az „admin”, „adminisztrátor” és ezek hosszabb
        // beosztásban szereplő változatai is jogosultságot adnak.
        return stripos($beosztas, 'admin') !== false;

    } catch (Throwable $e) {
        // Hiba esetén nem adunk adminjogot.
        return false;
    }
}

/**
 * Jogosultság ellenőrzése.
 *
 * Az eredeti szerepkörök megmaradnak.
 * Az admin beosztású tag adminjogot is kap.
 */
function jogosultsag(string $szerepkor): bool
{
    $felhasznalo = aktualisFelhasznalo();

    if (!$felhasznalo) {
        return false;
    }

    $aktualisSzerepkor = strtolower(
        trim((string)($felhasznalo['szerepkor'] ?? ''))
    );

    $ellenorzottSzerepkor = strtolower(trim($szerepkor));

    // Az adatbázisban megadott eredeti szerepkör.
    if ($aktualisSzerepkor === $ellenorzottSzerepkor) {
        return true;
    }

    // Az admin beosztás külön is adminjogot biztosít.
    if ($ellenorzottSzerepkor === 'admin') {
        return beosztasAlapjanAdmin($felhasznalo);
    }

    return false;
}

/**
 * Bejelentkezés megkövetelése.
 */
function bejelentkezesSzukseges(): void
{
    if (!bejelentkezve()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Csak admin jogosultsággal érhető el.
 */
function adminSzukseges(): void
{
    bejelentkezesSzukseges();

    if (!jogosultsag('admin')) {
        http_response_code(403);
        exit('Nincs jogosultságod ehhez a művelethez.');
    }
}

/**
 * Admin vagy parancsnok jogosultság szükséges.
 */
function adminVagyParancsnok(): void
{
    bejelentkezesSzukseges();

    if (
        !jogosultsag('admin')
        && !jogosultsag('parancsnok')
    ) {
        http_response_code(403);
        exit('Nincs jogosultságod ehhez a művelethez.');
    }
}

?>
