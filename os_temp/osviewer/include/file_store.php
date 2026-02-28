<?php
function safe_write_json(string $path, $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) { if (!@mkdir($dir, 0775, true)) return false; }
    $tmp = $path . '.tmp';
    $bak = $path . '.bak';
    $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    if (file_exists($path)) { @copy($path, $bak); }
    $ok = @file_put_contents($tmp, $json, LOCK_EX) !== false;
    if (!$ok) return false;
    return @rename($tmp, $path);
}
