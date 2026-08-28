<?php
// Live region-online check. regions.last_seen only reflects registration/
// startup time on this grid's OpenSim setup, not an ongoing heartbeat, so
// it can't be used to tell whether a region that's been running for hours
// is still online - only a real connection attempt can.

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

if (!function_exists('__region_status_resolve')) {
    function __region_status_resolve(string $host, array &$ipCache): string {
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        if (!isset($ipCache[$host])) {
            $ip = @gethostbyname($host);
            $ipCache[$host] = ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : $host;
        }
        return $ipCache[$host];
    }
}

if (!function_exists('__regions_online_map_sockets_ext')) {
    // Preferred path: the raw sockets extension lets us force an abortive
    // close (SO_LINGER=0) on connection attempts that never completed.
    // Without this, closing a still-connecting socket blocks for a very
    // noticeable ~0.5s EACH on at least this host/OS combination (confirmed
    // by direct measurement) - whether closed explicitly or implicitly via
    // PHP's own refcount cleanup at request end - which defeats the entire
    // point of checking regions concurrently.
    function __regions_online_map_sockets_ext(array $regions, float $timeout): array {
        $result = [];
        $sockets = [];
        $ipCache = [];

        foreach ($regions as $key => $target) {
            $host = (string)($target['host'] ?? '');
            $port = (int)($target['port'] ?? 0);
            $result[$key] = false;
            if ($host === '' || $port <= 0) {
                continue;
            }
            $ip = __region_status_resolve($host, $ipCache);
            if (!filter_var($ip, FILTER_VALIDATE_IP) || strpos($ip, ':') !== false) {
                continue; // only IPv4 handled here
            }
            $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($sock === false) {
                continue;
            }
            socket_set_nonblock($sock);
            @socket_connect($sock, $ip, $port);
            $sockets[$key] = $sock;
        }

        if (!empty($sockets)) {
            $deadline = microtime(true) + $timeout;
            while (!empty($sockets)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    break;
                }
                $read = [];
                $write = array_values($sockets);
                $except = [];
                $sec = (int)$remaining;
                $usec = (int)(($remaining - $sec) * 1_000_000);

                $changed = @socket_select($read, $write, $except, $sec, $usec);
                if ($changed === false || $changed === 0) {
                    break;
                }

                foreach ($sockets as $key => $sock) {
                    if (in_array($sock, $write, true)) {
                        $err = socket_get_option($sock, SOL_SOCKET, SO_ERROR);
                        $result[$key] = ($err === 0);
                        __region_status_abortive_close($sock);
                        unset($sockets[$key]);
                    }
                }
            }
        }

        // Anything left here never completed within the deadline - force-close
        // it too rather than leaving it (or letting request shutdown do it).
        foreach ($sockets as $sock) {
            __region_status_abortive_close($sock);
        }

        return $result;
    }
}

if (!function_exists('__region_status_abortive_close')) {
    function __region_status_abortive_close($sock): void {
        @socket_set_option($sock, SOL_SOCKET, SO_LINGER, ['l_onoff' => 1, 'l_linger' => 0]);
        @socket_close($sock);
    }
}

if (!function_exists('__regions_online_map_streams')) {
    // Fallback for installs without the sockets extension. Leaks any
    // never-completed sockets into a static array for the rest of the
    // request instead of closing them, since closing (or letting PHP's
    // refcount cleanup close) a still-connecting stream pays the same
    // ~0.5s-per-socket cost this whole function exists to avoid. The OS
    // reclaims them for free when the request/process actually ends.
    function __regions_online_map_streams(array $regions, float $timeout): array {
        $result = [];
        $sockets = [];
        $ipCache = [];

        foreach ($regions as $key => $target) {
            $host = (string)($target['host'] ?? '');
            $port = (int)($target['port'] ?? 0);
            $result[$key] = false;
            if ($host === '' || $port <= 0) {
                continue;
            }
            $ip = __region_status_resolve($host, $ipCache);
            $errno = 0;
            $errstr = '';
            $sock = @stream_socket_client(
                "tcp://{$ip}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
            if ($sock !== false) {
                $sockets[$key] = $sock;
            }
        }

        if (!empty($sockets)) {
            $deadline = microtime(true) + $timeout;
            while (!empty($sockets)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    break;
                }
                $read = [];
                $write = array_values($sockets);
                $except = null;
                $sec = (int)$remaining;
                $usec = (int)(($remaining - $sec) * 1_000_000);

                $changed = @stream_select($read, $write, $except, $sec, $usec);
                if ($changed === false || $changed === 0) {
                    break;
                }

                foreach ($sockets as $key => $sock) {
                    if (in_array($sock, $write, true)) {
                        $result[$key] = (@stream_socket_get_name($sock, true) !== false);
                        fclose($sock);
                        unset($sockets[$key]);
                    }
                }
            }
        }

        static $leakedSockets = [];
        foreach ($sockets as $sock) {
            $leakedSockets[] = $sock;
        }

        return $result;
    }
}

if (!function_exists('regions_online_map')) {
    /**
     * Check many regions' online status concurrently, bounded by roughly
     * one $timeout window total instead of one timeout per region.
     *
     * $regions: [key => ['host' => string, 'port' => int]]
     * Returns:  [key => bool]
     */
    function regions_online_map(array $regions, float $timeout = 1.0): array {
        if (function_exists('socket_create')) {
            return __regions_online_map_sockets_ext($regions, $timeout);
        }
        return __regions_online_map_streams($regions, $timeout);
    }
}

if (!function_exists('region_is_online')) {
    // Single-region convenience wrapper around regions_online_map(). Prefer
    // regions_online_map() directly when checking more than one region -
    // calling this in a loop re-serializes the concurrency it provides.
    function region_is_online(string $host, int $port, float $timeout = 1.0): bool {
        $map = regions_online_map(['_single' => ['host' => $host, 'port' => $port]], $timeout);
        return $map['_single'] ?? false;
    }
}
