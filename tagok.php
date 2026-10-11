<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

$oldalCim = 'Tagok';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function datumFormaz($d)
{
    return $d
        ? date('Y. m. d.', strtotime($d))
        : '—';
}

$q = trim($_GET['q'] ?? '');
$aktiv = $_GET['aktiv'] ?? 'mind';

$oldal = max(1, (int)($_GET['oldal'] ?? 1));
$meret = 15;

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| Keresés
|--------------------------------------------------------------------------
*/

if ($q !== '') {

    $where[] = "
        (
            t.teljes_nev LIKE ?
            OR t.becenev LIKE ?
            OR t.beosztas LIKE ?
            OR t.lakhely LIKE ?
            OR t.kepzettseg LIKE ?
            OR t.telefonszam LIKE ?
            OR t.email LIKE ?
        )
    ";

    for ($i = 0; $i < 7; $i++) {
        $params[] = "%{$q}%";
    }
}


/*
|--------------------------------------------------------------------------
| Aktív / inaktív szűrés
|--------------------------------------------------------------------------
*/

if ($aktiv === 'aktiv') {

    $where[] = 't.aktiv = 1';

} elseif ($aktiv === 'inaktiv') {

    $where[] = 't.aktiv = 0';
}


$w = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';


/*
|--------------------------------------------------------------------------
| Összes találat
|--------------------------------------------------------------------------
*/

$st = $pdo->prepare("
    SELECT COUNT(*)
    FROM tagok t
    $w
");

$st->execute($params);

$osszes = (int)$st->fetchColumn();

$oldalDb = max(
    1,
    (int)ceil($osszes / $meret)
);

$oldal = min(
    $oldal,
    $oldalDb
);

$offset = ($oldal - 1) * $meret;


/*
|--------------------------------------------------------------------------
| Tagok lekérése
|--------------------------------------------------------------------------
*/

$st = $pdo->prepare("
    SELECT
        t.*
    FROM tagok t

    $w

    ORDER BY
        t.aktiv DESC,
        t.teljes_nev ASC

    LIMIT $meret
    OFFSET $offset
");

$st->execute($params);

$tagok = $st->fetchAll();


/*
|--------------------------------------------------------------------------
| Statisztikák
|--------------------------------------------------------------------------
*/

$aktivDb = (int)$pdo
    ->query("SELECT COUNT(*) FROM tagok WHERE aktiv = 1")
    ->fetchColumn();

$inaktivDb = (int)$pdo
    ->query("SELECT COUNT(*) FROM tagok WHERE aktiv = 0")
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| Meghívó üzenetek
|--------------------------------------------------------------------------
*/

$meghivo = $_GET['meghivo'] ?? '';

require 'header.php';

?>

<div class="page-title">

    <div>

        <span class="eyebrow">
            ÁLLOMÁNY
        </span>

        <h1>
            Tagok
        </h1>

        <p class="muted">
            Az egyesület nyilvántartott tagjai.
        </p>

    </div>


    <?php if (jogosultsag('admin') || jogosultsag('parancsnok')): ?>

        <a
            href="tag_uj.php"
            class="btn btn-primary"
        >
            + Új tag
        </a>

    <?php endif; ?>

</div>


<?php if ($meghivo === 'sikeres'): ?>

    <div class="alert alert-success">
        ✉️ A regisztrációs meghívó sikeresen létrejött.
    </div>

<?php elseif ($meghivo === 'mar_van'): ?>

    <div class="alert alert-warning">
        ⚠️ Ennek a tagnak már van aktív regisztrációs meghívója.
    </div>

<?php elseif ($meghivo === 'hiba'): ?>

    <div class="alert alert-danger">
        ❌ Hiba történt a meghívó létrehozásakor.
    </div>

<?php endif; ?>


<div class="stats-grid compact">

    <div class="stat-card">

        <span>
            Összes
        </span>

        <strong>
            <?= $osszes ?>
        </strong>

    </div>


    <div class="stat-card">

        <span>
            Aktív
        </span>

        <strong>
            <?= $aktivDb ?>
        </strong>

    </div>


    <div class="stat-card">

        <span>
            Inaktív
        </span>

        <strong>
            <?= $inaktivDb ?>
        </strong>

    </div>

</div>


<div class="card filters">

    <form
        method="get"
        class="filter-row"
    >

        <input
            name="q"
            value="<?= e($q) ?>"
            placeholder="Keresés név, beosztás, lakhely..."
        >


        <select name="aktiv">

            <option
                value="mind"
                <?= $aktiv === 'mind' ? 'selected' : '' ?>
            >
                Minden tag
            </option>

            <option
                value="aktiv"
                <?= $aktiv === 'aktiv' ? 'selected' : '' ?>
            >
                Aktív
            </option>

            <option
                value="inaktiv"
                <?= $aktiv === 'inaktiv' ? 'selected' : '' ?>
            >
                Inaktív
            </option>

        </select>


        <button
            type="submit"
            class="btn btn-secondary"
        >
            Szűrés
        </button>


        <a
            href="tagok.php"
            class="btn btn-light"
        >
            Alaphelyzet
        </a>

    </form>

</div>


<div class="card table-card">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>
                        Név
                    </th>

                    <th>
                        Becenév
                    </th>

                    <th>
                        Beosztás
                    </th>

                    <th>
                        Elérhetőség
                    </th>

                    <th>
                        Belépés
                    </th>

                    <th>
                        Fiók
                    </th>

                    <th>
                        Állapot
                    </th>

                    <th>
                        Művelet
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($tagok as $t): ?>

                <tr>

                    <td>

                        <strong>
                            <?= e($t['teljes_nev']) ?>
                        </strong>

                    </td>


                    <td>

                        <?= e(
                            $t['becenev'] ?: '—'
                        ) ?>

                    </td>


                    <td>

                        <?= e(
                            $t['beosztas'] ?: '—'
                        ) ?>

                    </td>


                    <td>

                        <?php if (!empty($t['telefonszam'])): ?>

                            <div>
                                📞 <?= e($t['telefonszam']) ?>
                            </div>

                        <?php endif; ?>


                        <?php if (!empty($t['email'])): ?>

                            <div>
                                ✉️ <?= e($t['email']) ?>
                            </div>

                        <?php endif; ?>


                        <?php if (
                            empty($t['telefonszam'])
                            && empty($t['email'])
                        ): ?>

                            —

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= datumFormaz(
                            $t['belepes_datum']
                        ) ?>

                    </td>


                    <td>

                        <?php if (!empty($t['felhasznalo_id'])): ?>

                            <span class="badge badge-success">
                                ✓ Van fiók
                            </span>

                        <?php else: ?>

                            <span class="badge badge-muted">
                                Nincs fiók
                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <span
                            class="badge <?= $t['aktiv']
                                ? 'badge-success'
                                : 'badge-muted'
                            ?>"
                        >
                            <?= $t['aktiv']
                                ? 'Aktív'
                                : 'Inaktív'
                            ?>
                        </span>

                    </td>


                    <td>

                        <div class="table-actions">


                            <?php if (
                                (jogosultsag('Admin')
                                || jogosultsag('parancsnok'))
                                && (int)$t['aktiv'] === 1
                            ): ?>

                                <?php if (empty($t['felhasznalo_id'])): ?>

                                    <?php if (!empty($t['email'])): ?>

                                        <a
                                            href="regisztracios_meghivo.php?tag_id=<?= (int)$t['id'] ?>"
                                            class="btn btn-small btn-primary"
                                            onclick="return confirm('Biztosan létrehozod a regisztrációs meghívót ennek a tagnak?');"
                                        >
                                            ✉️ Meghívó
                                        </a>

                                    <?php else: ?>

                                        <span
                                            class="badge badge-muted"
                                            title="Nincs e-mail cím"
                                        >
                                            Nincs e-mail
                                        </span>

                                    <?php endif; ?>

                                <?php endif; ?>

                            <?php endif; ?>


                            <a
                                href="tag_szerkeszt.php?id=<?= (int)$t['id'] ?>"
                                class="btn btn-small btn-secondary"
                            >
                                Szerkesztés
                            </a>


                            <?php if (jogosultsag('admin')): ?>

                                <a
                                    href="tag_torles.php?id=<?= (int)$t['id'] ?>"
                                    class="btn btn-small btn-danger"
                                    onclick="return confirm('Biztosan törölni szeretnéd ezt a tagot?');"
                                >
                                    Törlés
                                </a>

                            <?php endif; ?>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$tagok): ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty-state"
                    >
                        Nincs találat.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php if ($oldalDb > 1): ?>

    <div class="pagination">

        <?php for ($i = 1; $i <= $oldalDb; $i++): ?>

            <a
                class="<?= $i === $oldal ? 'active' : '' ?>"
                href="?<?= http_build_query([
                    'q' => $q,
                    'aktiv' => $aktiv,
                    'oldal' => $i
                ]) ?>"
            >
                <?= $i ?>
            </a>

        <?php endfor; ?>

    </div>

<?php endif; ?>


<?php require 'footer.php'; ?>