<?php
/**
 * Theme functions and definitions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

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
