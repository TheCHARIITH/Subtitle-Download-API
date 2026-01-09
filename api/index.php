<?php
// Sinhala Subtitle Search & Download API - Improved Download Endpoint
// Author: TheCHARITH (Charith Pramodya Senananayake)
// Enhanced /download to better handle baiscope.lk time-based redirects and external hosts

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
    'sites' => ['baiscope', 'cineru', 'piratelk', 'zoom'],
    'note' => 'For baiscope.lk: Returns the intermediate redirect link if direct extraction fails (common due to timers). Open in browser to complete download.'
];

// ================= SITES =================
$sites = [
    'baiscope' => 'https://www.baiscope.lk',
    'cineru' => 'https://cineru.lk',
    'piratelk' => 'https://piratelk.com',
    'zoom' => 'https://zoom.lk',
];

// ================= FETCH =================
function fetchPage($url, $referer = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        CURLOPT_HEADER => true,  // Include headers for location check
        CURLOPT_NOBODY => false,
    ]);
    if ($referer) {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // Extract Location header if redirect
    preg_match('/Location:\s*(.+)/i', $headers, $matches);
    $location = $matches[1] ?? null;

    return [
        'body' => $body ?: null,
        'httpCode' => $httpCode,
        'effectiveUrl' => $effectiveUrl,
        'location' => trim($location),
        'headers' => $headers
    ];
}

// ================= SEARCH (unchanged) =================
function searchSite($siteUrl, $query) {
    $searchUrl = rtrim($siteUrl, '/') . '/?s=' . urlencode($query);
    $result = fetchPage($searchUrl);
    $html = $result['body'];
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $results = [];
    $queryLower = strtolower($query);
    $querySlug = str_replace(' ', '-', $queryLower);

    if (str_contains($siteUrl, 'cineru.lk')) {
        $nodes = $xpath->query('//article[contains(@class, "item-list")]//h2[contains(@class, "post-box-title")]/a');
    } elseif (str_contains($siteUrl, 'baiscope.lk')) {
        $nodes = $xpath->query('//h2[contains(@class, "post-box-title")]/a | //a[contains(@href, "sinhala-sub") or contains(@href, "sinhala-subtitle")]');
    } else {
        $nodes = $xpath->query('//a[@href]');
    }

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
        $url = trim($node->getAttribute('href'));
        if (!$title || !$url) continue;

        if (strpos($url, 'http') !== 0) {
            $url = rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
        }
        $titleLower = strtolower($title);
        $urlLower = strtolower($url);

        if (!preg_match('/sinhala|sub|subtitle|උපසිරැසි/i', $urlLower)) continue;

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
            'url' => $url
        ];
    }
    return $results;
}

// ================= DOWNLOAD =================
function getDownloadLink($pageUrl) {
    $pageResult = fetchPage($pageUrl);
    if (!$pageResult['body']) return null;

    $dom = new DOMDocument();
    @$dom->loadHTML($pageResult['body']);
    $xpath = new DOMXPath($dom);

    // Priority 1: Common text in download button (seen in many posts)
    $nodes = $xpath->query('//a[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "sinhala subtitles download") or contains(., "සිංහල උපසිරැසි මෙතනින් බාගන්න") or contains(., "බාගන්න")]');

    // Priority 2: Common classes or IDs
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//a[contains(@class, "baiscopebutton") or contains(@class, "dlm-buttons") or contains(@id, "download")]');
    }

    // Priority 3: Links to common redirect scripts
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//a[contains(@href, "go.php") or contains(@href, "download.php") or contains(@href, "/Downloads/")]');
    }

    // Fallback: Any .zip/.rar/.srt direct links
    if ($nodes->length === 0) {
        $nodes = $xpath->query('//a[contains(@href, ".zip") or contains(@href, ".rar") or contains(@href, ".srt")]');
    }

    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        if (!$href) continue;

        if (strpos($href, 'http') !== 0) {
            $base = dirname($pageUrl) . '/';
            $href = rtrim($base, '/') . '/' . ltrim($href, '/');
        }

        // Try full GET to follow redirects and capture final location
        $dlResult = fetchPage($href, $pageUrl);

        if ($dlResult['location']) {
            $final = $dlResult['location'];
            if (strpos($final, 'http') !== 0 && $dlResult['effectiveUrl']) {
                $final = rtrim($dlResult['effectiveUrl'], '/') . '/' . ltrim($final, '/');
            }
            // Basic check if final looks like a file host or direct file
            if (preg_match('/(drive\.google\.com|mediafire\.com|mega\.nz|usersdrive\.com|\.zip|\.rar|\.srt)/i', $final)) {
                return $final;
            }
        }

        // If effective URL is a file host or direct
        if (preg_match('/(drive\.google\.com|mediafire\.com|mega\.nz|usersdrive\.com|\.zip|\.rar|\.srt)/i', $dlResult['effectiveUrl'])) {
            return $dlResult['effectiveUrl'];
        }

        // If no direct, return the intermediate link (user can open in browser)
        if ($dlResult['httpCode'] == 200 || in_array($dlResult['httpCode'], [301, 302, 303])) {
            return $href;  // Return the button link as fallback
        }
    }

    return null;
}

// ================= ROUTER =================
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$params = $_GET;

switch ($path) {
    case '/':
        echo json_encode($apiInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
    case '/search':
        $query = $params['query'] ?? '';
        $site = $params['site'] ?? 'all';
        if (!$query) {
            echo json_encode(['error' => 'query parameter required']);
            exit;
        }
        $results = [];
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
            'author' => $apiInfo['author'],
            'query' => $query,
            'sites_searched' => $searched,
            'results' => $results
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
            'success' => (bool)$dl,
            'page_url' => $url,
            'download_url' => $dl,
            'note' => $dl ? 'If this is an intermediate link, open it in a browser to complete the timed redirect.' : 'No download link found.'
        ], JSON_UNESCAPED_UNICODE);
        break;
    default:
        echo json_encode(['error' => 'invalid endpoint']);
}
