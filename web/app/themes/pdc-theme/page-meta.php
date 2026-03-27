<?php
/**
 * Template Name: Meta
 *
 * Dedicated page for PDC metagame statistics.
 * Aggregates commander data across all past tournaments.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context         = Timber::context();
$context['post'] = Timber::get_post();

// Fetch all published tournaments, ordered by date descending
$posts = Timber::get_posts(array(
    'post_type'      => 'tournament',
    'posts_per_page' => -1,
    'meta_key'       => 'tournament_date',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
));

$today = date('Ymd');

// ----------------------------------------------------------------
// Aggregate commander counts across all past tournaments
// ----------------------------------------------------------------
$global_commander_counts = array();
$global_canon_names      = array(); // strtolower(name) => display name
$global_total_players    = 0;
$global_tournament_count = 0;
$top4_commander_counts   = array();
$top4_canon_names        = array();
$top4_total              = 0;

foreach ($posts as $post) {
    $fields = get_fields($post->ID) ?: array();

    $date_raw = $fields['tournament_date'] ?? '';

    // Skip upcoming tournaments (no meta data yet)
    if (!empty($date_raw) && $date_raw >= $today) {
        continue;
    }

    $meta_raw         = $fields['tournament_meta_list'] ?? '';
    $commander_counts = pdc_parse_meta_list($meta_raw);

    if (empty($commander_counts)) {
        continue;
    }

    $global_tournament_count++;
    foreach ($commander_counts as $name => $count) {
        $key = strtolower($name);
        if (!isset($global_canon_names[$key])) {
            $global_canon_names[$key] = $name;
        }
        $global_commander_counts[$key] = ($global_commander_counts[$key] ?? 0) + $count;
        $global_total_players += $count;
    }

    // Collect top 4 commanders from this tournament
    foreach (($fields['top8'] ?? array()) as $entry) {
        $place = (int) ($entry['place'] ?? 0);
        $name  = $entry['commander_name'] ?? '';
        if ($place >= 1 && $place <= 4 && $name !== '') {
            $key = strtolower($name);
            if (!isset($top4_canon_names[$key])) {
                $top4_canon_names[$key] = $name;
            }
            $top4_commander_counts[$key] = ($top4_commander_counts[$key] ?? 0) + 1;
            $top4_total++;
        }
    }
}

// ----------------------------------------------------------------
// Global meta: resolve Scryfall data for aggregated commanders
// ----------------------------------------------------------------
arsort($global_commander_counts);

$global_display_names = array_map(fn($key) => $global_canon_names[$key], array_keys($global_commander_counts));
$global_expanded = pdc_expand_commander_names($global_display_names);
$global_unique   = array_values(array_unique(array_filter($global_expanded)));
$global_cards    = !empty($global_unique)
    ? Scryfall_Service::get_cards_by_names($global_unique)
    : array();

$global_meta_commanders = array();
$global_color_counts    = array();

foreach ($global_commander_counts as $key => $count) {
    $name      = $global_canon_names[$key];
    $card_data = pdc_resolve_commander_card($name, $global_cards);

    if ($card_data && !empty($card_data->color_identity)) {
        foreach ($card_data->color_identity as $color) {
            $global_color_counts[$color] = ($global_color_counts[$color] ?? 0) + $count;
        }
    } elseif ($card_data) {
        $global_color_counts['C'] = ($global_color_counts['C'] ?? 0) + $count;
    }

    $global_meta_commanders[] = array(
        'name'       => $name,
        'count'      => $count,
        'percentage' => $global_total_players > 0 ? round($count / $global_total_players * 100) : 0,
        'image'      => $card_data ? Scryfall_Service::get_card_image($card_data, 'art_crop') : null,
        'colors'     => $card_data && !empty($card_data->color_identity) ? (array) $card_data->color_identity : array(),
    );
}

// Color identity meta for global
$global_color_identity_counts = array();
foreach ($global_meta_commanders as $cmd) {
    $colors = $cmd['colors'];
    sort($colors);
    $identity_key = !empty($colors) ? implode('', $colors) : 'C';
    $global_color_identity_counts[$identity_key] = ($global_color_identity_counts[$identity_key] ?? 0) + $cmd['count'];
}
arsort($global_color_identity_counts);

$context['global_meta'] = array(
    'commanders'            => $global_meta_commanders,
    'color_counts'          => pdc_sort_color_counts($global_color_counts),
    'color_identity_counts' => $global_color_identity_counts,
    'total_players'         => $global_total_players,
    'tournament_count'      => $global_tournament_count,
    'has_data'              => !empty($global_meta_commanders),
);

// ----------------------------------------------------------------
// Top 4 meta: resolve Scryfall data for top 4 commanders
// ----------------------------------------------------------------
arsort($top4_commander_counts);

$top4_display_names = array_map(fn($key) => $top4_canon_names[$key], array_keys($top4_commander_counts));
$top4_expanded = pdc_expand_commander_names($top4_display_names);
$top4_unique   = array_values(array_unique(array_filter($top4_expanded)));
// Reuse global_cards when possible, fetch missing ones
$top4_missing = array_diff($top4_unique, $global_unique);
$top4_cards   = $global_cards;
if (!empty($top4_missing)) {
    $top4_cards = array_merge($top4_cards, Scryfall_Service::get_cards_by_names(array_values($top4_missing)));
}

$top4_meta_commanders = array();
$top4_color_counts    = array();

foreach ($top4_commander_counts as $key => $count) {
    $name      = $top4_canon_names[$key];
    $card_data = pdc_resolve_commander_card($name, $top4_cards);

    if ($card_data && !empty($card_data->color_identity)) {
        foreach ($card_data->color_identity as $color) {
            $top4_color_counts[$color] = ($top4_color_counts[$color] ?? 0) + $count;
        }
    } elseif ($card_data) {
        $top4_color_counts['C'] = ($top4_color_counts['C'] ?? 0) + $count;
    }

    $top4_meta_commanders[] = array(
        'name'       => $name,
        'count'      => $count,
        'percentage' => $top4_total > 0 ? round($count / $top4_total * 100) : 0,
        'image'      => $card_data ? Scryfall_Service::get_card_image($card_data, 'art_crop') : null,
        'colors'     => $card_data && !empty($card_data->color_identity) ? (array) $card_data->color_identity : array(),
    );
}

$top4_color_identity_counts = array();
foreach ($top4_meta_commanders as $cmd) {
    $colors = $cmd['colors'];
    sort($colors);
    $identity_key = !empty($colors) ? implode('', $colors) : 'C';
    $top4_color_identity_counts[$identity_key] = ($top4_color_identity_counts[$identity_key] ?? 0) + $cmd['count'];
}
arsort($top4_color_identity_counts);

$context['top4_meta'] = array(
    'commanders'            => $top4_meta_commanders,
    'color_counts'          => pdc_sort_color_counts($top4_color_counts),
    'color_identity_counts' => $top4_color_identity_counts,
    'total'                 => $top4_total,
    'tournament_count'      => $global_tournament_count,
    'has_data'              => !empty($top4_meta_commanders),
);

Timber::render('page-meta.twig', $context);
