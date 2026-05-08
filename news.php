<?php
// ============================================================
// AETHER v5.0 — NEWS API (Google News + Wikipedia)
// ============================================================
require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['sid'])) {
    echo json_encode(['error' => 'SESSION_EXPIRED']);
    exit;
}

$session = $_SESSION['sid'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$query = trim($input['query'] ?? '');
$source = $input['source'] ?? 'google'; // google, wiki, scholar

if (!$query) {
    echo json_encode(['error' => 'Query required']);
    exit;
}

// Vérifier le cache d'abord
$cached = get_news_cache($query, $source);
if ($cached) {
    echo json_encode(['articles' => $cached, 'cached' => true]);
    exit;
}

// Fonction cURL pour récupérer les données
function do_curl(string $url, int $timeout = 30): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        ]
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return $error ? null : $result;
}

$articles = [];

try {
    if ($source === 'google' || $source === 'scholar') {
        // Google News RSS
        $encoded_query = urlencode($query);
        $url = "https://news.google.com/rss/search?q={$encoded_query}&hl=fr&gl=FR&ceid=FR:fr";
        
        $xml_content = do_curl($url);
        
        if ($xml_content) {
            $xml = @simplexml_load_string($xml_content);
            if ($xml && isset($xml->channel->item)) {
                $count = 0;
                foreach ($xml->channel->item as $item) {
                    if ($count >= 5) break;
                    
                    // Extraire le titre et la source
                    $title = (string)$item->title;
                    $link = (string)$item->link;
                    $pubDate = (string)$item->pubDate;
                    
                    // Google News inclut souvent la source dans le titre
                    $source_name = 'Google News';
                    if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/', $title, $matches)) {
                        $source_name = trim($matches[1]);
                        $title = trim($matches[2]);
                    }
                    
                    $articles[] = [
                        'title'   => $title,
                        'link'    => $link,
                        'pubDate' => $pubDate,
                        'source'  => $source_name
                    ];
                    $count++;
                }
            }
        }
        
    } elseif ($source === 'wiki') {
        // Wikipedia REST API
        $encoded_term = urlencode(str_replace(' ', '_', $query));
        $url = "https://fr.wikipedia.org/api/rest_v1/page/summary/{$encoded_term}";
        
        $json_content = do_curl($url);
        
        if ($json_content) {
            $data = json_decode($json_content, true);
            if ($data && !isset($data['type']) && isset($data['title'])) {
                $articles[] = [
                    'title'   => $data['title'],
                    'link'    => $data['content_urls']['desktop']['page'] ?? '',
                    'pubDate' => date('c'),
                    'source'  => 'Wikipedia',
                    'extract' => $data['extract'] ?? ''
                ];
            }
        }
    }
    
    // Sauvegarder en cache
    if (!empty($articles)) {
        save_news_cache($query, $source, $articles);
    }
    
    echo json_encode([
        'articles' => $articles,
        'cached'   => false,
        'count'    => count($articles)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'articles' => []]);
}
