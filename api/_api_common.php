<?php
// Shared helpers for this site's JSON API endpoints
// (economy_api.php, friends_api.php, groups_api.php)

if (!function_exists('uuidv4')) {
    function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('resolve_avatar')) {
    // Resolve avatar by UUID or "First Last"
    function resolve_avatar(mysqli $con, string $input): ?array {
        $input = trim($input);
        if (strlen($input) === 36 && strpos($input, '-') !== false) {
            $uuid = mysqli_real_escape_string($con, $input);
            $res = mysqli_query(
                $con,
                "SELECT PrincipalID, FirstName, LastName
                 FROM UserAccounts
                 WHERE PrincipalID = '$uuid'
                 LIMIT 1"
            );
            if ($res && $row = mysqli_fetch_assoc($res)) {
                return $row;
            }
            return null;
        }

        $parts = preg_split('/\s+/', $input);
        if (count($parts) < 2) {
            return null;
        }
        $first = mysqli_real_escape_string($con, $parts[0]);
        $last  = mysqli_real_escape_string($con, $parts[1]);

        $res = mysqli_query(
            $con,
            "SELECT PrincipalID, FirstName, LastName
             FROM UserAccounts
             WHERE FirstName = '$first' AND LastName = '$last'
             LIMIT 1"
        );
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row;
        }
        return null;
    }
}
