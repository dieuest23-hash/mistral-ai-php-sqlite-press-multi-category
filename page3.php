<?php
// page3.php — Article IA sur un seul article RSS non traité
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_mistral.php';

function emit(string $msg, string $level = 'info'): void {
    $icon = match($level) {
        'success' => '✅', 'error' => '❌', 'warn' => '⚠️',
        'ia' => '🤖', 'db' => '💾', 'rss' => '📡', default => 'ℹ️',
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

// ── Trouver une catégorie peu étudiée avec article non traité ────────────────
emit("Recherche d'un article non traité (catégorie la moins étudiée)…", 'db');

$art = $db->query("
    SELECT a.*, c.nom as categorie_nom, c.study_count as cat_study, c.id as cat_id,
           sc.nom as sous_cat_nom
    FROM ACTUALITEGOOGLE a
    JOIN CATEGORIE c ON a.categorie_id = c.id
    LEFT JOIN SOUS_CATEGORIE sc ON a.sous_categorie_id = sc.id
    WHERE a.traite = 0 AND a.titre != ''
    ORDER BY c.study_count ASC, a.pubdate DESC
    LIMIT 1
")->fetch();

if (!$art) {
    emit("Aucun article non traité disponible — on passe.", 'warn');
    signalOk(); exit;
}

emit("Article source : « {$art['titre']} » (catégorie: {$art['categorie_nom']})", 'rss');

// ── Prompt journalistique ─────────────────────────────────────────────────────
$contexte = $art['description'] ? "Description disponible:\n{$art['description']}\n" : '';
$sousCatCtx = $art['sous_cat_nom'] ? " (sous-catégorie: {$art['sous_cat_nom']})" : '';

$prompt = <<<PROMPT
Tu es un journaliste d'investigation spécialisé sur l'Algérie. Ton style mêle la rigueur philosophique de George Steiner, la défense combative de Jacques Vergès, la vivacité d'Idriss Aberkane, l'exigence épistémologique d'Aurélien Barrau et l'économie politique de Frédéric Lordon.

Voici un article de presse source sur l'Algérie :
Titre : {$art['titre']}
Catégorie : {$art['categorie_nom']}{$sousCatCtx}
Source : {$art['source']} — publié le {$art['pubdate']}
{$contexte}

Rédige un article journalistique SEO complet (600-900 mots) qui :
1. Commence par un titre original (préfixé "TITRE: " sur sa propre ligne)
2. Analyse en profondeur ce sujet algérien, va au-delà du fait brut
3. Contextualise historiquement et géopolitiquement avec focus sur l'Algérie et la région
4. Questionne les présupposés et les silences médiatiques
5. Utilise des intertitres (## Intertitre)
6. Conclut par une ouverture critique

Écris en français, directement, sans commentaire méta.
PROMPT;

emit("IA → rédaction article approfondi sur « {$art['titre']} »… (délai variable)", 'ia');

$result = mistralCall([['role' => 'user', 'content' => $prompt]], 1800, 0.8);

if (!$result['ok']) {
    emit("Erreur IA: {$result['error']}", 'error');
    // Marquer quand même comme traité pour éviter boucle infinie
    markActualitesTraitees([$art['id']]);
    signalOk(); exit;
}

$content = $result['content'];

// ── Extraire le titre ─────────────────────────────────────────────────────────
$titre = "Analyse : {$art['titre']}";
if (preg_match('/^TITRE:\s*(.+)$/m', $content, $m)) {
    $titre   = trim($m[1]);
    $content = preg_replace('/^TITRE:\s*.+\n?/m', '', $content, 1);
    $content = trim($content);
}

emit("Article rédigé : « $titre »", 'success');

// ── Sauvegarder ───────────────────────────────────────────────────────────────
$db->prepare("
    INSERT INTO ARTICLE (type, titre, contenu, categorie_id, sous_categorie_id, sources_ids)
    VALUES ('single_rss', ?, ?, ?, ?, ?)
")->execute([
    $titre,
    $content,
    $art['cat_id'],
    $art['sous_categorie_id'],
    (string)$art['id']
]);

emit("Article sauvegardé.", 'db');

// ── Mise à jour ───────────────────────────────────────────────────────────────
markActualitesTraitees([$art['id']]);
incrementStudyCount('CATEGORIE', $art['cat_id']);
if ($art['sous_categorie_id']) {
    incrementStudyCount('SOUS_CATEGORIE', $art['sous_categorie_id']);
}

emit("Page 3 terminée — passage à la page 4 ✨", 'success');
sleep(1);
signalOk();
?>
