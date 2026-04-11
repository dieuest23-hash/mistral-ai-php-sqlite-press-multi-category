<?php
// page1.php — Init BDD + catégories + sous-catégories IA + requêtes RSS + fetch articles
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_mistral.php';

// Les 100 catégories Google News par défaut
const DEFAULT_CATEGORIES = [
    "Politique Algérie","Gouvernement algérien","Élections Algérie","Partis politiques Algérie",
    "Économie Algérie","Hydrocarbures Algérie","Sonatrach","Diversification économique Algérie",
    "Startups Algérie","Innovation Algérie","Technologie Algérie","Numérique Algérie",
    "Jeunesse Algérie","Emploi jeunes Algérie","Entrepreneuriat Algérie","Formation professionnelle Algérie",
    "Agriculture Algérie","Sécurité alimentaire Algérie","Céréales Algérie","Irrigation Algérie",
    "Industrie Algérie","Made in Algeria","PME Algérie","Investissement étranger Algérie",
    "Énergie renouvelable Algérie","Solaire Algérie","Transition énergétique Algérie",
    "Infrastructure Algérie","Travaux publics Algérie","Transport Algérie","Métro Alger","Tramway Algérie",
    "Santé Algérie","Hôpitaux Algérie","Pharmacie Algérie","Recherche médicale Algérie",
    "Éducation Algérie","Universités algériennes","Recherche scientifique Algérie","Innovation pédagogique Algérie",
    "Culture algérienne","Arts Algérie","Cinéma algérien","Musique algérienne","Littérature algérienne",
    "Sport Algérie","Football algérien","Équipe nationale Algérie","Athlètes algériens",
    "Diaspora algérienne","Algériens de l'étranger","Communauté algérienne France","Communauté algérienne Canada",
    "Géopolitique Maghreb","Relations Algérie Maroc","Relations Algérie Tunisie","Union du Maghreb arabe",
    "Relations Algérie France","Diplomatie algérienne","Politique étrangère Algérie",
    "Afrique relations","Coopération Sud-Sud","Algérie Afrique subsaharienne","CEDEAO Algérie",
    "Monde arabe","Relations Algérie Moyen-Orient","OPEP Algérie","Gaz naturel Algérie",
    "Sécurité Algérie","Défense nationale Algérie","Armée algérienne","Lutte antiterroriste Algérie",
    "Justice Algérie","Réforme judiciaire Algérie","Droits humains Algérie","Société civile Algérie",
    "Environnement Algérie","Changement climatique Algérie","Désertification Algérie","Eau Algérie",
    "Femmes Algérie","Droits des femmes Algérie","Égalité genre Algérie","Leadership féminin Algérie",
    "Patrimoine algérien","Histoire Algérie","Mémoire collective Algérie","Archéologie Algérie",
    "Tourisme Algérie","Sites historiques Algérie","Hôtellerie Algérie","Destination Algérie",
    "Immobilier Algérie","Logement Algérie","Urbanisme Algérie","Villes algériennes",
    "Commerce Algérie","Exportations Algérie","Importations Algérie","Balance commerciale Algérie",
    "Banque Algérie","Finance islamique Algérie","Bourse Alger","Investissement Algérie",
    "Télécommunications Algérie","Internet Algérie","5G Algérie","Transformation digitale Algérie",
    "Mode algérienne","Design algérien","Artisanat algérien","Traditions algériennes",
    "Gastronomie algérienne","Cuisine traditionnelle Algérie","Produits du terroir Algérie",
    "Médias Algérie","Presse algérienne","Journalisme Algérie","Liberté de presse Algérie",
    "Associations Algérie","ONG Algérie","Bénévolat Algérie","Action sociale Algérie",
    "Retraites Algérie","Protection sociale Algérie","Solidarité nationale Algérie",
    "Recherche spatiale Algérie","Satellite algérien","Sciences spatiales Algérie",
    "Biotechnologies Algérie","Innovation santé Algérie","Génétique Algérie",
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
            'content' => "Génère exactement 8 sous-catégories journalistiques pertinentes pour la catégorie « {$categorie['nom']} » en Algérie. Réponds UNIQUEMENT avec un tableau JSON de strings, exemple: [\"sous-cat 1\",\"sous-cat 2\",...]. Rien d'autre."
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
            'content' => "Génère 20 requêtes de recherche Google News en français pour la sous-catégorie journalistique « {$sousCat['nom']} » (catégorie parente: « {$categorie['nom']} ») en Algérie. Ces requêtes doivent être variées, précises, actuelles et centrées sur le contexte algérien. Réponds UNIQUEMENT avec un tableau JSON de strings. Exemple: [\"requête 1\",\"requête 2\",...]. Rien d'autre."
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
