<?php

require_once 'db.php';
require_once 'auth.php';

bejelentkezesSzukseges();

/*
|--------------------------------------------------------------------------
| Jogosultság
|--------------------------------------------------------------------------
| Az adminisztrációs felületet admin és parancsnok érheti el.
*/

adminVagyParancsnok();

$oldalCim = 'Adminisztráció';

$felhasznalo = aktualisFelhasznalo();

function adminOldalE($ertek)
{
    return htmlspecialchars(
        (string)$ertek,
        ENT_QUOTES,
        'UTF-8'
    );
}

require 'header.php';

?>

<style>
.admin-page {
    max-width: 1200px;
    margin: 0 auto;
}

.admin-hero {
    background: linear-gradient(135deg, #0d0d0f, #202024);
    color: #fff;
    padding: 34px;
    border-radius: 16px;
    margin-bottom: 28px;
    border-left: 6px solid #c8102e;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .10);
}

.admin-hero .eyebrow {
    display: inline-block;
    color: #ff647a;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.admin-hero h1 {
    margin: 0 0 12px;
    color: #fff;
    font-size: clamp(27px, 4vw, 38px);
}

.admin-hero p {
    color: #d4d4d8;
    margin: 0;
    line-height: 1.7;
}

.admin-section-title {
    margin: 30px 0 16px;
    font-size: 22px;
    color: #18181a;
}

.admin-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.admin-menu-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-height: 190px;
    padding: 23px;
    background: #fff;
    border: 1px solid #e3e3e3;
    border-radius: 13px;
    color: #18181a;
    text-decoration: none;
    box-shadow: 0 8px 25px rgba(0, 0, 0, .04);
    transition: transform .2s, box-shadow .2s, border-color .2s;
    box-sizing: border-box;
}

.admin-menu-card:hover {
    transform: translateY(-4px);
    border-color: #c8102e;
    box-shadow: 0 14px 32px rgba(0, 0, 0, .09);
}

.admin-menu-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin-bottom: 17px;
    border-radius: 12px;
    background: #f5f5f3;
    font-size: 25px;
}

.admin-menu-title {
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 9px;
}

.admin-menu-description {
    color: #707075;
    font-size: 14px;
    line-height: 1.6;
    flex: 1;
}

.admin-menu-arrow {
    color: #c8102e;
    font-weight: 800;
    margin-top: 18px;
}

.admin-menu-card.featured {
    background: linear-gradient(135deg, #c8102e, #8f0b22);
    border-color: #c8102e;
    color: #fff;
}

.admin-menu-card.featured .admin-menu-icon {
    background: rgba(255, 255, 255, .15);
}

.admin-menu-card.featured .admin-menu-title,
.admin-menu-card.featured .admin-menu-arrow {
    color: #fff;
}

.admin-menu-card.featured .admin-menu-description {
    color: #ffe8ec;
}

.admin-menu-card.featured:hover {
    border-color: #8f0b22;
    box-shadow: 0 15px 35px rgba(200, 16, 46, .22);
}

.admin-note {
    margin-top: 25px;
    padding: 17px 20px;
    border: 1px solid #e3e3e3;
    border-radius: 11px;
    background: #f8f8f7;
    color: #55555a;
    font-size: 14px;
    line-height: 1.7;
}

@media (max-width: 900px) {
    .admin-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .admin-grid {
        grid-template-columns: 1fr;
    }

    .admin-hero {
        padding: 24px;
    }

    .admin-menu-card {
        min-height: 165px;
    }
}
</style>

<div class="admin-page">

    <section class="admin-hero">

        <span class="eyebrow">SZÖTE • VEZETŐI FELÜLET</span>

        <h1>Adminisztráció</h1>

        <p>
            Üdvözlünk az adminisztrációs felületen!
            Innen közvetlenül elérheted az egyesület
            tagjainak, riasztásainak, bevetéseinek,
            lakossági bejelentéseinek, valamint híreinek
            és eseményeinek kezelését.
        </p>

    </section>

    <h2 class="admin-section-title">
        🚨 Lakossági bejelentések
    </h2>

    <div class="admin-grid">

        <a href="esemeny_bejelentesek_admin.php" class="admin-menu-card featured">
            <span class="admin-menu-icon">🚨</span>
            <span class="admin-menu-title">Eseménybejelentések kezelése</span>
            <span class="admin-menu-description">
                A lakosságtól érkezett bejelentések megtekintése,
                ellenőrzése, elfogadása vagy elutasítása.
            </span>
            <span class="admin-menu-arrow">Bejelentések megnyitása →</span>
        </a>

        <a href="esemeny_bejelentes.php" class="admin-menu-card">
            <span class="admin-menu-icon">📝</span>
            <span class="admin-menu-title">Nyilvános bejelentő űrlap</span>
            <span class="admin-menu-description">
                A lakosság által használható eseménybejelentő űrlap megnyitása.
            </span>
            <span class="admin-menu-arrow">Űrlap megnyitása →</span>
        </a>

    </div>

    <h2 class="admin-section-title">
        📰 Hírek és események
    </h2>

    <div class="admin-grid">

        <a href="admin_hirek.php" class="admin-menu-card featured">
            <span class="admin-menu-icon">📰</span>
            <span class="admin-menu-title">Hírek és események kezelése</span>
            <span class="admin-menu-description">
                Új hírek és programok létrehozása, meglévő bejegyzések szerkesztése,
                közzététele, elrejtése vagy törlése.
            </span>
            <span class="admin-menu-arrow">Hírek kezelése →</span>
        </a>

        <a href="index.php#hirek" class="admin-menu-card">
            <span class="admin-menu-icon">🌐</span>
            <span class="admin-menu-title">Nyilvános hírek megtekintése</span>
            <span class="admin-menu-description">
                A főoldalon jelenleg látható, nyilvánosan közzétett hírek ellenőrzése.
            </span>
            <span class="admin-menu-arrow">Főoldali hírek megnyitása →</span>
        </a>

    </div>

    <h2 class="admin-section-title">
        🛠️ Egyesületi kezelés
    </h2>

    <div class="admin-grid">

        <a href="tagok.php" class="admin-menu-card">
            <span class="admin-menu-icon">👥</span>
            <span class="admin-menu-title">Tagok kezelése</span>
            <span class="admin-menu-description">
                A tagok adatainak, beosztásának és tagsági állapotának megtekintése.
            </span>
            <span class="admin-menu-arrow">Tagok megnyitása →</span>
        </a>

        <a href="riasztasok.php" class="admin-menu-card">
            <span class="admin-menu-icon">🔔</span>
            <span class="admin-menu-title">Riasztások</span>
            <span class="admin-menu-description">
                Hivatalos riasztások megtekintése, rögzítése és kezelése.
            </span>
            <span class="admin-menu-arrow">Riasztások megnyitása →</span>
        </a>

        <a href="bevetesek.php" class="admin-menu-card">
            <span class="admin-menu-icon">🚒</span>
            <span class="admin-menu-title">Bevetések</span>
            <span class="admin-menu-description">
                Bevetések adatainak, résztvevőinek és időpontjainak kezelése.
            </span>
            <span class="admin-menu-arrow">Bevetések megnyitása →</span>
        </a>

        <a href="dashboard.php" class="admin-menu-card">
            <span class="admin-menu-icon">📊</span>
            <span class="admin-menu-title">Vezérlőpult</span>
            <span class="admin-menu-description">
                Visszatérés a bejelentkezés utáni vezérlőpultra.
            </span>
            <span class="admin-menu-arrow">Vezérlőpult megnyitása →</span>
        </a>

        <a href="profil.php" class="admin-menu-card">
            <span class="admin-menu-icon">👤</span>
            <span class="admin-menu-title">Saját profil</span>
            <span class="admin-menu-description">
                Saját felhasználói profilod és személyes adataid megtekintése.
            </span>
            <span class="admin-menu-arrow">Profil megnyitása →</span>
        </a>

    </div>

    <div class="admin-note">
        <strong>Fontos:</strong>
        a bejelentések elfogadása önmagában még nem feltétlenül hoz létre hivatalos riasztást.
        Ehhez az elfogadott bejelentés részleteinél használd a „Hivatalos riasztás létrehozása”
        funkciót, ha az előző lépésben beállítottad.
    </div>

</div>

<?php require 'footer.php'; ?>
