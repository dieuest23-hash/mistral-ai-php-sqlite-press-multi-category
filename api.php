<?php
// api.php — Endpoint AJAX JSON pour index.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'articles':
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 40)));
            $catId   = isset($_GET['cat_id']) && $_GET['cat_id'] !== '' ? (int)$_GET['cat_id'] : null;
            $offset  = ($page - 1) * $perPage;

            $db    = getDB();
            $where = $catId ? "WHERE a.categorie_id = $catId" : '';

            $total = (int)$db->query("SELECT COUNT(*) FROM ARTICLE a $where")->fetchColumn();
            $stmt  = $db->prepare("
                SELECT a.id, a.type, a.titre, a.contenu, a.created_at,
                       a.categorie_id, c.nom as categorie_nom
                FROM ARTICLE a
                LEFT JOIN CATEGORIE c ON a.categorie_id = c.id
                $where
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            $articles = $stmt->fetchAll();

            // Tronquer le contenu pour le JSON (pas besoin du texte entier pour la grille)
            foreach ($articles as &$art) {
                // Garder contenu complet pour popup (on tronque à 5000 chars max côté JSON)
                $art['contenu'] = mb_substr($art['contenu'], 0, 5000);
            }

            echo json_encode([
                'articles' => $articles,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'categories':
            $db   = getDB();
            $cats = $db->query("
                SELECT c.id, c.nom, c.study_count,
                       COUNT(a.id) as article_count
                FROM CATEGORIE c
                LEFT JOIN ARTICLE a ON a.categorie_id = c.id
                GROUP BY c.id
                HAVING article_count > 0
                ORDER BY article_count DESC, c.nom
                LIMIT 50
            ")->fetchAll();
            echo json_encode($cats, JSON_UNESCAPED_UNICODE);
            break;

        case 'stats':
            $db = getDB();
            echo json_encode([
                'total_articles'  => (int)$db->query("SELECT COUNT(*) FROM ARTICLE")->fetchColumn(),
                'total_actualites'=> (int)$db->query("SELECT COUNT(*) FROM ACTUALITEGOOGLE")->fetchColumn(),
                'total_categories'=> (int)$db->query("SELECT COUNT(*) FROM CATEGORIE")->fetchColumn(),
                'total_loops'     => (int)$db->query("SELECT COUNT(*) FROM ARTICLE WHERE type='revue_presse'")->fetchColumn(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'logs':
            $db   = getDB();
            $logs = $db->query("SELECT * FROM LOG_CONSOLE ORDER BY id DESC LIMIT 100")->fetchAll();
            echo json_encode(array_reverse($logs), JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
