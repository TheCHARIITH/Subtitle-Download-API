<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$apiInfo = [
    'author' => 'TheCHARITH (Charith Pramodya Senananayake)',
    'api' => 'Sinhala Subtitle Search API',
    'endpoints' => [
        '/search?query=oppenheimer&site=all',
        '/search?query=deadpool&site=baiscope',
        '/download?url=https:
    ],
    'sites' => ['baiscope', 'cineru', 'piratelk', 'zoom']
];
$sites = [
    'baiscope' => 'https:
    'cineru'   => 'https:
    'piratelk' => 'https:
    'zoom'     => 'https:
];
function fetchPage($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html ?: null;
}
function searchSite($siteUrl, $query) {
    $searchUrl = rtrim($siteUrl, '/') . '/?s=' . urlencode($query);
    $html = fetchPage($searchUrl);
    if (!$html) return [];
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $results = [];
    $queryLower = strtolower($query);
    $querySlug  = str_replace(' ', '-', $queryLower);
    if (str_contains($siteUrl, 'cineru.lk')) {
        $nodes = $xpath->query('
    } elseif (str_contains($siteUrl, 'baiscope.lk')) {
        $nodes = $xpath->query('
    } else {
        $nodes = $xpath->query('
    }
    $excludePatterns = [
        '/category\
        '/trending/i','/imdb/i','/settings/i','/logout/i','/login/i',
        '/signup/i','/password/i','/faq/i','/guidance/i','/about/i',
        '/contact/i','/privacy/i','/terms/i','/#comment/i','/page\
    ];
    $excludeTitles = [
        'homepage','create','login','logout','register','account',
        'settings','trending','top imdb','my list','watched',
        'lost password','guidance','how can','a guide'
    ];
    foreach ($nodes as $node) {
        $title = trim($node->textContent);
        $url   = trim($node->getAttribute('href'));
        if (!$title || !$url) continue;
        if (strpos($url, 'http') !== 0) {
            $url = rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
        }
        $titleLower = strtolower($title);
        $urlLower   = strtolower($url);
        if (!preg_match('/sinhala|sub|subtitle|උපසිරැසි/i', $urlLower)) {
            continue;
        }
        if (str_contains($siteUrl, 'piratelk.com')) {
            if (!str_contains($titleLower, $queryLower) &&
                !str_contains($urlLower, $queryLower) &&
                !str_contains($urlLower, $querySlug)) {
                continue;
            }
        }
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) continue 2;
        }
        foreach ($excludeTitles as $word) {
            if (str_contains($titleLower, $word)) continue 2;
        }
        $title = preg_replace('/\(?\s*sinhala\s*(sub|subtitle?s?)\s*\)?/i', '', $title);
        $title = preg_replace('/\[\s*සිංහල\s*උපසිරැසි.*?\]/u', '', $title);
        $title = preg_replace('/\s*[\|।]\s*“.*?”\s*[\|।]?\s*$/u', '', $title); 
        $title = preg_replace('/\s*[\|।]\s*.*$/u', '', $title); 
        $title = trim($title);
        if (strlen($title) < 8) continue;
        if (in_array($url, array_column($results, 'url'))) continue;
        $results[] = [
            'title' => $title,
            'url'   => $url
        ];
    }
    return $results;
}
function getDownloadLink($pageUrl) {
    $html = fetchPage($pageUrl);
    if (!$html) return null;
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('
    if ($nodes->length === 0) {
        $nodes = $xpath->query('
        if ($nodes->length === 0) {
            $nodes = $xpath->query('
        }
    }
    if ($nodes->length === 0) {
        $nodes = $xpath->query('
    }
    if ($nodes->length === 0) {
        $nodes = $xpath->query('
    }
    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        if (!$href) continue;
        if (strpos($href, 'http') !== 0) {
            $base = parse_url($pageUrl, PHP_URL_SCHEME) . ':
            $href = rtrim($base, '/') . '/' . ltrim($href, '/');
        }
        $ch = curl_init($href);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode == 200 || $httpCode == 302 || $httpCode == 301) {
            return $href;
        }
    }
    return null;
}
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = $_GET;
switch ($path) {
    case '/':
        echo json_encode($apiInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
    case '/search':
        $query = $params['query'] ?? '';
        $site  = $params['site'] ?? 'all';
        if (!$query) {
            echo json_encode(['error' => 'query parameter required']);
            exit;
        }
        $results  = [];
        $searched = [];
        if ($site === 'all') {
            foreach ($sites as $key => $url) {
                $results[$key] = searchSite($url, $query);
                $searched[] = $key;
            }
        } elseif (isset($sites[$site])) {
            $results[$site] = searchSite($sites[$site], $query);
            $searched[] = $site;
        } else {
            echo json_encode(['error' => 'invalid site']);
            exit;
        }
        echo json_encode([
            'author'        => $apiInfo['author'],
            'query'         => $query,
            'sites_searched'=> $searched,
            'results'       => $results
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
    case '/download':
        $url = $params['url'] ?? '';
        if (!$url) {
            echo json_encode(['error' => 'url parameter required']);
            exit;
        }
        $dl = getDownloadLink($url);
        echo json_encode([
            'author' => 'TheCHARITH (Charith Pramodya Senananayake)',
            'success'      => (bool)$dl,
            'page_url'     => $url,
            'download_url' => $dl
        ], JSON_UNESCAPED_UNICODE);
        break;
    default:
        echo json_encode(['error' => 'invalid endpoint']);
}
