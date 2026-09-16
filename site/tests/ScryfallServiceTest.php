<?php

use PHPUnit\Framework\TestCase;

/**
 * Name resolution when a real card shares its name with an unplayable one.
 *
 * "No Way Out" is a Midnight Hunt common and a Duskmourn playtest Plane.
 * Scryfall's /cards/named and /cards/collection both answer with the Plane, so a
 * legal deck was rejected as "never printed at common". These lookups miss the
 * fixture cache by design, so Scryfall is stubbed through the transport seam,
 * with real responses from tests/fixtures/scryfall-http/.
 */
class ScryfallServiceTest extends TestCase
{
    const CACHED = PDC_CACHE_DIR . '/name_no-way-out.json';

    protected function setUp(): void
    {
        $plane   = self::fixture('no-way-out-plane');
        $sorcery = self::fixture('no-way-out-sorcery');

        // Scryfall as observed: every lookup favours the Plane, and the search
        // lists it first — taking the first result would still get it wrong.
        ScryfallService::$transport = function ($method, $url) use ($plane, $sorcery) {
            if (strpos($url, '/cards/collection') !== false) {
                return (object) array('data' => array($plane), 'not_found' => array());
            }
            if (strpos($url, '/cards/named') !== false) {
                return $plane;
            }
            if (strpos($url, '/cards/search') !== false) {
                return (object) array('data' => array($plane, $sorcery));
            }
            throw new LogicException("unexpected Scryfall call: $method $url");
        };
    }

    protected function tearDown(): void
    {
        ScryfallService::$transport = null;
        @unlink(self::CACHED);
    }

    public function test_deck_card_resolves_to_the_real_card_not_the_plane(): void
    {
        $card = ScryfallService::get_cards_by_names(array('No Way Out'))['no way out'];

        $this->assertSame('Sorcery', $card->type_line);
    }

    /** Production cached the Plane before the fix: that entry must heal itself. */
    public function test_a_plane_already_in_cache_is_replaced(): void
    {
        file_put_contents(self::CACHED, json_encode(self::fixture('no-way-out-plane')));

        $card = ScryfallService::get_cards_by_names(array('No Way Out'))['no way out'];

        $this->assertSame('Sorcery', $card->type_line);
        $this->assertSame('Sorcery', json_decode(file_get_contents(self::CACHED))->type_line);
    }

    public function test_commander_lookup_skips_the_plane_too(): void
    {
        $this->assertSame('Sorcery', ScryfallService::get_card_by_name('No Way Out')->type_line);
    }

    private static function fixture(string $name)
    {
        return json_decode(file_get_contents(PDC_TEST_ROOT . '/fixtures/scryfall-http/' . $name . '.json'));
    }
}
