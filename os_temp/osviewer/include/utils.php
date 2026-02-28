<?php
// Build a teleport link that works inside viewers.
// If HG_HOST is defined, prefer hop://; else fallback to secondlife:// schema.
function build_teleport(string $regionName, string $posCSV): string {
    $region = str_replace(' ', '%20', $regionName);
    $parts = array_map('trim', explode(',', $posCSV));
    $x = isset($parts[0]) ? (int)$parts[0] : 128;
    $y = isset($parts[1]) ? (int)$parts[1] : 128;
    $z = isset($parts[2]) ? (int)$parts[2] : 25;
    if (defined('HG_HOST') && HG_HOST) {
        // hop://host:port/Region/x/y/z
        return 'hop://' . HG_HOST . '/' . $region . '/' . $x . '/' . $y . '/' . $z;
    }
    // secondlife://Region/x/y/z
    return 'secondlife://' . $region . '/' . $x . '/' . $y . '/' . $z;
}
