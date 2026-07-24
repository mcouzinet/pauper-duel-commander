<?php
/**
 * Deck Validator (standalone, no WordPress)
 *
 * Validates a PDC (Pauper Duel Commander) decklist against format rules:
 * 1. Commander required + found on Scryfall
 * 2. Commander must be a Creature, Vehicle or Spacecraft (legendary or not)
 * 3. Commander must have been printed at uncommon at least once
 * 4. Deck size: 99 (solo) or 98 (with partner)
 * 5. All cards found on Scryfall
 * 6. No duplicates except basic lands
 * 7. Pauper legality: legalities.pauper !== "not_legal"
 * 8. Color identity: each card's color_identity is a subset of commander's
 * 9. Ban list: no card in banlist.json
 *
 * @package PDC_API
 * @since 2.0.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class DeckValidator {

    /**
     * Basic land names (allowed in multiple copies).
     */
    const BASIC_LAND_NAMES = array('Plains', 'Island', 'Swamp', 'Mountain', 'Forest', 'Wastes');

    /**
     * Card types allowed in the command zone (legendary or not).
     */
    const COMMANDER_TYPES = array('Creature', 'Vehicle', 'Spacecraft');

    /**
     * Ban list path override. Null means PDC_BANLIST_PATH. Set by the test suite.
     *
     * @var string|null
     */
    public static $banlist_path = null;

    /**
     * Main validation entry point.
     *
     * @param string $commander_name Commander card name
     * @param string $partner_name   Partner card name (empty string if none)
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

        // --- Rule 1: Commander is required ---
        if (empty($commander_name)) {
            $errors[] = array(
                'rule'    => 'commander',
                'message' => 'Le nom du general est obligatoire.',
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Parse decklist ---
        $parsed_cards = DecklistParser::parse($decklist_text);

        if (empty($parsed_cards)) {
            $errors[] = array(
                'rule'    => 'format',
                'message' => 'La decklist est vide ou dans un format invalide. Utilisez le format MTGO : "1 Nom de la carte".',
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Fetch commander(s) from Scryfall ---
        $commander_data = ScryfallService::get_card_by_name($commander_name);
        if (!$commander_data) {
            $errors[] = array(
                'rule'    => 'commander',
                'message' => 'Le general "' . $commander_name . '" est introuvable sur Scryfall. Verifiez l\'orthographe (en anglais).',
                'cards'   => array($commander_name),
            );
        }

        $has_partner  = !empty($partner_name);
        $partner_data = null;
        if ($has_partner) {
            $partner_data = ScryfallService::get_card_by_name($partner_name);
            if (!$partner_data) {
                $errors[] = array(
                    'rule'    => 'commander',
                    'message' => 'Le partenaire "' . $partner_name . '" est introuvable sur Scryfall. Verifiez l\'orthographe (en anglais).',
                    'cards'   => array($partner_name),
                );
            }
        }

        // --- Rule 2: Commander must be a creature ---
        if ($commander_data) {
            self::check_commander_is_creature($commander_data, $commander_name, $errors);
        }
        if ($partner_data) {
            self::check_commander_is_creature($partner_data, $partner_name, $errors);
        }

        // --- Rule 3: Commander rarity must be uncommon ---
        if ($commander_data) {
            self::check_commander_rarity($commander_data, $commander_name, $errors);
        }
        if ($partner_data) {
            self::check_commander_rarity($partner_data, $partner_name, $errors);
        }

        // --- Rule 4: Deck size (99 without partner, 98 with partner) ---
        $expected_size = $has_partner ? 98 : 99;
        self::check_deck_size($parsed_cards, $expected_size, $has_partner, $errors);

        // --- Fetch full card data from Scryfall (inline fetch_card_data) ---
        $enriched_cards = self::fetch_card_data($parsed_cards);

        // --- Rule 5: All cards found on Scryfall ---
        self::check_unresolvable_cards($parsed_cards, $enriched_cards, $errors);

        // --- Rule 6: No duplicates except basic lands ---
        self::check_duplicates($enriched_cards, $errors);

        // --- Rule 7: Pauper legality for deck cards ---
        self::check_card_rarities($enriched_cards, $errors);

        // --- Rule 8: Color identity ---
        if ($commander_data) {
            $color_identity = self::get_combined_color_identity($commander_data, $partner_data);
            self::check_color_identity($enriched_cards, $color_identity, $commander_name, $partner_name, $errors);
        }

        // --- Rule 9: Ban list ---
        // Throws if the ban list cannot be loaded: skipping this rule would let a
        // deck full of banned cards validate, which is worse than returning an error.
        $banned_names = self::get_banned_card_names();
        self::check_ban_list($enriched_cards, $banned_names, $commander_name, $partner_name, $errors);

        // --- Stats ---
        $total_cards  = 0;
        foreach ($parsed_cards as $c) {
            $total_cards += $c['quantity'];
        }
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
    // Inline fetch_card_data (from Deck_Renderer)
    // -------------------------------------------------------------------------

    /**
     * Fetch and enrich card data from Scryfall.
     *
     * Replaces Deck_Renderer::fetch_card_data() with a minimal version that
     * only includes the fields the validator needs.
     *
     * @param array $parsed_cards Array from DecklistParser::parse()
     * @return array Enriched cards with Scryfall data
     */
    private static function fetch_card_data($parsed_cards) {
        $enriched = array();

        // Bulk-fetch all unique card names
        $names     = array_unique(array_column($parsed_cards, 'name'));
        $cards_map = ScryfallService::get_cards_by_names($names);

        foreach ($parsed_cards as $card) {
            $lower     = strtolower($card['name']);
            $card_data = isset($cards_map[$lower]) ? $cards_map[$lower] : null;

            if ($card_data) {
                $enriched[] = array(
                    'quantity'      => $card['quantity'],
                    'name'          => $card['name'],
                    'scryfall_data' => $card_data,
                    'type_line'     => ScryfallService::get_type_line($card_data),
                );
            } else {
                $enriched[] = array(
                    'quantity'      => $card['quantity'],
                    'name'          => $card['name'],
                    'scryfall_data' => null,
                    'type_line'     => 'Unknown',
                );
            }
        }

        return $enriched;
    }

    // -------------------------------------------------------------------------
    // Rule checkers
    // -------------------------------------------------------------------------

    /**
     * Rule 2: Commander must be a creature, a Vehicle or a Spacecraft.
     *
     * Vehicles and Spacecraft are eligible per the format framing document:
     * the command zone is not restricted to creatures.
     */
    private static function check_commander_is_creature($card_data, $card_name, &$errors) {
        $type_line = isset($card_data->type_line) ? $card_data->type_line : '';

        $eligible = false;
        foreach (self::COMMANDER_TYPES as $type) {
            if (stripos($type_line, $type) !== false) {
                $eligible = true;
                break;
            }
        }

        if (!$eligible) {
            $type_label = $type_line ? $type_line : 'inconnu';
            $errors[] = array(
                'rule'    => 'commander_type',
                'message' => 'Le general "' . $card_name . '" doit etre une creature, un vehicule ou un vaisseau spatial (type actuel : ' . $type_label . ').',
                'cards'   => array($card_name),
            );
        }
    }

    /**
     * Rule 3: Commander must have been printed at uncommon at least once.
     *
     * The rule is "has ever been uncommon", not "this printing is uncommon", so
     * every printing is checked. Baleful Strix defaults to rare on Scryfall but
     * has an uncommon printing and is a legal, tournament-played commander.
     */
    private static function check_commander_rarity($card_data, $card_name, &$errors) {
        $rarities = ScryfallService::get_all_rarities($card_data);

        if (!in_array('uncommon', $rarities, true)) {
            $rarity = isset($card_data->rarity) ? $card_data->rarity : null;
            $rarity_label = $rarity ? ucfirst($rarity) : 'inconnue';
            $errors[] = array(
                'rule'    => 'commander_rarity',
                'message' => 'Le general "' . $card_name . '" doit avoir ete imprime au moins une fois en rarete Uncommon (rarete actuelle : ' . $rarity_label . ').',
                'cards'   => array($card_name),
            );
        }
    }

    /**
     * Rule 4: Deck must contain exactly 99 (or 98 with partner) cards.
     */
    private static function check_deck_size($parsed_cards, $expected_size, $has_partner, &$errors) {
        $total = 0;
        foreach ($parsed_cards as $card) {
            $total += $card['quantity'];
        }

        if ($total !== $expected_size) {
            $label = $has_partner
                ? '98 cartes (avec 2 generaux partenaires)'
                : '99 cartes (avec 1 general)';
            $errors[] = array(
                'rule'    => 'deck_size',
                'message' => 'Le deck contient ' . $total . ' carte(s). Un deck PDC doit contenir ' . $label . '.',
                'cards'   => array(),
            );
        }
    }

    /**
     * Rule 5: Flag cards that could not be resolved on Scryfall.
     */
    private static function check_unresolvable_cards($parsed_cards, $enriched_cards, &$errors) {
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
                'message' => 'Les cartes suivantes sont introuvables sur Scryfall. Verifiez l\'orthographe (noms en anglais) :',
                'cards'   => $not_found,
            );
        }
    }

    /**
     * Rule 6: No duplicates (except basic lands).
     */
    private static function check_duplicates($enriched_cards, &$errors) {
        $duplicates = array();
        foreach ($enriched_cards as $card) {
            if ($card['quantity'] > 1 && !self::is_basic_land($card)) {
                $duplicates[] = $card['name'] . ' (' . $card['quantity'] . ' copies)';
            }
        }
        if (!empty($duplicates)) {
            $errors[] = array(
                'rule'    => 'duplicates',
                'message' => 'Les cartes suivantes apparaissent en plusieurs exemplaires (seuls les terrains de base sont autorises en plusieurs copies) :',
                'cards'   => $duplicates,
            );
        }
    }

    /**
     * Rule 7: All deck cards must be Pauper-legal (printed at common in at least one set).
     *
     * Uses Scryfall's legalities.pauper field. 'legal' and 'banned' both mean the
     * card has a common printing. 'not_legal' means it has never been printed at common.
     */
    private static function check_card_rarities($enriched_cards, &$errors) {
        $invalid = array();
        foreach ($enriched_cards as $card) {
            if ($card['scryfall_data'] === null) {
                continue; // Already reported by check_unresolvable_cards
            }
            $has_common_printing = isset($card['scryfall_data']->legalities->pauper)
                && $card['scryfall_data']->legalities->pauper !== 'not_legal';
            if (!$has_common_printing) {
                $invalid[] = $card['name'];
            }
        }
        if (!empty($invalid)) {
            $errors[] = array(
                'rule'    => 'rarity',
                'message' => 'Les cartes suivantes n\'ont jamais ete imprimees en rarete Commune (non legales en Pauper) :',
                'cards'   => $invalid,
            );
        }
    }

    /**
     * Rule 8: All cards must be within the commander's color identity.
     */
    private static function check_color_identity($enriched_cards, $allowed_colors, $commander_name, $partner_name, &$errors) {
        $violations = array();
        foreach ($enriched_cards as $card) {
            if ($card['scryfall_data'] === null) {
                continue;
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
            if (!empty($partner_name)) {
                $label = '"' . $commander_name . '" + "' . $partner_name . '" (' . $identity_label . ')';
            } else {
                $label = '"' . $commander_name . '" (' . $identity_label . ')';
            }
            $errors[] = array(
                'rule'    => 'color_identity',
                'message' => 'Les cartes suivantes sont hors de l\'identite de couleur du general ' . $label . ' :',
                'cards'   => array_values(array_unique($violations)),
            );
        }
    }

    /**
     * Rule 9: No banned cards (check commander + partner + deck cards).
     */
    private static function check_ban_list($enriched_cards, $banned_names, $commander_name, $partner_name, &$errors) {
        $found = array();

        // Check commander
        if (in_array(strtolower($commander_name), $banned_names, true)) {
            $found[] = $commander_name;
        }

        // Check partner
        if (!empty($partner_name) && in_array(strtolower($partner_name), $banned_names, true)) {
            $found[] = $partner_name;
        }

        // Check deck cards
        foreach ($enriched_cards as $card) {
            if (in_array(strtolower($card['name']), $banned_names, true)) {
                $found[] = $card['name'];
            }
        }

        $found = array_values(array_unique($found));

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
     *
     * @param array $card Enriched card array
     * @return bool
     */
    private static function is_basic_land($card) {
        $type_line = isset($card['type_line']) ? $card['type_line'] : '';
        if (stripos($type_line, 'Basic Land') !== false) {
            return true;
        }
        return in_array($card['name'], self::BASIC_LAND_NAMES, true);
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
     * Load banned card names from banlist.json.
     *
     * @param string|null $path Override path (tests); defaults to PDC_BANLIST_PATH
     * @return array Lowercase card names
     * @throws RuntimeException If the ban list is missing, unreadable or malformed
     */
    public static function get_banned_card_names($path = null) {
        $path = $path ?? self::$banlist_path ?? PDC_BANLIST_PATH;

        if (!file_exists($path)) {
            throw new RuntimeException('Ban list not found at ' . $path);
        }

        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Ban list could not be read at ' . $path);
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['cards']) || !is_array($data['cards'])) {
            throw new RuntimeException('Ban list is malformed at ' . $path . ' (expected a "cards" array)');
        }

        return array_map('strtolower', $data['cards']);
    }

    // -------------------------------------------------------------------------
    // Result builder
    // -------------------------------------------------------------------------

    /**
     * @param bool       $is_valid
     * @param array      $errors
     * @param array      $warnings
     * @param array|null $stats
     * @return array
     */
    private static function build_result($is_valid, $errors, $warnings, $stats) {
        return array(
            'is_valid' => $is_valid,
            'errors'   => $errors,
            'warnings' => $warnings,
            'stats'    => $stats,
        );
    }
}
