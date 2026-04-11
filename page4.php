<?php
// page4.php — Revue de presse multi-catégories (10 catégories différentes)
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_mistral.php';

function emit(string $msg, string $level = 'info'): void {
    $icon = match($level) {
        'success' => '✅', 'error' => '❌', 'warn' => '⚠️',
        'ia' => '🤖', 'db' => '💾', default => 'ℹ️',
    };
    $time = date('H:i:s');
    echo "<script>window.addLog('[{$time}] {$icon} " . addslashes($msg) . "', '{$level}');</script>\n";
    flush(); ob_flush();
    dbLog($msg, $level);
}

function signalOk(): void {
    echo "<script>
    setTimeout(function(){
        try { window.parent.notifyIaOk(); } catch(e){}
        window.parent.postMessage({ type: 'iaResponseOk' }, '*');
    }, 800);
    </script>\n";
    flush(); ob_flush();
}

$db = getDB();

// ── Récupérer 10 catégories différentes les moins étudiées avec actu dispo ───
emit("Sélection de 10 catégories différentes les moins étudiées…", 'db');

$cats = $db->query("
    SELECT c.id, c.nom, c.study_count,
           GROUP_CONCAT(a.titre, ' ||| ') as titres,
           GROUP_CONCAT(a.description, ' ||| ') as descriptions,
           GROUP_CONCAT(a.id, ',') as art_ids
    FROM CATEGORIE c
    JOIN ACTUALITEGOOGLE a ON a.categorie_id = c.id AND a.traite = 0
    GROUP BY c.id
    ORDER BY c.study_count ASC, RANDOM()
    LIMIT 10
")->fetchAll();

if (count($cats) < 2) {
    emit("Pas assez de catégories avec actualités — on passe.", 'warn');
    signalOk(); exit;
}

emit(count($cats) . " catégories sélectionnées pour la revue de presse.", 'info');

// ── Construire le contexte ────────────────────────────────────────────────────
$revueContext = '';
$allArticleIds = [];
$catNoms = [];

foreach ($cats as $i => $cat) {
    $n = $i + 1;
    $catNoms[] = $cat['nom'];
    $titres = array_slice(explode(' ||| ', $cat['titres'] ?? ''), 0, 3);
    $titresText = implode("\n   - ", $titres);

    $revueContext .= "## {$n}. {$cat['nom']}\n";
    $revueContext .= "   - $titresText\n\n";

    // Collecter IDs pour mise à jour
    $ids = array_filter(explode(',', $cat['art_ids'] ?? ''));
    $allArticleIds = array_merge($allArticleIds, array_slice($ids, 0, 2));
}

$categsListe = implode(', ', $catNoms);

$prompt = <<<PROMPT
Tu es un journaliste d'investigation de niveau international. Ton style mêle la rigueur philosophique de George Steiner, la défense combative de Jacques Vergès, la vivacité d'Idriss Aberkane, l'exigence d'Aurélien Barrau et l'économie politique de Frédéric Lordon.

Voici les dernières actualités de 10 domaines différents :

$revueContext

Rédige une REVUE DE PRESSE complète et synthétique (1000-1400 mots) qui :
1. Commence par un titre accrocheur (préfixé "TITRE: " sur sa propre ligne)
2. Ouvre par une réflexion sur la cohérence de ces actualités dans l'époque
3. Traite chaque domaine en l'interconnectant aux autres (cherche les fils rouges)
4. Révèle les contradictions systémiques et les dynamiques profondes
5. Utilise des intertitres thématiques (## Intertitre), pas forcément par domaine
6. Conclut par une synthèse prospective audacieuse

Domaines couverts : $categsListe

Écris directement en français, sans commentaire méta ni introduction.
PROMPT;

emit("IA → rédaction revue de presse (" . count($cats) . " domaines)… (délai variable, article long)", 'ia');

$result = mistralCall([['role' => 'user', 'content' => $prompt]], 2500, 0.78);

if (!$result['ok']) {
    emit("Erreur IA revue: {$result['error']}", 'error');
    signalOk(); exit;
}

$content = $result['content'];

// ── Extraire titre ────────────────────────────────────────────────────────────
$titre = "Revue de presse : " . implode(', ', array_slice($catNoms, 0, 3)) . "…";
if (preg_match('/^TITRE:\s*(.+)$/m', $content, $m)) {
    $titre   = trim($m[1]);
    $content = preg_replace('/^TITRE:\s*.+\n?/m', '', $content, 1);
    $content = trim($content);
}

emit("Revue de presse rédigée : « $titre »", 'success');

// ── Sauvegarder ───────────────────────────────────────────────────────────────
// Catégorie principale = celle avec le study_count le plus bas
$mainCat = $cats[0];

$db->prepare("
    INSERT INTO ARTICLE (type, titre, contenu, categorie_id, sources_ids)
    VALUES ('revue_presse', ?, ?, ?, ?)
")->execute([
    $titre,
    $content,
    $mainCat['id'],
    implode(',', array_unique($allArticleIds))
]);

emit("Revue de presse sauvegardée.", 'db');

// ── Mise à jour des compteurs ────────────────────────────────────────────────
$processedIds = array_unique(array_filter($allArticleIds));
if ($processedIds) markActualitesTraitees($processedIds);

foreach ($cats as $cat) {
    incrementStudyCount('CATEGORIE', $cat['id']);
}

$totalArts = (int)$db->query("SELECT COUNT(*) FROM ARTICLE")->fetchColumn();
emit("Total articles en base : $totalArts", 'db');
emit("Page 4 terminée — retour à la page 1 pour continuer la boucle ♻️", 'success');

sleep(1);
signalOk();
?>
