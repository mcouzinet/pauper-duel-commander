<?php
/**
 * Archive Tournament Template
 *
 * Displays the list of all tournaments with top 8 and meta stats.
 * URL: /tournoi/
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context = Timber::context();

// Fetch all published tournaments, ordered by date descending
$posts = Timber::get_posts(array(
    'post_type'      => 'tournament',
    'posts_per_page' => -1,
    'meta_key'       => 'tournament_date',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
));

$tournaments = array();

foreach ($posts as $post) {
    $fields = get_fields($post->ID);
    if (!$fields) {
        $fields = array();
    }

    // Parse meta list textarea into commander counts
    $meta_raw         = $fields['tournament_meta_list'] ?? '';
    $commander_counts = pdc_parse_meta_list($meta_raw);

    // Collect all unique commander names for bulk Scryfall fetch
    $commander_names = array();
    foreach (($fields['top8'] ?? array()) as $entry) {
        if (!empty($entry['commander_name'])) {
            $commander_names[] = $entry['commander_name'];
        }
    }
    foreach (array_keys($commander_counts) as $name) {
        $commander_names[] = $name;
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
    // Compute meta stats from parsed commander_counts
    // ----------------------------------------------------------------
    $color_counts        = array();
    $total_participants  = array_sum($commander_counts);

    foreach ($commander_counts as $name => $count) {
        $card_data = $cards_map[strtolower($name)] ?? null;
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

    $tournaments[] = array(
        'post'            => $post,
        'title'           => $post->title,
        'slug'            => $post->slug,
        'link'            => $post->link,
        'date_raw'        => $date_raw,
        'date_formatted'  => $date_formatted,
        'location'        => $fields['tournament_location'] ?? '',
        'city'            => $fields['tournament_city'] ?? '',
        'player_count'    => (int) ($fields['tournament_player_count'] ?? 0),
        'signup_url'      => $fields['tournament_signup_url'] ?? '',
        'top8'            => $top8,
        'meta_commanders' => $meta_commanders,
        'color_counts'    => $color_counts,
        'has_meta'        => !empty($meta_commanders),
    );
}

$context['tournaments'] = $tournaments;

Timber::render('archive-tournament.twig', $context);
