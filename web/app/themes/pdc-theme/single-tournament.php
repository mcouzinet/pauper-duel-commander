<?php
/**
 * Single Tournament Template
 *
 * Displays the detail page for a single tournament.
 * URL: /tournoi/{slug}/
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context = Timber::context();
$post    = Timber::get_post();
$fields  = get_fields($post->ID) ?: array();

// Collect all unique commander names for bulk Scryfall fetch
$commander_names = array();
foreach (($fields['top8'] ?? array()) as $entry) {
    if (!empty($entry['commander_name'])) {
        $commander_names[] = $entry['commander_name'];
    }
}
foreach (($fields['participants'] ?? array()) as $p) {
    if (!empty($p['commander_name'])) {
        $commander_names[] = $p['commander_name'];
    }
}

$unique_names = array_values(array_unique(array_filter($commander_names)));
$cards_map    = !empty($unique_names)
    ? Scryfall_Service::get_cards_by_names($unique_names)
    : array();

// ----------------------------------------------------------------
// Enrich top 8 entries
// ----------------------------------------------------------------
$top8 = array();
foreach (($fields['top8'] ?? array()) as $entry) {
    $cmd_key   = strtolower($entry['commander_name'] ?? '');
    $card_data = $cards_map[$cmd_key] ?? null;

    $top8[] = array(
        'place'           => (int) ($entry['place'] ?? 0),
        'player_name'     => $entry['player_name'] ?? '',
        'commander_name'  => $entry['commander_name'] ?? '',
        'score'           => $entry['score'] ?? '',
        'commander_image' => $card_data ? Scryfall_Service::get_card_image($card_data, 'art_crop') : null,
        'decklist_url'    => !empty($entry['decklist_post']) ? get_permalink($entry['decklist_post']) : null,
    );
}
usort($top8, fn($a, $b) => $a['place'] - $b['place']);

// ----------------------------------------------------------------
// Compute meta stats
// ----------------------------------------------------------------
$commander_counts   = array();
$color_counts       = array();
$total_participants = count(array_filter($fields['participants'] ?? array(), fn($p) => !empty($p['commander_name'])));

foreach (($fields['participants'] ?? array()) as $p) {
    $name = trim($p['commander_name'] ?? '');
    if (empty($name)) {
        continue;
    }
    $commander_counts[$name] = ($commander_counts[$name] ?? 0) + 1;

    $card_data = $cards_map[strtolower($name)] ?? null;
    if ($card_data && !empty($card_data->color_identity)) {
        foreach ($card_data->color_identity as $color) {
            $color_counts[$color] = ($color_counts[$color] ?? 0) + 1;
        }
    } elseif ($card_data) {
        $color_counts['C'] = ($color_counts['C'] ?? 0) + 1;
    }
}
arsort($commander_counts);

$meta_commanders = array();
foreach ($commander_counts as $name => $count) {
    $card_data = $cards_map[strtolower($name)] ?? null;
    $meta_commanders[] = array(
        'name'       => $name,
        'count'      => $count,
        'percentage' => $total_participants > 0 ? round($count / $total_participants * 100) : 0,
        'image'      => $card_data ? Scryfall_Service::get_card_image($card_data, 'art_crop') : null,
    );
}

// ----------------------------------------------------------------
// Format date
// ----------------------------------------------------------------
$date_raw       = $fields['tournament_date'] ?? '';
$date_formatted = '';
if ($date_raw) {
    $dt = DateTime::createFromFormat('Ymd', $date_raw);
    if ($dt) {
        $date_formatted = wp_date('j F Y', $dt->getTimestamp());
    }
}

// Hero background: art_crop of the 1st place commander
$hero_image = null;
if (!empty($top8) && $top8[0]['place'] === 1 && !empty($top8[0]['commander_image'])) {
    $hero_image = $top8[0]['commander_image'];
}

$context['post']             = $post;
$context['date_formatted']   = $date_formatted;
$context['location']         = $fields['tournament_location'] ?? '';
$context['city']             = $fields['tournament_city'] ?? '';
$context['player_count']     = (int) ($fields['tournament_player_count'] ?? 0);
$context['signup_url']       = $fields['tournament_signup_url'] ?? '';
$context['top8']             = $top8;
$context['meta_commanders']  = $meta_commanders;
$context['color_counts']     = $color_counts;
$context['hero_image']       = $hero_image;
$context['has_meta']         = !empty($meta_commanders);
$context['archive_url']      = get_post_type_archive_link('tournament');

Timber::render('single-tournament.twig', $context);
