<?php
/**
 * Single Decklist Template
 *
 * Template for displaying individual decklist posts.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context = Timber::context();
$context['post'] = Timber::get_post();

// Get ACF fields (with safety check)
$commander = '';
$partner = '';
$decklist_text = '';
$deck_date = '';

if (function_exists('get_field')) {
    $commander = get_field('commander') ?: '';
    $partner = get_field('partner') ?: '';
    $decklist_text = get_field('decklist') ?: '';
    $deck_date = get_field('deck_date') ?: '';
}

// Parse decklist
$parsed_cards = Decklist_Parser::parse($decklist_text);

// Prepare deck data with Scryfall enrichment
$deck_data = Deck_Renderer::prepare_deck_data(
    $parsed_cards,
    $commander,
    $partner
);

// Add to context
$context['deck'] = $deck_data;
$context['raw_decklist'] = $decklist_text; // For export
$context['deck_date'] = $deck_date; // Custom date or fallback to post date

// Get taxonomies
$context['deck_author'] = wp_get_post_terms($context['post']->ID, 'deck_author');
$context['deck_archetype'] = wp_get_post_terms($context['post']->ID, 'deck_archetype');
$context['deck_color'] = wp_get_post_terms($context['post']->ID, 'deck_color');

Timber::render('single-decklist.twig', $context);
