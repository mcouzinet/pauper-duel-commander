<?php
/**
 * Template Name: Résultats de Tournoi
 *
 * Page template for tournament results.
 * Assign this template to the /tournoi/ page via the WordPress editor.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

use Timber\Timber;

$context         = Timber::context();
$context['post'] = Timber::get_post();

Timber::render('page-tournoi.twig', $context);
