<?php
/**
 * Deck Validator (standalone, no WordPress)
 *
 * Validates a PDC (Pauper Duel Commander) decklist against format rules:
 * 1. Commander required + found on Scryfall
 * 2. Commander must be a Creature, Vehicle, Spacecraft or Background
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
    const BASIC_LAND_NAMES = array(
        'Plains', 'Island', 'Swamp', 'Mountain', 'Forest', 'Wastes',
        'Snow-Covered Plains', 'Snow-Covered Island', 'Snow-Covered Swamp',
        'Snow-Covered Mountain', 'Snow-Covered Forest', 'Snow-Covered Wastes',
    );

    /**
     * Card types allowed in the command zone (rule 2.4).
     *
     * A Background is only ever the partner side of a "Choose a Background"
     * pairing, but the validator checks each commander independently and does not
     * enforce pairing legality, so listing it here is enough.
     */
    const COMMANDER_TYPES = array('Creature', 'Vehicle', 'Spacecraft', 'Background');

    /**
     * Ban list path override. Null means PDC_BANLIST_PATH. Set by the test suite.
     *
     * @var string|null
     */
    public static $banlist_path = null;


    /**
     * Response language. Set by the endpoint from the request; French by default
     * so every existing caller keeps the behaviour it had.
     *
     * The site is multilingual but this endpoint only ever answered in French, so
     * a player browsing in another language got translated rule labels wrapped
     * around French sentences.
     *
     * @var string
     */
    public static $locale = 'fr';

    /**
     * Lookup order per locale, mirroring lib/i18n.ts on the site side.
     *
     * English is the backup everywhere: a message missing from a locale reads
     * better in English than in French for anyone who did not pick French.
     */
    const LOCALE_FALLBACKS = array(
        'fr' => array('fr', 'en'),
        'en' => array('en', 'fr'),
        'it' => array('it', 'en', 'fr'),
    );

    /**
     * User-facing messages, keyed by id then locale.
     *
     * `%s` placeholders are filled positionally by msg(). French strings stay
     * unaccented, matching the rest of the API; Italian keeps its accents, where
     * dropping them would misspell the word rather than merely strip a diacritic.
     */
    const MESSAGES = array(
        'unknown_type' => array('fr' => 'inconnu', 'en' => 'unknown', 'it' => 'sconosciuto'),
        'unknown_rarity' => array('fr' => 'inconnue', 'en' => 'unknown', 'it' => 'sconosciuta'),
        'colorless' => array('fr' => 'Incolore', 'en' => 'Colorless', 'it' => 'Incolore'),
        'commander_required' => array(
            'fr' => 'Le nom du general est obligatoire.',
            'en' => 'The commander name is required.',
            'it' => 'Il nome del comandante è obbligatorio.',
        ),
        'format_invalid' => array(
            'fr' => 'La decklist est vide ou dans un format invalide. Utilisez le format MTGO : "1 Nom de la carte".',
            'en' => 'The decklist is empty or malformed. Use the MTGO format: "1 Card name".',
            'it' => 'La decklist è vuota o in un formato non valido. Usa il formato MTGO: "1 Nome della carta".',
        ),
        'commander_not_found' => array(
            'fr' => 'Le general "%s" est introuvable sur Scryfall. Verifiez l\'orthographe (en anglais).',
            'en' => 'Commander "%s" was not found on Scryfall. Check the spelling (English name).',
            'it' => 'Il comandante "%s" non è stato trovato su Scryfall. Controlla l\'ortografia (nome inglese).',
        ),
        'partner_not_found' => array(
            'fr' => 'Le partenaire "%s" est introuvable sur Scryfall. Verifiez l\'orthographe (en anglais).',
            'en' => 'Partner "%s" was not found on Scryfall. Check the spelling (English name).',
            'it' => 'Il partner "%s" non è stato trovato su Scryfall. Controlla l\'ortografia (nome inglese).',
        ),
        'commander_type' => array(
            'fr' => 'Le general "%s" doit etre une creature, un vehicule, un vaisseau spatial ou un background (type actuel : %s).',
            'en' => 'Commander "%s" must be a creature, vehicle, spacecraft or background (current type: %s).',
            'it' => 'Il comandante "%s" deve essere una creatura, un veicolo, un\'astronave o un background (tipo attuale: %s).',
        ),
        'commander_rarity' => array(
            'fr' => 'Le general "%s" doit avoir ete imprime au moins une fois en rarete Uncommon (rarete actuelle : %s).',
            'en' => 'Commander "%s" must have been printed at uncommon at least once (current rarity: %s).',
            'it' => 'Il comandante "%s" deve essere stato stampato almeno una volta in rarità Uncommon (rarità attuale: %s).',
        ),
        'deck_size' => array(
            'fr' => 'Le deck contient %s carte(s). Un deck PDC doit contenir %s.',
            'en' => 'The deck holds %s card(s). A PDC deck must hold %s.',
            'it' => 'Il mazzo contiene %s carta/e. Un mazzo PDC deve contenerne %s.',
        ),
        'deck_size_expected_solo' => array(
            'fr' => '99 cartes (avec 1 general)',
            'en' => '99 cards (plus 1 commander)',
            'it' => '99 carte (più 1 comandante)',
        ),
        'deck_size_expected_partner' => array(
            'fr' => '98 cartes (avec 2 generaux partenaires)',
            'en' => '98 cards (plus 2 partner commanders)',
            'it' => '98 carte (più 2 comandanti partner)',
        ),
        'not_found' => array(
            'fr' => 'Les cartes suivantes sont introuvables sur Scryfall. Verifiez l\'orthographe (noms en anglais) :',
            'en' => 'The following cards were not found on Scryfall. Check the spelling (English names):',
            'it' => 'Le carte seguenti non sono state trovate su Scryfall. Controlla l\'ortografia (nomi in inglese):',
        ),
        'duplicates' => array(
            'fr' => 'Les cartes suivantes apparaissent en plusieurs exemplaires (seuls les terrains de base sont autorises en plusieurs copies) :',
            'en' => 'The following cards appear more than once (only basic lands may be duplicated):',
            'it' => 'Le carte seguenti compaiono in più copie (solo le terre base possono essere duplicate):',
        ),
        'rarity' => array(
            'fr' => 'Les cartes suivantes n\'ont jamais ete imprimees en rarete Commune (non legales en Pauper) :',
            'en' => 'The following cards have never been printed at common rarity (not Pauper-legal):',
            'it' => 'Le carte seguenti non sono mai state stampate in rarità Comune (non legali in Pauper):',
        ),
        'color_identity' => array(
            'fr' => 'Les cartes suivantes sont hors de l\'identite de couleur du general %s :',
            'en' => 'The following cards fall outside the commander\'s color identity %s:',
            'it' => 'Le carte seguenti sono fuori dall\'identità di colore del comandante %s:',
        ),
        'ban_list' => array(
            'fr' => 'Les cartes suivantes sont bannies dans le format PDC :',
            'en' => 'The following cards are banned in the PDC format:',
            'it' => 'Le carte seguenti sono bandite nel formato PDC:',
        ),
    );

    /**
     * Resolve a catalogue message in the current locale.
     *
     * Extra arguments fill the `%s` placeholders in order. An unknown id returns
     * the id itself, which is visible in testing but never silently blank.
     *
     * @param string $id
     * @return string
     */
    public static function msg($id) {
        if (!isset(self::MESSAGES[$id])) {
            return $id;
        }
        $entry = self::MESSAGES[$id];

        $chain = isset(self::LOCALE_FALLBACKS[self::$locale])
            ? self::LOCALE_FALLBACKS[self::$locale]
            : self::LOCALE_FALLBACKS['fr'];
        $text = $entry['fr'];
        foreach ($chain as $candidate) {
            if (isset($entry[$candidate])) {
                $text = $entry[$candidate];
                break;
            }
        }

        $args = array_slice(func_get_args(), 1);
        foreach ($args as $value) {
            $text = preg_replace('/%s/', str_replace('$', '\\$', (string) $value), $text, 1);
        }
        return $text;
    }

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
                'message' => self::msg('commander_required'),
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Parse decklist ---
        $parsed_cards = DecklistParser::parse($decklist_text);

        if (empty($parsed_cards)) {
            $errors[] = array(
                'rule'    => 'format',
                'message' => self::msg('format_invalid'),
                'cards'   => array(),
            );
            return self::build_result(false, $errors, $warnings, null);
        }

        // --- Fetch commander(s) from Scryfall ---
        $commander_data = ScryfallService::get_card_by_name($commander_name);
        if (!$commander_data) {
            $errors[] = array(
                'rule'    => 'commander',
                'message' => self::msg('commander_not_found', $commander_name),
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
                    'message' => self::msg('partner_not_found', $partner_name),
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
     * Rule 2.4: Commander must be a Creature, Vehicle, Spacecraft or Background.
     *
     * The command zone is not restricted to creatures.
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
            $type_label = $type_line ? $type_line : self::msg('unknown_type');
            $errors[] = array(
                'rule'    => 'commander_type',
                'message' => self::msg('commander_type', $card_name, $type_label),
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
            $rarity_label = $rarity ? ucfirst($rarity) : self::msg('unknown_rarity');
            $errors[] = array(
                'rule'    => 'commander_rarity',
                'message' => self::msg('commander_rarity', $card_name, $rarity_label),
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
            $label = self::msg($has_partner ? 'deck_size_expected_partner' : 'deck_size_expected_solo');
            $errors[] = array(
                'rule'    => 'deck_size',
                'message' => self::msg('deck_size', $total, $label),
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
                'message' => self::msg('not_found'),
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
                'message' => self::msg('duplicates'),
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
                'message' => self::msg('rarity'),
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
            $identity_label = empty($allowed_colors) ? self::msg('colorless') : implode('', $allowed_colors);
            if (!empty($partner_name)) {
                $label = '"' . $commander_name . '" + "' . $partner_name . '" (' . $identity_label . ')';
            } else {
                $label = '"' . $commander_name . '" (' . $identity_label . ')';
            }
            $errors[] = array(
                'rule'    => 'color_identity',
                'message' => self::msg('color_identity', $label),
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
                'message' => self::msg('ban_list'),
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
     * Reads the supertypes rather than matching the literal string "Basic Land":
     * a Snow-Covered Plains is "Basic Snow Land - Plains", so "Snow" sits between
     * the two words and the substring is never found. Those decks were rejected
     * for duplicate lands even though rule 2.2 exempts every basic.
     *
     * Supertypes are whatever precedes the em dash ("Basic Snow Land" in
     * "Basic Snow Land - Plains"), or the whole line when there is none
     * ("Basic Land" for Wastes). Requiring both words there also keeps
     * Dryad Arbor out: "Land Creature - Forest Dryad" is not Basic.
     *
     * @param array $card Enriched card array
     * @return bool
     */
    private static function is_basic_land($card) {
        $type_line = isset($card['type_line']) ? $card['type_line'] : '';

        if ($type_line !== '') {
            // Scryfall uses an em dash; accept a hyphen too, in case a caller
            // hands us a hand-typed type line.
            $supertypes = preg_split('/\x{2014}|--|\s-\s/u', $type_line)[0];
            if (preg_match('/\bBasic\b/i', $supertypes) && preg_match('/\bLand\b/i', $supertypes)) {
                return true;
            }
        }

        // No Scryfall data: fall back to the names, snow variants included.
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
