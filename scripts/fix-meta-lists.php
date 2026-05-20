#!/usr/bin/env php
<?php
/**
 * Fix tournament meta lists by re-extracting from the SQL dump.
 * The SQL tuple parser truncates some multiline values.
 */

$DUMP_FILE = __DIR__ . '/../104dbb16-b827-4a0e-91f1-7a47216d36f8-mysql266.paupern221.2026-05-14-07h52';
$TOURNAMENTS_DIR = __DIR__ . '/../site/content/tournaments';

$dump = file_get_contents($DUMP_FILE);

// Step 1: Build post_id -> slug map for tournaments
$slugs = [];
// Find tournament posts: (...,'slug','','publish',...,'tournament',...)
preg_match_all("/\((\d+),\d+,'[^']*','[^']*','[^']*','([^']+)','[^']*','publish','[^']*','[^']*','[^']*','([a-z0-9-]+)'/", $dump, $m, PREG_SET_ORDER);
foreach ($m as $match) {
    $slugs[(int)$match[1]] = $match[3];
}

// Step 2: Find all tournament_meta_list values with post_id
// Format: (meta_id,post_id,'tournament_meta_list','value')
// The \r\n in the dump are literal backslash-r-backslash-n (not real CR/LF)
$offset = 0;
$needle = "tournament_meta_list','";
$results = [];

while (($pos = strpos($dump, $needle, $offset)) !== false) {
    // Find the post_id: scan backward for pattern (meta_id,post_id,'
    $pre = substr($dump, max(0, $pos - 20), 20);
    if (preg_match('/\((\d+),(\d+),\'$/', $pre, $pm)) {
        $post_id = (int)$pm[2];
    } else {
        $offset = $pos + 20;
        continue;
    }

    // Extract the value: starts after the needle
    // In the raw dump, the value ends at ') — but we need to handle \' escapes
    $val_start = $pos + strlen($needle);
    $val = '';
    $i = $val_start;
    $len = strlen($dump);
    while ($i < $len) {
        $ch = $dump[$i];
        if ($ch === '\\' && $i + 1 < $len) {
            $next = $dump[$i + 1];
            // MySQL escape sequences in the raw dump
            if ($next === 'r') { $val .= "\r"; $i += 2; continue; }
            if ($next === 'n') { $val .= "\n"; $i += 2; continue; }
            if ($next === "'") { $val .= "'"; $i += 2; continue; }
            if ($next === '\\') { $val .= '\\'; $i += 2; continue; }
            $val .= $next; $i += 2; continue;
        }
        if ($ch === "'") {
            // End of value (next char should be ')' or ',')
            break;
        }
        $val .= $ch;
        $i++;
    }

    // Skip field references and empty values
    if ($val !== 'field_tournament_meta_list' && trim($val) !== '') {
        $results[$post_id] = $val;
        echo "  post_id=$post_id: " . substr_count($val, "\n") . " newlines\n";
    }

    $offset = $i;
}

echo "Found " . count($results) . " tournament_meta_list values\n\n";

// Step 3: Parse each meta_list and update the tournament JSON
function parse_meta_list($text) {
    $counts = [];
    $canon = [];
    foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
            $qty = (int)$m[1];
            $name = trim($m[2]);
        } else {
            $qty = 1;
            $name = $line;
        }
        if ($name === '' || $qty < 1) continue;
        $name = preg_replace('/\s*\/\/\s*/', ' // ', $name);
        $key = strtolower($name);
        if (!isset($canon[$key])) $canon[$key] = $name;
        $counts[$key] = ($counts[$key] ?? 0) + $qty;
    }
    arsort($counts);
    $result = [];
    foreach ($counts as $key => $count) {
        $result[] = ['name' => $canon[$key], 'count' => $count];
    }
    return $result;
}

$updated = 0;
foreach ($results as $post_id => $raw_meta) {
    $slug = $slugs[$post_id] ?? null;
    if (!$slug) {
        echo "  WARNING: No slug for post_id $post_id\n";
        continue;
    }

    $json_path = "$TOURNAMENTS_DIR/$slug.json";
    if (!file_exists($json_path)) {
        echo "  WARNING: No JSON file for $slug\n";
        continue;
    }

    $parsed = parse_meta_list($raw_meta);
    $total = array_sum(array_column($parsed, 'count'));

    $data = json_decode(file_get_contents($json_path), true);
    $old_count = array_sum(array_column($data['metaList'] ?? [], 'count'));

    if ($total > $old_count) {
        $data['metaList'] = $parsed;
        file_put_contents($json_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        echo "  UPDATED $slug.json: $old_count -> $total entries (" . count($parsed) . " commanders)\n";
        $updated++;
    } else {
        echo "  OK $slug.json: already has $old_count entries\n";
    }
}

echo "\nUpdated $updated tournament files.\n";
