<?php
/**
 * Decklist Parser
 *
 * Parses MTGO-format decklists into structured data.
 * Format example:
 *   1 Lightning Bolt
 *   4 Mountain
 *   1 Sol Ring
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Decklist_Parser {

    /**
     * Parse MTGO format decklist
     *
     * Converts MTGO format text into structured array.
     * Each line should be: "quantity card_name"
     *
     * @param string $decklist_text Raw decklist text
     * @return array Array of cards with structure:
     *   [
     *     ['quantity' => 1, 'name' => 'Lightning Bolt'],
     *     ['quantity' => 4, 'name' => 'Mountain'],
     *     ...
     *   ]
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
     * Parse a single line of MTGO format
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
        // Trim whitespace
        $line = trim($line);

        // Skip empty lines
        if (empty($line)) {
            return null;
        }

        // Skip comment lines (starting with // or #)
        if (preg_match('/^(\/\/|#)/', $line)) {
            return null;
        }

        // Skip sideboard marker
        if (strtolower($line) === 'sideboard' || strtolower($line) === 'sideboard:') {
            return null;
        }

        // Parse format: "quantity card_name"
        // Match number at start, followed by space(s), followed by card name
        if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
            $quantity = (int) $matches[1];
            $card_name = trim($matches[2]);

            // Validate
            if ($quantity > 0 && !empty($card_name)) {
                return array(
                    'quantity' => $quantity,
                    'name' => $card_name
                );
            }
        }

        // Line doesn't match expected format
        return null;
    }

    /**
     * Validate decklist format
     *
     * Checks if the decklist contains valid MTGO format lines.
     *
     * @param string $decklist_text Raw decklist text
     * @return bool True if valid, false otherwise
     */
    public static function validate($decklist_text) {
        if (empty($decklist_text)) {
            return false;
        }

        $cards = self::parse($decklist_text);

        // Must have at least one valid card
        return count($cards) > 0;
    }

    /**
     * Count total cards in decklist
     *
     * @param string $decklist_text Raw decklist text
     * @return int Total number of cards (quantity sum)
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
     * Get unique card count
     *
     * @param string $decklist_text Raw decklist text
     * @return int Number of unique cards
     */
    public static function count_unique($decklist_text) {
        $cards = self::parse($decklist_text);
        return count($cards);
    }

    /**
     * Format decklist back to MTGO format
     *
     * @param array $cards Array of card data
     * @return string MTGO format text
     */
    public static function format_to_mtgo($cards) {
        $lines = array();

        foreach ($cards as $card) {
            if (isset($card['quantity']) && isset($card['name'])) {
                $lines[] = $card['quantity'] . ' ' . $card['name'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format decklist to Moxfield format
     *
     * Moxfield format is similar to MTGO but with some differences.
     *
     * @param array $cards Array of card data
     * @param string $commander Commander card name
     * @param string $partner Partner card name (optional)
     * @return string Moxfield format text
     */
    public static function format_to_moxfield($cards, $commander = '', $partner = '') {
        $lines = array();

        // Add commander section
        if (!empty($commander)) {
            $lines[] = 'Commander';
            $lines[] = '1 ' . $commander;

            if (!empty($partner)) {
                $lines[] = '1 ' . $partner;
            }

            $lines[] = '';
        }

        // Add main deck
        if (!empty($cards)) {
            $lines[] = 'Deck';

            foreach ($cards as $card) {
                if (isset($card['quantity']) && isset($card['name'])) {
                    $lines[] = $card['quantity'] . ' ' . $card['name'];
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Normalize card name
     *
     * Handles common variations and formatting issues.
     *
     * @param string $card_name Raw card name
     * @return string Normalized card name
     */
    public static function normalize_card_name($card_name) {
        // Trim whitespace
        $card_name = trim($card_name);

        // Remove extra spaces
        $card_name = preg_replace('/\s+/', ' ', $card_name);

        // Handle split cards (Fire // Ice -> Fire // Ice)
        $card_name = preg_replace('/\s*\/\/\s*/', ' // ', $card_name);

        return $card_name;
    }

    /**
     * Extract card names from parsed decklist
     *
     * @param array $cards Parsed cards array
     * @return array Array of card names
     */
    public static function get_card_names($cards) {
        return array_map(function($card) {
            return $card['name'];
        }, $cards);
    }
}
