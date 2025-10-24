<?php
/**
 * Theme functions and definitions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Composer dependencies
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Enqueue scripts and styles
 */
function pdc_theme_enqueue_assets() {
    // Enqueue the main stylesheet
    wp_enqueue_style(
        'pdc-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );

    // Enqueue Bud-compiled assets
    $manifest_path = get_template_directory() . '/public/manifest.json';

    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true);

        if (isset($manifest['app.css'])) {
            wp_enqueue_style(
                'pdc-theme-app',
                get_template_directory_uri() . '/public/' . $manifest['app.css'],
                [],
                null
            );
        }

        if (isset($manifest['app.js'])) {
            wp_enqueue_script(
                'pdc-theme-app',
                get_template_directory_uri() . '/public/' . $manifest['app.js'],
                [],
                null,
                true
            );
        }

        if (isset($manifest['runtime.js'])) {
            wp_enqueue_script(
                'pdc-theme-runtime',
                get_template_directory_uri() . '/public/' . $manifest['runtime.js'],
                [],
                null,
                true
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'pdc_theme_enqueue_assets');

/**
 * Initialize Timber
 */
// Set Timber directories
Timber\Timber::$dirname = ['views', 'views/components', 'views/layouts', 'views/modules'];

/**
 * Timber context
 */
function pdc_theme_add_to_context($context) {
    // Site info
    $context['site'] = new Timber\Site();

    // Menu
    $context['menu'] = Timber\Timber::get_menu('primary');

    // Theme options
    if (function_exists('get_fields')) {
        $context['options'] = get_fields('option');
    }

    return $context;
}
add_filter('timber/context', 'pdc_theme_add_to_context');

/**
 * Theme setup
 */
function pdc_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ]);

    // Register navigation menus
    register_nav_menus([
        'primary' => __('Menu principal', 'pdc-theme'),
    ]);
}
add_action('after_setup_theme', 'pdc_theme_setup');

/**
 * Register widget areas
 */
function pdc_theme_widgets_init() {
    register_sidebar([
        'name'          => __('Sidebar', 'pdc-theme'),
        'id'            => 'sidebar-1',
        'description'   => __('Add widgets here.', 'pdc-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s mb-8">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title text-xl font-bold mb-4">',
        'after_title'   => '</h2>',
    ]);
}
add_action('widgets_init', 'pdc_theme_widgets_init');

/**
 * Disable Gutenberg Editor
 */
// Disable Gutenberg on the back end
add_filter('use_block_editor_for_post', '__return_false');

// Disable Gutenberg for widgets
add_filter('use_widgets_block_editor', '__return_false');

// Disable the Gutenberg blocks CSS on the front-end
function pdc_theme_disable_gutenberg_styles() {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style'); // WooCommerce blocks if present
    wp_dequeue_style('global-styles');
}
add_action('wp_enqueue_scripts', 'pdc_theme_disable_gutenberg_styles', 100);

/**
 * ACF JSON - Save and Load
 */
// Save ACF JSON to theme folder
add_filter('acf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/acf-json';
});

// Load ACF JSON from theme folder
add_filter('acf/settings/load_json', function($paths) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});
