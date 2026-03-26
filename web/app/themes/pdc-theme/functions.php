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

    // Enqueue Mana Font for Magic mana symbols (via CDN)
    wp_enqueue_style(
        'mana-font',
        'https://cdn.jsdelivr.net/npm/mana-font@latest/css/mana.min.css',
        [],
        '1.18.0'
    );

    // Enqueue theme CSS
    $css_path = get_template_directory() . '/public/css/app.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'pdc-theme-app',
            get_template_directory_uri() . '/public/css/app.css',
            ['mana-font'],
            filemtime($css_path)
        );
    }

    // Enqueue Bud-compiled JS
    $manifest_path = get_template_directory() . '/public/manifest.json';
    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true);

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
 * Enqueue editor assets (for Gutenberg block editor)
 */
function pdc_theme_enqueue_editor_assets() {
    // Enqueue Mana Font for Magic mana symbols (via CDN)
    wp_enqueue_style(
        'mana-font',
        'https://cdn.jsdelivr.net/npm/mana-font@latest/css/mana.min.css',
        [],
        '1.18.0'
    );

    // Enqueue theme CSS for block preview
    $css_path = get_template_directory() . '/public/css/app.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'pdc-theme-app-editor',
            get_template_directory_uri() . '/public/css/app.css',
            ['mana-font'],
            filemtime($css_path)
        );
    }

    // Enqueue editor-specific CSS to override Gutenberg defaults
    $editor_css_path = get_template_directory() . '/public/css/editor.css';
    if (file_exists($editor_css_path)) {
        wp_enqueue_style(
            'pdc-theme-editor-overrides',
            get_template_directory_uri() . '/public/css/editor.css',
            ['pdc-theme-app-editor'],
            filemtime($editor_css_path)
        );
    }
}
add_action('enqueue_block_editor_assets', 'pdc_theme_enqueue_editor_assets');

/**
 * Initialize Timber
 */
// Set Timber directories
Timber\Timber::$dirname = ['views', 'views/components', 'views/layouts', 'views/modules', 'views/blocks'];

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
 * Note: Translation functions are already provided by Timber v2
 * Available in Twig templates: __(text, domain), _e(text, domain), _n(single, plural, number, domain)
 * No need to register them manually.
 */

/**
 * Theme setup
 */
function pdc_theme_setup() {
    // Load theme text domain for translations
    load_theme_textdomain('pdc-theme', get_template_directory() . '/languages');

    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
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
 * Gutenberg Editor Configuration
 */
// Disable Gutenberg for widgets (keep classic widgets)
add_filter('use_widgets_block_editor', '__return_false');

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

/**
 * Add Favicons
 */
function pdc_theme_add_favicons() {
    $theme_uri = get_template_directory_uri();
    ?>
    <link rel="icon" type="image/x-icon" href="<?php echo $theme_uri; ?>/public/img/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $theme_uri; ?>/public/img/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $theme_uri; ?>/public/img/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $theme_uri; ?>/public/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $theme_uri; ?>/public/img/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?php echo $theme_uri; ?>/public/img/android-chrome-512x512.png">
    <link rel="manifest" href="<?php echo $theme_uri; ?>/public/img/site.webmanifest">
    <meta name="theme-color" content="#FF5722">
    <?php
}
add_action('wp_head', 'pdc_theme_add_favicons');

/**
 * Load Custom Blocks
 */
require_once get_template_directory() . '/inc/blocks.php';

/**
 * Load Decklist Classes
 */
require_once get_template_directory() . '/inc/class-scryfall-service.php';
require_once get_template_directory() . '/inc/class-decklist-parser.php';
require_once get_template_directory() . '/inc/class-deck-renderer.php';
require_once get_template_directory() . '/inc/class-deck-validator.php';
require_once get_template_directory() . '/inc/tournament-fields.php';

/**
 * Wrapper function for Scryfall API (for backward compatibility with M07 module)
 *
 * @param string $set_code The set code (e.g., "znr")
 * @param string $collector_number The collector number
 * @return array|null Card data from Scryfall API
 */
function get_scryfall_card($set_code, $collector_number) {
    return Scryfall_Service::get_card_by_set($set_code, $collector_number);
}

/**
 * Register Decklist Custom Post Type
 */
function pdc_register_decklist_cpt() {
    $labels = array(
        'name' => __('Decklists', 'pdc-theme'),
        'singular_name' => __('Decklist', 'pdc-theme'),
        'menu_name' => __('Decklists', 'pdc-theme'),
        'add_new' => __('Ajouter une decklist', 'pdc-theme'),
        'add_new_item' => __('Ajouter une nouvelle decklist', 'pdc-theme'),
        'edit_item' => __('Modifier la decklist', 'pdc-theme'),
        'new_item' => __('Nouvelle decklist', 'pdc-theme'),
        'view_item' => __('Voir la decklist', 'pdc-theme'),
        'search_items' => __('Rechercher des decklists', 'pdc-theme'),
        'not_found' => __('Aucune decklist trouvée', 'pdc-theme'),
        'not_found_in_trash' => __('Aucune decklist dans la corbeille', 'pdc-theme'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => false,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-index-card',
        'supports' => array('title', 'editor', 'thumbnail', 'author'),
        'rewrite' => array(
            'slug' => 'decklist',
            'with_front' => false,
        ),
        'capability_type' => 'post',
    );

    register_post_type('decklist', $args);
}
add_action('init', 'pdc_register_decklist_cpt');

/**
 * Register Decklist Taxonomies
 */
function pdc_register_decklist_taxonomies() {
    // Taxonomy: Deck Author
    register_taxonomy('deck_author', 'decklist', array(
        'labels' => array(
            'name' => __('Auteurs', 'pdc-theme'),
            'singular_name' => __('Auteur', 'pdc-theme'),
            'search_items' => __('Rechercher des auteurs', 'pdc-theme'),
            'all_items' => __('Tous les auteurs', 'pdc-theme'),
            'edit_item' => __('Modifier l\'auteur', 'pdc-theme'),
            'update_item' => __('Mettre à jour l\'auteur', 'pdc-theme'),
            'add_new_item' => __('Ajouter un auteur', 'pdc-theme'),
            'new_item_name' => __('Nouveau nom d\'auteur', 'pdc-theme'),
            'menu_name' => __('Auteurs', 'pdc-theme'),
        ),
        'public' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => false,
        'rewrite' => array('slug' => 'deck-author'),
    ));

    // Taxonomy: Deck Archetype
    register_taxonomy('deck_archetype', 'decklist', array(
        'labels' => array(
            'name' => __('Archétypes', 'pdc-theme'),
            'singular_name' => __('Archétype', 'pdc-theme'),
            'search_items' => __('Rechercher des archétypes', 'pdc-theme'),
            'all_items' => __('Tous les archétypes', 'pdc-theme'),
            'edit_item' => __('Modifier l\'archétype', 'pdc-theme'),
            'update_item' => __('Mettre à jour l\'archétype', 'pdc-theme'),
            'add_new_item' => __('Ajouter un archétype', 'pdc-theme'),
            'new_item_name' => __('Nouveau nom d\'archétype', 'pdc-theme'),
            'menu_name' => __('Archétypes', 'pdc-theme'),
        ),
        'public' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => false,
        'rewrite' => array('slug' => 'deck-archetype'),
    ));

    // Taxonomy: Deck Color
    register_taxonomy('deck_color', 'decklist', array(
        'labels' => array(
            'name' => __('Couleurs', 'pdc-theme'),
            'singular_name' => __('Couleur', 'pdc-theme'),
            'search_items' => __('Rechercher des couleurs', 'pdc-theme'),
            'all_items' => __('Toutes les couleurs', 'pdc-theme'),
            'edit_item' => __('Modifier la couleur', 'pdc-theme'),
            'update_item' => __('Mettre à jour la couleur', 'pdc-theme'),
            'add_new_item' => __('Ajouter une couleur', 'pdc-theme'),
            'new_item_name' => __('Nouveau nom de couleur', 'pdc-theme'),
            'menu_name' => __('Couleurs', 'pdc-theme'),
        ),
        'public' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => false,
        'rewrite' => array('slug' => 'deck-color'),
    ));
}
add_action('init', 'pdc_register_decklist_taxonomies');

/**
 * Disable Polylang translation for Decklist post type
 * Decklists should remain in English only, not be translated
 * However, taxonomies (Auteurs, Archétypes, Couleurs) remain translatable
 */
function pdc_disable_polylang_for_decklist($post_types, $is_settings) {
    // Remove 'decklist' from translatable post types
    if (isset($post_types['decklist'])) {
        unset($post_types['decklist']);
    }
    return $post_types;
}
add_filter('pll_get_post_types', 'pdc_disable_polylang_for_decklist', 10, 2);

/**
 * AJAX handler: Validate a PDC deck
 *
 * Accepts POST fields: nonce, commander, partner, decklist
 * Returns JSON: { success, data: { is_valid, errors, warnings, stats } }
 */
function pdc_ajax_validate_deck() {
    // Verify nonce
    if (!check_ajax_referer('pdc_validate_deck', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Requête invalide. Veuillez recharger la page et réessayer.'), 403);
    }

    $commander = isset($_POST['commander']) ? sanitize_text_field(wp_unslash($_POST['commander'])) : '';
    $partner   = isset($_POST['partner'])   ? sanitize_text_field(wp_unslash($_POST['partner']))   : '';
    $decklist  = isset($_POST['decklist'])  ? sanitize_textarea_field(wp_unslash($_POST['decklist'])) : '';

    if (empty($commander)) {
        wp_send_json_error(array('message' => 'Le nom du général est obligatoire.'), 400);
    }

    $result = Deck_Validator::validate($commander, $partner, $decklist);

    wp_send_json_success($result);
}
add_action('wp_ajax_pdc_validate_deck', 'pdc_ajax_validate_deck');
add_action('wp_ajax_nopriv_pdc_validate_deck', 'pdc_ajax_validate_deck');

/**
 * Invalidate the ban list cache whenever a post is saved.
 * Ensures the ban list stays in sync when the M07 block is updated.
 */
function pdc_invalidate_ban_list_on_save($post_id) {
    if (wp_is_post_revision($post_id)) {
        return;
    }
    Deck_Validator::invalidate_ban_list_cache();
}
add_action('save_post', 'pdc_invalidate_ban_list_on_save');

/**
 * Parse tournament meta list textarea into commander counts.
 *
 * Accepts a text block with one commander per line.
 * Optional number prefix = quantity (default 1).
 *
 * Format examples:
 *   "2 Strix"           → Strix × 2
 *   "Arabella"           → Arabella × 1
 *   "1 Dargo / Black"    → Dargo / Black × 1
 *
 * @param string $text Raw textarea content.
 * @return array [ 'Commander Name' => count, … ] sorted by count desc.
 */
function pdc_parse_meta_list($text) {
    $counts = array();
    $lines  = preg_split('/\r\n|\r|\n/', trim($text));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Match optional leading number (digits) followed by space(s), then the commander name
        if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
            $qty  = (int) $m[1];
            $name = trim($m[2]);
        } else {
            $qty  = 1;
            $name = $line;
        }

        if ($name === '' || $qty < 1) {
            continue;
        }

        $counts[$name] = ($counts[$name] ?? 0) + $qty;
    }

    arsort($counts);
    return $counts;
}

/**
 * Split a commander name into its parts if it contains a partner separator.
 *
 * Accepts " // " or " / " as separator between partner / background names.
 * Returns an array of individual card names, or a single-element array
 * if no separator is found.
 *
 * @param string $name Commander name (may contain separator).
 * @return array Individual card names.
 */
function pdc_split_commander_name($name) {
    // Try " // " first (canonical MTG notation)
    if (strpos($name, '//') !== false) {
        $parts = array_map('trim', explode('//', $name));
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    // Try " / " (common shorthand) — require spaces around slash
    // to avoid splitting card names that contain "/" without spaces
    if (preg_match('/ \/ /', $name)) {
        $parts = array_map('trim', preg_split('/ \/ /', $name));
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    return array($name);
}

/**
 * Expand commander names for Scryfall lookup.
 *
 * Splits partner / background pairs into individual card names
 * so each can be looked up on Scryfall independently.
 *
 * @param array $names Commander names (may contain partner pairs).
 * @return array Flat array of individual card names.
 */
function pdc_expand_commander_names(array $names) {
    $expanded = array();
    foreach ($names as $name) {
        foreach (pdc_split_commander_name($name) as $part) {
            $expanded[] = $part;
        }
    }
    return $expanded;
}

/**
 * Resolve Scryfall card data for a commander name that may be a partner pair.
 *
 * For single commanders, returns the card data directly from the map.
 * For partner pairs, returns an object with:
 *   - color_identity merged from both cards
 *   - image from the first card
 *
 * @param string $name      The full commander name as entered.
 * @param array  $cards_map Map of strtolower(card_name) => Scryfall card object.
 * @return object|null Resolved card data or null if no card found.
 */
function pdc_resolve_commander_card($name, array $cards_map) {
    $parts = pdc_split_commander_name($name);

    // Single commander
    if (count($parts) === 1) {
        return $cards_map[strtolower($parts[0])] ?? null;
    }

    // Partner pair: lookup each part and merge
    $cards = array();
    foreach ($parts as $part) {
        $card = $cards_map[strtolower($part)] ?? null;
        if ($card) {
            $cards[] = $card;
        }
    }

    if (empty($cards)) {
        return null;
    }

    // Build a merged pseudo-card with combined color_identity and first card's image
    $merged = clone $cards[0];
    $colors = array();
    foreach ($cards as $card) {
        if (!empty($card->color_identity)) {
            $colors = array_merge($colors, (array) $card->color_identity);
        }
    }
    $merged->color_identity = array_values(array_unique($colors));

    return $merged;
}

/**
 * Sort color counts by count descending, with WUBRG+C order as tiebreaker.
 *
 * @param array $color_counts ['W' => n, 'U' => n, …]
 * @return array Sorted color counts (preserves keys).
 */
function pdc_sort_color_counts(array $color_counts) {
    $wubrg_order = array('W' => 0, 'U' => 1, 'B' => 2, 'R' => 3, 'G' => 4, 'C' => 5);

    uksort($color_counts, function($a, $b) use ($color_counts, $wubrg_order) {
        // Sort by count descending
        $diff = $color_counts[$b] - $color_counts[$a];
        if ($diff !== 0) {
            return $diff;
        }
        // Tiebreaker: WUBRG order
        return ($wubrg_order[$a] ?? 9) - ($wubrg_order[$b] ?? 9);
    });

    return $color_counts;
}

/**
 * Register Tournament Custom Post Type
 */
function pdc_register_tournament_cpt() {
    register_post_type('tournament', array(
        'labels' => array(
            'name'               => __('Tournois', 'pdc-theme'),
            'singular_name'      => __('Tournoi', 'pdc-theme'),
            'menu_name'          => __('Tournois', 'pdc-theme'),
            'add_new_item'       => __('Ajouter un tournoi', 'pdc-theme'),
            'edit_item'          => __('Modifier le tournoi', 'pdc-theme'),
            'view_item'          => __('Voir le tournoi', 'pdc-theme'),
            'search_items'       => __('Rechercher des tournois', 'pdc-theme'),
            'not_found'          => __('Aucun tournoi trouvé', 'pdc-theme'),
            'not_found_in_trash' => __('Aucun tournoi dans la corbeille', 'pdc-theme'),
        ),
        'public'            => true,
        'has_archive'       => true,
        'publicly_queryable'=> true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_nav_menus' => true,
        'show_in_rest'      => false,
        'menu_position'     => 6,
        'menu_icon'         => 'dashicons-awards',
        'supports'          => array('title'),
        'rewrite'           => array(
            'slug'       => 'tournoi',
            'with_front' => false,
        ),
        'capability_type'   => 'post',
    ));
}
add_action('init', 'pdc_register_tournament_cpt');
