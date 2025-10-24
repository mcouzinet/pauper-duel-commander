<?php
/**
 * Page template
 */

use Timber\Timber;

$context = Timber::context();
$post = Timber::get_post();
$context['post'] = $post;
$context['modules'] = get_field('modules', $post->ID);

Timber::render('page.twig', $context);
