<?php
// Live region-online check. regions.last_seen only reflects registration/
// startup time on this grid's OpenSim setup, not an ongoing heartbeat, so
// it can't be used to tell whether a region that's been running for hours
// is still online - only a real connection attempt can.

if (!function_exists('region_is_online')) {
    function region_is_online(string $host, int $port, float $timeout = 0.75): bool {
        if ($host === '' || $port <= 0) {
            return false;
        }
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }
}

if (!function_exists('region_status_port')) {
    // OpenSim regions commonly expose an HTTP interface on serverHttpPort;
    // fall back to serverPort if that's missing/zero.
    function region_status_port(array $row): int {
        $httpPort = isset($row['serverHttpPort']) ? (int)$row['serverHttpPort'] : 0;
        if ($httpPort > 0) {
            return $httpPort;
        }
        return isset($row['serverPort']) ? (int)$row['serverPort'] : 0;
    }
}
