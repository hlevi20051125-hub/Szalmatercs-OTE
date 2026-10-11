<?php



require_once "auth.php";

require_once "db.php";



bejelentkezesSzukseges();





// =====================================================

// SEGÉDFÜGGVÉNY

// =====================================================



function e($ertek)

{

    return htmlspecialchars((string)$ertek, ENT_QUOTES, "UTF-8");

}





// =====================================================

// BEJELENTKEZETT FELHASZNÁLÓ

// =====================================================



$felhasznalo = aktualisFelhasznalo();



if (!$felhasznalo) {

    header("Location: login.php");

    exit;

}



$felhasznaloId = (int)$felhasznalo["id"];





// =====================================================

// AKTUÁLIS TAG KERESÉSE

// =====================================================

//

// A bejelentkezett felhasználóhoz tartozó tagot

// a tagok.felhasznalo_id mező alapján keressük.

//



$stmt = $pdo->prepare("

    SELECT

        id,

        teljes_nev,

        becenev,

        profilkep

    FROM tagok

    WHERE felhasznalo_id = ?

    LIMIT 1

");



$stmt->execute([$felhasznaloId]);



$sajatTag = $stmt->fetch();





// =====================================================

// MŰVELET

// =====================================================



$muvelet = $_GET["muvelet"] ?? "";



if ($_SERVER["REQUEST_METHOD"] === "POST") {



    $muvelet = $_POST["muvelet"] ?? "";

    $ertesitesId = (int)($_POST["ertesites_id"] ?? 0);





    // -------------------------------------------------

    // ÉRTESÍTÉS MEGNYITÁSA

    // -------------------------------------------------



    if ($muvelet === "olvasott" && $ertesitesId > 0) {



        if ($sajatTag) {



            $stmt = $pdo->prepare("

                UPDATE ertesitesek

                SET

                    allapot = CASE

                        WHEN allapot = 'uj'

                        THEN 'olvasott'

                        ELSE allapot

                    END,

                    olvasva = COALESCE(olvasva, NOW())

                WHERE id = ?

                  AND cimzett_tag_id = ?

            ");



            $stmt->execute([

                $ertesitesId,

                (int)$sajatTag["id"]

            ]);

        }



        header("Location: ertesites.php?id=" . $ertesitesId);

        exit;

    }





    // -------------------------------------------------

    // BEVETÉSI MEGHÍVÓ ELFOGADÁSA

    // -------------------------------------------------



    if ($muvelet === "elfogad" && $ertesitesId > 0) {



        if ($sajatTag) {



            $stmt = $pdo->prepare("

                UPDATE ertesitesek

                SET

                    allapot = 'elfogadva',

                    olvasva = COALESCE(olvasva, NOW()),

                    megvalaszolva = NOW()

                WHERE id = ?

                  AND cimzett_tag_id = ?

                  AND allapot IN ('uj', 'olvasott')

            ");



            $stmt->execute([

                $ertesitesId,

                (int)$sajatTag["id"]

            ]);

        }



        header("Location: ertesites.php?id=" . $ertesitesId . "&siker=elfogadva");

        exit;

    }





    // -------------------------------------------------

    // BEVETÉSI MEGHÍVÓ ELUTASÍTÁSA

    // -------------------------------------------------



    if ($muvelet === "elutasit" && $ertesitesId > 0) {



        if ($sajatTag) {



            $stmt = $pdo->prepare("

                UPDATE ertesitesek

                SET

                    allapot = 'elutasitva',

                    olvasva = COALESCE(olvasva, NOW()),

                    megvalaszolva = NOW()

                WHERE id = ?

                  AND cimzett_tag_id = ?

                  AND allapot IN ('uj', 'olvasott')

            ");



            $stmt->execute([

                $ertesitesId,

                (int)$sajatTag["id"]

            ]);

        }



        header("Location: ertesites.php?id=" . $ertesitesId . "&siker=elutasitva");

        exit;

    }

}





// =====================================================

// EGY KONKRÉT ÉRTESÍTÉS

// =====================================================



$ertesitesId = (int)($_GET["id"] ?? 0);



$ertesites = null;



if ($ertesitesId > 0 && $sajatTag) {



    $stmt = $pdo->prepare("

        SELECT

            e.id,

            e.cimzett_tag_id,

            e.kuldo_felhasznalo_id,

            e.bevetes_id,

            e.tipus,

            e.cim,

            e.uzenet,

            e.allapot,

            e.letrehozva,

            e.olvasva,

            e.megvalaszolva,



            r.riasztas_szam,

            r.datum,

            r.riasztasi_ido,

            r.helyszin,

            r.telepules,

            r.esemeny_tipus,

            r.leiras,

            r.jelzes_modja,

            r.allapot AS riasztas_allapot



        FROM ertesitesek e



        LEFT JOIN bevetesek b

            ON b.id = e.bevetes_id



        LEFT JOIN riasztasok r

            ON r.id = b.riasztas_id



        WHERE e.id = ?

          AND e.cimzett_tag_id = ?



        LIMIT 1

    ");



    $stmt->execute([

        $ertesitesId,

        (int)$sajatTag["id"]

    ]);



    $ertesites = $stmt->fetch();





    // -------------------------------------------------

    // MEGNYITÁSKOR OLVASOTT

    // -------------------------------------------------



    if (

        $ertesites &&

        $ertesites["allapot"] === "uj"

    ) {



        $stmt = $pdo->prepare("

            UPDATE ertesitesek

            SET

                allapot = 'olvasott',

                olvasva = COALESCE(olvasva, NOW())

            WHERE id = ?

              AND cimzett_tag_id = ?

        ");



        $stmt->execute([

            $ertesitesId,

            (int)$sajatTag["id"]

        ]);



        $ertesites["allapot"] = "olvasott";

        $ertesites["olvasva"] = date("Y-m-d H:i:s");

    }

}





// =====================================================

// ÉRTESÍTÉSEK LISTÁJA

// =====================================================



$ertesitesek = [];



if ($sajatTag) {



    $stmt = $pdo->prepare("

        SELECT

            e.id,

            e.tipus,

            e.cim,

            e.uzenet,

            e.allapot,

            e.bevetes_id,

            e.letrehozva,



            r.riasztas_szam,

            r.datum,

            r.riasztasi_ido,

            r.helyszin,

            r.telepules,

            r.esemeny_tipus

        FROM ertesitesek e



        LEFT JOIN bevetesek b

            ON b.id = e.bevetes_id



        LEFT JOIN riasztasok r

            ON r.id = b.riasztas_id



        WHERE e.cimzett_tag_id = ?



        ORDER BY

            e.letrehozva DESC,

            e.id DESC

    ");



    $stmt->execute([

        (int)$sajatTag["id"]

    ]);



    $ertesitesek = $stmt->fetchAll();

}





// =====================================================

// ÁLLAPOT FORMÁZÁSA

// =====================================================



function allapotSzoveg($allapot)

{

    switch ($allapot) {



        case "uj":

            return "Új";



        case "olvasott":

            return "Olvasott";



        case "elfogadva":

            return "Elfogadva";



        case "elutasitva":

            return "Elutasítva";



        default:

            return $allapot;

    }

}





function allapotClass($allapot)

{

    switch ($allapot) {



        case "uj":

            return "status-new";



        case "olvasott":

            return "status-read";



        case "elfogadva":

            return "status-accepted";



        case "elutasitva":

            return "status-rejected";



        default:

            return "status-read";

    }

}



?>



<!DOCTYPE html>

<html lang="hu">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Értesítések – SZÖTE</title>



    <link

        rel="stylesheet"

        href="style.css"

    >



</head>





<body>





<!-- =====================================================

     FEJLÉC

     ===================================================== -->



<header class="site-header">



    <div class="header-inner">



        <a

            href="dashboard.php"

            class="logo"

        >



            <span class="logo-mark">

                SZ

            </span>



            <span>

                SZÖTE

            </span>



        </a>





        <nav class="main-nav">



            <a href="dashboard.php">

                Vezérlőpult

            </a>



            <a href="tagok.php">

                Tagok

            </a>



            <a href="riasztasok.php">

                Riasztások

            </a>



            <a href="bevetesek.php">

                Bevetések

            </a>



            <a

                href="ertesites.php"

                class="nav-login"

            >

                🔔 Értesítések

            </a>



            <a href="logout.php">

                Kilépés

            </a>



        </nav>



    </div>



</header>





<!-- =====================================================

     FŐ TARTALOM

     ===================================================== -->



<main class="content-section">



    <div class="content-container">





        <div class="section-heading">



            <span class="eyebrow">

                ÉRTESÍTÉSEK

            </span>



            <h1>

                🔔 Értesítéseim

            </h1>



            <p>

                Itt láthatod a neked küldött meghívókat

                és egyéb fontos értesítéseket.

            </p>



        </div>





        <?php if (!$sajatTag): ?>



            <div class="contact-card">



                <div class="contact-icon">

                    ⚠️

                </div>



                <h3>

                    Nincs tagprofil

                </h3>



                <p>

                    A bejelentkezett felhasználóhoz

                    jelenleg nem tartozik tagprofil.

                </p>



            </div>





        <?php else: ?>





            <?php if (!$ertesitesek): ?>



                <div class="contact-card">



                    <div class="contact-icon">

                        📭

                    </div>



                    <h3>

                        Nincs értesítés

                    </h3>



                    <p>

                        Jelenleg nincs új értesítésed.

                    </p>



                </div>





            <?php else: ?>





                <div class="notifications-list">



                    <?php foreach ($ertesitesek as $item): ?>



                        <a

                            href="ertesites.php?id=<?= (int)$item["id"] ?>"

                            class="notification-card <?= e($item["allapot"]) ?>"

                        >



                            <div class="notification-icon">



                                <?php if ($item["tipus"] === "bevetesi_meghivo"): ?>



                                    🚒



                                <?php else: ?>



                                    🔔



                                <?php endif; ?>



                            </div>





                            <div class="notification-content">



                                <div class="notification-top">



                                    <h3>

                                        <?= e($item["cim"]) ?>

                                    </h3>



                                    <span

                                        class="notification-status <?= e(allapotClass($item["allapot"])) ?>"

                                    >

                                        <?= e(allapotSzoveg($item["allapot"])) ?>

                                    </span>



                                </div>





                                <?php if (!empty($item["uzenet"])): ?>



                                    <p>

                                        <?= nl2br(e($item["uzenet"])) ?>

                                    </p>



                                <?php endif; ?>





                                <?php if (!empty($item["riasztas_szam"])): ?>



                                    <div class="notification-event">



                                        <strong>

                                            🚨 Riasztás:

                                        </strong>



                                        <?= e($item["riasztas_szam"]) ?>





                                        <?php if (!empty($item["helyszin"])): ?>



                                            <span>

                                                • <?= e($item["helyszin"]) ?>

                                            </span>



                                        <?php endif; ?>



                                    </div>



                                <?php endif; ?>





                                <small>

                                    <?= e(date("Y. m. d. H:i", strtotime($item["letrehozva"]))) ?>

                                </small>



                            </div>



                        </a>



                    <?php endforeach; ?>



                </div>



            <?php endif; ?>





            <?php if ($ertesites): ?>



                <div class="notification-detail">



                    <div class="notification-detail-header">



                        <span class="eyebrow">

                            ÉRTESÍTÉS

                        </span>



                        <h2>

                            <?= e($ertesites["cim"]) ?>

                        </h2>



                        <span

                            class="notification-status <?= e(allapotClass($ertesites["allapot"])) ?>"

                        >

                            <?= e(allapotSzoveg($ertesites["allapot"])) ?>

                        </span>



                    </div>





                    <?php if (!empty($ertesites["uzenet"])): ?>



                        <div class="notification-message">



                            <?= nl2br(e($ertesites["uzenet"])) ?>



                        </div>



                    <?php endif; ?>





                    <?php if (!empty($ertesites["riasztas_szam"])): ?>



                        <div class="event-box">



                            <h3>

                                🚨 Bevetési adatok

                            </h3>





                            <div class="event-grid">



                                <div>



                                    <strong>

                                        Riasztási szám

                                    </strong>



                                    <span>

                                        <?= e($ertesites["riasztas_szam"]) ?>

                                    </span>



                                </div>





                                <div>



                                    <strong>

                                        Dátum

                                    </strong>



                                    <span>

                                        <?= e(date("Y. m. d.", strtotime($ertesites["datum"]))) ?>

                                    </span>



                                </div>





                                <div>



                                    <strong>

                                        Riasztási idő

                                    </strong>



                                    <span>

                                        <?= e(substr($ertesites["riasztasi_ido"], 0, 5)) ?>

                                    </span>



                                </div>





                                <div>



                                    <strong>

                                        Helyszín

                                    </strong>



                                    <span>

                                        <?= e($ertesites["helyszin"]) ?>

                                    </span>



                                </div>





                                <div>



                                    <strong>

                                        Település

                                    </strong>



                                    <span>

                                        <?= e($ertesites["telepules"] ?: "—") ?>

                                    </span>



                                </div>





                                <div>



                                    <strong>

                                        Esemény

                                    </strong>



                                    <span>

                                        <?= e($ertesites["esemeny_tipus"]) ?>

                                    </span>



                                </div>



                            </div>



                        </div>



                    <?php endif; ?>





                    <?php if (

                        $ertesites["tipus"] === "bevetesi_meghivo" &&

                        in_array(

                            $ertesites["allapot"],

                            ["uj", "olvasott"],

                            true

                        )

                    ): ?>



                        <div class="notification-actions">



                            <form

                                method="post"

                                action="ertesites.php?id=<?= (int)$ertesites["id"] ?>"

                            >



                                <input

                                    type="hidden"

                                    name="ertesites_id"

                                    value="<?= (int)$ertesites["id"] ?>"

                                >



                                <input

                                    type="hidden"

                                    name="muvelet"

                                    value="elfogad"

                                >



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >

                                    ✅ Elfogadom

                                </button>



                            </form>





                            <form

                                method="post"

                                action="ertesites.php?id=<?= (int)$ertesites["id"] ?>"

                            >



                                <input

                                    type="hidden"

                                    name="ertesites_id"

                                    value="<?= (int)$ertesites["id"] ?>"

                                >



                                <input

                                    type="hidden"

                                    name="muvelet"

                                    value="elutasit"

                                >



                                <button

                                    type="submit"

                                    class="btn btn-danger"

                                >

                                    ❌ Nem tudok menni

                                </button>



                            </form>



                        </div>



                    <?php endif; ?>





                    <?php if ($ertesites["allapot"] === "elfogadva"): ?>



                        <div class="success-box">



                            ✅

                            <strong>

                                A meghívást elfogadtad.

                            </strong>



                        </div>



                    <?php endif; ?>





                    <?php if ($ertesites["allapot"] === "elutasitva"): ?>



                        <div class="error-box">



                            ❌

                            <strong>

                                Jelezted, hogy nem tudsz részt venni.

                            </strong>



                        </div>



                    <?php endif; ?>





                    <div class="notification-meta">



                        Küldve:

                        <?= e(date(

                            "Y. m. d. H:i",

                            strtotime($ertesites["letrehozva"])

                        )) ?>



                    </div>



                </div>



            <?php endif; ?>





        <?php endif; ?>



    </div>



</main>





<!-- =====================================================

     EGYSZERŰ OLDALSTÍLUSOK

     ===================================================== -->



<style>



.notifications-list {

    display: grid;

    gap: 15px;

    margin-bottom: 40px;

}



.notification-card {

    display: flex;

    gap: 18px;

    padding: 20px;

    background: #fff;

    border: 1px solid #e3e3e3;

    border-radius: 12px;

    text-decoration: none;

    color: #18181a;

    transition: .2s ease;

}



.notification-card:hover {

    transform: translateY(-2px);

    box-shadow: 0 12px 30px rgba(0,0,0,.08);

}



.notification-card.uj {

    border-left: 5px solid #c8102e;

}



.notification-icon {

    width: 48px;

    height: 48px;

    min-width: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #151518;

    border-radius: 10px;

    font-size: 24px;

}



.notification-content {

    flex: 1;

}



.notification-top {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    align-items: center;

}



.notification-top h3 {

    margin: 0;

}



.notification-content p {

    margin: 8px 0;

    color: #707075;

}



.notification-content small {

    color: #707075;

}



.notification-event {

    margin: 10px 0;

}



.notification-status {

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;

}



.status-new {

    background: #ffe5e8;

    color: #c8102e;

}



.status-read {

    background: #eeeeef;

    color: #555;

}



.status-accepted {

    background: #e4f7e9;

    color: #187a35;

}



.status-rejected {

    background: #ffe5e5;

    color: #a40000;

}



.notification-detail {

    margin-top: 30px;

    padding: 30px;

    background: #fff;

    border: 1px solid #e3e3e3;

    border-radius: 14px;

    box-shadow: 0 12px 35px rgba(0,0,0,.08);

}



.notification-detail-header h2 {

    margin: 8px 0 12px;

}



.notification-message {

    padding: 20px;

    margin: 20px 0;

    background: #f5f5f3;

    border-left: 4px solid #c8102e;

    border-radius: 8px;

}



.event-box {

    margin-top: 20px;

    padding: 22px;

    background: #151518;

    color: #fff;

    border-radius: 12px;

}



.event-box h3 {

    margin-top: 0;

}



.event-grid {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 18px;

}



.event-grid div {

    display: flex;

    flex-direction: column;

    gap: 5px;

}



.event-grid strong {

    color: #c8102e;

    font-size: 13px;

}



.event-grid span {

    color: #fff;

}



.notification-actions {

    display: flex;

    gap: 12px;

    margin-top: 25px;

}



.notification-actions form {

    margin: 0;

}



.success-box,

.error-box {

    margin-top: 20px;

    padding: 16px 20px;

    border-radius: 10px;

}



.success-box {

    background: #e4f7e9;

    color: #187a35;

}



.error-box {

    background: #ffe5e5;

    color: #a40000;

}



.notification-meta {

    margin-top: 25px;

    color: #707075;

    font-size: 13px;

}





@media (max-width: 800px) {



    .event-grid {

        grid-template-columns: 1fr 1fr;

    }



    .notification-top {

        align-items: flex-start;

        flex-direction: column;

    }



}





@media (max-width: 600px) {



    .notification-card {

        padding: 15px;

    }



    .notification-detail {

        padding: 20px;

    }



    .event-grid {

        grid-template-columns: 1fr;

    }



    .notification-actions {

        flex-direction: column;

    }



    .notification-actions .btn {

        width: 100%;

    }



}



</style>





</body>

</html>