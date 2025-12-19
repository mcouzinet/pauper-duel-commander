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
     * Format mana cost to HTML
     *
     * Converts Scryfall mana cost string (e.g., "{2}{U}{U}") to HTML with symbols.
     *
     * @param string $mana_cost Mana cost string from Scryfall
     * @return string HTML formatted mana cost
     */
    public static function format_mana_cost($mana_cost) {
        if (empty($mana_cost)) {
            return '';
        }

        // Replace each mana symbol with a span
        // {W} -> <span class="mana-symbol mana-w">W</span>
        $formatted = preg_replace_callback('/{([^}]+)}/', function($matches) {
            $symbol = $matches[1];
            $class = 'mana-' . sanitize_html_class(strtolower(str_replace('/', '', $symbol)));

            return '<span class="mana-symbol ' . esc_attr($class) . '" title="' . esc_attr($symbol) . '">' . esc_html($symbol) . '</span>';
        }, $mana_cost);

        return '<span class="mana-cost">' . $formatted . '</span>';
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
        $cmc_distribution = array();
        $total_cmc = 0;
        $non_land_cards = 0;

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
                if (!isset($cmc_distribution[$cmc])) {
                    $cmc_distribution[$cmc] = 0;
                }
                $cmc_distribution[$cmc] += $card['quantity'];

                // Calculate average CMC
                $total_cmc += $cmc * $card['quantity'];
                $non_land_cards += $card['quantity'];
            }
        }

        // Sort CMC distribution
        ksort($cmc_distribution);

        // Calculate average CMC
        $average_cmc = $non_land_cards > 0 ? round($total_cmc / $non_land_cards, 1) : 0;

        return array(
            'total_cards' => $total_cards,
            'unique_cards' => count($cards),
            'type_counts' => $type_counts,
            'cmc_distribution' => $cmc_distribution,
            'average_cmc' => $average_cmc,
        );
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

        // Get commander data
        $commander_data = null;
        if (!empty($commander_name)) {
            $commander_scryfall = Scryfall_Service::get_card_by_name($commander_name);
            if ($commander_scryfall) {
                $commander_data = array(
                    'name' => $commander_name,
                    'scryfall_data' => $commander_scryfall,
                    'image_url' => Scryfall_Service::get_card_image($commander_scryfall, 'normal'),
                    'image_url_art_crop' => Scryfall_Service::get_card_image($commander_scryfall, 'art_crop'),
                    'mana_cost' => Scryfall_Service::get_mana_cost($commander_scryfall),
                    'type_line' => Scryfall_Service::get_type_line($commander_scryfall),
                );
            }
        }

        // Get partner data
        $partner_data = null;
        if (!empty($partner_name)) {
            $partner_scryfall = Scryfall_Service::get_card_by_name($partner_name);
            if ($partner_scryfall) {
                $partner_data = array(
                    'name' => $partner_name,
                    'scryfall_data' => $partner_scryfall,
                    'image_url' => Scryfall_Service::get_card_image($partner_scryfall, 'normal'),
                    'mana_cost' => Scryfall_Service::get_mana_cost($partner_scryfall),
                    'type_line' => Scryfall_Service::get_type_line($partner_scryfall),
                );
            }
        }

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
