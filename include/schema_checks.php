<?php
// Shared MySQL schema-introspection helpers (used where a page needs to
// tolerate different fork's table/column naming, e.g. regions vs
// GridRegions, estate_settings vs EstateSettings).

if (!function_exists('osv_table_exists')) {
    function osv_table_exists(mysqli $c, string $t): bool {
        $t = $c->real_escape_string($t);
        if ($rs = $c->query("SHOW TABLES LIKE '{$t}'")) {
            $ok = $rs->num_rows > 0;
            $rs->close();
            return $ok;
        }
        return false;
    }
}

if (!function_exists('osv_get_columns')) {
    function osv_get_columns(mysqli $c, string $t): array {
        $cols = [];
        if ($rs = $c->query("SHOW COLUMNS FROM `{$t}`")) {
            while ($row = $rs->fetch_assoc()) {
                $cols[strtolower($row['Field'])] = $row['Field'];
            }
            $rs->close();
        }
        return $cols;
    }
}

if (!function_exists('osv_pick_col')) {
    function osv_pick_col(array $cols, array $cands): ?string {
        foreach ($cands as $cand) {
            $k = strtolower($cand);
            if (isset($cols[$k])) return $cols[$k];
        }
        return null;
    }
}
