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

// Localize validator.js strings for translation
wp_localize_script('pdc-theme-app', 'pdcValidatorL10n', array(
    // Rule labels
    'rule_format'          => __('Format invalide', 'pdc-theme'),
    'rule_commander'       => __('Général introuvable', 'pdc-theme'),
    'rule_commander_type'  => __('Type du général', 'pdc-theme'),
    'rule_commander_rarity'=> __('Rareté du général', 'pdc-theme'),
    'rule_deck_size'       => __('Nombre de cartes', 'pdc-theme'),
    'rule_not_found'       => __('Cartes introuvables', 'pdc-theme'),
    'rule_duplicates'      => __('Doublons', 'pdc-theme'),
    'rule_rarity'          => __('Rareté des cartes', 'pdc-theme'),
    'rule_color_identity'  => __('Identité de couleur', 'pdc-theme'),
    'rule_ban_list'        => __('Cartes bannies', 'pdc-theme'),
    // Client-side messages
    'error_no_commander'   => __('Veuillez saisir le nom du général.', 'pdc-theme'),
    'error_no_decklist'    => __('Veuillez saisir votre decklist.', 'pdc-theme'),
    'error_network'        => __('Erreur réseau : %s', 'pdc-theme'),
    'error_unexpected'     => __('Une erreur inattendue s\'est produite. Veuillez réessayer.', 'pdc-theme'),
    'error_server'         => __('Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.', 'pdc-theme'),
    // Button states
    'btn_loading'          => __('Validation en cours…', 'pdc-theme'),
    'btn_default'          => __('Valider le deck', 'pdc-theme'),
    // Result messages
    'result_valid'         => __('Deck Valide !', 'pdc-theme'),
    'result_invalid'       => __('Deck Invalide', 'pdc-theme'),
    'result_valid_msg'     => __('Votre deck respecte toutes les règles du format PDC.', 'pdc-theme'),
    'result_invalid_msg'   => __('%count% problème(s) détecté(s). Consultez les détails ci-dessous.', 'pdc-theme'),
    // Stats
    'stats_total'          => __('cartes au total', 'pdc-theme'),
    'stats_unique'         => __('cartes uniques', 'pdc-theme'),
));

Timber::render('page-validateur.twig', $context);
