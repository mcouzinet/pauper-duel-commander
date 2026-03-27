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
    );
}

// Split into upcoming vs past tournaments based on date
$today = date('Ymd');
$upcoming    = array();
$past        = array();

foreach ($tournaments as $t) {
    if (!empty($t['date_raw']) && $t['date_raw'] >= $today) {
        $upcoming[] = $t;
    } else {
        $past[] = $t;
    }
}

// Upcoming: nearest first (ASC)
usort($upcoming, fn($a, $b) => strcmp($a['date_raw'], $b['date_raw']));

$context['upcoming_tournaments'] = $upcoming;
$context['past_tournaments']     = $past;
$context['tournaments']          = $tournaments;

Timber::render('archive-tournament.twig', $context);
