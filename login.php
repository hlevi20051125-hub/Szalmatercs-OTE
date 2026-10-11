<?php
require_once 'db.php'; require_once 'auth.php';
if (bejelentkezve()) { header('Location: dashboard.php'); exit; }
$hiba='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $felh=trim($_POST['felhasznalonev']??''); $jelszo=$_POST['jelszo']??'';
  if ($felh==='' || $jelszo==='') $hiba='Töltsd ki mindkét mezőt.';
  else { $st=$pdo->prepare('SELECT id,felhasznalonev,jelszo,szerepkor,nev FROM felhasznalok WHERE felhasznalonev=? AND aktiv=1 LIMIT 1'); $st->execute([$felh]); $u=$st->fetch(); if($u && password_verify($jelszo,$u['jelszo'])) { unset($u['jelszo']); $_SESSION['felhasznalo']=$u; header('Location: dashboard.php'); exit; } $hiba='Hibás felhasználónév vagy jelszó.'; }
}
?><!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Bejelentkezés – SZÖTE</title><link rel="stylesheet" href="style.css"></head><body class="login-page"><div class="login-shell"><a class="logo login-logo" href="index.php"><span class="logo-mark">SZ</span><span>SZÖTE</span></a><div class="login-card"><span class="eyebrow">BELSŐ RENDSZER</span><h1>Bejelentkezés</h1><p class="muted">Lépj be a tűzoltósági kezelőfelületre.</p><?php if($hiba): ?><div class="alert alert-danger"><?= htmlspecialchars($hiba) ?></div><?php endif; ?><form method="post" class="form-grid single"><label>Felhasználónév<input type="text" name="felhasznalonev" autocomplete="username" required></label><label>Jelszó<input type="password" name="jelszo" autocomplete="current-password" required></label><button class="btn btn-primary btn-block">Belépés</button></form><a class="back-link" href="index.php">← Vissza a főoldalra</a></div></div></body></html>
