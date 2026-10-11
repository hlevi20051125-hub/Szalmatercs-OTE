<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Ezt az oldalt csak adminisztrátor vagy parancsnok kezelheti.
adminVagyParancsnok();

if (empty($_SESSION['csrf_hirek'])) {
    $_SESSION['csrf_hirek'] = bin2hex(random_bytes(32));
}

function hirekE(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hirekUzenet(string $tipus, string $szoveg): never
{
    $_SESSION['hirek_uzenet'] = ['tipus' => $tipus, 'szoveg' => $szoveg];
    header('Location: admin_hirek.php');
    exit;
}

$hiba = '';
$siker = '';
$uzenet = $_SESSION['hirek_uzenet'] ?? null;
unset($_SESSION['hirek_uzenet']);

$szerkesztId = filter_input(INPUT_GET, 'szerkeszt', FILTER_VALIDATE_INT) ?: 0;
$szerkesztendo = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)$_SESSION['csrf_hirek'], $token)) {
            throw new RuntimeException('A munkamenet lejárt vagy az űrlap érvénytelen. Frissítsd az oldalt, majd próbáld újra.');
        }

        $muvelet = (string)($_POST['muvelet'] ?? 'mentes');

        if ($muvelet === 'torles') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new RuntimeException('A törlendő hír azonosítója nem megfelelő.');
            }
            $stmt = $pdo->prepare('DELETE FROM hirek WHERE id = ?');
            $stmt->execute([$id]);
            hirekUzenet('siker', $stmt->rowCount() ? 'A bejegyzést töröltük.' : 'A bejegyzés már nem található.');
        }

        if ($muvelet === 'mentes') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            $cim = trim((string)($_POST['cim'] ?? ''));
            $rovidLeiras = trim((string)($_POST['rovid_leiras'] ?? ''));
            $tartalom = trim((string)($_POST['tartalom'] ?? ''));
            $kep = trim((string)($_POST['kep'] ?? ''));
            $aktiv = isset($_POST['aktiv']) ? 1 : 0;

            if ($cim === '' || $rovidLeiras === '' || $tartalom === '') {
                throw new RuntimeException('Kérlek, töltsd ki a címet, a rövid leírást és a teljes tartalmat.');
            }
            if (mb_strlen($cim) > 200) {
                throw new RuntimeException('A cím legfeljebb 200 karakter lehet.');
            }
            if (mb_strlen($rovidLeiras) > 500) {
                throw new RuntimeException('A rövid leírás legfeljebb 500 karakter lehet.');
            }
            if (mb_strlen($kep) > 255) {
                throw new RuntimeException('A kép elérési útja legfeljebb 255 karakter lehet.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE hirek SET cim = ?, rovid_leiras = ?, tartalom = ?, kep = ?, aktiv = ?, modositva = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$cim, $rovidLeiras, $tartalom, ($kep !== '' ? $kep : null), $aktiv, $id]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM hirek WHERE id = ?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('A szerkesztendő bejegyzés nem található.');
                    }
                }
                hirekUzenet('siker', 'A bejegyzést elmentettük.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO hirek (cim, rovid_leiras, tartalom, kep, aktiv, letrehozva, modositva) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $stmt->execute([$cim, $rovidLeiras, $tartalom, ($kep !== '' ? $kep : null), $aktiv]);
                hirekUzenet('siker', 'Az új bejegyzést létrehoztuk.');
            }
        }

        throw new RuntimeException('Ismeretlen művelet.');
    }

    if ($szerkesztId > 0) {
        $stmt = $pdo->prepare('SELECT id, cim, rovid_leiras, tartalom, kep, aktiv FROM hirek WHERE id = ?');
        $stmt->execute([$szerkesztId]);
        $szerkesztendo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$szerkesztendo) {
            $hiba = 'A kiválasztott bejegyzés nem található.';
            $szerkesztId = 0;
        }
    }

    $hirek = $pdo->query('SELECT id, cim, rovid_leiras, kep, aktiv, letrehozva, modositva FROM hirek ORDER BY letrehozva DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $hiba = $e instanceof PDOException
            ? 'Az adatbázis nem tudta elmenteni a módosítást. Ellenőrizd, hogy a hirek tábla oszlopai megegyeznek-e a képen láthatókkal.'
            : $e->getMessage();
    } else {
        $hiba = 'Nem sikerült betölteni a híreket. Ellenőrizd az adatbázis-kapcsolatot és a hirek tábla elérhetőségét.';
    }
    $hirek = $hirek ?? [];
}

$form = [
    'id' => (int)($szerkesztendo['id'] ?? 0),
    'cim' => (string)($szerkesztendo['cim'] ?? ''),
    'rovid_leiras' => (string)($szerkesztendo['rovid_leiras'] ?? ''),
    'tartalom' => (string)($szerkesztendo['tartalom'] ?? ''),
    'kep' => (string)($szerkesztendo['kep'] ?? ''),
    'aktiv' => (int)($szerkesztendo['aktiv'] ?? 1),
];
?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hírek és események kezelése – SZÖTE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--red:#c82026;--red-dark:#a8171d;--ink:#171717;--muted:#666;--line:#e6e6e6;--bg:#f4f5f7;--white:#fff}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,Arial,sans-serif;line-height:1.55}
        a{color:inherit}.topbar{background:#151515;color:#fff;border-bottom:4px solid var(--red)}.topbar-inner{max-width:1180px;margin:auto;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .brand{font-weight:800;letter-spacing:.5px;text-decoration:none}.brand-mark{display:inline-grid;place-items:center;width:38px;height:38px;background:var(--red);border-radius:8px;margin-right:9px}
        .navlinks{display:flex;gap:10px;flex-wrap:wrap}.navlinks a{font-size:14px;text-decoration:none;color:#fff;padding:8px 11px;border-radius:7px}.navlinks a:hover{background:#333}
        .wrap{max-width:1180px;margin:32px auto;padding:0 22px}.page-title{margin:0 0 6px;font-size:clamp(26px,4vw,36px);letter-spacing:-1px}.intro{color:var(--muted);margin:0 0 24px}
        .layout{display:grid;grid-template-columns:minmax(0, .95fr) minmax(0,1.25fr);gap:22px;align-items:start}.panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 5px 22px #15151508}.panel h2{margin:0 0 18px;font-size:20px}
        .field{margin-bottom:16px}.field label{display:block;font-weight:700;font-size:14px;margin-bottom:6px}.field input,.field textarea{width:100%;font:inherit;padding:11px 12px;border:1px solid #d5d5d5;border-radius:8px;background:#fff}.field input:focus,.field textarea:focus{outline:2px solid #c8202633;border-color:var(--red)}.field textarea{min-height:105px;resize:vertical}.field .hint{font-size:12px;color:var(--muted);margin-top:5px}
        .checkrow{display:flex;gap:10px;align-items:center;margin:5px 0 18px}.checkrow input{width:18px;height:18px;accent-color:var(--red)}.checkrow label{font-weight:600}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:8px;padding:11px 15px;font:700 14px Inter,Arial,sans-serif;text-decoration:none;cursor:pointer}.btn-primary{background:var(--red);color:#fff}.btn-primary:hover{background:var(--red-dark)}.btn-light{background:#eee;color:#222}.btn-danger{background:#fff0f0;color:#a8171d}.btn-small{padding:7px 10px;font-size:12px}.form-actions{display:flex;gap:9px;flex-wrap:wrap}
        .alert{padding:13px 15px;border-radius:9px;margin:0 0 18px;font-size:14px}.alert-error{background:#fff0f0;border:1px solid #f0c7c7;color:#8d171c}.alert-success{background:#edfaef;border:1px solid #c4e8ca;color:#1e6b2c}
        .news-list{display:grid;gap:12px}.news-item{border:1px solid var(--line);border-radius:10px;padding:15px}.news-item-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.news-item h3{margin:0 0 5px;font-size:16px;overflow-wrap:anywhere}.news-item p{margin:0;color:var(--muted);font-size:13px;white-space:pre-line;overflow-wrap:anywhere}.status{display:inline-block;flex-shrink:0;border-radius:30px;padding:4px 9px;font-size:11px;font-weight:800}.status-on{background:#e9f8ed;color:#206b31}.status-off{background:#eee;color:#555}.news-meta{font-size:11px;color:#777;margin-top:10px}.item-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}.empty{padding:24px;text-align:center;color:var(--muted);background:#fafafa;border:1px dashed #ccc;border-radius:10px}
        @media(max-width:850px){.layout{grid-template-columns:1fr}.wrap{margin-top:22px}.panel{padding:19px}}@media(max-width:480px){.topbar-inner{padding:14px}.wrap{padding:0 13px}.news-item-top{display:block}.status{margin-top:9px}}
    </style>
</head>
<body>
<header class="topbar"><div class="topbar-inner">
    <a class="brand" href="index.php"><span class="brand-mark">SZ</span>SZÖTE · Admin</a>
    <nav class="navlinks" aria-label="Admin navigáció"><a href="index.php">Főoldal</a><a href="admin.php">Admin felület</a><a href="logout.php">Kilépés</a></nav>
</div></header>
<main class="wrap">
    <h1 class="page-title">Hírek és események</h1>
    <p class="intro">Itt tudod létrehozni, módosítani, elrejteni vagy törölni az egyesület bejegyzéseit.</p>

    <?php if ($hiba !== ''): ?><div class="alert alert-error" role="alert"><?= hirekE($hiba) ?></div><?php endif; ?>
    <?php if (is_array($uzenet) && ($uzenet['tipus'] ?? '') === 'siker'): ?><div class="alert alert-success" role="status"><?= hirekE($uzenet['szoveg'] ?? '') ?></div><?php endif; ?>

    <div class="layout">
        <section class="panel" aria-labelledby="form-title">
            <h2 id="form-title"><?= $form['id'] > 0 ? 'Bejegyzés szerkesztése' : 'Új hír vagy esemény' ?></h2>
            <form method="post" action="admin_hirek.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= hirekE($_SESSION['csrf_hirek']) ?>">
                <input type="hidden" name="muvelet" value="mentes">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                <div class="field"><label for="cim">Cím *</label><input id="cim" name="cim" maxlength="200" required value="<?= hirekE($form['cim']) ?>" placeholder="Pl. Tavaszi tűzoltógyakorlat"></div>
                <div class="field"><label for="rovid_leiras">Rövid leírás *</label><textarea id="rovid_leiras" name="rovid_leiras" maxlength="500" required placeholder="Röviden, miről szól a hír vagy esemény?"><?= hirekE($form['rovid_leiras']) ?></textarea><div class="hint">Legfeljebb 500 karakter. Ez jelenhet meg a főoldali előnézetben.</div></div>
                <div class="field"><label for="tartalom">Teljes tartalom *</label><textarea id="tartalom" name="tartalom" required style="min-height:180px" placeholder="Írd le a hír vagy esemény részleteit."><?= hirekE($form['tartalom']) ?></textarea></div>
                <div class="field"><label for="kep">Kép elérési útja (nem kötelező)</label><input id="kep" name="kep" maxlength="255" value="<?= hirekE($form['kep']) ?>" placeholder="Pl. kepek/tavaszi-gyakorlat.jpg"><div class="hint">Ez csak a kép útvonalát tárolja; fájlfeltöltést egy következő lépésben adunk hozzá.</div></div>
                <div class="checkrow"><input type="checkbox" id="aktiv" name="aktiv" value="1" <?= $form['aktiv'] === 1 ? 'checked' : '' ?>><label for="aktiv">Megjelenhet a nyilvános oldalon</label></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= $form['id'] > 0 ? 'Módosítások mentése' : 'Hír közzététele' ?></button><?php if ($form['id'] > 0): ?><a class="btn btn-light" href="admin_hirek.php">Mégse</a><?php endif; ?></div>
            </form>
        </section>

        <section class="panel" aria-labelledby="list-title">
            <h2 id="list-title">Meglévő bejegyzések (<?= count($hirek) ?>)</h2>
            <?php if (!$hirek): ?><div class="empty">Még nincs hír vagy esemény. A bal oldali űrlappal létrehozhatod az elsőt.</div><?php else: ?>
                <div class="news-list">
                    <?php foreach ($hirek as $hir): ?>
                        <article class="news-item">
                            <div class="news-item-top"><div><h3><?= hirekE($hir['cim']) ?></h3><p><?= hirekE($hir['rovid_leiras']) ?></p></div><span class="status <?= (int)$hir['aktiv'] === 1 ? 'status-on' : 'status-off' ?>"><?= (int)$hir['aktiv'] === 1 ? 'Látható' : 'Rejtett' ?></span></div>
                            <div class="news-meta">Létrehozva: <?= hirekE($hir['letrehozva'] ?? '—') ?><?php if (!empty($hir['modositva'])): ?> · Módosítva: <?= hirekE($hir['modositva']) ?><?php endif; ?></div>
                            <div class="item-actions"><a class="btn btn-light btn-small" href="admin_hirek.php?szerkeszt=<?= (int)$hir['id'] ?>">Szerkesztés</a>
                                <form method="post" action="admin_hirek.php" onsubmit="return confirm('Biztosan törlöd ezt a bejegyzést? A művelet nem vonható vissza.');"><input type="hidden" name="csrf_token" value="<?= hirekE($_SESSION['csrf_hirek']) ?>"><input type="hidden" name="muvelet" value="torles"><input type="hidden" name="id" value="<?= (int)$hir['id'] ?>"><button class="btn btn-danger btn-small" type="submit">Törlés</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
