<?php
require_once 'db.php'; require_once 'auth.php'; bejelentkezesSzukseges(); $oldalCim='Dashboard';
$aktivTagok=(int)$pdo->query("SELECT COUNT(*) FROM tagok WHERE aktiv=1")->fetchColumn();
$szolgJarmu=(int)$pdo->query("SELECT COUNT(*) FROM jarmuvek WHERE statusz='szolgalatban'")->fetchColumn();
$nyitott=(int)$pdo->query("SELECT COUNT(*) FROM riasztasok WHERE allapot IN ('uj','folyamatban')")->fetchColumn();
$bevetesDb=(int)$pdo->query("SELECT COUNT(*) FROM bevetesek")->fetchColumn();
require 'header.php'; ?>
<div class="page-title"><div><span class="eyebrow">SZÖTE</span><h1>Vezérlőpult</h1><p class="muted">Üdvözöllek, <?= htmlspecialchars($_SESSION['felhasznalo']['nev'] ?? $_SESSION['felhasznalo']['felhasznalonev']) ?>!</p></div></div>
<div class="stats-grid"><div class="stat-card"><span>Aktív tagok</span><strong><?= $aktivTagok ?></strong><a href="tagok.php">Megtekintés →</a></div><div class="stat-card"><span>Szolgálatban lévő járművek</span><strong><?= $szolgJarmu ?></strong><a href="bevetes_uj.php">Bevetés indítása →</a></div><div class="stat-card"><span>Nyitott riasztások</span><strong><?= $nyitott ?></strong><a href="riasztasok.php">Riasztások →</a></div><div class="stat-card"><span>Összes bevetés</span><strong><?= $bevetesDb ?></strong><a href="bevetesek.php">Bevetések →</a></div></div>
<div class="quick-grid"><a class="quick-card" href="riasztas_uj.php"><b>+ Új riasztás</b><span>Rögzíts egy új riasztást.</span></a><a class="quick-card" href="bevetes_uj.php"><b>🚒 Új bevetés</b><span>Rögzítsd a kivonulást és a személyi állományt.</span></a><a class="quick-card" href="tag_uj.php"><b>+ Új tag</b><span>Új egyesületi tag felvétele.</span></a></div>
<?php require 'footer.php'; ?>