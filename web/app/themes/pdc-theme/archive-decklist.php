<?php
/**
 * Archive Decklist Template
 *
 * Template for displaying the decklist archive.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

require_once get_template_directory() . '/inc/class-scryfall-service.php';

$context = Timber::context();

// Color identity labels (same as meta page)
$ci_labels = array(
    'W'     => 'Mono Blanc',
    'U'     => 'Mono Bleu',
    'B'     => 'Mono Noir',
    'R'     => 'Mono Rouge',
    'G'     => 'Mono Vert',
    'C'     => 'Incolore',
    'BU'    => 'Dimir',
    'BR'    => 'Rakdos',
    'BG'    => 'Golgari',
    'BW'    => 'Orzhov',
    'GR'    => 'Gruul',
    'GU'    => 'Simic',
    'GW'    => 'Selesnya',
    'RU'    => 'Izzet',
    'RW'    => 'Boros',
    'UW'    => 'Azorius',
    'BGR'   => 'Jund',
    'BGU'   => 'Sultai',
    'BGW'   => 'Abzan',
    'BRU'   => 'Grixis',
    'BRW'   => 'Mardu',
    'BUW'   => 'Esper',
    'GRU'   => 'Temur',
    'GRW'   => 'Naya',
    'GUW'   => 'Bant',
    'RUW'   => 'Jeskai',
    'BGRU'  => 'Sans Blanc',
    'BGRW'  => 'Sans Bleu',
    'BGUW'  => 'Sans Rouge',
    'BRUW'  => 'Sans Vert',
    'GRUW'  => 'Sans Noir',
    'BGRUW' => '5 Couleurs',
);

// Current filters
$current_archetype      = isset($_GET['deck_archetype']) ? sanitize_text_field($_GET['deck_archetype']) : '';
$current_color_identity = isset($_GET['color_identity']) ? sanitize_text_field($_GET['color_identity']) : '';

// Query args — we fetch ALL decklists (no pagination yet) so we can compute
// color identity counts and filter client-side before paginating.
// For large datasets this should be refactored, but for now the decklist count is small.
$args = array(
    'post_type'      => 'decklist',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
);

// Handle archetype taxonomy filter
if (!empty($current_archetype)) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'deck_archetype',
            'field'    => 'slug',
            'terms'    => $current_archetype,
        ),
    );
}

// Get all posts
$all_posts = Timber::get_posts($args);

// Enrich posts with commander data and compute color identity
$enriched_posts         = array();
$color_identity_counts  = array();

foreach ($all_posts as $post) {
    $commander_name = function_exists('get_field') ? get_field('commander', $post->ID) : '';

    $partner_name = function_exists('get_field') ? get_field('partner', $post->ID) : '';

    $post_data = array(
        'post'            => $post,
        'commander_image' => '',
        'commander_name'  => $commander_name ?: '',
        'partner_name'    => $partner_name ?: '',
        'color_identity'  => 'C',
    );

    // Get commander data from ACF + Scryfall
    if (function_exists('get_field') && $commander_name) {
        $post_data['commander_name'] = $commander_name;

        $commander_data = Scryfall_Service::get_card_by_name($commander_name);
        if ($commander_data) {
            $post_data['commander_image'] = Scryfall_Service::get_card_image($commander_data, 'art_crop');

            // Compute color identity key
            $colors = !empty($commander_data->color_identity) ? (array) $commander_data->color_identity : array();

            // Check for partner
            $partner_name = function_exists('get_field') ? get_field('partner', $post->ID) : '';
            if ($partner_name) {
                $partner_data = Scryfall_Service::get_card_by_name($partner_name);
                if ($partner_data && !empty($partner_data->color_identity)) {
                    $colors = array_merge($colors, (array) $partner_data->color_identity);
                }
            }

            $colors = array_values(array_unique($colors));
            sort($colors);
            $identity_key = !empty($colors) ? implode('', $colors) : 'C';
            $post_data['color_identity'] = $identity_key;
        }
    }

    // Get taxonomies
    $post_data['deck_archetype'] = wp_get_post_terms($post->ID, 'deck_archetype');
    $post_data['deck_color']     = wp_get_post_terms($post->ID, 'deck_color');

    // Count color identities (before filtering by color identity, so counts reflect archetype-filtered set)
    $ci_key = $post_data['color_identity'];
    $color_identity_counts[$ci_key] = ($color_identity_counts[$ci_key] ?? 0) + 1;

    $enriched_posts[] = $post_data;
}

// Filter by color identity if requested
if (!empty($current_color_identity)) {
    $enriched_posts = array_filter($enriched_posts, function ($item) use ($current_color_identity) {
        return $item['color_identity'] === $current_color_identity;
    });
    $enriched_posts = array_values($enriched_posts);
}

// Manual pagination
$paged         = get_query_var('paged') ? (int) get_query_var('paged') : 1;
$per_page      = 24;
$total         = count($enriched_posts);
$total_pages   = max(1, (int) ceil($total / $per_page));
$offset        = ($paged - 1) * $per_page;
$paged_posts   = array_slice($enriched_posts, $offset, $per_page);

$context['deck_posts'] = $paged_posts;

// Build pagination data for Twig
$pagination = array(
    'current' => $paged,
    'total'   => $total_pages,
    'pages'   => array(),
    'prev'    => null,
    'next'    => null,
);

$base_url = get_post_type_archive_link('decklist');
$filter_params = array();
if (!empty($current_archetype)) {
    $filter_params['deck_archetype'] = $current_archetype;
}
if (!empty($current_color_identity)) {
    $filter_params['color_identity'] = $current_color_identity;
}

for ($i = 1; $i <= $total_pages; $i++) {
    $page_params = $filter_params;
    if ($i > 1) {
        $page_params['paged'] = $i;
    }
    $link = !empty($page_params) ? add_query_arg($page_params, $base_url) : $base_url;
    if ($i > 1 && empty($page_params['paged'])) {
        $link = add_query_arg('paged', $i, $link);
    }
    $pagination['pages'][] = array(
        'title'   => $i,
        'link'    => $link,
        'current' => $i === $paged,
    );
}

if ($paged > 1) {
    $prev_params = $filter_params;
    if ($paged - 1 > 1) {
        $prev_params['paged'] = $paged - 1;
    }
    $pagination['prev'] = array(
        'link' => add_query_arg($prev_params, $base_url),
    );
}

if ($paged < $total_pages) {
    $next_params          = $filter_params;
    $next_params['paged'] = $paged + 1;
    $pagination['next']   = array(
        'link' => add_query_arg($next_params, $base_url),
    );
}

$context['pagination'] = $pagination;

// Build color identity filter options sorted by count desc
arsort($color_identity_counts);
$color_identities = array();
foreach ($color_identity_counts as $key => $count) {
    $color_identities[] = array(
        'key'   => $key,
        'label' => $ci_labels[$key] ?? $key,
        'count' => $count,
    );
}
$context['color_identities'] = $color_identities;

// Get archetype terms for filter
$context['all_archetypes'] = get_terms(array(
    'taxonomy'   => 'deck_archetype',
    'hide_empty' => true,
));

// Current filters for Twig
$context['current_archetype']      = $current_archetype;
$context['current_color_identity'] = $current_color_identity;

Timber::render('archive-decklist.twig', $context);
