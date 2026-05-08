<?php
require_once 'config.php';

function get_db(): PDO {
    if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0755, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA journal_mode=WAL");

    // Users (login par email)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Sessions
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER DEFAULT NULL,
        model TEXT DEFAULT 'chat',
        mode TEXT DEFAULT 'info',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        role TEXT,
        content TEXT,
        tokens_in INT DEFAULT 0,
        tokens_out INT DEFAULT 0,
        model_used TEXT,
        latency_ms INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Contextes d'analyse (remplace analyses - simplifié pour v5.0)
    $pdo->exec("CREATE TABLE IF NOT EXISTS contexts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        message_id INT,
        themes TEXT,
        intent TEXT,
        keywords TEXT,
        depth_level INT DEFAULT 1,
        clarity_score INT DEFAULT 50,
        density_score INT DEFAULT 50,
        clarity_advice TEXT,
        density_advice TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Cache news
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        query_hash TEXT UNIQUE,
        query_text TEXT,
        articles TEXT,
        source_type TEXT DEFAULT 'google',
        fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Glossaire vivant
    $pdo->exec("CREATE TABLE IF NOT EXISTS glossary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        term TEXT,
        definition TEXT,
        context_snippet TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Plans de session
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        plan_markdown TEXT,
        themes_snapshot TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Contexte mémoire par utilisateur (résumé glissant)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_context (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        context_summary TEXT,
        msg_count INT DEFAULT 0,
        user_corrections TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    return $pdo;
}

function ensure_session(string $session, ?int $user_id = null): void {
    $db = get_db();
    $db->prepare("INSERT OR IGNORE INTO sessions (id, user_id) VALUES (?,?)")->execute([$session, $user_id]);
}

function save_message(string $session, string $role, string $content, int $ti = 0, int $to = 0, string $model = '', int $lat = 0): int {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO messages (session_id,role,content,tokens_in,tokens_out,model_used,latency_ms) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$session, $role, $content, $ti, $to, $model, $lat]);
    return (int)$db->lastInsertId();
}

// Sauvegarde le contexte d'analyse simplifié (v5.0)
function save_context(string $session, int $msg_id, array $data): void {
    $db = get_db();
    $db->prepare("INSERT INTO contexts (
        session_id, message_id, themes, intent, keywords, 
        depth_level, clarity_score, density_score, clarity_advice, density_advice
    ) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([
        $session,
        $msg_id,
        json_encode($data['themes'] ?? []),
        $data['intent'] ?? '',
        json_encode($data['keywords'] ?? []),
        $data['depth_level'] ?? 1,
        $data['clarity_score'] ?? 50,
        $data['density_score'] ?? 50,
        $data['clarity_advice'] ?? '',
        $data['density_advice'] ?? ''
    ]);
}

// Récupère le dernier contexte pour une session
function get_last_context(string $session): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM contexts WHERE session_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$session]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];
    
    return [
        'themes' => json_decode($row['themes'], true) ?: [],
        'intent' => $row['intent'] ?? '',
        'keywords' => json_decode($row['keywords'], true) ?: [],
        'depth_level' => (int)($row['depth_level'] ?? 1),
        'clarity_score' => (int)($row['clarity_score'] ?? 50),
        'density_score' => (int)($row['density_score'] ?? 50),
        'clarity_advice' => $row['clarity_advice'] ?? '',
        'density_advice' => $row['density_advice'] ?? ''
    ];
}

// Glossaire : ajouter un terme
function save_glossary_term(string $session, string $term, string $definition = '', string $context = ''): void {
    $db = get_db();
    $db->prepare("INSERT INTO glossary (session_id, term, definition, context_snippet) VALUES (?,?,?,?)")
      ->execute([$session, $term, $definition, $context]);
}

// Glossaire : récupérer tous les termes d'une session
function get_glossary(string $session): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM glossary WHERE session_id=? ORDER BY created_at");
    $stmt->execute([$session]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// News cache : vérifier si en cache
function get_news_cache(string $query, string $source = 'google'): ?array {
    $db = get_db();
    $hash = md5($query . '_' . $source);
    $stmt = $db->prepare("SELECT articles, fetched_at FROM news_cache WHERE query_hash=? AND source_type=?");
    $stmt->execute([$hash, $source]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) return null;
    
    // Vérifier TTL
    $age = time() - strtotime($row['fetched_at']);
    if ($age > NEWS_CACHE_TTL) return null;
    
    return json_decode($row['articles'], true) ?: null;
}

// News cache : sauvegarder
function save_news_cache(string $query, string $source, array $articles): void {
    $db = get_db();
    $hash = md5($query . '_' . $source);
    $db->prepare("INSERT OR REPLACE INTO news_cache (query_hash, query_text, articles, source_type) VALUES (?,?,?,?)")
      ->execute([$hash, $query, json_encode($articles), $source]);
}

// Plan : sauvegarder
function save_plan(string $session, string $plan_md, array $themes): void {
    $db = get_db();
    $db->prepare("INSERT INTO session_plans (session_id, plan_markdown, themes_snapshot) VALUES (?,?,?)")
      ->execute([$session, $plan_md, json_encode($themes)]);
}

// Plan : récupérer
function get_plan(string $session): ?string {
    $db = get_db();
    $stmt = $db->prepare("SELECT plan_markdown FROM session_plans WHERE session_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$session]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['plan_markdown'] : null;
}

function get_history(string $session, int $limit = 20): array {
    $db   = get_db();
    $stmt = $db->prepare("SELECT role,content FROM messages WHERE session_id=? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$session, $limit]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function get_context_summary(string $session): string {
    $db   = get_db();
    $stmt = $db->prepare("SELECT context_summary FROM user_context WHERE session_id=? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$session]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['context_summary'] ?? '';
}

function save_context_summary(string $session, string $summary, int $msg_count): void {
    $db = get_db();
    $existing = $db->prepare("SELECT id FROM user_context WHERE session_id=?");
    $existing->execute([$session]);
    if ($existing->fetch()) {
        $db->prepare("UPDATE user_context SET context_summary=?, msg_count=?, updated_at=CURRENT_TIMESTAMP WHERE session_id=?")->execute([$summary, $msg_count, $session]);
    } else {
        $db->prepare("INSERT INTO user_context (session_id,context_summary,msg_count) VALUES (?,?,?)")->execute([$session, $summary, $msg_count]);
    }
}

function get_session_stats(string $session): array {
    $db = get_db();
    $m  = $db->prepare("SELECT COUNT(*) as cnt, SUM(tokens_in+tokens_out) as tok FROM messages WHERE session_id=?");
    $m->execute([$session]);
    $ms = $m->fetch(PDO::FETCH_ASSOC);
    
    // Stats basées sur contexts (v5.0) au lieu de analyses
    $a  = $db->prepare("SELECT AVG(clarity_score) as avg_clarity, AVG(density_score) as avg_density, AVG(depth_level) as avg_depth FROM contexts WHERE session_id=?");
    $a->execute([$session]);
    $as = $a->fetch(PDO::FETCH_ASSOC);
    
    return array_merge($ms ?? [], $as ?? []);
}

// Supprime les données d'une session (pour clear.php)
function clear_session_data(string $session): void {
    $db = get_db();
    $db->prepare("DELETE FROM messages WHERE session_id=?")->execute([$session]);
    $db->prepare("DELETE FROM contexts WHERE session_id=?")->execute([$session]);
    $db->prepare("DELETE FROM glossary WHERE session_id=?")->execute([$session]);
    $db->prepare("DELETE FROM session_plans WHERE session_id=?")->execute([$session]);
    $db->prepare("DELETE FROM user_context WHERE session_id=?")->execute([$session]);
}
