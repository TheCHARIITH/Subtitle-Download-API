<?php
// Sinhala Subtitle Search & Download API
// Author: TheCHARITH (Charith Pramodya Senananayake)
// Improved version with precise scraping for cineru.lk and cleaner titles

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ================= API INFO =================
$apiInfo = [
    'author' => 'TheCHARITH (Charith Pramodya Senananayake)',
    'api' => 'Sinhala Subtitle Search API',
    'endpoints' => [
        '/search?query=oppenheimer&site=all',
        '/search?query=deadpool&site=baiscope',
        '/download?url=https://www.baiscope.lk/...',
    ],
    'sites' => ['baiscope', 'cineru', 'piratelk', 'zoom']
];

// ================= SITES =================
$sites = [
    'baiscope' => 'https://www.baiscope.lk',
    'cineru'   => 'https://cineru.lk',
    'piratelk' => 'https://piratelk.com',
    'zoom'     => 'https://zoom.lk',
];

// ================= FETCH =================
function fetchPage($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
@@ -29,99 +39,140 @@ function fetchPage($url) {
    curl_close($ch);
    return $html ?: null;
}

// ================= SEARCH =================
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

    // ── Site-specific XPath selectors ─────────────────────────────────────
    if (str_contains($siteUrl, 'cineru.lk')) {
        // Exact match for cineru.lk structure (as per your HTML sample)
        $nodes = $xpath->query('//article[contains(@class, "item-list")]//h2[contains(@class, "post-box-title")]/a');
    } elseif (str_contains($siteUrl, 'baiscope.lk')) {
        // Baiscope often uses similar structure or direct href with sinhala
        $nodes = $xpath->query('//h2[contains(@class, "post-box-title")]/a | //a[contains(@href, "sinhala-sub") or contains(@href, "sinhala-subtitle")]');
    } else {
        // Fallback for piratelk, zoom etc.
        $nodes = $xpath->query('//a[@href]');
    }

    // ── Common exclude patterns ───────────────────────────────────────────
    $excludePatterns = [
        '/category\//i','/tag\//i','/\?/i','/account/i','/home/i',
        '/trending/i','/imdb/i','/settings/i','/logout/i','/login/i',
        '/signup/i','/password/i','/faq/i','/guidance/i','/about/i',
        '/contact/i','/privacy/i','/terms/i','/#comment/i','/page\//i'
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

        // Make absolute URL
        if (strpos($url, 'http') !== 0) {
            $url = rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
        }

        $titleLower = strtolower($title);
        $urlLower   = strtolower($url);

        // Must be a subtitle post (strong filter)
        if (!preg_match('/sinhala|sub|subtitle|උපසිරැසි/i', $urlLower)) {
            continue;
        }

        // Strict matching for PirateLK (as in original)
        if (str_contains($siteUrl, 'piratelk.com')) {
            if (!str_contains($titleLower, $queryLower) &&
                !str_contains($urlLower, $queryLower) &&
                !str_contains($urlLower, $querySlug)) {
                continue;
            }
        }

        // Exclude junk links
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) continue 2;
        }
        foreach ($excludeTitles as $word) {
            if (str_contains($titleLower, $word)) continue 2;
        }

        // Clean up title
        $title = preg_replace('/\(?\s*sinhala\s*(sub|subtitle?s?)\s*\)?/i', '', $title);
        $title = preg_replace('/\[\s*සිංහල\s*උපසිරැසි.*?\]/u', '', $title);
        $title = preg_replace('/\s*[\|।]\s*“.*?”\s*[\|।]?\s*$/u', '', $title); // remove quoted Sinhala tagline
        $title = preg_replace('/\s*[\|।]\s*.*$/u', '', $title); // fallback remove anything after |
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

// ================= DOWNLOAD =================
function getDownloadLink($pageUrl) {
    $html = fetchPage($pageUrl);
    if (!$html) return null;
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    $nodes = $xpath->query('//a[contains(., "සිංහල උපසිරැසි මෙතනින් බාගන්න") and contains(., "SInhala Subtitles")]');
    
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//*[@id="post-237722"]/div/div[3]/a');
        if ($nodes->length === 0) {
            $nodes = $xpath->query('//div[starts-with(@id, "post-")]/div/div[3]/a');
        }
    }
    
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//a[contains(@class, "dlm-buttons-button-baiscopebutton") or contains(@class, "dlm-buttons-button")]');
    }
    
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//a[contains(@href, "/Downloads/") or contains(@href, ".zip") or contains(@href, ".rar") or contains(@href, ".srt")]');
    }
    
    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        if (!$href) continue;
        
        // Make absolute URL
        if (strpos($href, 'http') !== 0) {
            $base = parse_url($pageUrl, PHP_URL_SCHEME) . '://' . parse_url($pageUrl, PHP_URL_HOST);
            $href = rtrim($base, '/') . '/' . ltrim($href, '/');
        }
        
        // HEAD request
        $ch = curl_init($href);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
@@ -133,27 +184,35 @@ function getDownloadLink($pageUrl) {
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 || $httpCode == 302 || $httpCode == 301) {
            return $href;
        }
    }
    
    return null;
}
// ================= ROUTER =================
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
@@ -166,13 +225,15 @@ function getDownloadLink($pageUrl) {
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
@@ -187,6 +248,7 @@ function getDownloadLink($pageUrl) {
            'download_url' => $dl
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'invalid endpoint']);
}
