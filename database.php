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

    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER DEFAULT NULL,
        model TEXT DEFAULT 'chat',
        mode TEXT DEFAULT 'normal',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS analyses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        message_id INT,
        sentiment TEXT,
        sentiment_score REAL,
        emotion_primary TEXT,
        emotion_secondary TEXT,
        tone TEXT,
        style_formal INT,
        style_assertive INT,
        style_creative INT,
        complexity INT,
        vocabulary_richness INT,
        avg_sentence_len REAL,
        word_count INT,
        themes TEXT,
        keywords TEXT,
        intent TEXT,
        language_patterns TEXT,
        rhetorical_devices TEXT,
        cognitive_load INT,
        information_density INT,
        question_count INT,
        certainty_level INT,
        raw_analysis_a TEXT,
        raw_analysis_b TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Contexte mémoire par utilisateur (résumé glissant)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_context (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT,
        context_summary TEXT,
        msg_count INT DEFAULT 0,
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

function save_analysis(string $session, int $msg_id, array $a, array $b): void {
    $db   = get_db();
    $text = $a['source_text'] ?? '';
    $wc   = str_word_count($text);
    $sents = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $avg  = ($wc > 0 && count($sents) > 0) ? round($wc / count($sents), 1) : 0;

    $db->prepare("INSERT INTO analyses (
        session_id,message_id,sentiment,sentiment_score,emotion_primary,emotion_secondary,tone,
        style_formal,style_assertive,style_creative,complexity,vocabulary_richness,
        avg_sentence_len,word_count,themes,keywords,intent,language_patterns,rhetorical_devices,
        cognitive_load,information_density,question_count,certainty_level,raw_analysis_a,raw_analysis_b
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $session, $msg_id,
        $a['sentiment'] ?? 'neutre', $a['sentiment_score'] ?? 50,
        $a['emotion_primary'] ?? '', $a['emotion_secondary'] ?? '', $a['tone'] ?? '',
        $a['style_formal'] ?? 50, $a['style_assertive'] ?? 50, $a['style_creative'] ?? 50,
        $b['complexity'] ?? 50, $b['vocabulary_richness'] ?? 50,
        $avg, $wc,
        json_encode($b['themes'] ?? []), json_encode($b['keywords'] ?? []),
        $b['intent'] ?? '', json_encode($b['language_patterns'] ?? []),
        json_encode($b['rhetorical_devices'] ?? []),
        $b['cognitive_load'] ?? 50, $b['information_density'] ?? 50,
        substr_count($text, '?'), $b['certainty_level'] ?? 50,
        json_encode($a), json_encode($b),
    ]);
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
    $a  = $db->prepare("SELECT AVG(sentiment_score) as avg_sent, AVG(complexity) as avg_cpx, AVG(cognitive_load) as avg_cog FROM analyses WHERE session_id=?");
    $a->execute([$session]);
    $as = $a->fetch(PDO::FETCH_ASSOC);
    return array_merge($ms ?? [], $as ?? []);
}
