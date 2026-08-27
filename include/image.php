<?php
// include/image.php - OpenSim Texture JP2 to JPG Proxy Converter
include_once __DIR__ . "/config.php";

$image_url = isset($_GET['image_url']) ? trim($_GET['image_url']) : '';

if (empty($image_url)) {
    http_response_code(400);
    die('No image URL provided!');
}

/**
 * Only ever fetch from this grid's own asset/texture server. Without this
 * check, $image_url is fully attacker-controlled and this endpoint becomes
 * an open SSRF proxy - internal hosts, cloud metadata endpoints, file://
 * paths, anything the server can reach.
 */
function image_url_is_allowed(string $url): bool {
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    static $allowed = null;
    if ($allowed === null) {
        $allowed = [];
        foreach ([GRID_ASSETS_SERVER, TEXTURE_PNG_SERVICE_URL] as $base) {
            $b = parse_url($base);
            if (!empty($b['host'])) {
                $bScheme = strtolower($b['scheme'] ?? 'http');
                $bPort   = $b['port'] ?? ($bScheme === 'https' ? 443 : 80);
                $allowed[strtolower($b['host']) . ':' . $bPort] = true;
            }
        }
    }

    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
    return isset($allowed[strtolower($parts['host']) . ':' . $port]);
}

if (!image_url_is_allowed($image_url)) {
    http_response_code(403);
    die('This image URL is not from an allowed source.');
}

function fetch_image_data(string $url) {
    // Attempt 1: file_get_contents
    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'user_agent' => 'OpenSimWebHelper/1.0',
                'follow_location' => 0,
            ]
        ];
        $context = stream_context_create($opts);
        $data = @file_get_contents($url, false, $context);
        if ($data !== false && strlen($data) > 0) {
            return $data;
        }
    }

    // Attempt 2: cURL Fallback
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'OpenSimWebHelper/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $data !== false && strlen($data) > 0) {
            return $data;
        }
    }

    return false;
}

$image_data = fetch_image_data($image_url);

if ($image_data === false) {
    http_response_code(404);
    die('Error loading image!');
}

try {
    $imagick = new Imagick();
    $imagick->readImageBlob($image_data);

    if (in_array(strtoupper($imagick->getImageFormat()), ['JPEG2000', 'JP2', 'J2K'])) {
        $imagick->setImageFormat('jpg');
    }

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    echo $imagick->getImageBlob();
    $imagick->clear();
    $imagick->destroy();
} catch (Exception $e) {
    http_response_code(500);
    die('Error converting image: ' . $e->getMessage());
}
?>