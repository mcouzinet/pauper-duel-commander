<?php
/**
 * Scryfall API Service
 *
 * Handles all interactions with the Scryfall API for Magic: The Gathering card data.
 * Provides caching via WordPress transients to minimize API calls.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scryfall_Service {

    /**
     * Cache duration (7 days)
     */
    const CACHE_DURATION = 30 * DAY_IN_SECONDS;

    /**
     * Scryfall API base URL
     */
    const API_BASE = 'https://api.scryfall.com';

    /**
     * Get card data by exact name
     *
     * Uses Scryfall's named cards endpoint for exact matching.
     * Results are cached for 24 hours.
     *
     * @param string $card_name The exact name of the card
     * @return object|null Card data object or null on failure
     */
    public static function get_card_by_name($card_name) {
        // Normalize card name for cache key
        $cache_key = 'scryfall_name_' . sanitize_key($card_name);

        // Check cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Query Scryfall API
        $api_url = self::API_BASE . '/cards/named?exact=' . urlencode($card_name);
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'PDC-Theme/' . wp_get_theme()->get('Version') . '; ' . get_bloginfo('url')
            )
        ));

        if (is_wp_error($response)) {
            error_log('Scryfall API error for card "' . $card_name . '": ' . $response->get_error_message());
            return null;
        }

        // Check HTTP status code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Scryfall API returned status ' . $response_code . ' for card "' . $card_name . '"');
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        // Check for API errors
        if (!$data || (isset($data->object) && $data->object === 'error')) {
            error_log('Scryfall returned error for card "' . $card_name . '"');
            return null;
        }

        // Cache the result
        set_transient($cache_key, $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get card data by set code and collector number
     *
     * Reuses the existing get_scryfall_card function for backwards compatibility.
     *
     * @param string $set_code The set code (e.g., "mh2")
     * @param string $collector_number The collector number (e.g., "84")
     * @return object|null Card data object or null on failure
     */
    public static function get_card_by_set($set_code, $collector_number) {
        $cache_key = 'scryfall_' . $set_code . '_' . $collector_number;

        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $api_url = self::API_BASE . '/cards/' . urlencode($set_code) . '/' . urlencode($collector_number);
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'PDC-Theme/' . wp_get_theme()->get('Version') . '; ' . get_bloginfo('url')
            )
        ));

        if (is_wp_error($response)) {
            error_log('Scryfall API error for card ' . $set_code . '/' . $collector_number . ': ' . $response->get_error_message());
            return null;
        }

        // Check HTTP status code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Scryfall API returned status ' . $response_code . ' for card ' . $set_code . '/' . $collector_number);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || (isset($data->object) && $data->object === 'error')) {
            error_log('Scryfall returned error for card ' . $set_code . '/' . $collector_number);
            return null;
        }

        set_transient($cache_key, $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get card image URL
     *
     * Handles both single-faced and double-faced cards.
     *
     * @param object $card_data Scryfall card data object
     * @param string $size Image size: 'small', 'normal', 'large', 'png', 'border_crop'
     * @return string|null Image URL or null if not found
     */
    public static function get_card_image($card_data, $size = 'normal') {
        if (!$card_data) {
            return null;
        }

        // Single-faced card
        if (isset($card_data->image_uris->$size)) {
            return $card_data->image_uris->$size;
        }

        // Double-faced card - use front face
        if (isset($card_data->card_faces[0]->image_uris->$size)) {
            return $card_data->card_faces[0]->image_uris->$size;
        }

        return null;
    }

    /**
     * Get card mana cost
     *
     * @param object $card_data Scryfall card data object
     * @return string|null Mana cost string (e.g., "{2}{U}{U}") or null
     */
    public static function get_mana_cost($card_data) {
        if (!$card_data) {
            return null;
        }

        // Single-faced card
        if (isset($card_data->mana_cost)) {
            return $card_data->mana_cost;
        }

        // Double-faced card - use front face
        if (isset($card_data->card_faces[0]->mana_cost)) {
            return $card_data->card_faces[0]->mana_cost;
        }

        return null;
    }

    /**
     * Get card converted mana cost (CMC)
     *
     * @param object $card_data Scryfall card data object
     * @return int|null CMC value or null
     */
    public static function get_cmc($card_data) {
        if (!$card_data) {
            return null;
        }

        return isset($card_data->cmc) ? (int) $card_data->cmc : null;
    }

    /**
     * Get card colors
     *
     * @param object $card_data Scryfall card data object
     * @return array Array of color codes (e.g., ['W', 'U', 'B', 'R', 'G']) or empty array
     */
    public static function get_colors($card_data) {
        if (!$card_data) {
            return array();
        }

        // Single-faced card
        if (isset($card_data->colors) && is_array($card_data->colors)) {
            return $card_data->colors;
        }

        // Double-faced card - use front face
        if (isset($card_data->card_faces[0]->colors) && is_array($card_data->card_faces[0]->colors)) {
            return $card_data->card_faces[0]->colors;
        }

        return array();
    }

    /**
     * Get card type line
     *
     * @param object $card_data Scryfall card data object
     * @return string|null Type line (e.g., "Creature — Human Wizard") or null
     */
    public static function get_type_line($card_data) {
        if (!$card_data) {
            return null;
        }

        // Single-faced card
        if (isset($card_data->type_line)) {
            return $card_data->type_line;
        }

        // Double-faced card - use front face
        if (isset($card_data->card_faces[0]->type_line)) {
            return $card_data->card_faces[0]->type_line;
        }

        return null;
    }

    /**
     * Determine the primary card type for sorting
     *
     * Returns one of: 'Creature', 'Planeswalker', 'Instant', 'Sorcery',
     * 'Artifact', 'Enchantment', 'Land', 'Other'
     *
     * @param object $card_data Scryfall card data object
     * @return string Primary card type
     */
    public static function get_primary_type($card_data) {
        $type_line = self::get_type_line($card_data);

        if (!$type_line) {
            return 'Other';
        }

        // Check for each type in priority order
        // Land is first because lands are always lands, even if they're "Artifact Land" etc.
        $types = array(
            'Land',
            'Creature',
            'Planeswalker',
            'Instant',
            'Sorcery',
            'Artifact',
            'Enchantment'
        );

        foreach ($types as $type) {
            if (stripos($type_line, $type) !== false) {
                return $type;
            }
        }

        return 'Other';
    }

    /**
     * Get multiple cards by name using Scryfall's /cards/collection bulk endpoint.
     *
     * Checks individual transient caches first; only fetches uncached cards via the
     * API in batches of 75 (the Scryfall limit). Results are stored in the same
     * per-card transients used by get_card_by_name(), so both methods share the cache.
     *
     * @param array $names Array of exact card names
     * @return array Map of strtolower(card_name) => card data object|null
     */
    public static function get_cards_by_names(array $names) {
        $result   = array();
        $to_fetch = array();

        foreach ($names as $name) {
            $cache_key   = 'scryfall_name_' . sanitize_key($name);
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $result[strtolower($name)] = $cached_data;
            } else {
                $to_fetch[] = $name;
            }
        }

        if (empty($to_fetch)) {
            return $result;
        }

        foreach (array_chunk($to_fetch, 75) as $batch) {
            $identifiers = array_map(function($name) {
                return array('name' => $name);
            }, $batch);

            $response = wp_remote_post(self::API_BASE . '/cards/collection', array(
                'timeout' => 15,
                'headers' => array(
                    'User-Agent'   => 'PDC-Theme/' . wp_get_theme()->get('Version') . '; ' . get_bloginfo('url'),
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array('identifiers' => $identifiers)),
            ));

            if (is_wp_error($response)) {
                error_log('Scryfall /cards/collection error: ' . $response->get_error_message());
                foreach ($batch as $name) {
                    $result[strtolower($name)] = null;
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Scryfall /cards/collection returned status ' . $response_code);
                foreach ($batch as $name) {
                    $result[strtolower($name)] = null;
                }
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response));

            if (!$data || !isset($data->data)) {
                error_log('Scryfall /cards/collection: unexpected response format');
                foreach ($batch as $name) {
                    $result[strtolower($name)] = null;
                }
                continue;
            }

            // Index returned cards by canonical name and cache individually
            $found = array();
            foreach ($data->data as $card) {
                set_transient('scryfall_name_' . sanitize_key($card->name), $card, self::CACHE_DURATION);
                $found[strtolower($card->name)] = $card;
            }

            // Map each requested name to its result; try fuzzy search for missing ones
            foreach ($batch as $name) {
                $lower = strtolower($name);
                if (isset($found[$lower])) {
                    $result[$lower] = $found[$lower];
                } else {
                    // Fallback: search by name (supports translated / non-English names)
                    $card = self::search_card_by_name($name);
                    if ($card) {
                        set_transient('scryfall_name_' . sanitize_key($name), $card, self::CACHE_DURATION);
                    }
                    $result[$lower] = $card;
                }
            }
        }

        return $result;
    }

    /**
     * Search for a card by name, supporting non-English / translated names.
     *
     * Uses the Scryfall /cards/search endpoint which can match printed_name
     * across all languages.
     *
     * @param string $name Card name (any language)
     * @return object|null Card data object or null
     */
    public static function search_card_by_name($name) {
        $cache_key   = 'scryfall_name_' . sanitize_key($name);
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Try exact English name first
        $api_url  = self::API_BASE . '/cards/named?exact=' . urlencode($name);
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'PDC-Theme/' . wp_get_theme()->get('Version') . '; ' . get_bloginfo('url'),
            ),
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response));
            if ($data && isset($data->object) && $data->object === 'card') {
                set_transient($cache_key, $data, self::CACHE_DURATION);
                return $data;
            }
        }

        // Fallback: search across all languages
        $search_url = self::API_BASE . '/cards/search?q=' . urlencode('!"' . $name . '" include:extras') . '&unique=cards';
        $response   = wp_remote_get($search_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'PDC-Theme/' . wp_get_theme()->get('Version') . '; ' . get_bloginfo('url'),
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Scryfall search failed for "' . $name . '"');
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        if (!$data || !isset($data->data[0])) {
            return null;
        }

        $card = $data->data[0];
        set_transient($cache_key, $card, self::CACHE_DURATION);
        return $card;
    }

    /**
     * Clear cache for a specific card name
     *
     * @param string $card_name The card name to clear from cache
     * @return bool True on success, false on failure
     */
    public static function clear_card_cache($card_name) {
        $cache_key = 'scryfall_name_' . sanitize_key($card_name);
        return delete_transient($cache_key);
    }

    /**
     * Clear all Scryfall caches
     *
     * Warning: This will clear ALL transients starting with 'scryfall_'
     *
     * @return int Number of caches cleared
     */
    public static function clear_all_caches() {
        global $wpdb;

        $count = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_scryfall_%'
             OR option_name LIKE '_transient_timeout_scryfall_%'"
        );

        return $count;
    }
}
