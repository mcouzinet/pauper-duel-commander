<?php
/**
 * ACF Blocks Registration
 *
 * Registers all custom ACF blocks for the Gutenberg editor
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom block category
 */
function pdc_block_categories($categories) {
    return array_merge(
        [
            [
                'slug'  => 'pdc-modules',
                'title' => __('PDC Modules', 'pdc-theme'),
                'icon'  => 'layout',
            ],
        ],
        $categories
    );
}
add_filter('block_categories_all', 'pdc_block_categories');

/**
 * Generic render callback for all ACF blocks
 *
 * @param array    $attributes Block attributes
 * @param string   $content    Block content
 * @param bool     $is_preview Whether in editor preview mode
 * @param int      $post_id    Current post ID
 * @param WP_Block $wp_block   Block instance
 */
function pdc_acf_block_render_callback($attributes, $content = '', $is_preview = false, $post_id = 0, $wp_block = null) {
    // Get block slug from block name (remove 'acf/' prefix)
    $slug = str_replace('acf/', '', $attributes['name']);

    // Convert hyphens to underscores for template file names
    $template_slug = str_replace('-', '_', $slug);

    // Get block name for display
    $block_name = $attributes['title'] ?? str_replace(['_', '-'], ' ', ucfirst($slug));

    // Get ACF fields for this block
    $fields = get_fields();

    // DEBUG: Log block rendering (remove this after testing)
    error_log('ACF Block Render: ' . $slug . ' | Fields: ' . (empty($fields) ? 'EMPTY' : 'OK') . ' | Preview: ' . ($is_preview ? 'YES' : 'NO'));

    // Prepare minimal context (avoid rebuilding global context for performance)
    $context = [];

    // Add block-specific data
    $context['block'] = $attributes;
    $context['is_preview'] = $is_preview;
    $context['post_id'] = $post_id;

    // Add fields to 'module' for template compatibility
    $context['module'] = $fields ?: [];

    // Add unique key for template IDs (like flexible content loop index)
    $context['key'] = $attributes['id'] ?? uniqid();

    // Add block ID for anchors
    $context['block_id'] = 'block-' . $context['key'];

    // Add custom classes
    $context['class_name'] = $attributes['className'] ?? '';

    // Render the block template (use underscore version for backward compatibility)
    $template_path = 'blocks/' . $template_slug . '.twig';

    // DEBUG: Check if template exists
    $full_path = get_template_directory() . '/views/' . $template_path;
    if (!file_exists($full_path)) {
        error_log('ACF Block Template NOT FOUND: ' . $full_path);
        echo '<!-- Template not found: ' . esc_html($template_path) . ' -->';
        return;
    }

    try {
        Timber\Timber::render($template_path, $context);
    } catch (Exception $e) {
        error_log('ACF Block Render Error: ' . $e->getMessage());
        if (!$is_preview) {
            echo '<!-- Block render error: ' . esc_html($e->getMessage()) . ' -->';
        }
    }
}

/**
 * Register all ACF blocks
 */
function pdc_register_acf_blocks() {
    // Check if ACF function exists
    if (!function_exists('acf_register_block_type')) {
        return;
    }

    // Block configuration array
    $blocks = [
        [
            'name'            => 'm01-block-and-title',
            'title'           => __('M01 - Block et titre', 'pdc-theme'),
            'description'     => __('Module avec titre et blocs répétés', 'pdc-theme'),
            'icon'            => 'grid-view',
            'keywords'        => ['block', 'titre', 'grid'],
        ],
        [
            'name'            => 'm02-hero',
            'title'           => __('M02 - Hero', 'pdc-theme'),
            'description'     => __('Section hero en haut de page', 'pdc-theme'),
            'icon'            => 'cover-image',
            'keywords'        => ['hero', 'banner', 'header'],
        ],
        [
            'name'            => 'm03-features-grid',
            'title'           => __('M03 - Features Grid', 'pdc-theme'),
            'description'     => __('Grille de fonctionnalités', 'pdc-theme'),
            'icon'            => 'screenoptions',
            'keywords'        => ['features', 'grid', 'cards'],
        ],
        [
            'name'            => 'm04-text-image',
            'title'           => __('M04 - Texte et Image', 'pdc-theme'),
            'description'     => __('Layout 2 colonnes avec texte et image', 'pdc-theme'),
            'icon'            => 'align-pull-left',
            'keywords'        => ['text', 'image', 'règles', 'content'],
        ],
        [
            'name'            => 'm05-callout',
            'title'           => __('M05 - Encart Important', 'pdc-theme'),
            'description'     => __('Bloc pour mettre en avant informations importantes', 'pdc-theme'),
            'icon'            => 'info',
            'keywords'        => ['callout', 'warning', 'info', 'attention'],
        ],
         [
            'name'            => 'm06-steps',
            'title'           => __('M06 - Liste Numérotée', 'pdc-theme'),
            'description'     => __('Steps verticaux avec numéros stylisés', 'pdc-theme'),
            'icon'            => 'editor-ol',
            'keywords'        => ['steps', 'liste', 'numéros', 'étapes'],
        ],
        [
            'name'            => 'm07-ban-list',
            'title'           => __('M07 - Ban List', 'pdc-theme'),
            'description'     => __('Liste des cartes bannies', 'pdc-theme'),
            'icon'            => 'index-card',
            'keywords'        => ['ban', 'list', 'cards'],
        ],
        [
            'name'            => 'm08-faq-accordion',
            'title'           => __('M08 - FAQ Accordion', 'pdc-theme'),
            'description'     => __('Accordéon de questions fréquentes', 'pdc-theme'),
            'icon'            => 'editor-help',
            'keywords'        => ['faq', 'accordion', 'questions'],
        ],
        [
            'name'            => 'm09-community',
            'title'           => __('M09 - Community', 'pdc-theme'),
            'description'     => __('Section communauté', 'pdc-theme'),
            'icon'            => 'groups',
            'keywords'        => ['community', 'social', 'discord'],
        ]
    ];

    // Register each block
    foreach ($blocks as $block) {
        acf_register_block_type([
            'name'              => $block['name'],
            'title'             => $block['title'],
            'description'       => $block['description'],
            'render_callback'   => 'pdc_acf_block_render_callback',
            'category'          => 'pdc-modules',
            'icon'              => $block['icon'],
            'keywords'          => $block['keywords'],
            'api_version'       => 3,
            'mode'              => 'preview',
            'supports'          => [
                'align'         => false,
                'anchor'        => true,
                'customClassName' => true,
                'jsx'           => true,
            ],
            'example'  => [
                'attributes' => [
                    'mode' => 'preview',
                    'data' => [],
                ]
            ],
        ]);
    }
}
add_action('acf/init', 'pdc_register_acf_blocks');
