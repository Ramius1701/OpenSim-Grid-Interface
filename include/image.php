<?php
// include/image.php - OpenSim Texture JP2 to JPG Proxy Converter
include_once __DIR__ . "/config.php";

$image_url = isset($_GET['image_url']) ? trim($_GET['image_url']) : '';

if (empty($image_url)) {
    http_response_code(400);
    die('No image URL provided!');
}

function fetch_image_data(string $url) {
    // Attempt 1: file_get_contents
    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'user_agent' => 'OpenSimWebHelper/1.0'
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
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