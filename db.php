<?php
// db.php — SQLite helper & schema initializer
define('DB_PATH', __DIR__ . '/googlenews.sqlite');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    initSchema($pdo);
    return $pdo;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS CATEGORIE (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT NOT NULL UNIQUE,
            study_count INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS SOUS_CATEGORIE (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            categorie_id INTEGER NOT NULL,
            nom TEXT NOT NULL,
            study_count INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY(categorie_id) REFERENCES CATEGORIE(id),
            UNIQUE(categorie_id, nom)
        );

        CREATE TABLE IF NOT EXISTS RECHERCHE_RSS (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sous_categorie_id INTEGER NOT NULL,
            query TEXT NOT NULL,
            study_count INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY(sous_categorie_id) REFERENCES SOUS_CATEGORIE(id),
            UNIQUE(sous_categorie_id, query)
        );

        CREATE TABLE IF NOT EXISTS ACTUALITEGOOGLE (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recherche_id INTEGER,
            categorie_id INTEGER,
            sous_categorie_id INTEGER,
            titre TEXT NOT NULL,
            description TEXT,
            lien TEXT UNIQUE,
            source TEXT,
            pubdate TEXT,
            traite INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY(recherche_id) REFERENCES RECHERCHE_RSS(id)
        );

        CREATE TABLE IF NOT EXISTS ARTICLE (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            titre TEXT NOT NULL,
            contenu TEXT NOT NULL,
            categorie_id INTEGER,
            sous_categorie_id INTEGER,
            sources_ids TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY(categorie_id) REFERENCES CATEGORIE(id)
        );

        CREATE TABLE IF NOT EXISTS LOG_CONSOLE (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            niveau TEXT DEFAULT 'info',
            message TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");
}

function dbLog(string $message, string $niveau = 'info'): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO LOG_CONSOLE (message, niveau) VALUES (?, ?)")
           ->execute([$message, $niveau]);
        // Keep only last 500 logs
        $db->exec("DELETE FROM LOG_CONSOLE WHERE id NOT IN (SELECT id FROM LOG_CONSOLE ORDER BY id DESC LIMIT 500)");
    } catch (Exception $e) {}
}

function getLeastStudiedCategorie(): ?array {
    $db = getDB();
    return $db->query("SELECT * FROM CATEGORIE ORDER BY study_count ASC, RANDOM() LIMIT 1")->fetch() ?: null;
}

function getLeastStudiedSousCategorie(int $categorieId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM SOUS_CATEGORIE WHERE categorie_id = ? ORDER BY study_count ASC, RANDOM() LIMIT 1");
    $stmt->execute([$categorieId]);
    return $stmt->fetch() ?: null;
}

function getLeastStudiedRecherche(int $sousCategorieId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM RECHERCHE_RSS WHERE sous_categorie_id = ? ORDER BY study_count ASC, RANDOM() LIMIT 1");
    $stmt->execute([$sousCategorieId]);
    return $stmt->fetch() ?: null;
}

function incrementStudyCount(string $table, int $id): void {
    $db = getDB();
    // RECHERCHE_RSS n'a pas de colonne updated_at
    $tablesWithUpdatedAt = ['CATEGORIE', 'SOUS_CATEGORIE'];
    if (in_array(strtoupper($table), $tablesWithUpdatedAt)) {
        $db->prepare("UPDATE $table SET study_count = study_count + 1, updated_at = datetime('now') WHERE id = ?")
           ->execute([$id]);
    } else {
        $db->prepare("UPDATE $table SET study_count = study_count + 1 WHERE id = ?")
           ->execute([$id]);
    }
}

function getRecentArticles(int $limit = 40, int $offset = 0, ?int $categorieId = null): array {
    $db = getDB();
    $where = $categorieId ? "WHERE a.categorie_id = $categorieId" : '';
    $stmt = $db->prepare("
        SELECT a.*, c.nom as categorie_nom
        FROM ARTICLE a
        LEFT JOIN CATEGORIE c ON a.categorie_id = c.id
        $where
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function countArticles(?int $categorieId = null): int {
    $db = getDB();
    $where = $categorieId ? "WHERE categorie_id = $categorieId" : '';
    return (int)$db->query("SELECT COUNT(*) FROM ARTICLE $where")->fetchColumn();
}

function getAllCategories(): array {
    $db = getDB();
    return $db->query("SELECT c.*, COUNT(a.id) as article_count FROM CATEGORIE c LEFT JOIN ARTICLE a ON a.categorie_id = c.id GROUP BY c.id ORDER BY c.nom")->fetchAll();
}

function getRecentLogs(int $limit = 100): array {
    $db = getDB();
    return $db->query("SELECT * FROM LOG_CONSOLE ORDER BY id DESC LIMIT $limit")->fetchAll();
}

function getUnprocessedActualite(int $categorieId, int $limit = 10): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM ACTUALITEGOOGLE
        WHERE categorie_id = ? AND traite = 0
        ORDER BY pubdate DESC
        LIMIT ?
    ");
    $stmt->execute([$categorieId, $limit]);
    return $stmt->fetchAll();
}

function markActualitesTraitees(array $ids): void {
    if (!$ids) return;
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("UPDATE ACTUALITEGOOGLE SET traite = 1 WHERE id IN ($placeholders)")
       ->execute($ids);
}

function getActualiteFromMultiCategories(int $limit = 10): array {
    $db = getDB();
    return $db->query("
        SELECT a.*, c.nom as categorie_nom
        FROM ACTUALITEGOOGLE a
        LEFT JOIN CATEGORIE c ON a.categorie_id = c.id
        WHERE a.traite = 0
        GROUP BY a.categorie_id
        ORDER BY c.study_count ASC
        LIMIT $limit
    ")->fetchAll();
}
