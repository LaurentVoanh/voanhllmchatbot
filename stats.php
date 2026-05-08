<?php
require_once 'config.php';
require_once 'database.php';
header('Content-Type: application/json; charset=utf-8');

$db = get_db();

// Profils par session : stats + thèmes/émotions dominants
$stmt = $db->query("
    SELECT
        m.session_id,
        COUNT(DISTINCT m.id) as msg_count,
        SUM(m.tokens_in + m.tokens_out) as total_tokens,
        AVG(a.sentiment_score) as avg_sent,
        AVG(a.complexity)      as avg_cpx,
        AVG(a.cognitive_load)  as avg_cog,
        GROUP_CONCAT(a.emotion_primary, '|||') as emotions,
        GROUP_CONCAT(a.themes, '|||')           as themes_raw
    FROM messages m
    LEFT JOIN analyses a ON a.session_id = m.session_id AND a.message_id = m.id
    WHERE m.role = 'user'
    GROUP BY m.session_id
    ORDER BY MAX(m.created_at) DESC
    LIMIT 20
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profiles = array_map(function($row) {
    // Top émotions
    $emotions = array_filter(array_unique(explode('|||', $row['emotions'] ?? '')));
    $emotions = array_values(array_slice($emotions, 0, 3));

    // Top thèmes (flatten les JSON arrays)
    $themes_all = [];
    foreach (explode('|||', $row['themes_raw'] ?? '') as $t) {
        $arr = json_decode($t, true);
        if (is_array($arr)) $themes_all = array_merge($themes_all, $arr);
    }
    $theme_counts = array_count_values($themes_all);
    arsort($theme_counts);
    $top_themes = array_slice(array_keys($theme_counts), 0, 5);

    return [
        'session_id'   => $row['session_id'],
        'msg_count'    => (int)$row['msg_count'],
        'total_tokens' => (int)($row['total_tokens'] ?? 0),
        'avg_sent'     => round((float)($row['avg_sent'] ?? 50), 1),
        'avg_cpx'      => round((float)($row['avg_cpx']  ?? 50), 1),
        'avg_cog'      => round((float)($row['avg_cog']  ?? 50), 1),
        'top_emotions' => $emotions,
        'top_themes'   => $top_themes,
    ];
}, $rows);

echo json_encode(['profiles' => $profiles], JSON_UNESCAPED_UNICODE);
