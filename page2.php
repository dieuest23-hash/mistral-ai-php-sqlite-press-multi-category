<?php
// page2.php — Article IA multi-actualités, style journalistique de référence
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

// ── Trouver une catégorie avec articles non traités ──────────────────────────
emit("Recherche d'une catégorie avec actualités non traitées…", 'db');

$cat = $db->query("
    SELECT c.*, COUNT(a.id) as nb_dispo
    FROM CATEGORIE c
    JOIN ACTUALITEGOOGLE a ON a.categorie_id = c.id AND a.traite = 0
    GROUP BY c.id
    ORDER BY c.study_count ASC, nb_dispo DESC
    LIMIT 1
")->fetch();

if (!$cat) {
    emit("Pas encore assez d'actualités — on attend la page 1.", 'warn');
    signalOk(); exit;
}

emit("Catégorie retenue : « {$cat['nom']} » ({$cat['nb_dispo']} articles dispo)", 'info');

// ── Récupérer 10 articles non traités ────────────────────────────────────────
$articles = $db->prepare("
    SELECT * FROM ACTUALITEGOOGLE
    WHERE categorie_id = ? AND traite = 0
    ORDER BY pubdate DESC
    LIMIT 10
");
$articles->execute([$cat['id']]);
$articles = $articles->fetchAll();

emit(count($articles) . " actualités sélectionnées pour synthèse.", 'info');

// ── Construire le contexte pour l'IA ────────────────────────────────────────
$newsText = '';
$nb = count($articles);
foreach ($articles as $i => $art) {
    $n = $i + 1;
    $newsText .= "[$n] {$art['titre']}\n";
    if ($art['description']) $newsText .= "    {$art['description']}\n";
    $newsText .= "    Source: {$art['source']} — {$art['pubdate']}\n\n";
}

$prompt = <<<PROMPT
Tu es un journaliste d'investigation spécialisé sur l'Algérie. Ton style mêle la rigueur philosophique de George Steiner, la défense combative de Jacques Vergès, la vivacité d'Idriss Aberkane, l'exigence épistémologique d'Aurélien Barrau et l'économie politique de Frédéric Lordon.

Voici {$nb} actualités récentes sur le thème « {$cat['nom']} » en Algérie :

$newsText

Rédige un article journalistique SEO de haute qualité (800-1200 mots) qui :
1. Commence par un titre accrocheur (préfixé par "TITRE: " sur sa propre ligne)
2. Offre une analyse de fond, pas un simple résumé
3. Contextualise dans les grandes dynamiques algériennes et africaines contemporaines
4. Interroge les contradictions et les silences
5. Conserve une structure claire avec des intertitres (H2 marqués ##)
6. Se termine par une perspective ou une question ouverte

Écris directement l'article, en français, sans introduction méta.
PROMPT;

$nb = count($articles);

emit("IA → rédaction article « {$cat['nom']} » ({$nb} sources)… (délai variable)", 'ia');

$result = mistralCall([['role' => 'user', 'content' => $prompt]], 2000, 0.75);

if (!$result['ok']) {
    emit("Erreur IA article: {$result['error']}", 'error');
    signalOk(); exit;
}

$content = $result['content'];

// ── Extraire titre ────────────────────────────────────────────────────────────
$titre = "Analyse : {$cat['nom']}";
if (preg_match('/^TITRE:\s*(.+)$/m', $content, $m)) {
    $titre   = trim($m[1]);
    $content = preg_replace('/^TITRE:\s*.+\n?/m', '', $content, 1);
    $content = trim($content);
}

emit("Article généré : « $titre »", 'success');

// ── Sauvegarder l'article ────────────────────────────────────────────────────
$ids = array_column($articles, 'id');

$db->prepare("
    INSERT INTO ARTICLE (type, titre, contenu, categorie_id, sources_ids)
    VALUES ('multi_news', ?, ?, ?, ?)
")->execute([$titre, $content, $cat['id'], implode(',', $ids)]);

emit("Article sauvegardé en base.", 'db');

// ── Marquer les actualités comme traitées ────────────────────────────────────
markActualitesTraitees($ids);
incrementStudyCount('CATEGORIE', $cat['id']);

emit("Page 2 terminée — passage à la page 3 ✨", 'success');
sleep(1); // Délai API obligatoire
signalOk();
?>
