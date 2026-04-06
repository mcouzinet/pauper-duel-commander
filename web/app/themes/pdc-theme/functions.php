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

    // Reload text domain when Polylang switches the locale (e.g. /en/ pages).
    // Without this, the textdomain is loaded once at after_setup_theme with the
    // default locale, before Polylang has a chance to switch it.
    add_action('change_locale', function () {
        unload_textdomain('pdc-theme');
        load_theme_textdomain('pdc-theme', get_template_directory() . '/languages');
    });

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
 * Register theme strings for Polylang translation.
 *
 * These strings are used in Twig templates via __() and in JS via wp_localize_script.
 * They become translatable in the Polylang admin: Languages > String translations.
 */
function pdc_register_polylang_strings() {
    if (!function_exists('pll_register_string')) {
        return;
    }

    // --- Components ---
    pll_register_string('footer_tagline', 'Un format Commander accessible et compétitif', 'pdc-theme');
    pll_register_string('footer_rights', 'Tous droits réservés.', 'pdc-theme');
    pll_register_string('footer_legal', 'Wizards of the Coast, Magic: The Gathering, leurs logos respectifs sont des marques déposées de Wizards of the Coast LLC.', 'pdc-theme');
    pll_register_string('header_menu_fallback', 'Menu non configuré', 'pdc-theme');
    pll_register_string('header_lang_fallback', 'Langues non configurées', 'pdc-theme');

    // --- Single Decklist ---
    pll_register_string('decklist_by', 'par', 'pdc-theme');
    pll_register_string('decklist_on', 'le', 'pdc-theme');
    pll_register_string('decklist_mana_curve', 'Courbe de Mana', 'pdc-theme');
    pll_register_string('decklist_export', 'Exporter la decklist', 'pdc-theme');
    pll_register_string('decklist_title', 'Decklist', 'pdc-theme');

    // --- Page Validateur ---
    pll_register_string('validator_title', 'Validateur de Deck', 'pdc-theme');
    pll_register_string('validator_subtitle_1', 'Vérifiez si votre deck est conforme aux règles du format', 'pdc-theme');
    pll_register_string('validator_pdc', 'Pauper Duel Commander', 'pdc-theme');
    pll_register_string('validator_subtitle_2', 'Entrez votre général et votre decklist au format MTGO pour obtenir une analyse complète.', 'pdc-theme');
    pll_register_string('validator_commander', 'Général', 'pdc-theme');
    pll_register_string('validator_commander_placeholder', 'Ex : Isamaru, Hound of Konda', 'pdc-theme');
    pll_register_string('validator_commander_help', "Nom exact en anglais tel qu'il apparaît sur Scryfall.", 'pdc-theme');
    pll_register_string('validator_partner_toggle', 'Deck avec partenaire (Partner / Background)', 'pdc-theme');
    pll_register_string('validator_partner', 'Partenaire', 'pdc-theme');
    pll_register_string('validator_partner_placeholder', 'Ex : Keleth, Sunmane Familiar', 'pdc-theme');
    pll_register_string('validator_partner_help', 'Nom exact en anglais. Le deck devra contenir 98 cartes au lieu de 99.', 'pdc-theme');
    pll_register_string('validator_decklist_format', 'Format MTGO : une ligne par carte, commençant par la quantité. Ex :', 'pdc-theme');
    pll_register_string('validator_decklist_help', 'Ne mettez pas votre général dans la decklist, il est renseigné séparément ci-dessus.', 'pdc-theme');
    pll_register_string('validator_submit', 'Valider le deck', 'pdc-theme');
    pll_register_string('validator_rules_title', 'Règles du format PDC', 'pdc-theme');
    pll_register_string('validator_rule_100', '100 cartes', 'pdc-theme');
    pll_register_string('validator_rule_100_desc', '99 cartes + 1 général (ou 98 + 2 en cas de partenaire).', 'pdc-theme');
    pll_register_string('validator_rule_uncommon', 'Général Uncommon', 'pdc-theme');
    pll_register_string('validator_rule_uncommon_desc', 'Le général doit obligatoirement être de rareté Uncommon.', 'pdc-theme');
    pll_register_string('validator_rule_common', 'Cartes Communes', 'pdc-theme');
    pll_register_string('validator_rule_common_desc', 'Toutes les 99 cartes du deck doivent être de rareté Commune (Common).', 'pdc-theme');
    pll_register_string('validator_rule_color', 'Identité de couleur', 'pdc-theme');
    pll_register_string('validator_rule_color_desc', "Toutes les cartes doivent respecter l'identité de couleur du général.", 'pdc-theme');
    pll_register_string('validator_rule_singleton', 'Pas de doublon', 'pdc-theme');
    pll_register_string('validator_rule_singleton_desc', 'Maximum 1 exemplaire de chaque carte, sauf les terrains de base.', 'pdc-theme');
    pll_register_string('validator_rule_ban', 'Ban List', 'pdc-theme');
    pll_register_string('validator_rule_ban_desc', 'Les cartes figurant sur la liste des interdictions PDC ne sont pas autorisées.', 'pdc-theme');

    // --- Validator JS strings ---
    pll_register_string('js_rule_format', 'Format invalide', 'pdc-theme');
    pll_register_string('js_rule_commander', 'Général introuvable', 'pdc-theme');
    pll_register_string('js_rule_commander_type', 'Type du général', 'pdc-theme');
    pll_register_string('js_rule_commander_rarity', 'Rareté du général', 'pdc-theme');
    pll_register_string('js_rule_deck_size', 'Nombre de cartes', 'pdc-theme');
    pll_register_string('js_rule_not_found', 'Cartes introuvables', 'pdc-theme');
    pll_register_string('js_rule_duplicates', 'Doublons', 'pdc-theme');
    pll_register_string('js_rule_rarity', 'Rareté des cartes', 'pdc-theme');
    pll_register_string('js_rule_ban_list', 'Cartes bannies', 'pdc-theme');
    pll_register_string('js_error_no_commander', 'Veuillez saisir le nom du général.', 'pdc-theme');
    pll_register_string('js_error_no_decklist', 'Veuillez saisir votre decklist.', 'pdc-theme');
    pll_register_string('js_error_network', 'Erreur réseau : %s', 'pdc-theme');
    pll_register_string('js_error_unexpected', "Une erreur inattendue s'est produite. Veuillez réessayer.", 'pdc-theme');
    pll_register_string('js_error_server', 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.', 'pdc-theme');
    pll_register_string('js_btn_loading', 'Validation en cours…', 'pdc-theme');
    pll_register_string('js_result_valid', 'Deck Valide !', 'pdc-theme');
    pll_register_string('js_result_invalid', 'Deck Invalide', 'pdc-theme');
    pll_register_string('js_result_valid_msg', 'Votre deck respecte toutes les règles du format PDC.', 'pdc-theme');
    pll_register_string('js_result_invalid_msg', '%count% problème(s) détecté(s). Consultez les détails ci-dessous.', 'pdc-theme');
    pll_register_string('js_stats_total', 'cartes au total', 'pdc-theme');
    pll_register_string('js_stats_unique', 'cartes uniques', 'pdc-theme');

    // --- Page Meta ---
    pll_register_string('meta_title', 'Méta PDC', 'pdc-theme');
    pll_register_string('meta_subtitle', 'Analyse du métagame Pauper Duel Commander : généraux les plus joués, répartition des couleurs et tendances par tournoi.', 'pdc-theme');
    pll_register_string('meta_global', 'Méta Globale', 'pdc-theme');
    pll_register_string('meta_stats_on', 'Statistiques agrégées sur', 'pdc-theme');
    pll_register_string('meta_tournaments', 'tournois', 'pdc-theme');
    pll_register_string('meta_player_entries', 'entrées joueurs', 'pdc-theme');
    pll_register_string('meta_most_played', 'Généraux les plus joués', 'pdc-theme');
    pll_register_string('meta_banned', 'Banned', 'pdc-theme');
    pll_register_string('meta_ci_title', 'Méta par identité de couleur', 'pdc-theme');
    pll_register_string('meta_color_dist', 'Répartition des couleurs', 'pdc-theme');
    pll_register_string('meta_top4', 'Méta Top 4', 'pdc-theme');
    pll_register_string('meta_top4_desc', 'Généraux ayant atteint le top 4 sur', 'pdc-theme');
    pll_register_string('meta_top4_spots', 'places en top 4', 'pdc-theme');
    pll_register_string('meta_top4_commanders', 'Généraux en Top 4', 'pdc-theme');
    pll_register_string('meta_top4_ci', 'Identités de couleur en Top 4', 'pdc-theme');
    pll_register_string('meta_top4_colors', 'Couleurs en Top 4', 'pdc-theme');
    pll_register_string('meta_no_data', 'Aucune donnée de méta disponible', 'pdc-theme');
    pll_register_string('meta_no_data_desc', 'Les statistiques du métagame seront disponibles après les premiers tournois.', 'pdc-theme');

    // --- Color identity labels ---
    pll_register_string('ci_mono_white', 'Mono Blanc', 'pdc-theme');
    pll_register_string('ci_mono_blue', 'Mono Bleu', 'pdc-theme');
    pll_register_string('ci_mono_black', 'Mono Noir', 'pdc-theme');
    pll_register_string('ci_mono_red', 'Mono Rouge', 'pdc-theme');
    pll_register_string('ci_mono_green', 'Mono Vert', 'pdc-theme');
    pll_register_string('ci_colorless', 'Incolore', 'pdc-theme');
    pll_register_string('ci_dimir', 'Dimir', 'pdc-theme');
    pll_register_string('ci_rakdos', 'Rakdos', 'pdc-theme');
    pll_register_string('ci_golgari', 'Golgari', 'pdc-theme');
    pll_register_string('ci_orzhov', 'Orzhov', 'pdc-theme');
    pll_register_string('ci_gruul', 'Gruul', 'pdc-theme');
    pll_register_string('ci_simic', 'Simic', 'pdc-theme');
    pll_register_string('ci_selesnya', 'Selesnya', 'pdc-theme');
    pll_register_string('ci_izzet', 'Izzet', 'pdc-theme');
    pll_register_string('ci_boros', 'Boros', 'pdc-theme');
    pll_register_string('ci_azorius', 'Azorius', 'pdc-theme');
    pll_register_string('ci_jund', 'Jund', 'pdc-theme');
    pll_register_string('ci_sultai', 'Sultai', 'pdc-theme');
    pll_register_string('ci_abzan', 'Abzan', 'pdc-theme');
    pll_register_string('ci_grixis', 'Grixis', 'pdc-theme');
    pll_register_string('ci_mardu', 'Mardu', 'pdc-theme');
    pll_register_string('ci_esper', 'Esper', 'pdc-theme');
    pll_register_string('ci_temur', 'Temur', 'pdc-theme');
    pll_register_string('ci_naya', 'Naya', 'pdc-theme');
    pll_register_string('ci_bant', 'Bant', 'pdc-theme');
    pll_register_string('ci_jeskai', 'Jeskai', 'pdc-theme');
    pll_register_string('ci_sans_white', 'Sans Blanc', 'pdc-theme');
    pll_register_string('ci_sans_blue', 'Sans Bleu', 'pdc-theme');
    pll_register_string('ci_sans_red', 'Sans Rouge', 'pdc-theme');
    pll_register_string('ci_sans_green', 'Sans Vert', 'pdc-theme');
    pll_register_string('ci_sans_black', 'Sans Noir', 'pdc-theme');
    pll_register_string('ci_5_colors', '5 Couleurs', 'pdc-theme');

    // --- Color labels ---
    pll_register_string('color_white', 'Blanc', 'pdc-theme');
    pll_register_string('color_blue', 'Bleu', 'pdc-theme');
    pll_register_string('color_black', 'Noir', 'pdc-theme');
    pll_register_string('color_red', 'Rouge', 'pdc-theme');
    pll_register_string('color_green', 'Vert', 'pdc-theme');

    // --- Deck Validator backend ---
    pll_register_string('validator_error_commander_required', 'Le nom du général est obligatoire.', 'pdc-theme');
    pll_register_string('validator_error_empty_decklist', 'La decklist est vide ou dans un format invalide. Utilisez le format MTGO : "1 Nom de la carte".', 'pdc-theme');
    pll_register_string('validator_error_commander_not_found', "Le général « %s » est introuvable sur Scryfall. Vérifiez l'orthographe (en anglais).", 'pdc-theme');
    pll_register_string('validator_error_partner_not_found', "Le partenaire « %s » est introuvable sur Scryfall. Vérifiez l'orthographe (en anglais).", 'pdc-theme');
    pll_register_string('validator_error_type', 'Le général « %1$s » doit être une créature (type actuel : %2$s).', 'pdc-theme');
    pll_register_string('validator_error_rarity', 'Le général « %1$s » doit être de rareté Uncommon (rareté actuelle : %2$s).', 'pdc-theme');
    pll_register_string('validator_deck_size_98', '98 cartes (avec 2 généraux partenaires)', 'pdc-theme');
    pll_register_string('validator_deck_size_99', '99 cartes (avec 1 général)', 'pdc-theme');
    pll_register_string('validator_error_deck_size', 'Le deck contient %1$s carte(s). Un deck PDC doit contenir %2$s.', 'pdc-theme');
    pll_register_string('validator_error_not_found', "Les cartes suivantes sont introuvables sur Scryfall. Vérifiez l'orthographe (noms en anglais) :", 'pdc-theme');
    pll_register_string('validator_error_duplicates', 'Les cartes suivantes apparaissent en plusieurs exemplaires (seuls les terrains de base sont autorisés en plusieurs copies) :', 'pdc-theme');
    pll_register_string('validator_error_pauper', "Les cartes suivantes n'ont jamais été imprimées en rareté Commune (non légales en Pauper) :", 'pdc-theme');
    pll_register_string('validator_error_color_identity', "Les cartes suivantes sont hors de l'identité de couleur du général %s :", 'pdc-theme');
    pll_register_string('validator_error_banned', 'Les cartes suivantes sont bannies dans le format PDC :', 'pdc-theme');
    pll_register_string('validator_warning_ban_list', "La liste des cartes bannies n'a pas pu être chargée. La vérification de la ban list a été ignorée.", 'pdc-theme');
    pll_register_string('ajax_error_invalid_request', 'Requête invalide. Veuillez recharger la page et réessayer.', 'pdc-theme');

    // --- Single Decklist (additional) ---
    pll_register_string('decklist_banned_commander', 'Commandant banni', 'pdc-theme');
    pll_register_string('decklist_banned_desc', "Ce commandant est actuellement banni en PDC. Cette decklist n'est plus légale dans le format.", 'pdc-theme');
    pll_register_string('decklist_mana_colors', 'Couleurs de Mana', 'pdc-theme');
    pll_register_string('decklist_card_types', 'Types de Cartes', 'pdc-theme');

    // --- Archive Decklist ---
    pll_register_string('archive_decklist_title', 'Quelques Decklists', 'pdc-theme');
    pll_register_string('archive_decklist_desc', "Notre collection de decklists est en construction. Vous trouverez ici nos premières sélections pour le Pauper Duel Commander : de quoi vous inspirer, tester le format, ou affiner votre propre stratégie.", 'pdc-theme');
    pll_register_string('filter_archetype', 'Archétype', 'pdc-theme');
    pll_register_string('filter_all_archetypes', 'Tous les archétypes', 'pdc-theme');
    pll_register_string('filter_all_colors', 'Toutes les couleurs', 'pdc-theme');
    pll_register_string('filter_reset', 'Réinitialiser les filtres', 'pdc-theme');
    pll_register_string('pagination_previous', 'Précédent', 'pdc-theme');
    pll_register_string('pagination_next', 'Suivant', 'pdc-theme');
    pll_register_string('no_decklist_found', 'Aucune decklist trouvée', 'pdc-theme');
    pll_register_string('no_decklist_hint', 'Essayez de modifier vos filtres pour voir plus de résultats.', 'pdc-theme');
    pll_register_string('view_all_decklists', 'Voir toutes les decklists', 'pdc-theme');

    // --- Archive Tournament ---
    pll_register_string('tournament_title', 'Tournois PDC', 'pdc-theme');
    pll_register_string('tournament_desc', "Inscrivez-vous aux prochains tournois et retrouvez les résultats, top 8 et méta des événements passés.", 'pdc-theme');
    pll_register_string('upcoming_tournaments', 'Prochains Tournois', 'pdc-theme');
    pll_register_string('players', 'joueurs', 'pdc-theme');
    pll_register_string('sign_up', "S'inscrire", 'pdc-theme');
    pll_register_string('more_details', 'Plus de détails', 'pdc-theme');
    pll_register_string('tournament_results', 'Résultats des Tournois', 'pdc-theme');
    pll_register_string('no_tournament_yet', 'Aucun tournoi pour le moment', 'pdc-theme');
    pll_register_string('tournament_results_coming', 'Les résultats des prochains tournois seront publiés ici.', 'pdc-theme');
    pll_register_string('no_tournament_results', 'Aucun résultat de tournoi disponible pour le moment.', 'pdc-theme');

    // --- Single Tournament ---
    pll_register_string('tournaments_label', 'Tournois', 'pdc-theme');
    pll_register_string('registration', 'Inscription', 'pdc-theme');
    pll_register_string('deck_label', 'Deck', 'pdc-theme');
    pll_register_string('results_unavailable', 'Résultats non disponibles.', 'pdc-theme');
    pll_register_string('meta_label', 'Méta', 'pdc-theme');
    pll_register_string('commanders_played', 'Généraux joués', 'pdc-theme');
    pll_register_string('color_identities', 'Identités de couleur', 'pdc-theme');
    pll_register_string('meta_unavailable', 'Données de méta non disponibles.', 'pdc-theme');

    // --- Header ---
    pll_register_string('language_label', 'Langue', 'pdc-theme');
}
add_action('init', 'pdc_register_polylang_strings');

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
        wp_send_json_error(array('message' => __('Requête invalide. Veuillez recharger la page et réessayer.', 'pdc-theme')), 403);
    }

    $commander = isset($_POST['commander']) ? sanitize_text_field(wp_unslash($_POST['commander'])) : '';
    $partner   = isset($_POST['partner'])   ? sanitize_text_field(wp_unslash($_POST['partner']))   : '';
    $decklist  = isset($_POST['decklist'])  ? sanitize_textarea_field(wp_unslash($_POST['decklist'])) : '';

    if (empty($commander)) {
        wp_send_json_error(array('message' => __('Le nom du général est obligatoire.', 'pdc-theme')), 400);
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
    $counts      = array();
    $canon_names = array(); // strtolower(name) => first-seen display name
    $lines       = preg_split('/\r\n|\r|\n/', trim($text));

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

        // Normalize separator whitespace to avoid duplicates from inconsistent input
        $name = preg_replace('/\s*\/\/\s*/', ' // ', $name);

        // Normalize key to avoid case-sensitivity duplicates
        $key = strtolower($name);
        if (!isset($canon_names[$key])) {
            $canon_names[$key] = $name;
        }
        $counts[$key] = ($counts[$key] ?? 0) + $qty;
    }

    arsort($counts);

    // Re-key with canonical display names
    $result = array();
    foreach ($counts as $key => $count) {
        $result[$canon_names[$key]] = $count;
    }
    return $result;
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
            'slug'       => 'tournois',
            'with_front' => false,
        ),
        'capability_type'   => 'post',
    ));
}
add_action('init', 'pdc_register_tournament_cpt');

/**
 * Redirect old /tournoi/ URLs to /tournois/
 */
function pdc_redirect_old_tournament_slug() {
    if (preg_match('#^/tournoi(/.*)?$#', $_SERVER['REQUEST_URI'], $matches)) {
        $path = isset($matches[1]) ? $matches[1] : '/';
        wp_redirect(home_url('/tournois' . $path), 301);
        exit;
    }
}
add_action('template_redirect', 'pdc_redirect_old_tournament_slug');
