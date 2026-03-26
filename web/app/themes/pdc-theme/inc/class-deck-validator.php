<?php
/**
 * Deck Validator
 *
 * Validates a PDC (Pauper Duel Commander) decklist against format rules:
 * - 100 cards total (99 + 1 commander, or 98 + 2 with partner)
 * - Common rarity for the 99 deck cards
 * - Commander(s) must be uncommon rarity
 * - All cards within the commander's color identity
 * - No duplicates (except basic lands)
 * - No banned cards (from M07 ban list block)
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-scryfall-service.php';
require_once __DIR__ . '/class-decklist-parser.php';
require_once __DIR__ . '/class-deck-renderer.php';

class Deck_Validator {

    /**
     * Basic land type names (allowed in multiple copies).
     */
    const BASIC_LAND_TYPES = array('Plains', 'Island', 'Swamp', 'Mountain', 'Forest', 'Wastes');

    /**
     * Cache key for banned card names.
     */
    const BAN_LIST_CACHE_KEY = 'pdc_banned_card_names';

    /**
     * Main validation entry point.
     *
     * @param string $commander_name Commander card name (already sanitized by caller)
     * @param string $partner_name   Partner card name (optional, empty string if none)
     * @param string $decklist_text  Raw MTGO-format decklist
     * @return array {
     *   'is_valid'  => bool,
     *   'errors'    => array of ['rule' => string, 'message' => string, 'cards' => array],
     *   'warnings'  => array of strings,
     *   'stats'     => array|null,
     * }
     */
    public static function validate($commander_name, $partner_name, $decklist_text) {
        $errors   = array();
        $warnings = array();

        $commander_name = trim($commander_name);
        $partner_name   = trim($partner_name);

        if (empty($commander_name)) {
            $errors[] = array(
                'rule'    => 'commander',
                'message' => 'Le nom du général est obligatoire.',
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Parse decklist ---
        $parsed_cards = Decklist_Parser::parse($decklist_text);

        if (empty($parsed_cards)) {
            $errors[] = array(
                'rule'    => 'format',
                'message' => 'La decklist est vide ou dans un format invalide. Utilisez le format MTGO : "1 Nom de la carte".',
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Fetch commander(s) from Scryfall ---
        $commander_data = Scryfall_Service::get_card_by_name($commander_name);
        if (!$commander_data) {
            $errors[] = array(
                'rule'    => 'commander',
                'message' => "Le général « {$commander_name} » est introuvable sur Scryfall. Vérifiez l'orthographe (en anglais).",
                'cards'   => array($commander_name),
            );
        }

        $has_partner  = !empty($partner_name);
        $partner_data = null;
        if ($has_partner) {
            $partner_data = Scryfall_Service::get_card_by_name($partner_name);
            if (!$partner_data) {
                $errors[] = array(
                    'rule'    => 'commander',
                    'message' => "Le partenaire « {$partner_name} » est introuvable sur Scryfall. Vérifiez l'orthographe (en anglais).",
                    'cards'   => array($partner_name),
                );
            }
        }

        // --- Rule 1: Commander rarity must be uncommon ---
        if ($commander_data) {
            self::check_commander_rarity($commander_data, $commander_name, $errors);
        }
        if ($partner_data) {
            self::check_commander_rarity($partner_data, $partner_name, $errors);
        }

        // --- Rule 2: Deck size (99 without partner, 98 with partner) ---
        $expected_size = $has_partner ? 98 : 99;
        self::check_deck_size($parsed_cards, $expected_size, $has_partner, $errors);

        // --- Fetch full card data from Scryfall (with cache) ---
        $enriched_cards = Deck_Renderer::fetch_card_data($parsed_cards);

        // --- Check for unresolvable cards ---
        self::check_unresolvable_cards($parsed_cards, $enriched_cards, $errors);

        // --- Rule 3: No duplicates except basic lands ---
        self::check_duplicates($enriched_cards, $errors);

        // --- Rule 4: Common rarity for deck cards ---
        self::check_card_rarities($enriched_cards, $errors);

        // --- Rule 5: Color identity ---
        if ($commander_data) {
            $color_identity = self::get_combined_color_identity($commander_data, $partner_data);
            self::check_color_identity($enriched_cards, $color_identity, $commander_name, $partner_name, $errors);
        }

        // --- Rule 6: Ban list ---
        $banned_names = self::get_banned_card_names();
        if (!empty($banned_names)) {
            self::check_ban_list($enriched_cards, $banned_names, $errors);
        } else {
            $warnings[] = 'La liste des cartes bannies n\'a pas pu être chargée. La vérification de la ban list a été ignorée.';
        }

        $total_cards  = array_sum(array_column($parsed_cards, 'quantity'));
        $unique_cards = count($parsed_cards);

        return self::build_result(
            empty($errors),
            $errors,
            $warnings,
            array(
                'total_cards'  => $total_cards,
                'unique_cards' => $unique_cards,
            )
        );
    }

    // -------------------------------------------------------------------------
    // Rule checkers
    // -------------------------------------------------------------------------

    /**
     * Rule: Commander must be uncommon rarity.
     */
    private static function check_commander_rarity($card_data, $card_name, &$errors) {
        $rarity = isset($card_data->rarity) ? $card_data->rarity : null;
        if ($rarity !== 'uncommon') {
            $rarity_label = $rarity ? ucfirst($rarity) : 'inconnue';
            $errors[] = array(
                'rule'    => 'commander_rarity',
                'message' => "Le général « {$card_name} » doit être de rareté Uncommon (rareté actuelle : {$rarity_label}).",
                'cards'   => array($card_name),
            );
        }
    }

    /**
     * Rule: Deck must contain exactly 99 (or 98 with partner) cards.
     */
    private static function check_deck_size($parsed_cards, $expected_size, $has_partner, &$errors) {
        $total = array_sum(array_column($parsed_cards, 'quantity'));
        if ($total !== $expected_size) {
            $label = $has_partner
                ? '98 cartes (avec 2 généraux partenaires)'
                : '99 cartes (avec 1 général)';
            $errors[] = array(
                'rule'    => 'deck_size',
                'message' => "Le deck contient {$total} carte(s). Un deck PDC doit contenir {$label}.",
                'cards'   => array(),
            );
        }
    }

    /**
     * Flag cards that could not be resolved on Scryfall.
     *
     * Deck_Renderer::fetch_card_data() sets scryfall_data => null for unknown cards.
     * We detect those and surface an explicit error.
     */
    private static function check_unresolvable_cards($parsed_cards, $enriched_cards, &$errors) {
        // Build a set of names that were successfully resolved
        $resolved = array();
        foreach ($enriched_cards as $card) {
            if ($card['scryfall_data'] !== null) {
                $resolved[strtolower($card['name'])] = true;
            }
        }

        $not_found = array();
        foreach ($parsed_cards as $card) {
            if (!isset($resolved[strtolower($card['name'])])) {
                $not_found[] = $card['name'];
            }
        }

        if (!empty($not_found)) {
            $errors[] = array(
                'rule'    => 'not_found',
                'message' => 'Les cartes suivantes sont introuvables sur Scryfall. Vérifiez l\'orthographe (noms en anglais) :',
                'cards'   => $not_found,
            );
        }
    }

    /**
     * Rule: No duplicates (except basic lands).
     */
    private static function check_duplicates($enriched_cards, &$errors) {
        $duplicates = array();
        foreach ($enriched_cards as $card) {
            if ($card['quantity'] > 1 && !self::is_basic_land($card)) {
                $duplicates[] = $card['name'] . " ({$card['quantity']} copies)";
            }
        }
        if (!empty($duplicates)) {
            $errors[] = array(
                'rule'    => 'duplicates',
                'message' => 'Les cartes suivantes apparaissent en plusieurs exemplaires (seuls les terrains de base sont autorisés en plusieurs copies) :',
                'cards'   => $duplicates,
            );
        }
    }

    /**
     * Rule: All deck cards must be Pauper-legal (i.e. printed at common in at least one set).
     *
     * Uses Scryfall's legalities.pauper field which correctly accounts for all printings,
     * rather than the rarity of the default printing returned by the API.
     */
    private static function check_card_rarities($enriched_cards, &$errors) {
        $invalid = array();
        foreach ($enriched_cards as $card) {
            if ($card['scryfall_data'] === null) {
                continue; // Already reported by check_unresolvable_cards
            }
            // 'legal' and 'banned' both mean the card has a common printing in paper.
            // 'not_legal' means it has never been printed at common rarity.
            $has_common_printing = isset($card['scryfall_data']->legalities->pauper)
                && $card['scryfall_data']->legalities->pauper !== 'not_legal';
            if (!$has_common_printing) {
                $invalid[] = $card['name'];
            }
        }
        if (!empty($invalid)) {
            $errors[] = array(
                'rule'    => 'rarity',
                'message' => 'Les cartes suivantes n\'ont jamais été imprimées en rareté Commune (non légales en Pauper) :',
                'cards'   => $invalid,
            );
        }
    }

    /**
     * Rule: All cards must be within the commander's color identity.
     */
    private static function check_color_identity($enriched_cards, $allowed_colors, $commander_name, $partner_name, &$errors) {
        $violations = array();
        foreach ($enriched_cards as $card) {
            if ($card['scryfall_data'] === null) {
                continue; // Already reported by check_unresolvable_cards
            }
            $card_identity = isset($card['scryfall_data']->color_identity)
                ? (array) $card['scryfall_data']->color_identity
                : array();

            foreach ($card_identity as $color) {
                if (!in_array($color, $allowed_colors, true)) {
                    $violations[] = $card['name'];
                    break;
                }
            }
        }
        if (!empty($violations)) {
            $identity_label = empty($allowed_colors) ? 'Incolore' : implode('', $allowed_colors);
            $label = !empty($partner_name)
                ? "« {$commander_name} » + « {$partner_name} » ({$identity_label})"
                : "« {$commander_name} » ({$identity_label})";
            $errors[] = array(
                'rule'    => 'color_identity',
                'message' => "Les cartes suivantes sont hors de l'identité de couleur du général {$label} :",
                'cards'   => array_values(array_unique($violations)),
            );
        }
    }

    /**
     * Rule: No banned cards.
     */
    private static function check_ban_list($enriched_cards, $banned_names, &$errors) {
        $found = array();
        foreach ($enriched_cards as $card) {
            if (in_array(strtolower($card['name']), $banned_names, true)) {
                $found[] = $card['name'];
            }
        }
        if (!empty($found)) {
            $errors[] = array(
                'rule'    => 'ban_list',
                'message' => 'Les cartes suivantes sont bannies dans le format PDC :',
                'cards'   => $found,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if a card is a basic land (allowed in multiple copies).
     */
    private static function is_basic_land($card) {
        $type_line = isset($card['type_line']) ? $card['type_line'] : '';
        if (stripos($type_line, 'Basic Land') !== false) {
            return true;
        }
        return in_array($card['name'], self::BASIC_LAND_TYPES, true);
    }

    /**
     * Get the combined color identity of commander + optional partner.
     *
     * @param object      $commander_data Scryfall card data
     * @param object|null $partner_data   Scryfall card data or null
     * @return array Array of color codes e.g. ['W', 'U']
     */
    private static function get_combined_color_identity($commander_data, $partner_data) {
        $identity = array();
        if (isset($commander_data->color_identity)) {
            $identity = array_merge($identity, (array) $commander_data->color_identity);
        }
        if ($partner_data && isset($partner_data->color_identity)) {
            $identity = array_merge($identity, (array) $partner_data->color_identity);
        }
        return array_values(array_unique($identity));
    }

    /**
     * Get list of banned card names (lowercase) by scanning published posts/pages
     * for M07 ban list blocks and fetching their Scryfall card names.
     *
     * Uses a 1-hour transient cache. Extracts Scryfall URLs directly from the
     * serialized block content in post_content (more reliable than parse_blocks()).
     *
     * @return array Array of lowercase card names, or empty array on failure
     */
    public static function get_banned_card_names() {
        $cached = get_transient(self::BAN_LIST_CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $banned_names = array();

        $posts = get_posts(array(
            'post_type'        => array('page', 'post'),
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'suppress_filters' => false,
        ));

        foreach ($posts as $post) {
            // Quick check: skip posts without the M07 ban list block
            if (strpos($post->post_content, 'acf/m07-ban-list') === false) {
                continue;
            }

            // Extract Scryfall URLs from the ban list block's serialised attributes.
            // Pattern matches both: "cards_N_scryfall_url":"URL" (JSON attrs) and
            // the URL appearing in ACF-rendered HTML for the block.
            preg_match_all(
                '#"cards_\d+_scryfall_url"\s*:\s*"(https://scryfall\.com/card/[^"]+)"#',
                $post->post_content,
                $matches
            );

            foreach ($matches[1] as $url) {
                if (!preg_match('#scryfall\.com/card/([^/]+)/([^/\s"\']+)#', $url, $parts)) {
                    continue;
                }
                $card_data = Scryfall_Service::get_card_by_set($parts[1], $parts[2]);
                if ($card_data && isset($card_data->name)) {
                    $banned_names[] = strtolower($card_data->name);
                }
            }
        }

        $banned_names = array_values(array_unique($banned_names));
        set_transient(self::BAN_LIST_CACHE_KEY, $banned_names, HOUR_IN_SECONDS);

        return $banned_names;
    }

    /**
     * Invalidate the ban list cache (call after editing the ban list block).
     */
    public static function invalidate_ban_list_cache() {
        delete_transient(self::BAN_LIST_CACHE_KEY);
    }

    // -------------------------------------------------------------------------
    // Result builder
    // -------------------------------------------------------------------------

    private static function build_result($is_valid, $errors, $warnings, $stats) {
        return array(
            'is_valid' => $is_valid,
            'errors'   => $errors,
            'warnings' => $warnings,
            'stats'    => $stats,
        );
    }
}
