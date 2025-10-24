<?php
/**
 * Single post template
 */

use Timber\Timber;

$context = Timber::context();
$context['post'] = Timber::get_post();

// Récupérer les modules ACF si disponibles
if (function_exists('get_field')) {
    $context['post']->modules = get_field('modules');
}

Timber::render('single.twig', $context);
