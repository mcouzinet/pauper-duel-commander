<?php
/**
 * Deck Renderer
 *
 * Handles rendering logic for decklists:
 * - Fetching card data from Scryfall
 * - Sorting cards by type and CMC
 * - Formatting mana costs
 * - Preparing data for templates
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-scryfall-service.php';

class Deck_Renderer {

    /**
     * Type sorting order
     */
    const TYPE_ORDER = array(
        'Creature' => 1,
        'Planeswalker' => 2,
        'Instant' => 3,
        'Sorcery' => 4,
        'Artifact' => 5,
        'Enchantment' => 6,
        'Land' => 7,
        'Other' => 8
    );

    /**
     * Fetch and enrich card data
     *
     * Takes parsed decklist and fetches full Scryfall data for each card.
     *
     * @param array $parsed_cards Array from Decklist_Parser::parse()
     * @return array Enriched cards with Scryfall data
     */
    public static function fetch_card_data($parsed_cards) {
        $enriched = array();

        foreach ($parsed_cards as $card) {
            $card_data = Scryfall_Service::get_card_by_name($card['name']);

            if ($card_data) {
                $enriched[] = array(
                    'quantity' => $card['quantity'],
                    'name' => $card['name'],
                    'scryfall_data' => $card_data,
                    'cmc' => Scryfall_Service::get_cmc($card_data),
                    'type' => Scryfall_Service::get_primary_type($card_data),
                    'type_line' => Scryfall_Service::get_type_line($card_data),
                    'mana_cost' => Scryfall_Service::get_mana_cost($card_data),
                    'colors' => Scryfall_Service::get_colors($card_data),
                    'image_url' => Scryfall_Service::get_card_image($card_data, 'normal'),
                    'image_url_small' => Scryfall_Service::get_card_image($card_data, 'small'),
                );
            } else {
                // Card not found - include with minimal data
                $enriched[] = array(
                    'quantity' => $card['quantity'],
                    'name' => $card['name'],
                    'scryfall_data' => null,
                    'cmc' => 0,
                    'type' => 'Other',
                    'type_line' => 'Unknown',
                    'mana_cost' => '',
                    'colors' => array(),
                    'image_url' => null,
                    'image_url_small' => null,
                );
            }
        }

        return $enriched;
    }

    /**
     * Sort cards by type and CMC
     *
     * @param array $cards Enriched cards array
     * @return array Sorted cards
     */
    public static function sort_cards($cards) {
        usort($cards, function($a, $b) {
            // First, sort by type
            $type_a = self::TYPE_ORDER[$a['type']] ?? 99;
            $type_b = self::TYPE_ORDER[$b['type']] ?? 99;

            if ($type_a !== $type_b) {
                return $type_a - $type_b;
            }

            // Then by CMC
            $cmc_diff = $a['cmc'] - $b['cmc'];
            if ($cmc_diff !== 0) {
                return $cmc_diff;
            }

            // Finally by name alphabetically
            return strcasecmp($a['name'], $b['name']);
        });

        return $cards;
    }

    /**
     * Group cards by type
     *
     * @param array $cards Sorted cards array
     * @return array Cards grouped by type
     */
    public static function group_by_type($cards) {
        $grouped = array();

        foreach ($cards as $card) {
            $type = $card['type'];

            if (!isset($grouped[$type])) {
                $grouped[$type] = array();
            }

            $grouped[$type][] = $card;
        }

        // Sort groups by type order
        uksort($grouped, function($a, $b) {
            $order_a = self::TYPE_ORDER[$a] ?? 99;
            $order_b = self::TYPE_ORDER[$b] ?? 99;
            return $order_a - $order_b;
        });

        return $grouped;
    }

    /**
     * Format mana cost with Mana Font icons
     *
     * Converts Scryfall mana cost string (e.g., "{2}{U}{U}") to HTML with Mana Font icons.
     * Uses the Mana Font library: https://mana.andrewgioia.com/
     *
     * @param string $mana_cost Mana cost string from Scryfall
     * @return string HTML formatted mana cost with icon fonts
     */
    public static function format_mana_cost($mana_cost) {
        if (empty($mana_cost)) {
            return '';
        }

        // Replace each mana symbol with Mana Font icon
        // {W} -> <i class="ms ms-w ms-cost"></i>
        // {2} -> <i class="ms ms-2 ms-cost"></i>
        // {X} -> <i class="ms ms-x ms-cost"></i>
        $formatted = preg_replace_callback('/{([^}]+)}/', function($matches) {
            $symbol = $matches[1];

            // Convert symbol to Mana Font class
            // Handle special cases: numbers, X/Y/Z, colored mana, hybrid mana
            $symbol_lower = strtolower($symbol);

            // Handle hybrid mana (e.g., W/U, 2/W, etc.)
            if (strpos($symbol_lower, '/') !== false) {
                // Split hybrid symbols: "w/u" becomes "wu"
                $symbol_lower = str_replace('/', '', $symbol_lower);
            }

            // Sanitize the class name
            $mana_class = sanitize_html_class($symbol_lower);

            return '<i class="ms ms-' . esc_attr($mana_class) . ' ms-cost" title="' . esc_attr($symbol) . '"></i>';
        }, $mana_cost);

        return '<span class="mana-cost-wrapper">' . $formatted . '</span>';
    }

    /**
     * Format color name to mana symbol
     *
     * Converts color names (e.g., "Blanc", "W", "UB") to Mana Font icons.
     * Supports multiple colors (e.g., "UB", "RB", "WUBR").
     *
     * @param string $color_name Color name or code(s)
     * @return string HTML formatted mana symbol(s)
     */
    public static function format_color_symbol($color_name) {
        if (empty($color_name)) {
            return '';
        }

        // Map color names to mana symbols
        $color_map = array(
            'Blanc' => 'W',
            'Bleu' => 'U',
            'Noir' => 'B',
            'Rouge' => 'R',
            'Vert' => 'G',
            'Incolore' => 'C',
        );

        // If it's a full color name, convert it
        if (isset($color_map[$color_name])) {
            $color_name = $color_map[$color_name];
        }

        // Process each character as a color symbol (for "UB", "RB", "WUBR", etc.)
        $symbols = '';
        $valid_colors = array('W', 'U', 'B', 'R', 'G', 'C');

        for ($i = 0; $i < strlen($color_name); $i++) {
            $char = strtoupper($color_name[$i]);

            // Only process valid mana color letters
            if (in_array($char, $valid_colors)) {
                $symbol_lower = strtolower($char);
                $mana_class = sanitize_html_class($symbol_lower);
                $symbols .= '<i class="ms ms-' . esc_attr($mana_class) . ' ms-cost ms-shadow" style="font-size: 1.25em;" title="' . esc_attr($char) . '"></i>';
            }
        }

        return $symbols;
    }

    /**
     * Calculate deck statistics
     *
     * @param array $cards Enriched cards array
     * @return array Statistics
     */
    public static function calculate_stats($cards) {
        $total_cards = 0;
        $type_counts = array();
        $cmc_distribution = array(
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            '7+' => 0
        );
        $total_cmc = 0;
        $non_land_cards = 0;
        $color_counts = array(
            'W' => 0,  // White
            'U' => 0,  // Blue
            'B' => 0,  // Black
            'R' => 0,  // Red
            'G' => 0,  // Green
            'C' => 0,  // Colorless
            'Multi' => 0  // Multicolor
        );

        foreach ($cards as $card) {
            $total_cards += $card['quantity'];

            // Count by type
            $type = $card['type'];
            if (!isset($type_counts[$type])) {
                $type_counts[$type] = 0;
            }
            $type_counts[$type] += $card['quantity'];

            // CMC distribution (excluding lands)
            if ($type !== 'Land') {
                $cmc = $card['cmc'];
                if ($cmc >= 7) {
                    $cmc_distribution['7+'] += $card['quantity'];
                } else {
                    $cmc_distribution[$cmc] += $card['quantity'];
                }

                // Calculate average CMC
                $total_cmc += $cmc * $card['quantity'];
                $non_land_cards += $card['quantity'];
            }

            // Count by color
            $colors = isset($card['colors']) ? $card['colors'] : array();
            if (empty($colors)) {
                $color_counts['C'] += $card['quantity'];
            } elseif (count($colors) > 1) {
                $color_counts['Multi'] += $card['quantity'];
            } else {
                $color_code = $colors[0];
                if (isset($color_counts[$color_code])) {
                    $color_counts[$color_code] += $card['quantity'];
                }
            }
        }

        // Sort CMC distribution
        ksort($cmc_distribution);

        // Calculate average CMC
        $average_cmc = $non_land_cards > 0 ? round($total_cmc / $non_land_cards, 1) : 0;

        // Remove colors with 0 cards
        $color_counts = array_filter($color_counts, function($count) {
            return $count > 0;
        });

        return array(
            'total_cards' => $total_cards,
            'unique_cards' => count($cards),
            'type_counts' => $type_counts,
            'cmc_distribution' => $cmc_distribution,
            'color_counts' => $color_counts,
            'average_cmc' => $average_cmc,
        );
    }

    /**
     * Fetch special card data (commander or partner)
     *
     * @param string $card_name Card name to fetch
     * @param bool $include_art_crop Whether to include art_crop image (for commander background)
     * @return array|null Card data array or null if not found
     */
    private static function fetch_special_card($card_name, $include_art_crop = false) {
        if (empty($card_name)) {
            return null;
        }

        $card_scryfall = Scryfall_Service::get_card_by_name($card_name);
        if (!$card_scryfall) {
            return null;
        }

        $card_data = array(
            'name' => $card_name,
            'scryfall_data' => $card_scryfall,
            'image_url' => Scryfall_Service::get_card_image($card_scryfall, 'normal'),
            'mana_cost' => Scryfall_Service::get_mana_cost($card_scryfall),
            'type_line' => Scryfall_Service::get_type_line($card_scryfall),
        );

        if ($include_art_crop) {
            $card_data['image_url_art_crop'] = Scryfall_Service::get_card_image($card_scryfall, 'art_crop');
        }

        return $card_data;
    }

    /**
     * Prepare deck data for template
     *
     * Main method that orchestrates fetching, sorting, and formatting.
     *
     * @param array $parsed_cards Parsed cards from Decklist_Parser
     * @param string $commander_name Commander card name
     * @param string $partner_name Partner card name (optional)
     * @return array Complete deck data ready for template
     */
    public static function prepare_deck_data($parsed_cards, $commander_name = '', $partner_name = '') {
        // Fetch and enrich all cards
        $enriched_cards = self::fetch_card_data($parsed_cards);

        // Sort cards
        $sorted_cards = self::sort_cards($enriched_cards);

        // Group by type
        $grouped_cards = self::group_by_type($sorted_cards);

        // Get commander data (with art_crop for background)
        $commander_data = self::fetch_special_card($commander_name, true);

        // Get partner data
        $partner_data = self::fetch_special_card($partner_name, false);

        // Calculate stats
        $stats = self::calculate_stats($enriched_cards);

        return array(
            'cards' => $sorted_cards,
            'cards_by_type' => $grouped_cards,
            'commander' => $commander_data,
            'partner' => $partner_data,
            'stats' => $stats,
        );
    }
}
