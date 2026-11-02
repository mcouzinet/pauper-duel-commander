<?php
/**
 * Decklist Parser (standalone, no WordPress)
 *
 * Parses MTGO-format decklists into structured data.
 * Format example:
 *   1 Lightning Bolt
 *   4 Mountain
 *   1 Sol Ring
 *
 * @package PDC_API
 * @since 2.0.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class DecklistParser {

    /**
     * Parse MTGO format decklist.
     *
     * Converts MTGO format text into a structured array.
     * Each line should be: "quantity card_name"
     *
     * A card written on several lines is one entry, its quantities added up:
     * one entry per line let "1 Sacred Peaks" twice pass the singleton rule,
     * which reads each entry's quantity.
     *
     * @param string $decklist_text Raw decklist text
     * @return array Array of ['quantity' => int, 'name' => string]
     */
    public static function parse($decklist_text) {
        if (empty($decklist_text)) {
            return array();
        }

        $cards = array();
        $lines = explode("\n", $decklist_text);
        $skip  = false;

        foreach ($lines as $line) {
            // A section header switches sections: a maybeboard or token list is
            // not part of the deck, whatever follows it until the next header.
            $section = self::section_header($line);
            if ($section !== null) {
                $skip = in_array($section, self::IGNORED_SECTIONS, true);
                continue;
            }
            if ($skip) {
                continue;
            }
            $parsed = self::parse_line($line);
            if (!$parsed) {
                continue;
            }
            $key = strtolower($parsed['name']);
            if (isset($cards[$key])) {
                $cards[$key]['quantity'] += $parsed['quantity'];
            } else {
                $cards[$key] = $parsed;
            }
        }

        return array_values($cards);
    }

    /** Section headers written by deckbuilders (Moxfield, Archidekt, Arena...). */
    const SECTIONS = array(
        'about', 'artifact', 'artifacts', 'battle', 'battles', 'commander', 'commanders',
        'companion', 'considering', 'creature', 'creatures', 'deck', 'enchantment',
        'enchantments', 'instant', 'instants', 'land', 'lands', 'main', 'mainboard',
        'maindeck', 'maybeboard', 'other', 'planeswalker', 'planeswalkers', 'sideboard',
        'sorceries', 'sorcery', 'spells', 'tokens',
    );

    /** Sections whose cards are not in the deck. */
    const IGNORED_SECTIONS = array('maybeboard', 'considering', 'tokens');

    /**
     * Section name if the line is a header ("Creatures (25)", "// Maybeboard",
     * "SIDEBOARD:"), null otherwise.
     */
    private static function section_header($line) {
        $line = strtolower(trim($line));
        if (!preg_match('/^(?:\/\/\s*)?([a-z ]+?)\s*(?:\(\d+\))?\s*:?$/', $line, $m)) {
            return null;
        }
        return in_array($m[1], self::SECTIONS, true) ? $m[1] : null;
    }

    /**
     * Parse a single line.
     *
     * Accepts the quantity as "4 Mountain", "4x Mountain" or none (1 copy), and
     * strips what deckbuilders add around the name: printing ("(2X2) 117",
     * "[M20]"), foil or role markers ("*F*", "*CMDR*"), tags ("#!Removal",
     * "[Removal]", "^Have^"). Only the name reaches the validator.
     *
     * @param string $line Single line from decklist
     * @return array|null Parsed card data or null if invalid
     */
    private static function parse_line($line) {
        // Bullets from a copy-paste of a rendered list.
        $line = trim(preg_replace('/^\s*[-*\x{2022}]\s+/u', '', $line));

        if ($line === '' || preg_match('/^(\/\/|#)/', $line)) {
            return null;
        }

        $quantity = 1;
        if (preg_match('/^(\d+)\s*[xX]?\s+(.+)$/', $line, $m)) {
            $quantity = (int) $m[1];
            $line     = $m[2];
        } elseif (!preg_match('/^[\p{L}\[]/u', $line)) {
            return null;
        }

        $name = $line;
        $name = preg_replace('/^\[[^\]]*\]\s*/', '', $name);           // Deckstats: "[M20] Name"
        $name = preg_replace('/\s+#.*$/', '', $name);                      // Moxfield / Deckstats tags
        $name = preg_replace('/\s*\^[^^]*\^/', '', $name);                // Archidekt colour tags
        $name = preg_replace('/\s*\[[^\]]*\]/', '', $name);              // Archidekt categories
        $name = preg_replace('/\s*\*[A-Za-z]+\*/', '', $name);            // *F*, *E*, *CMDR*
        $name = preg_replace('/\s+\([A-Za-z0-9]{2,6}\)(\s+\S+)?\s*$/', '', $name); // (SET) 123
        $name = self::normalize_card_name($name);

        if ($quantity <= 0 || $name === '') {
            return null;
        }
        return array('quantity' => $quantity, 'name' => $name);
    }

    /**
     * Drop one copy of each commander from parsed cards.
     *
     * Deckbuilder exports list the commander with the deck: that copy is the
     * commander slot, not a 100th card.
     *
     * @param array $cards Array from parse()
     * @param array $names Commander and partner names (empty ones ignored)
     * @return array
     */
    public static function without_commanders($cards, $names) {
        foreach ($names as $name) {
            $key = strtolower(self::normalize_card_name((string) $name));
            foreach ($cards as $i => $card) {
                if ($key !== '' && strtolower($card['name']) === $key) {
                    if (--$cards[$i]['quantity'] === 0) {
                        unset($cards[$i]);
                    }
                    break;
                }
            }
        }
        return array_values($cards);
    }

    /**
     * The deck as plain "quantity name" lines, whatever format it came in.
     *
     * @param array $cards Array from parse()
     * @return string
     */
    public static function to_text($cards) {
        return implode("\n", array_map(function ($c) {
            return $c['quantity'] . ' ' . $c['name'];
        }, $cards));
    }

    /**
     * Validate decklist format.
     *
     * @param string $decklist_text Raw decklist text
     * @return bool True if at least one valid card line
     */
    public static function validate($decklist_text) {
        if (empty($decklist_text)) {
            return false;
        }
        $cards = self::parse($decklist_text);
        return count($cards) > 0;
    }

    /**
     * Count total cards (sum of quantities).
     *
     * @param string $decklist_text Raw decklist text
     * @return int
     */
    public static function count_cards($decklist_text) {
        $cards = self::parse($decklist_text);
        $total = 0;
        foreach ($cards as $card) {
            $total += $card['quantity'];
        }
        return $total;
    }

    /**
     * Count unique card entries.
     *
     * @param string $decklist_text Raw decklist text
     * @return int
     */
    public static function count_unique($decklist_text) {
        return count(self::parse($decklist_text));
    }

    /**
     * Normalize card name.
     *
     * @param string $card_name Raw card name
     * @return string Normalized card name
     */
    public static function normalize_card_name($card_name) {
        $card_name = trim($card_name);
        $card_name = preg_replace('/\s+/', ' ', $card_name);
        // Normalize split / double-faced separator: "Fire // Ice", "Delver / Insectile"
        $card_name = preg_replace('/\s*\/\/\s*|\s+\/\s+/', ' // ', $card_name);
        return $card_name;
    }

    /**
     * Extract card names from parsed decklist.
     *
     * @param array $cards Parsed cards array
     * @return array Array of card name strings
     */
    public static function get_card_names($cards) {
        return array_map(function ($card) {
            return $card['name'];
        }, $cards);
    }
}
