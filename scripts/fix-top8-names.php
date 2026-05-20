#!/usr/bin/env php
<?php
/**
 * Fix top8 commander names by re-extracting from the SQL dump.
 * The SQL tuple parser truncates values containing ' // '.
 */

$DUMP_FILE = __DIR__ . '/../104dbb16-b827-4a0e-91f1-7a47216d36f8-mysql266.paupern221.2026-05-14-07h52';
$TOURNAMENTS_DIR = __DIR__ . '/../site/content/tournaments';

$dump = file_get_contents($DUMP_FILE);

// Build post_id -> slug map
$slugs = [];
// Quick: search for each tournament JSON and map by checking post titles
foreach (glob("$TOURNAMENTS_DIR/*.json") as $f) {
    $d = json_decode(file_get_contents($f), true);
    $slug = basename($f, '.json');
    $title = $d['title'];
    // Find post ID by title in dump
    $escaped_title = preg_quote($title, '/');
    if (preg_match("/\((\d+),\d+,'[^']*','[^']*','[^']*','$escaped_title'/", $dump, $m)) {
        $slugs[(int)$m[1]] = $slug;
    }
}

echo "Mapped " . count($slugs) . " tournaments to post IDs\n";

// Extract all top8_N_commander_name values with post_id
$needle = "_commander_name','";
$offset = 0;
$top8_data = []; // post_id => [index => name]

while (($pos = strpos($dump, $needle, $offset)) !== false) {
    // Check this is a top8 field (not participants)
    $pre = substr($dump, max(0, $pos - 30), 30);
    if (strpos($pre, 'top8_') === false) {
        $offset = $pos + 10;
        continue;
    }

    // Extract post_id
    if (!preg_match('/\((\d+),(\d+),\'top8_(\d+)_$/', $pre, $pm)) {
        // Try wider context
        $pre2 = substr($dump, max(0, $pos - 50), 50);
        if (!preg_match('/\((\d+),(\d+),\'top8_(\d+)_/', $pre2, $pm)) {
            $offset = $pos + 10;
            continue;
        }
    }

    $post_id = (int)$pm[2];
    $index = (int)$pm[3];

    // Extract value
    $val_start = $pos + strlen($needle);
    $val = '';
    $i = $val_start;
    $len = strlen($dump);
    while ($i < $len) {
        $ch = $dump[$i];
        if ($ch === '\\' && $i + 1 < $len) {
            $next = $dump[$i + 1];
            if ($next === "'") { $val .= "'"; $i += 2; continue; }
            if ($next === '\\') { $val .= '\\'; $i += 2; continue; }
            $val .= $next; $i += 2; continue;
        }
        if ($ch === "'") { break; }
        $val .= $ch;
        $i++;
    }

    if (!empty(trim($val)) && strpos($val, 'field_') !== 0) {
        // Normalize // separator
        $val = preg_replace('/\s*\/\/\s*/', ' // ', trim($val));
        $top8_data[$post_id][$index] = $val;
    }

    $offset = $i;
}

echo "Found top8 commander names for " . count($top8_data) . " posts\n\n";

// Update tournament JSONs
$updated = 0;
foreach ($top8_data as $post_id => $entries) {
    $slug = $slugs[$post_id] ?? null;
    if (!$slug) continue;

    $json_path = "$TOURNAMENTS_DIR/$slug.json";
    if (!file_exists($json_path)) continue;

    $data = json_decode(file_get_contents($json_path), true);
    $changed = false;

    foreach ($data['top8'] as &$entry) {
        $idx = $entry['place'] - 1; // place is 1-indexed, data is 0-indexed
        // Try to find matching index
        foreach ($entries as $dump_idx => $dump_name) {
            // Match by similar name (our entry might be truncated)
            if (strpos(strtolower($dump_name), strtolower($entry['commanderName'])) === 0
                && $dump_name !== $entry['commanderName']) {
                echo "  $slug: [{$entry['commanderName']}] -> [$dump_name]\n";
                $entry['commanderName'] = $dump_name;
                $changed = true;
                break;
            }
        }
    }
    unset($entry);

    if ($changed) {
        file_put_contents($json_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $updated++;
    }
}

echo "\nUpdated $updated tournament files.\n";
