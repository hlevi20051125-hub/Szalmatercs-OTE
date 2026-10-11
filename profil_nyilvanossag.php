<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

bejelentkezesSzukseges();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function nyilvE($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$felhasznalo = aktualisFelhasznalo();
if (!$felhasznalo || empty($felhasznalo['id'])) {
    header('Location: login.php');
    exit;
}

$felhasznaloId = (int)$felhasznalo['id'];
$uzenet = '';
$hiba = '';

if (empty($_SESSION['csrf_nyilvanossag'])) {
    $_SESSION['csrf_nyilvanossag'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo->prepare('SELECT id, nyilvanos_adatlap, nyilvanos_telefon, nyilvanos_email FROM tagok WHERE felhasznalo_id = ? LIMIT 1');
    $stmt->execute([$felhasznaloId]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Nem sikerült betölteni a közzétételi beállításokat. Ellenőrizd, hogy lefuttattad-e az adatbázis-módosító SQL-t.');
}

if (!$tag) {
    require __DIR__ . '/header.php';
    echo '<main class="container" style="max-width:850px;margin:40px auto;padding:24px"><h1>Nyilvánossági beállítások</h1><p>A fiókodhoz még nincs tagprofil kapcsolva. Kérd az adminisztrátor segítségét.</p><a class="btn btn-primary" href="profil.php">Vissza a profilhoz</a></main>';
    require __DIR__ . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_nyilvanossag'], $token)) {
        $hiba = 'A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt, majd próbáld újra.';
    } else {
        $nyilvanosAdatlap = isset($_POST['nyilvanos_adatlap']) ? 1 : 0;
        $nyilvanosTelefon = isset($_POST['nyilvanos_telefon']) ? 1 : 0;
        $nyilvanosEmail = isset($_POST['nyilvanos_email']) ? 1 : 0;

        // Ha a teljes adatlap nem nyilvános, a kapcsolati adatok se jelenjenek meg.
        if ($nyilvanosAdatlap !== 1) {
            $nyilvanosTelefon = 0;
            $nyilvanosEmail = 0;
        }

        try {
            $save = $pdo->prepare('UPDATE tagok SET nyilvanos_adatlap = ?, nyilvanos_telefon = ?, nyilvanos_email = ? WHERE id = ? AND felhasznalo_id = ?');
            $save->execute([$nyilvanosAdatlap, $nyilvanosTelefon, $nyilvanosEmail, (int)$tag['id'], $felhasznaloId]);
            $tag['nyilvanos_adatlap'] = $nyilvanosAdatlap;
            $tag['nyilvanos_telefon'] = $nyilvanosTelefon;
            $tag['nyilvanos_email'] = $nyilvanosEmail;
            $uzenet = 'A közzétételi beállításaidat elmentettük.';
        } catch (PDOException $e) {
            $hiba = 'Nem sikerült menteni a beállításokat. Ellenőrizd az adatbázist.';
        }
    }
}

$oldalCim = 'Nyilvánossági beállítások';
require __DIR__ . '/header.php';
?>
<style>
.publicity-wrap{max-width:850px;margin:36px auto;padding:0 18px 50px;color:#202024}
.publicity-card{background:#fff;border:1px solid #e4e4e7;border-radius:18px;padding:clamp(20px,4vw,34px);box-shadow:0 12px 35px rgba(0,0,0,.06)}
.publicity-card h1{margin:0 0 10px;font-size:clamp(26px,4vw,36px)}
.publicity-intro{color:#62626b;line-height:1.7;margin-bottom:24px}
.publicity-option{display:flex;align-items:flex-start;gap:14px;padding:18px 0;border-top:1px solid #ededf0}
.publicity-option input{width:21px;height:21px;accent-color:#c91f2c;margin-top:3px;flex-shrink:0}
.publicity-option strong{display:block;margin-bottom:5px}
.publicity-option small{display:block;color:#666671;line-height:1.6}
.publicity-alert{padding:13px 15px;border-radius:10px;margin:15px 0;line-height:1.5}
.publicity-success{background:#eaf8ef;color:#176437}.publicity-error{background:#fff0f0;color:#9e1b24}
.publicity-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.publicity-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:12px 18px;background:#c91f2c;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}
.publicity-secondary{background:#222;color:#fff}
</style>
<main class="publicity-wrap">
  <section class="publicity-card">
    <h1>🔐 Nyilvánossági beállítások</h1>
    <p class="publicity-intro">Te döntöd el, mely adataid jelenhetnek meg az SZÖTE weboldalának nyilvános tagi adatlapján. A beállítások alapértelmezés szerint ki vannak kapcsolva. A lakhelyed és a születési dátumod ezen az oldalon nem kerül nyilvánosságra.</p>
    <?php if ($uzenet !== ''): ?><div class="publicity-alert publicity-success"><?= nyilvE($uzenet) ?></div><?php endif; ?>
    <?php if ($hiba !== ''): ?><div class="publicity-alert publicity-error"><?= nyilvE($hiba) ?></div><?php endif; ?>
    <form method="post" action="profil_nyilvanossag.php">
      <input type="hidden" name="csrf_token" value="<?= nyilvE($_SESSION['csrf_nyilvanossag']) ?>">
      <label class="publicity-option">
        <input type="checkbox" name="nyilvanos_adatlap" value="1" <?= (int)$tag['nyilvanos_adatlap'] === 1 ? 'checked' : '' ?>>
        <span><strong>Nyilvános tagi adatlap engedélyezése</strong><small>Az aktív tagok között megjelenhet a neved, beosztásod és profilképed. A részletes adatlapodon a bemutatkozásod, beceneved és képzettséged is látható lehet. A telefonszám és az e-mail ettől még külön engedélyhez kötött.</small></span>
      </label>
      <label class="publicity-option">
        <input type="checkbox" name="nyilvanos_telefon" value="1" <?= (int)$tag['nyilvanos_telefon'] === 1 ? 'checked' : '' ?> <?= (int)$tag['nyilvanos_adatlap'] !== 1 ? 'disabled' : '' ?>>
        <span><strong>Telefonszám nyilvánossá tétele</strong><small>A telefonszámod csak akkor jelenik meg, ha a nyilvános adatlapot is engedélyezed, és itt külön hozzájárulsz.</small></span>
      </label>
      <label class="publicity-option">
        <input type="checkbox" name="nyilvanos_email" value="1" <?= (int)$tag['nyilvanos_email'] === 1 ? 'checked' : '' ?> <?= (int)$tag['nyilvanos_adatlap'] !== 1 ? 'disabled' : '' ?>>
        <span><strong>E-mail-cím nyilvánossá tétele</strong><small>Az e-mail-címed csak külön engedély esetén jelenik meg a nyilvános adatlapodon.</small></span>
      </label>
      <div class="publicity-actions">
        <button class="publicity-button" type="submit">Beállítások mentése</button>
        <a class="publicity-button publicity-secondary" href="profil.php">Vissza a profilhoz</a>
      </div>
    </form>
  </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const publicCheck = document.querySelector('input[name="nyilvanos_adatlap"]');
  const dependentChecks = document.querySelectorAll('input[name="nyilvanos_telefon"], input[name="nyilvanos_email"]');
  if (!publicCheck) return;
  function updateDependent() {
    dependentChecks.forEach(function (check) {
      check.disabled = !publicCheck.checked;
      if (!publicCheck.checked) check.checked = false;
    });
  }
  publicCheck.addEventListener('change', updateDependent);
  updateDependent();
});
</script>
<?php require __DIR__ . '/footer.php'; ?>
