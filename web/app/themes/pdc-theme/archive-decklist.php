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

// Get all decklists with pagination
$args = array(
    'post_type' => 'decklist',
    'posts_per_page' => 24,
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
    'orderby' => 'date',
    'order' => 'DESC',
);

// Handle taxonomy filters
$tax_query = array();

if (isset($_GET['deck_author']) && !empty($_GET['deck_author'])) {
    $tax_query[] = array(
        'taxonomy' => 'deck_author',
        'field' => 'slug',
        'terms' => sanitize_text_field($_GET['deck_author']),
    );
}

if (isset($_GET['deck_archetype']) && !empty($_GET['deck_archetype'])) {
    $tax_query[] = array(
        'taxonomy' => 'deck_archetype',
        'field' => 'slug',
        'terms' => sanitize_text_field($_GET['deck_archetype']),
    );
}

if (isset($_GET['deck_color']) && !empty($_GET['deck_color'])) {
    $tax_query[] = array(
        'taxonomy' => 'deck_color',
        'field' => 'slug',
        'terms' => sanitize_text_field($_GET['deck_color']),
    );
}

if (!empty($tax_query)) {
    $args['tax_query'] = $tax_query;
}

// Get posts
$context['posts'] = Timber::get_posts($args);

// Enrich posts with additional data
$enriched_posts = array();
foreach ($context['posts'] as $post) {
    $post_data = array(
        'post' => $post,
        'commander_image' => '',
        'commander_name' => '',
    );

    // Get commander data from ACF
    if (function_exists('get_field')) {
        $commander_name = get_field('commander', $post->ID);
        if ($commander_name) {
            $post_data['commander_name'] = $commander_name;

            // Get commander image from Scryfall
            $commander_data = Scryfall_Service::get_card_by_name($commander_name);
            if ($commander_data) {
                $post_data['commander_image'] = Scryfall_Service::get_card_image($commander_data, 'art_crop');
            }
        }
    }

    // Get taxonomies
    $post_data['deck_author'] = wp_get_post_terms($post->ID, 'deck_author');
    $post_data['deck_archetype'] = wp_get_post_terms($post->ID, 'deck_archetype');
    $post_data['deck_color'] = wp_get_post_terms($post->ID, 'deck_color');

    $enriched_posts[] = $post_data;
}

$context['deck_posts'] = $enriched_posts;

// Get all terms for filters
$context['all_authors'] = get_terms(array(
    'taxonomy' => 'deck_author',
    'hide_empty' => true,
));

$context['all_archetypes'] = get_terms(array(
    'taxonomy' => 'deck_archetype',
    'hide_empty' => true,
));

$context['all_colors'] = get_terms(array(
    'taxonomy' => 'deck_color',
    'hide_empty' => true,
));

// Current filters
$context['current_author'] = isset($_GET['deck_author']) ? sanitize_text_field($_GET['deck_author']) : '';
$context['current_archetype'] = isset($_GET['deck_archetype']) ? sanitize_text_field($_GET['deck_archetype']) : '';
$context['current_color'] = isset($_GET['deck_color']) ? sanitize_text_field($_GET['deck_color']) : '';

Timber::render('archive-decklist.twig', $context);
