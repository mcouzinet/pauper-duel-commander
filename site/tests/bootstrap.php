<?php
/**
 * PHPUnit bootstrap for the PDC validator API.
 *
 * The tests are hermetic: they never call Scryfall. ScryfallService reads through
 * a file cache keyed `name_<sanitized-card-name>.json`, so we point PDC_CACHE_DIR
 * at a throwaway directory pre-seeded with real Scryfall responses from
 * tests/fixtures/scryfall/. Every card the tests reference is present there, so
 * every lookup is a cache hit and no HTTP request is ever made.
 *
 * The fixtures are copied (not used in place) because the cache expires on mtime.
 */

define('PDC_TEST_ROOT', __DIR__);
define('PDC_SITE_ROOT', dirname(__DIR__));

// Throwaway cache, seeded below. Must be defined before config.php.
define('PDC_CACHE_DIR', sys_get_temp_dir() . '/pdc-test-cache-' . getmypid());
define('PDC_BANLIST_PATH', PDC_SITE_ROOT . '/content/banlist.json');

if (!is_dir(PDC_CACHE_DIR)) {
    mkdir(PDC_CACHE_DIR, 0777, true);
}

foreach (glob(PDC_TEST_ROOT . '/fixtures/scryfall/*.json') as $fixture) {
    copy($fixture, PDC_CACHE_DIR . '/' . basename($fixture));
    touch(PDC_CACHE_DIR . '/' . basename($fixture)); // fresh mtime so the TTL never expires it
}

register_shutdown_function(function () {
    foreach (glob(PDC_CACHE_DIR . '/*.json') as $file) {
        unlink($file);
    }
    @rmdir(PDC_CACHE_DIR);
});

require_once PDC_SITE_ROOT . '/public/api/lib/config.php';
