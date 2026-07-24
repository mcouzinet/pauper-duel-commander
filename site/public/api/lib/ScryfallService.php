<?php
/**
 * Scryfall API Service (standalone, no WordPress)
 *
 * Handles all interactions with the Scryfall API for Magic: The Gathering card data.
 * Uses file-based JSON cache instead of WordPress transients.
 * All HTTP requests use cURL instead of wp_remote_get/post.
 *
 * @package PDC_API
 * @since 2.0.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class ScryfallService {

    /**
     * Timestamp of last API request (for rate limiting).
     *
     * @var float
     */
    private static $last_request_time = 0.0;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Get card data by exact name.
     *
     * @param string $card_name The exact English card name
     * @return object|null Card data object or null on failure
     */
    public static function get_card_by_name($card_name) {
        $cache_key = 'name_' . pdc_sanitize_key($card_name);

        $cached = self::cache_get($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        $url  = SCRYFALL_API_BASE . '/cards/named?exact=' . urlencode($card_name);
        $data = self::http_get($url);

        if (!$data || (isset($data->object) && $data->object === 'error')) {
            error_log('Scryfall: card not found "' . $card_name . '"');
            return null;
        }

        self::cache_set($cache_key, $data);
        return $data;
    }

    /**
     * Get card data by set code and collector number.
     *
     * @param string $set_code         Set code (e.g. "mh2")
     * @param string $collector_number Collector number (e.g. "84")
     * @return object|null
     */
    public static function get_card_by_set($set_code, $collector_number) {
        $cache_key = 'set_' . pdc_sanitize_key($set_code . '_' . $collector_number);

        $cached = self::cache_get($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        $url  = SCRYFALL_API_BASE . '/cards/' . urlencode($set_code) . '/' . urlencode($collector_number);
        $data = self::http_get($url);

        if (!$data || (isset($data->object) && $data->object === 'error')) {
            error_log('Scryfall: card not found ' . $set_code . '/' . $collector_number);
            return null;
        }

        self::cache_set($cache_key, $data);
        return $data;
    }

    /**
     * Get multiple cards by name using Scryfall's /cards/collection bulk endpoint.
     *
     * Checks file cache first; only fetches uncached cards via the API in batches
     * of 75 (the Scryfall limit). Results are stored in per-card cache files.
     *
     * @param array $names Array of exact card names
     * @return array Map of strtolower(card_name) => card data object|null
     */
    public static function get_cards_by_names(array $names) {
        $result   = array();
        $to_fetch = array();

        // Budget for per-name fallback lookups, shared across every batch below.
        $fallbacks_used = 0;

        foreach ($names as $name) {
            $cache_key = 'name_' . pdc_sanitize_key($name);
            $cached    = self::cache_get($cache_key);
            if ($cached !== null) {
                $result[strtolower($name)] = $cached;
            } else {
                $to_fetch[] = $name;
            }
        }

        if (empty($to_fetch)) {
            return $result;
        }

        foreach (array_chunk($to_fetch, 75) as $batch) {
            $identifiers = array_map(function ($name) {
                return array('name' => $name);
            }, $batch);

            $data = self::http_post(
                SCRYFALL_API_BASE . '/cards/collection',
                json_encode(array('identifiers' => $identifiers))
            );

            if (!$data || !isset($data->data)) {
                error_log('Scryfall /cards/collection: unexpected response');
                foreach ($batch as $name) {
                    $result[strtolower($name)] = null;
                }
                continue;
            }

            // Index returned cards by lowercase name and cache individually
            $found = array();
            foreach ($data->data as $card) {
                $card_cache_key = 'name_' . pdc_sanitize_key($card->name);
                self::cache_set($card_cache_key, $card);
                $found[strtolower($card->name)] = $card;
            }

            // Map each requested name; fallback search for missing ones.
            //
            // The fallback is one HTTP round trip per name, each gated by a 100 ms
            // sleep, so it is capped: a list of junk names would otherwise hold a
            // PHP worker open for minutes and hammer Scryfall. Past the cap, names
            // resolve to null and surface as "card not found", which is the right
            // answer for a decklist that is mostly unrecognisable anyway.
            foreach ($batch as $name) {
                $lower = strtolower($name);
                if (isset($found[$lower])) {
                    $result[$lower] = $found[$lower];
                    continue;
                }

                if ($fallbacks_used >= PDC_MAX_FALLBACK_LOOKUPS) {
                    $result[$lower] = null;
                    continue;
                }

                $fallbacks_used++;
                $card = self::search_card_by_name($name);
                if ($card) {
                    $card_cache_key = 'name_' . pdc_sanitize_key($name);
                    self::cache_set($card_cache_key, $card);
                }
                $result[$lower] = $card;
            }
        }

        return $result;
    }

    /**
     * Search for a card by name, supporting non-English / translated names.
     *
     * @param string $name Card name (any language)
     * @return object|null Card data object or null
     */
    public static function search_card_by_name($name) {
        $cache_key = 'name_' . pdc_sanitize_key($name);
        $cached    = self::cache_get($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        // Try exact English name first
        $data = self::http_get(SCRYFALL_API_BASE . '/cards/named?exact=' . urlencode($name));
        if ($data && isset($data->object) && $data->object === 'card') {
            self::cache_set($cache_key, $data);
            return $data;
        }

        // Fallback: search across all languages
        $search_url = SCRYFALL_API_BASE . '/cards/search?q=' . urlencode('!"' . $name . '" include:extras') . '&unique=cards';
        $data       = self::http_get($search_url);

        if (!$data || !isset($data->data[0])) {
            error_log('Scryfall search failed for "' . $name . '"');
            return null;
        }

        $card = $data->data[0];
        self::cache_set($cache_key, $card);
        return $card;
    }

    // -------------------------------------------------------------------------
    // Card data extractors (pure, no API calls)
    // -------------------------------------------------------------------------

    /**
     * Get card image URL (handles single-faced and double-faced cards).
     *
     * @param object $card_data Scryfall card data
     * @param string $size      'small', 'normal', 'large', 'png', 'border_crop', 'art_crop'
     * @return string|null
     */
    public static function get_card_image($card_data, $size = 'normal') {
        if (!$card_data) {
            return null;
        }
        if (isset($card_data->image_uris->$size)) {
            return $card_data->image_uris->$size;
        }
        if (isset($card_data->card_faces[0]->image_uris->$size)) {
            return $card_data->card_faces[0]->image_uris->$size;
        }
        return null;
    }

    /**
     * @param object $card_data
     * @return string|null Mana cost (e.g. "{2}{U}{U}")
     */
    public static function get_mana_cost($card_data) {
        if (!$card_data) {
            return null;
        }
        if (isset($card_data->mana_cost)) {
            return $card_data->mana_cost;
        }
        if (isset($card_data->card_faces[0]->mana_cost)) {
            return $card_data->card_faces[0]->mana_cost;
        }
        return null;
    }

    /**
     * @param object $card_data
     * @return int|null
     */
    public static function get_cmc($card_data) {
        if (!$card_data) {
            return null;
        }
        return isset($card_data->cmc) ? (int) $card_data->cmc : null;
    }

    /**
     * @param object $card_data
     * @return array Color codes e.g. ['W', 'U']
     */
    public static function get_colors($card_data) {
        if (!$card_data) {
            return array();
        }
        if (isset($card_data->colors) && is_array($card_data->colors)) {
            return $card_data->colors;
        }
        if (isset($card_data->card_faces[0]->colors) && is_array($card_data->card_faces[0]->colors)) {
            return $card_data->card_faces[0]->colors;
        }
        return array();
    }

    /**
     * Get every rarity this card has ever been printed at.
     *
     * A single Scryfall card object describes one printing, so `$card->rarity`
     * answers "what is this printing's rarity", not "has this card ever been
     * uncommon" — which is what the PDC commander rule actually asks. Baleful
     * Strix, for instance, defaults to rare but has an uncommon printing, and is
     * a legal commander played in tournaments.
     *
     * Follows `prints_search_uri`, which lists all printings, and counts only
     * those released in paper or on MTGO — see below. Cached like everything
     * else, keyed separately from the card itself.
     *
     * @param object $card_data Scryfall card object
     * @return array Lowercase rarity strings, e.g. ['rare', 'uncommon']
     */
    public static function get_all_rarities($card_data) {
        if (!$card_data || !isset($card_data->name)) {
            return array();
        }

        // A card with no known other printings still has its own rarity.
        $own = isset($card_data->rarity) ? array($card_data->rarity) : array();

        if (!isset($card_data->prints_search_uri)) {
            return $own;
        }

        $cache_key = 'prints_' . pdc_sanitize_key($card_data->name);
        $cached    = self::cache_get($cache_key);
        if ($cached !== null) {
            return array_values(array_unique(array_map('strval', (array) $cached)));
        }

        $data = self::http_get($card_data->prints_search_uri);
        if (!$data || !isset($data->data) || !is_array($data->data)) {
            error_log('Scryfall prints lookup failed for "' . $card_data->name . '"');
            return $own;   // fall back to the single printing rather than nothing
        }

        // Only printings that exist in paper or on MTGO count. Arena rebalances
        // rarities for its own play patterns: Abhorrent Overlord is rare in every
        // paper set but uncommon in an Arena-only release, and counting that would
        // make it a legal commander when it is not. 561 creatures are uncommon on
        // Arena alone.
        $rarities = $own;
        foreach ($data->data as $print) {
            if (!isset($print->rarity)) {
                continue;
            }
            $games = isset($print->games) ? (array) $print->games : array();
            if (in_array('paper', $games, true) || in_array('mtgo', $games, true)) {
                $rarities[] = $print->rarity;
            }
        }
        $rarities = array_values(array_unique($rarities));

        self::cache_set($cache_key, $rarities);
        return $rarities;
    }

    /**
     * @param object $card_data
     * @return string|null e.g. "Creature -- Human Wizard"
     */
    public static function get_type_line($card_data) {
        if (!$card_data) {
            return null;
        }
        if (isset($card_data->type_line)) {
            return $card_data->type_line;
        }
        if (isset($card_data->card_faces[0]->type_line)) {
            return $card_data->card_faces[0]->type_line;
        }
        return null;
    }

    /**
     * Determine the primary card type for sorting.
     *
     * @param object $card_data
     * @return string One of: Creature, Planeswalker, Instant, Sorcery, Artifact, Enchantment, Land, Other
     */
    public static function get_primary_type($card_data) {
        $type_line = self::get_type_line($card_data);
        if (!$type_line) {
            return 'Other';
        }

        $types = array('Land', 'Creature', 'Planeswalker', 'Instant', 'Sorcery', 'Artifact', 'Enchantment');
        foreach ($types as $type) {
            if (stripos($type_line, $type) !== false) {
                return $type;
            }
        }
        return 'Other';
    }

    // -------------------------------------------------------------------------
    // File-based cache
    // -------------------------------------------------------------------------

    /**
     * Read a cached value.
     *
     * @param string $key Sanitized cache key
     * @return object|null Decoded JSON object, or null if miss / expired
     */
    private static function cache_get($key) {
        $path = self::cache_path($key);
        if (!file_exists($path)) {
            return null;
        }
        // Check TTL based on file modification time
        if ((time() - filemtime($path)) > PDC_CACHE_TTL) {
            @unlink($path);
            return null;
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json);
        return ($data !== null) ? $data : null;
    }

    /**
     * Write a value to cache.
     *
     * @param string $key  Sanitized cache key
     * @param object $data Data to cache (will be JSON-encoded)
     */
    private static function cache_set($key, $data) {
        $dir = PDC_CACHE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = self::cache_path($key);
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }

    /**
     * Build the absolute file path for a cache key.
     *
     * @param string $key
     * @return string
     */
    private static function cache_path($key) {
        return PDC_CACHE_DIR . '/' . $key . '.json';
    }

    // -------------------------------------------------------------------------
    // HTTP helpers (cURL)
    // -------------------------------------------------------------------------

    /**
     * Perform a GET request via cURL.
     *
     * @param string $url
     * @return object|null Decoded JSON or null on failure
     */
    private static function http_get($url) {
        self::rate_limit();

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => array(
                'User-Agent: ' . SCRYFALL_USER_AGENT,
                'Accept: application/json',
            ),
        ));

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            if ($err) {
                error_log('Scryfall cURL error: ' . $err);
            }
            return null;
        }

        return json_decode($body);
    }

    /**
     * Perform a POST request via cURL (JSON body).
     *
     * @param string $url
     * @param string $json_body JSON-encoded request body
     * @return object|null Decoded JSON or null on failure
     */
    private static function http_post($url, $json_body) {
        self::rate_limit();

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json_body,
            CURLOPT_HTTPHEADER     => array(
                'User-Agent: ' . SCRYFALL_USER_AGENT,
                'Content-Type: application/json',
                'Accept: application/json',
            ),
        ));

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            if ($err) {
                error_log('Scryfall cURL error: ' . $err);
            }
            return null;
        }

        return json_decode($body);
    }

    /**
     * Enforce Scryfall's rate limit (100 ms between requests).
     */
    private static function rate_limit() {
        $now  = microtime(true);
        $diff = $now - self::$last_request_time;
        $wait = (SCRYFALL_RATE_LIMIT_MS / 1000) - $diff;

        if ($wait > 0) {
            usleep((int) ($wait * 1000000));
        }

        self::$last_request_time = microtime(true);
    }
}
