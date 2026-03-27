<?php
/**
 * Template Name: Méta
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
// Per-tournament meta + global aggregation
// ----------------------------------------------------------------
$global_commander_counts = array();
$global_total_players    = 0;
$global_color_counts     = array();
$global_tournament_count = 0;

$tournaments_meta = array();

foreach ($posts as $post) {
    $fields = get_fields($post->ID) ?: array();

    $date_raw = $fields['tournament_date'] ?? '';

    // Skip upcoming tournaments (no meta data yet)
    if (!empty($date_raw) && $date_raw >= $today) {
        continue;
    }

    // Parse meta list textarea into commander counts
    $meta_raw         = $fields['tournament_meta_list'] ?? '';
    $commander_counts = pdc_parse_meta_list($meta_raw);

    if (empty($commander_counts)) {
        continue;
    }

    // Collect all commander names and expand partner pairs for Scryfall
    $commander_names = array();
    foreach (array_keys($commander_counts) as $name) {
        $commander_names[] = $name;
    }
    $expanded_names = pdc_expand_commander_names($commander_names);

    $unique_names = array_values(array_unique(array_filter($expanded_names)));
    $cards_map    = !empty($unique_names)
        ? Scryfall_Service::get_cards_by_names($unique_names)
        : array();

    // Compute per-tournament stats
    $color_counts       = array();
    $total_participants = array_sum($commander_counts);

    foreach ($commander_counts as $name => $count) {
        $card_data = pdc_resolve_commander_card($name, $cards_map);
        if ($card_data && !empty($card_data->color_identity)) {
            foreach ($card_data->color_identity as $color) {
                $color_counts[$color] = ($color_counts[$color] ?? 0) + $count;
            }
        } elseif ($card_data) {
            $color_counts['C'] = ($color_counts['C'] ?? 0) + $count;
        }
    }

    $meta_commanders = array();
    foreach ($commander_counts as $name => $count) {
        $card_data = pdc_resolve_commander_card($name, $cards_map);
        $meta_commanders[] = array(
            'name'       => $name,
            'count'      => $count,
            'percentage' => $total_participants > 0 ? round($count / $total_participants * 100) : 0,
            'image'      => $card_data ? Scryfall_Service::get_card_image($card_data, 'art_crop') : null,
            'colors'     => $card_data && !empty($card_data->color_identity) ? (array) $card_data->color_identity : array(),
        );
    }

    // Color identity breakdown for this tournament
    $color_identity_counts = array();
    foreach ($meta_commanders as $cmd) {
        $colors = $cmd['colors'];
        sort($colors);
        $identity_key = !empty($colors) ? implode('', $colors) : 'C';
        $color_identity_counts[$identity_key] = ($color_identity_counts[$identity_key] ?? 0) + $cmd['count'];
    }
    arsort($color_identity_counts);

    // Format date
    $date_formatted = '';
    if ($date_raw) {
        $dt = DateTime::createFromFormat('Ymd', $date_raw);
        if ($dt) {
            $date_formatted = wp_date('j F Y', $dt->getTimestamp());
        }
    }

    $tournaments_meta[] = array(
        'title'                 => $post->title,
        'link'                  => $post->link,
        'date_formatted'        => $date_formatted,
        'player_count'          => (int) ($fields['tournament_player_count'] ?? 0),
        'meta_commanders'       => $meta_commanders,
        'color_counts'          => pdc_sort_color_counts($color_counts),
        'color_identity_counts' => $color_identity_counts,
        'total_participants'    => $total_participants,
    );

    // Accumulate global stats
    $global_tournament_count++;
    foreach ($meta_commanders as $cmd) {
        $global_commander_counts[$cmd['name']] = ($global_commander_counts[$cmd['name']] ?? 0) + $cmd['count'];
        $global_total_players += $cmd['count'];
    }
}

// ----------------------------------------------------------------
// Global meta: resolve Scryfall data for aggregated commanders
// ----------------------------------------------------------------
arsort($global_commander_counts);

$global_expanded = pdc_expand_commander_names(array_keys($global_commander_counts));
$global_unique   = array_values(array_unique(array_filter($global_expanded)));
$global_cards    = !empty($global_unique)
    ? Scryfall_Service::get_cards_by_names($global_unique)
    : array();

$global_meta_commanders = array();
$global_color_counts    = array();

foreach ($global_commander_counts as $name => $count) {
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

$context['tournaments_meta'] = $tournaments_meta;

Timber::render('page-meta.twig', $context);
