<?php
/**
 * Template Name: Validateur de Deck
 *
 * Page template for the PDC deck validator tool.
 * Assign this template to any WordPress page via the editor.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context         = Timber::context();
$context['post'] = Timber::get_post();

// Pass a nonce for the AJAX request
$context['validator_nonce'] = wp_create_nonce('pdc_validate_deck');

// Pass the AJAX URL
$context['ajax_url'] = admin_url('admin-ajax.php');

Timber::render('page-validateur.twig', $context);
