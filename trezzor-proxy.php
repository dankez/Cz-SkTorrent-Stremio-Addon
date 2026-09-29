<?php
/**
 * TreZzoR / Czech-Server Tracker Proxy pre Websupport PHP hosting (napr. sss.sk)
 * Bezpečný proxy skript s overením tajného API tokenu.
 */

// Nastavte si vlastný tajný kľúč:
define('PROXY_SECRET', 'trezzor_secret_123');

// Kontrola tajného kľúča
$token = $_GET['token'] ?? $_SERVER['HTTP_X_PROXY_TOKEN'] ?? '';
if ($token !== PROXY_SECRET) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Prístup odmietnutý - neplatný token']);
    exit;
}

$action = $_GET['action'] ?? 'search';
$userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Chýba meno alebo heslo']);
        exit;
    }

    $ch = curl_init('https://tracker.czech-server.com/prihlasenie.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['uid' => $username, 'pwd' => $password]));
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_REFERER, 'https://tracker.czech-server.com/prihlasenie.php');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    curl_close($ch);

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
    $cookies = [];
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }

    header('Content-Type: application/json; charset=utf-8');
    if (!empty($cookies['uid']) && !empty($cookies['pass'])) {
        echo json_encode([
            'uid' => $cookies['uid'],
            'pass' => $cookies['pass'],
            'secure2' => $cookies['secure2'] ?? '',
            'username' => $username
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Neplatné prihlasovacie údaje']);
    }
    exit;
}

if ($action === 'search') {
    $search = $_GET['search'] ?? '';
    $uid = $_GET['uid'] ?? '';
    $pass = $_GET['pass'] ?? '';
    $secure2 = $_GET['secure2'] ?? '';

    $cookieHeader = "uid={$uid}; pass={$pass}";
    if ($secure2) $cookieHeader .= "; secure2={$secure2}";

    $url = 'https://tracker.czech-server.com/torrents.php?search=' . urlencode($search) . '&active=1';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_COOKIE, $cookieHeader);
    curl_setopt($ch, CURLOPT_REFERER, 'https://tracker.czech-server.com/');
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($status);
    header('Content-Type: text/html; charset=windows-1250');
    echo $body;
    exit;
}

if ($action === 'download') {
    $targetUrl = $_GET['url'] ?? '';
    $uid = $_GET['uid'] ?? '';
    $pass = $_GET['pass'] ?? '';
    $secure2 = $_GET['secure2'] ?? '';

    if (!str_starts_with($targetUrl, 'https://tracker.czech-server.com/')) {
        http_response_code(400);
        echo 'Invalid target URL';
        exit;
    }

    $cookieHeader = "uid={$uid}; pass={$pass}";
    if ($secure2) $cookieHeader .= "; secure2={$secure2}";

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_COOKIE, $cookieHeader);
    curl_setopt($ch, CURLOPT_REFERER, 'https://tracker.czech-server.com/');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $fileData = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($status);
    header('Content-Type: application/x-bittorrent');
    echo $fileData;
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Neznáma akcia']);
