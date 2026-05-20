#!/usr/bin/env php
<?php
/**
 * Export WordPress data to JSON for Astro migration.
 *
 * Parses the SQL dump file DIRECTLY — no MySQL server needed.
 * Extracts INSERT statements for posts, postmeta, terms, etc.
 *
 * Usage:
 *   php scripts/export-wp-data.php
 *
 * @package PDC Migration
 */

$DUMP_FILE = __DIR__ . '/../104dbb16-b827-4a0e-91f1-7a47216d36f8-mysql266.paupern221.2026-05-14-07h52';
$OUT       = __DIR__ . '/../site/content';
$PREFIX    = 'mod17_';

if (!file_exists($DUMP_FILE)) {
    die("ERROR: Dump file not found: {$DUMP_FILE}\n");
}

echo "Parsing SQL dump: " . basename($DUMP_FILE) . " (" . round(filesize($DUMP_FILE) / 1024 / 1024, 1) . " MB)\n\n";

$dump = file_get_contents($DUMP_FILE);

// ---------------------------------------------------------------------------
// SQL Parser — extract rows from INSERT statements
// ---------------------------------------------------------------------------

/**
 * Decode a MySQL escape sequence character.
 */
function mysql_unescape_char(string $ch): string {
    $map = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0"];
    return isset($map[$ch]) ? $map[$ch] : $ch;
}

/**
 * Extract all rows from INSERT INTO statements for a given table.
 * Works by finding "INSERT INTO `table` VALUES " then parsing character-by-character
 * to correctly handle escaped quotes and semicolons inside string values.
 */
function extract_table_rows(string $dump, string $table): array {
    $rows = [];
    $needle = "INSERT INTO `{$table}` VALUES ";
    $needle_len = strlen($needle);
    $dump_len = strlen($dump);
    $offset = 0;

    while (($pos = strpos($dump, $needle, $offset)) !== false) {
        $pos += $needle_len; // skip past "VALUES "

        // Parse rows character by character until we hit a ; outside of quotes
        $in_string = false;
        $escape_next = false;
        $depth = 0;
        $current_row = '';
        $i = $pos;

        while ($i < $dump_len) {
            $ch = $dump[$i];

            if ($escape_next) {
                $current_row .= $ch;
                $escape_next = false;
                $i++;
                continue;
            }

            if ($ch === '\\' && $in_string) {
                $next = $i + 1 < $dump_len ? $dump[$i + 1] : '';
                $current_row .= mysql_unescape_char($next);
                $i += 2;
                continue;
            }

            if ($ch === "'" && !$in_string) {
                $in_string = true;
                $i++;
                continue;
            }

            if ($ch === "'" && $in_string) {
                // Check for '' (escaped quote in SQL)
                if ($i + 1 < $dump_len && $dump[$i + 1] === "'") {
                    $current_row .= "'";
                    $i += 2;
                    continue;
                }
                $in_string = false;
                $i++;
                continue;
            }

            if ($in_string) {
                $current_row .= $ch;
                $i++;
                continue;
            }

            // Outside of strings
            if ($ch === '(') {
                $depth++;
                if ($depth === 1) {
                    $current_row = '';
                    $i++;
                    continue;
                }
            }

            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $rows[] = parse_row_values($current_row);
                    $current_row = '';
                    $i++;
                    continue;
                }
            }

            if ($ch === ';' && $depth === 0) {
                break; // end of INSERT statement
            }

            if ($depth > 0) {
                $current_row .= $ch;
            }

            $i++;
        }

        $offset = $i;
    }

    return $rows;
}

/**
 * Parse a comma-separated row string into array of values.
 */
function parse_row_values(string $row): array {
    $values = [];
    $current = '';
    $in_string = false;
    $escape_next = false;
    $len = strlen($row);

    for ($i = 0; $i < $len; $i++) {
        $ch = $row[$i];

        if ($escape_next) {
            $current .= mysql_unescape_char($ch);
            $escape_next = false;
            continue;
        }

        if ($ch === '\\') {
            $escape_next = true;
            continue;
        }

        if ($ch === "'" && !$in_string) {
            $in_string = true;
            continue; // skip opening quote
        }

        if ($ch === "'" && $in_string) {
            $in_string = false;
            continue; // skip closing quote
        }

        if ($in_string) {
            $current .= $ch;
            continue;
        }

        if ($ch === ',') {
            $values[] = trim($current) === 'NULL' ? null : $current;
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    // Last value
    $values[] = trim($current) === 'NULL' ? null : $current;

    return $values;
}

// ---------------------------------------------------------------------------
// Parse all tables we need
// ---------------------------------------------------------------------------

echo "Extracting tables...\n";

// Posts table columns (from MySQL dump):
// ID, post_author, post_date, post_date_gmt, post_content, post_title,
// post_excerpt, post_status, comment_status, ping_status, post_password,
// post_name, to_ping, pinged, post_modified, post_modified_gmt,
// post_content_filtered, post_parent, guid, menu_order, post_type,
// post_mime_type, comment_count
$posts_raw = extract_table_rows($dump, "{$PREFIX}posts");
$posts = [];
foreach ($posts_raw as $r) {
    $posts[] = [
        'ID'           => (int) $r[0],
        'post_date'    => $r[2],
        'post_content' => $r[4],
        'post_title'   => $r[5],
        'post_status'  => $r[7],
        'post_name'    => $r[11],
        'post_type'    => $r[20],
    ];
}
echo "  posts: " . count($posts) . " rows\n";

// Postmeta: meta_id, post_id, meta_key, meta_value
$postmeta_raw = extract_table_rows($dump, "{$PREFIX}postmeta");
$postmeta = []; // post_id => [key => value]
foreach ($postmeta_raw as $r) {
    $pid = (int) $r[1];
    $postmeta[$pid][$r[2]] = $r[3];
}
echo "  postmeta: " . count($postmeta_raw) . " rows\n";

// Terms: term_id, name, slug, term_group
$terms_raw = extract_table_rows($dump, "{$PREFIX}terms");
$terms = []; // term_id => [name, slug]
foreach ($terms_raw as $r) {
    $terms[(int)$r[0]] = ['name' => $r[1], 'slug' => $r[2]];
}
echo "  terms: " . count($terms) . " rows\n";

// Term taxonomy: term_taxonomy_id, term_id, taxonomy, description, parent, count
$term_tax_raw = extract_table_rows($dump, "{$PREFIX}term_taxonomy");
$term_tax = []; // term_taxonomy_id => [term_id, taxonomy]
foreach ($term_tax_raw as $r) {
    $term_tax[(int)$r[0]] = ['term_id' => (int)$r[1], 'taxonomy' => $r[2]];
}
echo "  term_taxonomy: " . count($term_tax) . " rows\n";

// Term relationships: object_id, term_taxonomy_id, term_order
$term_rel_raw = extract_table_rows($dump, "{$PREFIX}term_relationships");
$term_rels = []; // object_id => [term_taxonomy_id, ...]
foreach ($term_rel_raw as $r) {
    $term_rels[(int)$r[0]][] = (int)$r[1];
}
echo "  term_relationships: " . count($term_rel_raw) . " rows\n\n";

// Free the raw dump from memory
unset($dump, $posts_raw, $postmeta_raw, $terms_raw, $term_tax_raw, $term_rel_raw);

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

function get_meta(int $post_id, string $key): string {
    global $postmeta;
    return $postmeta[$post_id][$key] ?? '';
}

function get_post_slug_by_id(int $id): ?string {
    global $posts;
    foreach ($posts as $p) {
        if ($p['ID'] === $id) return $p['post_name'];
    }
    return null;
}

function get_taxonomy_terms_for_post(int $post_id, string $taxonomy): array {
    global $term_rels, $term_tax, $terms;
    $result = [];
    foreach ($term_rels[$post_id] ?? [] as $tt_id) {
        $tt = $term_tax[$tt_id] ?? null;
        if ($tt && $tt['taxonomy'] === $taxonomy) {
            $t = $terms[$tt['term_id']] ?? null;
            if ($t) $result[] = $t['name'];
        }
    }
    return $result;
}

function parse_meta_list(string $text): array {
    $counts = [];
    $canon = [];
    foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
            $qty = (int) $m[1];
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

function format_ymd_date(string $raw): string {
    if (strlen($raw) === 8 && ctype_digit($raw)) {
        return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $raw;
}

function write_json(string $path, $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}

function fetch_scryfall_card_name(string $set, string $number): ?string {
    static $last = 0;
    $now = microtime(true);
    if ($now - $last < 0.12) usleep((int)((0.12 - ($now - $last)) * 1e6));
    $url = "https://api.scryfall.com/cards/" . urlencode($set) . "/" . urlencode($number);

    // Use curl if available (more reliable SSL handling than file_get_contents)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'PDC-Export/1.0',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $last = microtime(true);
        $json = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($json === false || $code !== 200) return null;
    } else {
        $ctx = stream_context_create(['http' => [
            'header' => "User-Agent: PDC-Export/1.0\r\n",
            'timeout' => 10,
        ]]);
        $last = microtime(true);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) return null;
    }

    $data = json_decode($json, true);
    return $data['name'] ?? null;
}

// ---------------------------------------------------------------------------
// 1. Export Tournaments
// ---------------------------------------------------------------------------

echo "--- TOURNAMENTS ---\n";
$tournament_count = 0;

foreach ($posts as $post) {
    if ($post['post_type'] !== 'tournament' || $post['post_status'] !== 'publish') continue;

    $id = $post['ID'];
    $date_raw = get_meta($id, 'tournament_date');
    $date = $date_raw ? format_ymd_date($date_raw) : substr($post['post_date'], 0, 10);

    // Parse top8 repeater
    $top8_count = (int) get_meta($id, 'top8');
    $top8 = [];
    for ($i = 0; $i < $top8_count; $i++) {
        $commander = get_meta($id, "top8_{$i}_commander_name");
        $commander = preg_replace('/\s*\/\/\s*/', ' // ', $commander);
        $dl_id = (int) get_meta($id, "top8_{$i}_decklist_post");
        $top8[] = [
            'place'         => (int) (get_meta($id, "top8_{$i}_place") ?: ($i + 1)),
            'playerName'    => get_meta($id, "top8_{$i}_player_name"),
            'commanderName' => $commander,
            'score'         => get_meta($id, "top8_{$i}_score"),
            'decklistSlug'  => $dl_id > 0 ? (get_post_slug_by_id($dl_id) ?? '') : '',
        ];
    }
    usort($top8, fn($a, $b) => $a['place'] - $b['place']);

    $meta_list = parse_meta_list(get_meta($id, 'tournament_meta_list'));

    $actual = get_meta($id, 'tournament_actual_player_count');
    $data = [
        'title'             => $post['post_title'],
        'date'              => $date,
        'location'          => get_meta($id, 'tournament_location'),
        'city'              => get_meta($id, 'tournament_city'),
        'playerCount'       => (int) get_meta($id, 'tournament_player_count'),
        'actualPlayerCount' => $actual !== '' ? (int) $actual : null,
        'signupUrl'         => get_meta($id, 'tournament_signup_url'),
        'details'           => get_meta($id, 'tournament_details'),
        'top8'              => $top8,
        'metaList'          => $meta_list,
    ];

    $slug = $post['post_name'];
    write_json("{$OUT}/tournaments/{$slug}.json", $data);
    echo "  [{$date}] {$post['post_title']} -> {$slug}.json ({$top8_count} top8, " . count($meta_list) . " meta)\n";
    $tournament_count++;
}
echo "Exported {$tournament_count} tournaments.\n\n";

// ---------------------------------------------------------------------------
// 2. Export Decklists
// ---------------------------------------------------------------------------

echo "--- DECKLISTS ---\n";
$decklist_count = 0;

foreach ($posts as $post) {
    if ($post['post_type'] !== 'decklist' || $post['post_status'] !== 'publish') continue;

    $id = $post['ID'];
    $date_raw = get_meta($id, 'deck_date');
    $date = $date_raw ? format_ymd_date($date_raw) : substr($post['post_date'], 0, 10);

    $authors = get_taxonomy_terms_for_post($id, 'deck_author');
    $archetypes = get_taxonomy_terms_for_post($id, 'deck_archetype');

    $data = [
        'title'     => $post['post_title'],
        'commander' => get_meta($id, 'commander'),
        'partner'   => get_meta($id, 'partner'),
        'date'      => $date,
        'author'    => implode(', ', $authors),
        'archetype' => implode(', ', $archetypes),
        'cards'     => get_meta($id, 'decklist'),
    ];

    $slug = $post['post_name'];
    write_json("{$OUT}/decklists/{$slug}.json", $data);

    $lines = $data['cards'] ? substr_count(trim($data['cards']), "\n") + 1 : 0;
    echo "  {$post['post_title']} -> {$slug}.json (cmdr: {$data['commander']}, {$lines} lines)\n";
    $decklist_count++;
}
echo "Exported {$decklist_count} decklists.\n\n";

// ---------------------------------------------------------------------------
// 3. Export Ban List
// ---------------------------------------------------------------------------

echo "--- BAN LIST ---\n";

// Extract ban list directly from the raw dump file (more reliable than parsed content,
// because the ACF block JSON is deeply escaped in MySQL strings)
$raw_dump = file_get_contents($DUMP_FILE);
$scryfall_urls = [];

// Find all scryfall_url fields inside m07-ban-list blocks
// In the raw dump, these look like: cards_0_scryfall_url\":\"https://scryfall.com/card/SET/NUM/name\"
preg_match_all(
    '/cards_\d+_scryfall_url[\\\\]*"[:\\s]*[\\\\]*"(https?:[\\\\\/]*scryfall\.com[\\\\\/]*card[\\\\\/]*([a-z0-9]+)[\\\\\/]*([0-9a-z]+)[^"\\\\]*)/',
    $raw_dump,
    $matches
);

foreach ($matches[2] as $i => $set) {
    $num = $matches[3][$i];
    $key = "{$set}/{$num}";
    $scryfall_urls[$key] = ['set' => $set, 'number' => $num];
}
unset($raw_dump);

echo "  Found " . count($scryfall_urls) . " unique banned card URLs.\n";

$banned = [];
foreach ($scryfall_urls as $key => $info) {
    $name = fetch_scryfall_card_name($info['set'], $info['number']);
    if ($name) {
        $banned[] = $name;
        echo "  Fetched: {$name} ({$key})\n";
    } else {
        echo "  FAILED: {$key}\n";
    }
}

sort($banned);
write_json("{$OUT}/banlist.json", [
    'lastUpdated' => date('Y-m-d'),
    'cards' => $banned,
]);
echo "Exported " . count($banned) . " banned cards.\n\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "============================\n";
echo "  EXPORT SUMMARY\n";
echo "============================\n";
echo "  Tournaments : {$tournament_count}\n";
echo "  Decklists   : {$decklist_count}\n";
echo "  Banned cards: " . count($banned) . "\n";
echo "  Output dir  : {$OUT}/\n";
echo "============================\n\n";
echo "Done.\n";
