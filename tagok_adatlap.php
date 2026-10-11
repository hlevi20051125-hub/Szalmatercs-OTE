<?php
require_once __DIR__ . '/db.php';

function adatlapE($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$tagId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tagId || $tagId < 1) {
    http_response_code(404);
    $tag = null;
} else {
    $stmt = $pdo->prepare("SELECT id, teljes_nev, becenev, beosztas, kepzettseg, bemutatkozas, profilkep, belepes_datum, telefonszam, email, nyilvanos_telefon, nyilvanos_email FROM tagok WHERE id = ? AND aktiv = 1 AND nyilvanos_adatlap = 1 LIMIT 1");
    $stmt->execute([$tagId]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$tag) http_response_code(404);
}

$oldalCim = $tag ? (($tag['teljes_nev'] ?: 'Tagi adatlap') . ' – SZÖTE') : 'Adatlap nem található – SZÖTE';
require __DIR__ . '/header.php';

$kepFajl = trim((string)($tag['profilkep'] ?? ''));
$kepUrl = $kepFajl !== '' ? 'profilkepek/' . rawurlencode(basename($kepFajl)) : '';
?>
<style>
.public-member-page{max-width:980px;margin:38px auto;padding:0 18px 55px;color:#202024}
.public-member-card{overflow:hidden;background:#fff;border:1px solid #e6e6e9;border-radius:20px;box-shadow:0 14px 40px rgba(0,0,0,.07)}
.public-member-hero{display:flex;align-items:center;gap:25px;padding:clamp(22px,5vw,42px);background:linear-gradient(135deg,#151518,#29292e);color:#fff;border-bottom:5px solid #c91f2c}
.public-member-photo{width:138px;height:138px;flex:0 0 138px;object-fit:cover;border-radius:50%;border:4px solid rgba(255,255,255,.85);background:#eee}
.public-member-placeholder{display:flex;align-items:center;justify-content:center;font-size:55px}
.public-member-kicker{color:#ffb2b7;text-transform:uppercase;letter-spacing:.14em;font-size:12px;font-weight:800}
.public-member-hero h1{margin:8px 0;font-size:clamp(27px,4vw,39px);line-height:1.15}
.public-member-role{display:inline-block;margin-top:5px;padding:7px 11px;border-radius:999px;background:#c91f2c;color:white;font-weight:700}
.public-member-content{padding:clamp(20px,4vw,35px)}
.public-member-section{padding:0 0 24px;margin:0 0 24px;border-bottom:1px solid #ededf0}
.public-member-section:last-of-type{border-bottom:0;margin-bottom:0}
.public-member-section h2{margin:0 0 15px;font-size:21px}
.public-member-info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.public-member-info-item{background:#f7f7f8;border-radius:11px;padding:14px;overflow-wrap:anywhere}
.public-member-label{display:block;color:#6b6b74;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.public-member-value{font-size:15px;line-height:1.65;white-space:pre-line}
.public-member-value a{color:#b71925;font-weight:700;text-decoration:none}
.public-member-empty{color:#696972;line-height:1.7}
.public-member-back{display:inline-flex;margin-top:22px;background:#c91f2c;color:white;text-decoration:none;padding:12px 17px;border-radius:10px;font-weight:700}
@media(max-width:620px){.public-member-hero{align-items:flex-start;flex-direction:column}.public-member-photo{width:110px;height:110px;flex-basis:110px}.public-member-info{grid-template-columns:1fr}}
</style>
<main class="public-member-page">
<?php if (!$tag): ?>
  <section class="public-member-card"><div class="public-member-content"><h1>Az adatlap nem található</h1><p class="public-member-empty">Ez a tag nem engedélyezte a nyilvános adatlapot, már nem aktív, vagy a hivatkozás hibás.</p><a class="public-member-back" href="index.php#elerheto">← Vissza az aktív tagokhoz</a></div></section>
<?php else: ?>
  <article class="public-member-card">
    <header class="public-member-hero">
      <?php if ($kepUrl !== ''): ?><img class="public-member-photo" src="<?= adatlapE($kepUrl) ?>" alt="<?= adatlapE($tag['teljes_nev']) ?> profilképe" onerror="this.style.display='none'">
      <?php else: ?><div class="public-member-photo public-member-placeholder" aria-hidden="true">🚒</div><?php endif; ?>
      <div><div class="public-member-kicker">SZÖTE · Nyilvános tagi adatlap</div><h1><?= adatlapE($tag['teljes_nev'] ?: 'Önkéntes tűzoltó') ?></h1>
        <?php if (!empty($tag['becenev'])): ?><div>„<?= adatlapE($tag['becenev']) ?>”</div><?php endif; ?>
        <span class="public-member-role">🚒 <?= adatlapE($tag['beosztas'] ?: 'Önkéntes tűzoltó') ?></span>
      </div>
    </header>
    <div class="public-member-content">
      <?php if (!empty($tag['bemutatkozas'])): ?><section class="public-member-section"><h2>Bemutatkozás</h2><div class="public-member-value"><?= adatlapE($tag['bemutatkozas']) ?></div></section><?php endif; ?>
      <?php if (!empty($tag['kepzettseg'])): ?><section class="public-member-section"><h2>🎓 Képzettség</h2><div class="public-member-value"><?= adatlapE($tag['kepzettseg']) ?></div></section><?php endif; ?>
      <?php
        $vanKapcsolat = ((int)$tag['nyilvanos_telefon'] === 1 && !empty($tag['telefonszam'])) || ((int)$tag['nyilvanos_email'] === 1 && !empty($tag['email']));
      ?>
      <?php if ($vanKapcsolat): ?>
        <section class="public-member-section"><h2>Kapcsolat</h2><div class="public-member-info">
          <?php if ((int)$tag['nyilvanos_telefon'] === 1 && !empty($tag['telefonszam'])): ?><div class="public-member-info-item"><span class="public-member-label">Telefonszám</span><div class="public-member-value"><a href="tel:<?= adatlapE($tag['telefonszam']) ?>">📞 <?= adatlapE($tag['telefonszam']) ?></a></div></div><?php endif; ?>
          <?php if ((int)$tag['nyilvanos_email'] === 1 && !empty($tag['email'])): ?><div class="public-member-info-item"><span class="public-member-label">E-mail</span><div class="public-member-value"><a href="mailto:<?= adatlapE($tag['email']) ?>">✉️ <?= adatlapE($tag['email']) ?></a></div></div><?php endif; ?>
        </div></section>
      <?php endif; ?>
      <?php if (!empty($tag['belepes_datum'])): ?><section class="public-member-section"><h2>Az egyesület tagja</h2><div class="public-member-value"><?= adatlapE(date('Y. m. d.', strtotime($tag['belepes_datum']))) ?></div></section><?php endif; ?>
      <a class="public-member-back" href="index.php#elerheto">← Vissza az aktív tagokhoz</a>
    </div>
  </article>
<?php endif; ?>
</main>
<?php require __DIR__ . '/footer.php'; ?>
