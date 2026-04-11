<?php
// page1.php — Init BDD + catégories + sous-catégories IA + requêtes RSS + fetch articles
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_mistral.php';

// Les 100 catégories Google News par défaut
const DEFAULT_CATEGORIES = [
    "Politique France","Politique internationale","Économie mondiale","Finance marchés",
    "Technologie IA","Cybersécurité","Spatial astronomie","Sciences recherche",
    "Santé médecine","Environnement climat","Énergie transition","Agriculture alimentation",
    "Transports mobilité","Urbanisme architecture","Éducation université","Culture arts",
    "Cinéma séries","Musique","Littérature livres","Jeux vidéo","Sport football",
    "Sport tennis","Sport cyclisme","Sport rugby","Sport jeux olympiques",
    "Géopolitique Moyen-Orient","Géopolitique Russie Ukraine","Géopolitique Chine",
    "Géopolitique Afrique","Géopolitique Amérique latine","Immigration sociologie",
    "Droits humains","Justice droit","Défense armée","Terrorisme sécurité",
    "Diplomatie relations internationales","Réseaux sociaux numérique","Startups innovation",
    "Automobile électrique","Aéronautique aviation","Médias presse","Philosophie société",
    "Religions spiritualité","Histoire mémoire","Archéologie patrimoine","Gastronomie cuisine",
    "Mode luxe","Tourisme voyages","Immobilier logement","Emploi travail",
    "Entrepreneuriat PME","Commerce distribution","Luxe joaillerie","Crypto blockchain",
    "Intelligence artificielle générative","Robotique automatisation","Biotechnologies",
    "Médecine génomique","Psychiatrie santé mentale","Addiction dépendances",
    "Vieillissement démographie","Natalité famille","Pauvreté inégalités","Mondialisation",
    "Protectionnisme souveraineté","BCE FED politique monétaire","Dette publique","Retraites",
    "Fiscalité impôts","Syndicalisme travail","Grèves mouvements sociaux","Féminisme genre",
    "LGBTQ+ droits","Racisme discriminations","Antisémitisme","Islamophobie","Laïcité",
    "Désinformation fake news","Surveillance vie privée","Liberté de la presse",
    "Partis politiques élections","Extrême droite montée","Gauche radicale","Écologie politique",
    "Nucléaire énergie","Pétrole gaz","Eau ressources naturelles","Biodiversité extinction",
    "Océans pollution","Météo événements extrêmes","Séismes catastrophes naturelles",
    "Pandémies épidémies","Vaccins santé publique","Hôpital système de soins",
    "Drogues politiques","Prison justice pénale","Corruption scandales","Diplomatie UE",
    "Brexit conséquences","Balkans Europe de l'Est","Asie du Sud-Est","Inde émergence",
];

// ── Fonctions utilitaires ────────────────────────────────────────────────────

function emit(string $msg, string $level = 'info'): void {
    $icon = match($level) {
        'success' => '✅',
        'error'   => '❌',
        'warn'    => '⚠️',
        'ia'      => '🤖',
        'rss'     => '📡',
        'db'      => '💾',
        default   => 'ℹ️',
    };
    $time = date('H:i:s');
    echo "<script>window.addLog('[{$time}] {$icon} " . addslashes($msg) . "', '{$level}');</script>\n";
    flush();
    ob_flush();
    dbLog($msg, $level);
}

// ── Step 1 : Initialiser la BDD et les catégories ───────────────────────────
$db = getDB();
emit("Connexion SQLite OK — " . DB_PATH, 'db');

$catCount = (int)$db->query("SELECT COUNT(*) FROM CATEGORIE")->fetchColumn();
emit("Catégories en base : $catCount", 'db');

if ($catCount === 0) {
    emit("Base vide → insertion des 100 catégories par défaut…", 'info');
    $stmt = $db->prepare("INSERT OR IGNORE INTO CATEGORIE (nom) VALUES (?)");
    foreach (DEFAULT_CATEGORIES as $cat) {
        $stmt->execute([$cat]);
    }
    $catCount = (int)$db->query("SELECT COUNT(*) FROM CATEGORIE")->fetchColumn();
    emit("$catCount catégories insérées.", 'success');
} else {
    emit("Base existante — $catCount catégories chargées.", 'db');
}

// ── Step 2 : Choisir la catégorie la moins étudiée ──────────────────────────
$categorie = getLeastStudiedCategorie();
if (!$categorie) {
    emit("Aucune catégorie disponible !", 'error');
    signalOk(); exit;
}
emit("Catégorie choisie : « {$categorie['nom']} » (étude: {$categorie['study_count']})", 'info');

// ── Step 3 : Demander à l'IA des sous-catégories ─────────────────────────────
$existingSubs = $db->prepare("SELECT COUNT(*) FROM SOUS_CATEGORIE WHERE categorie_id = ?");
$existingSubs->execute([$categorie['id']]);
$subCount = (int)$existingSubs->fetchColumn();

if ($subCount < 3) {
    emit("IA → génération de sous-catégories pour « {$categorie['nom']} »…", 'ia');

    $result = mistralCall([
        [
            'role'    => 'user',
            'content' => "Génère exactement 8 sous-catégories journalistiques pertinentes pour la catégorie « {$categorie['nom']} ». Réponds UNIQUEMENT avec un tableau JSON de strings, exemple: [\"sous-cat 1\",\"sous-cat 2\",...]. Rien d'autre."
        ]
    ], 400, 0.6);

    if (!$result['ok']) {
        emit("Erreur IA sous-catégories: {$result['error']}", 'error');
    } else {
        $subs = extractJson($result['content']);
        if (!is_array($subs)) {
            // Fallback: split par lignes
            $subs = array_filter(array_map('trim', explode("\n", $result['content'])));
            $subs = array_values(array_map(fn($s) => trim($s, '"-•*1234567890. '), $subs));
        }
        $subs = array_filter($subs, fn($s) => is_string($s) && strlen($s) > 3);

        $stmtSub = $db->prepare("INSERT OR IGNORE INTO SOUS_CATEGORIE (categorie_id, nom) VALUES (?, ?)");
        $inserted = 0;
        foreach (array_slice($subs, 0, 10) as $sub) {
            $stmtSub->execute([$categorie['id'], $sub]);
            $inserted++;
        }
        emit("$inserted sous-catégories ajoutées pour « {$categorie['nom']} ».", 'success');
    }
    // Délai de courtoisie API
    sleep(1);
} else {
    emit("Sous-catégories existantes ($subCount) pour « {$categorie['nom']} » — réutilisation.", 'db');
}

// ── Step 4 : Choisir la sous-catégorie la moins étudiée ──────────────────────
$sousCat = getLeastStudiedSousCategorie($categorie['id']);
if (!$sousCat) {
    emit("Aucune sous-catégorie disponible — on incrémente et on passe.", 'warn');
    incrementStudyCount('CATEGORIE', $categorie['id']);
    signalOk(); exit;
}
emit("Sous-catégorie choisie : « {$sousCat['nom']} » (étude: {$sousCat['study_count']})", 'info');

// ── Step 5 : Demander 20 requêtes RSS à l'IA ─────────────────────────────────
$existingQueries = $db->prepare("SELECT COUNT(*) FROM RECHERCHE_RSS WHERE sous_categorie_id = ?");
$existingQueries->execute([$sousCat['id']]);
$queryCount = (int)$existingQueries->fetchColumn();

if ($queryCount < 5) {
    emit("IA → génération de 20 requêtes RSS pour « {$sousCat['nom']} »…", 'ia');

    $result = mistralCall([
        [
            'role'    => 'user',
            'content' => "Génère 20 requêtes de recherche Google News en français pour la sous-catégorie journalistique « {$sousCat['nom']} » (catégorie parente: « {$categorie['nom']} »). Ces requêtes doivent être variées, précises, actuelles. Réponds UNIQUEMENT avec un tableau JSON de strings. Exemple: [\"requête 1\",\"requête 2\",...]. Rien d'autre."
        ]
    ], 600, 0.7);

    if (!$result['ok']) {
        emit("Erreur IA requêtes: {$result['error']}", 'error');
    } else {
        $queries = extractJson($result['content']);
        if (!is_array($queries)) {
            $queries = array_filter(array_map('trim', explode("\n", $result['content'])));
            $queries = array_values(array_map(fn($s) => trim($s, '"-•*1234567890. '), $queries));
        }
        $queries = array_filter($queries, fn($s) => is_string($s) && strlen($s) > 5);

        $stmtQ = $db->prepare("INSERT OR IGNORE INTO RECHERCHE_RSS (sous_categorie_id, query) VALUES (?, ?)");
        $inserted = 0;
        foreach (array_slice($queries, 0, 20) as $q) {
            $stmtQ->execute([$sousCat['id'], $q]);
            $inserted++;
        }
        emit("$inserted requêtes RSS ajoutées pour « {$sousCat['nom']} ».", 'success');
    }
    sleep(1);
} else {
    emit("Requêtes RSS existantes ($queryCount) pour « {$sousCat['nom']} » — réutilisation.", 'db');
}

// ── Step 6 : Fetch RSS pour les requêtes les moins étudiées ──────────────────
$recherches = $db->prepare("SELECT * FROM RECHERCHE_RSS WHERE sous_categorie_id = ? ORDER BY study_count ASC LIMIT 5");
$recherches->execute([$sousCat['id']]);
$recherches = $recherches->fetchAll();

$totalArticles = 0;
$stmtInsAct = $db->prepare("
    INSERT OR IGNORE INTO ACTUALITEGOOGLE
    (recherche_id, categorie_id, sous_categorie_id, titre, description, lien, source, pubdate)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($recherches as $rech) {
    emit("RSS fetch → « {$rech['query']} »…", 'rss');
    $articles = fetchRSS($rech['query']);
    $count    = 0;
    foreach ($articles as $art) {
        try {
            $stmtInsAct->execute([
                $rech['id'],
                $categorie['id'],
                $sousCat['id'],
                $art['titre'],
                $art['description'],
                $art['lien'],
                $art['source'],
                $art['pubdate'],
            ]);
            $count++;
        } catch (Exception $e) {}
    }
    $totalArticles += $count;
    emit("$count articles récupérés pour « {$rech['query']} ».", $count > 0 ? 'success' : 'warn');
    incrementStudyCount('RECHERCHE_RSS', $rech['id']);
}

emit("Total articles récupérés cette session : $totalArticles", 'success');

// ── Step 7 : Mise à jour compteurs ──────────────────────────────────────────
incrementStudyCount('SOUS_CATEGORIE', $sousCat['id']);
incrementStudyCount('CATEGORIE', $categorie['id']);

$grandTotal = (int)$db->query("SELECT COUNT(*) FROM ACTUALITEGOOGLE")->fetchColumn();
emit("ACTUALITEGOOGLE : $grandTotal articles en base au total.", 'db');
emit("Page 1 terminée — passage à la page 2 ✨", 'success');

// ── Signal OK ────────────────────────────────────────────────────────────────
function signalOk(): void {
    echo "<script>
    setTimeout(function(){
        try { window.parent.notifyIaOk(); } catch(e){}
        window.parent.postMessage({ type: 'iaResponseOk' }, '*');
    }, 800);
    </script>\n";
    flush(); ob_flush();
}
signalOk();
?>
