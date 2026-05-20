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
     * @param string $decklist_text Raw decklist text
     * @return array Array of ['quantity' => int, 'name' => string]
     */
    public static function parse($decklist_text) {
        if (empty($decklist_text)) {
            return array();
        }

        $cards = array();
        $lines = explode("\n", $decklist_text);

        foreach ($lines as $line) {
            $parsed = self::parse_line($line);
            if ($parsed) {
                $cards[] = $parsed;
            }
        }

        return $cards;
    }

    /**
     * Parse a single line of MTGO format.
     *
     * Expected format: "quantity card_name"
     * Examples:
     *   "1 Lightning Bolt"
     *   "4 Mountain"
     *   "2 Counterspell"
     *
     * @param string $line Single line from decklist
     * @return array|null Parsed card data or null if invalid
     */
    private static function parse_line($line) {
        $line = trim($line);

        // Skip empty lines
        if (empty($line)) {
            return null;
        }

        // Skip comment lines (starting with // or #)
        if (preg_match('/^(\/\/|#)/', $line)) {
            return null;
        }

        // Skip sideboard / deck section markers
        $lower = strtolower($line);
        if ($lower === 'sideboard' || $lower === 'sideboard:' || $lower === 'deck' || $lower === 'deck:' || $lower === 'commander' || $lower === 'commander:') {
            return null;
        }

        // Parse "quantity card_name" (e.g. "1 Lightning Bolt")
        if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
            $quantity  = (int) $matches[1];
            $card_name = trim($matches[2]);

            if ($quantity > 0 && !empty($card_name)) {
                return array(
                    'quantity' => $quantity,
                    'name'     => self::normalize_card_name($card_name),
                );
            }
        }

        // Fallback: line without quantity prefix (assume 1 copy)
        if (preg_match('/^[A-Za-z]/', $line)) {
            return array(
                'quantity' => 1,
                'name'     => self::normalize_card_name($line),
            );
        }

        return null;
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
        // Normalize split card separator: "Fire // Ice"
        $card_name = preg_replace('/\s*\/\/\s*/', ' // ', $card_name);
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
