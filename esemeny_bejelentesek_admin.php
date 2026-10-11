
<?php
require_once "db.php";
require_once "auth.php";

bejelentkezesSzukseges();

$felhasznalo = aktualisFelhasznalo();
$szerepkor = $felhasznalo["szerepkor"] ?? "";

if (!in_array($szerepkor, ["admin", "parancsnok"], true)) {
    http_response_code(403);
    exit("Nincs jogosultságod a bejelentések kezeléséhez.");
}

if (empty($_SESSION["csrf_esemeny"])) {
    $_SESSION["csrf_esemeny"] = bin2hex(random_bytes(32));
}

function e($ertek): string
{
    return htmlspecialchars(
        (string)($ertek ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function allapotNev(string $allapot): string
{
    return match ($allapot) {
        "uj" => "Új",
        "ellenorzes_alatt" => "Ellenőrzés alatt",
        "elfogadva" => "Elfogadva",
        "elutasitva" => "Elutasítva",
        default => "Ismeretlen"
    };
}

$hiba = "";
$uzenet = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_esemeny"], $token)
    ) {
        $hiba = "A munkamenet lejárt vagy érvénytelen. Frissítsd az oldalt.";
    } else {
        $bejelentesId = filter_input(
            INPUT_POST,
            "bejelentes_id",
            FILTER_VALIDATE_INT
        );

        $muvelet = $_POST["muvelet"] ?? "";
        $adminMegjegyzes = trim($_POST["admin_megjegyzes"] ?? "");

        if (!$bejelentesId || $bejelentesId < 1) {
            $hiba = "Érvénytelen bejelentésazonosító.";
        } elseif (mb_strlen($adminMegjegyzes, "UTF-8") > 5000) {
            $hiba = "Az admin megjegyzés legfeljebb 5000 karakter lehet.";
        } elseif (!in_array(
            $muvelet,
            ["ellenorzes_alatt", "elfogadva", "elutasitva"],
            true
        )) {
            $hiba = "Érvénytelen művelet.";
        } else {
            try {
                $ellenorzes = $pdo->prepare(
                    "SELECT id, allapot
                     FROM esemeny_bejelentesek
                     WHERE id = ?
                     LIMIT 1"
                );
                $ellenorzes->execute([$bejelentesId]);
                $bejelentes = $ellenorzes->fetch(PDO::FETCH_ASSOC);

                if (!$bejelentes) {
                    $hiba = "A bejelentés nem található.";
                } else {
                    $mentes = $pdo->prepare(
                        "UPDATE esemeny_bejelentesek
                         SET allapot = ?,
                             admin_megjegyzes = ?,
                             feldolgozta = ?
                         WHERE id = ?"
                    );

                    $mentes->execute([
                        $muvelet,
                        $adminMegjegyzes !== "" ? $adminMegjegyzes : null,
                        (int)$felhasznalo["id"],
                        $bejelentesId
                    ]);

                    $uzenet = "A bejelentés módosítása sikeres.";
                }
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $hiba = "Nem sikerült menteni a módosításokat.";
            }
        }
    }
}

$szuro = $_GET["allapot"] ?? "mind";
$engedelyezettSzurok = [
    "mind",
    "uj",
    "ellenorzes_alatt",
    "elfogadva",
    "elutasitva"
];

if (!in_array($szuro, $engedelyezettSzurok, true)) {
    $szuro = "mind";
}

$kereses = trim($_GET["kereses"] ?? "");
$kivalasztottId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

$oldal = max(1, (int)($_GET["oldal"] ?? 1));
$oldalMeret = 15;

$feltetelek = [];
$parameterek = [];

if ($szuro !== "mind") {
    $feltetelek[] = "allapot = ?";
    $parameterek[] = $szuro;
}

if ($kereses !== "") {
    $feltetelek[] = "(
        esemeny_tipus LIKE ?
        OR helyszin LIKE ?
        OR telepules LIKE ?
        OR bejelento_nev LIKE ?
    )";

    $minta = "%" . $kereses . "%";

    array_push(
        $parameterek,
        $minta,
        $minta,
        $minta,
        $minta
    );
}

$where = $feltetelek
    ? "WHERE " . implode(" AND ", $feltetelek)
    : "";

$osszesDb = 0;
$bejelentesek = [];
$kivalasztott = null;

try {
    $st = $pdo->prepare(
        "SELECT COUNT(*)
         FROM esemeny_bejelentesek
         $where"
    );
    $st->execute($parameterek);
    $osszesDb = (int)$st->fetchColumn();

    $oldalakSzama = max(
        1,
        (int)ceil($osszesDb / $oldalMeret)
    );

    $oldal = min($oldal, $oldalakSzama);
    $offset = ($oldal - 1) * $oldalMeret;

    $st = $pdo->prepare(
        "SELECT *
         FROM esemeny_bejelentesek
         $where
         ORDER BY
            CASE allapot
                WHEN 'uj' THEN 1
                WHEN 'ellenorzes_alatt' THEN 2
                WHEN 'elfogadva' THEN 3
                WHEN 'elutasitva' THEN 4
                ELSE 5
            END,
            letrehozva DESC
         LIMIT $oldalMeret OFFSET $offset"
    );
    $st->execute($parameterek);
    $bejelentesek = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($kivalasztottId) {
        $st = $pdo->prepare(
            "SELECT *
             FROM esemeny_bejelentesek
             WHERE id = ?
             LIMIT 1"
        );
        $st->execute([$kivalasztottId]);
        $kivalasztott = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $hiba = "Nem sikerült betölteni a bejelentéseket. Ellenőrizd az adatbázist.";
}

$statisztika = [
    "uj" => 0,
    "ellenorzes_alatt" => 0,
    "elfogadva" => 0,
    "elutasitva" => 0
];

try {
    $st = $pdo->query(
        "SELECT allapot, COUNT(*) AS darab
         FROM esemeny_bejelentesek
         GROUP BY allapot"
    );

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $sor) {
        if (isset($statisztika[$sor["allapot"]])) {
            $statisztika[$sor["allapot"]] = (int)$sor["darab"];
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
}

function urlParameterek(array $ujErtekek = []): string
{
    $adatok = array_merge($_GET, $ujErtekek);

    foreach ($adatok as $kulcs => $ertek) {
        if ($ertek === null || $ertek === "") {
            unset($adatok[$kulcs]);
        }
    }

    return "?" . http_build_query($adatok);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bejelentések kezelése – SZÖTE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f5f5f3;
            color: #18181a;
            font-family: Inter, Arial, sans-serif;
        }
        .admin-head {
            background: #0d0d0f;
            color: #fff;
            border-bottom: 3px solid #c8102e;
        }
        .admin-head-inner {
            max-width: 1400px;
            min-height: 74px;
            margin: auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            color: #fff;
            font-weight: 800;
            font-size: 20px;
            text-decoration: none;
        }
        .brand span { color: #f04459; }
        .back-link { color: #fff; text-decoration: none; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 32px auto; padding: 0 24px 60px; }
        h1 { margin: 0 0 8px; font-size: clamp(25px, 4vw, 36px); }
        .muted { color: #707075; line-height: 1.6; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin: 24px 0;
        }
        .stat, .panel {
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 13px;
            box-shadow: 0 8px 24px rgba(0,0,0,.045);
        }
        .stat { padding: 18px; }
        .stat strong { display: block; font-size: 27px; margin-bottom: 4px; }
        .stat span { color: #707075; font-size: 13px; }
        .panel { padding: 22px; margin-bottom: 22px; }
        .filters {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 12px;
        }
        input, select, textarea {
            width: 100%;
            border: 1px solid #d8d8d8;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
            font: inherit;
        }
        textarea { min-height: 115px; resize: vertical; }
        label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 7px; }
        .btn {
            border: 0;
            border-radius: 8px;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .primary { background: #c8102e; color: #fff; }
        .dark { background: #202024; color: #fff; }
        .light { background: #f0f0f0; color: #222; }
        .danger { background: #fff0f0; color: #a10c24; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 13px 12px; text-align: left; border-bottom: 1px solid #ececec; font-size: 13px; vertical-align: top; }
        th { color: #666; background: #fafafa; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        td strong { display: block; margin-bottom: 4px; }
        .status { display: inline-block; border-radius: 20px; padding: 5px 9px; font-size: 11px; font-weight: 800; background: #eee; }
        .uj { background: #fff0d4; color: #805000; }
        .ellenorzes_alatt { background: #e9efff; color: #254caa; }
        .elfogadva { background: #e5f7e9; color: #17652c; }
        .elutasitva { background: #ffe8e8; color: #a10c24; }
        .alert { border-radius: 9px; padding: 14px 16px; margin: 18px 0; line-height: 1.5; }
        .error { background: #fff0f0; color: #a10c24; border: 1px solid #edb8c0; }
        .success { background: #eef9f1; color: #17652c; border: 1px solid #b8dfc2; }
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .detail-item { padding: 13px; background: #f8f8f8; border-radius: 8px; overflow-wrap: anywhere; }
        .detail-item small { display: block; color: #777; margin-bottom: 6px; }
        .detail-item p { margin: 0; line-height: 1.6; white-space: pre-wrap; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
        .pagination { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        .pagination a { padding: 8px 12px; background: #f0f0f0; color: #222; text-decoration: none; border-radius: 7px; }
        .pagination .current { background: #c8102e; color: #fff; }
        @media(max-width: 800px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .wrap { padding: 0 14px 40px; }
            .panel { padding: 16px; }
            .admin-head-inner { padding: 12px 16px; }
        }
    </style>
</head>
<body>

<header class="admin-head">
    <div class="admin-head-inner">
        <a class="brand" href="admin.php">SZ<span>Ö</span>TE · Bejelentések</a>
        <a class="back-link" href="admin.php">← Vissza az adminhoz</a>
    </div>
</header>

<main class="wrap">
    <h1>Nyilvános eseménybejelentések</h1>
    <p class="muted">Ellenőrizd a beérkező bejelentéseket, és rögzítsd a döntést.</p>

    <?php if ($hiba !== ""): ?>
        <div class="alert error"><?= e($hiba) ?></div>
    <?php endif; ?>

    <?php if ($uzenet !== ""): ?>
        <div class="alert success"><?= e($uzenet) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat"><strong><?= $statisztika["uj"] ?></strong><span>Új bejelentés</span></div>
        <div class="stat"><strong><?= $statisztika["ellenorzes_alatt"] ?></strong><span>Ellenőrzés alatt</span></div>
        <div class="stat"><strong><?= $statisztika["elfogadva"] ?></strong><span>Elfogadva</span></div>
        <div class="stat"><strong><?= $statisztika["elutasitva"] ?></strong><span>Elutasítva</span></div>
    </section>

    <p class="muted">Az elfogadás itt még csak a bejelentés állapotát módosítja; hivatalos riasztást nem hoz létre.</p>

    <?php if ($kivalasztott): ?>
        <section class="panel" id="reszletek">
            <h2>Bejelentés #<?= (int)$kivalasztott["id"] ?> – részletek</h2>
            <p>
                <span class="status <?= e($kivalasztott["allapot"]) ?>">
                    <?= e(allapotNev($kivalasztott["allapot"])) ?>
                </span>
            </p>

            <div class="detail-grid">
                <div class="detail-item"><small>Esemény típusa</small><p><?= e($kivalasztott["esemeny_tipus"]) ?></p></div>
                <div class="detail-item"><small>Település</small><p><?= e($kivalasztott["telepules"]) ?></p></div>
                <div class="detail-item"><small>Helyszín</small><p><?= e($kivalasztott["helyszin"]) ?></p></div>
                <div class="detail-item"><small>Beküldés ideje</small><p><?= e($kivalasztott["letrehozva"]) ?></p></div>
                <div class="detail-item"><small>Bejelentő neve</small><p><?= e($kivalasztott["bejelento_nev"] ?: "Nem adta meg") ?></p></div>
                <div class="detail-item"><small>Telefonszám</small><p><?= e($kivalasztott["bejelento_telefon"] ?: "Nem adta meg") ?></p></div>
                <div class="detail-item"><small>E-mail cím</small><p><?= e($kivalasztott["bejelento_email"] ?: "Nem adta meg") ?></p></div>
                <div class="detail-item"><small>Leírás</small><p><?= e($kivalasztott["leiras"] ?: "Nem adott meg leírást.") ?></p></div>
            </div>

            <form method="post" action="esemeny_bejelentesek_admin.php?id=<?= (int)$kivalasztott["id"] ?>" style="margin-top:24px">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_esemeny"]) ?>">
                <input type="hidden" name="bejelentes_id" value="<?= (int)$kivalasztott["id"] ?>">

                <label for="admin_megjegyzes">Admin megjegyzés</label>
                <textarea id="admin_megjegyzes" name="admin_megjegyzes" maxlength="5000" placeholder="Például: telefonon ellenőrizve, további információ szükséges..."><?= e($kivalasztott["admin_megjegyzes"]) ?></textarea>

                <div class="actions">
                    <button class="btn dark" name="muvelet" value="ellenorzes_alatt">🔎 Ellenőrzés alatt</button>
                    <button class="btn primary" name="muvelet" value="elfogadva">✓ Elfogadás</button>
                    <button class="btn danger" name="muvelet" value="elutasitva" onclick="return confirm('Biztosan elutasítod ezt a bejelentést?')">✕ Elutasítás</button>
                    <a class="btn light" href="esemeny_bejelentesek_admin.php">Vissza a listához</a>
                </div>
            </form>

            <p class="muted">Az elfogadás itt még csak a bejelentés állapotát módosítja; hivatalos riasztást nem hoz létre.</p>
        </section>
    <?php endif; ?>

    <section class="panel">
        <form method="get" class="filters">
            <div>
                <label for="allapot">Állapot</label>
                <select name="allapot" id="allapot">
                    <option value="mind" <?= $szuro === "mind" ? "selected" : "" ?>>Minden állapot</option>
                    <option value="uj" <?= $szuro === "uj" ? "selected" : "" ?>>Új</option>
                    <option value="ellenorzes_alatt" <?= $szuro === "ellenorzes_alatt" ? "selected" : "" ?>>Ellenőrzés alatt</option>
                    <option value="elfogadva" <?= $szuro === "elfogadva" ? "selected" : "" ?>>Elfogadva</option>
                    <option value="elutasitva" <?= $szuro === "elutasitva" ? "selected" : "" ?>>Elutasítva</option>
                </select>
            </div>
            <div>
                <label for="kereses">Keresés</label>
                <input id="kereses" type="search" name="kereses" value="<?= e($kereses) ?>" placeholder="Esemény, helyszín, település, bejelentő">
            </div>
            <div style="align-self:end">
                <button class="btn primary" type="submit">Keresés</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Bejelentések (<?= $osszesDb ?>)</h2>

        <?php if (!$bejelentesek): ?>
            <p class="muted">Nincs a szűrésnek megfelelő bejelentés.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Azonosító</th>
                        <th>Esemény</th>
                        <th>Helyszín</th>
                        <th>Beküldés</th>
                        <th>Állapot</th>
                        <th>Művelet</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bejelentesek as $sor): ?>
                        <tr>
                            <td>#<?= (int)$sor["id"] ?></td>
                            <td>
                                <strong><?= e($sor["esemeny_tipus"]) ?></strong>
                                <?= e($sor["bejelento_nev"] ?: "Névtelen bejelentő") ?>
                            </td>
                            <td>
                                <strong><?= e($sor["telepules"]) ?></strong>
                                <?= e($sor["helyszin"]) ?>
                            </td>
                            <td><?= e($sor["letrehozva"]) ?></td>
                            <td>
                                <span class="status <?= e($sor["allapot"]) ?>">
                                    <?= e(allapotNev($sor["allapot"])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn primary" href="esemeny_bejelentesek_admin.php<?= e(urlParameterek(["id" => (int)$sor["id"]])) ?>#reszletek">Megnyitás</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $oldalakSzama = max(1, (int)ceil($osszesDb / $oldalMeret));
            ?>
            <?php if ($oldalakSzama > 1): ?>
                <nav class="pagination" aria-label="Lapozás">
                    <?php for ($i = 1; $i <= $oldalakSzama; $i++): ?>
                        <a
                            class="<?= $i === $oldal ? "current" : "" ?>"
                            href="esemeny_bejelentesek_admin.php<?= e(urlParameterek(["oldal" => $i, "id" => null])) ?>"
                        ><?= $i ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
```
