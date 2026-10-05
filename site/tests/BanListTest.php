<?php

use PHPUnit\Framework\TestCase;

/**
 * Loading the ban list is the one thing the validator is not allowed to fail
 * silently at, so it gets its own tests.
 */
class BanListTest extends TestCase
{
    private $tmp;

    protected function tearDown(): void
    {
        DeckValidator::$banlist_path = null;
        if ($this->tmp && file_exists($this->tmp)) {
            unlink($this->tmp);
        }
    }

    private function writeTmp(string $contents): string
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'pdc-banlist-');
        file_put_contents($this->tmp, $contents);
        return $this->tmp;
    }

    /**
     * The real file, whatever the committee put in it: no card is named, so an
     * announcement never breaks this test (the suite runs on a fixed list).
     */
    public function testLoadsTheRealBanList(): void
    {
        $path = PDC_SITE_ROOT . '/content/banlist.json';
        $names = DeckValidator::get_banned_card_names($path);

        $this->assertNotEmpty($names);
        $this->assertSame(
            array_map('strtolower', json_decode(file_get_contents($path), true)['cards']),
            $names,
            'names are lowercased for comparison'
        );
    }

    /**
     * The path must resolve inside api/, which is what actually gets deployed.
     * `content/` is not rsynced to the VPS, so a path pointing there resolves to
     * nothing in production — the bug this suite was written for.
     */
    public function testResolvedPathIsInsideTheDeployedApiDirectory(): void
    {
        $path = pdc_resolve_banlist_path();

        $this->assertFileExists($path, 'run `npm run build` to generate public/api/data/banlist.json');
        $this->assertSame(
            realpath(PDC_SITE_ROOT . '/public/api/data/banlist.json'),
            realpath($path)
        );
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ban list not found');

        DeckValidator::get_banned_card_names('/nonexistent/banlist.json');
    }

    public function testThrowsWhenJsonIsMalformed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        DeckValidator::get_banned_card_names($this->writeTmp('{ not json'));
    }

    public function testThrowsWhenCardsArrayIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        DeckValidator::get_banned_card_names($this->writeTmp('{"lastUpdated":"2026-05-20"}'));
    }
}
