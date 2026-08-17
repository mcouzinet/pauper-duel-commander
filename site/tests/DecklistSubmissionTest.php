<?php

use PHPUnit\Framework\TestCase;

class DecklistSubmissionTest extends TestCase
{
    private function input(array $over = array()): array
    {
        return array_merge(array(
            'commander' => 'Gut, True Soul Zealot',
            'partner'   => 'Inspiring Leader',
            'decklist'  => "1 Lightning Bolt\r\n1 Mountain",
            'author'    => 'Axel',
            'archetype' => 'Aggro',
        ), $over);
    }

    public function testBuildsCanonicalJson(): void
    {
        $json = DecklistSubmission::from_input($this->input())->to_json();
        $d = json_decode($json, true);

        $this->assertSame('Gut, True Soul Zealot // Inspiring Leader', $d['title']);
        $this->assertSame('Gut, True Soul Zealot', $d['commander']);
        $this->assertSame('Inspiring Leader', $d['partner']);
        $this->assertSame('Axel', $d['author']);
        $this->assertSame('Aggro', $d['archetype']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $d['date']);
        // CRLF normalised to LF in the stored cards.
        $this->assertStringContainsString("1 Lightning Bolt\n1 Mountain", $d['cards']);
        $this->assertStringNotContainsString("\r", $d['cards']);
    }

    public function testOmitsEmptyOptionalFields(): void
    {
        $d = json_decode(DecklistSubmission::from_input(array(
            'commander' => 'Mother of Runes',
            'decklist'  => '99 Plains',
        ))->to_json(), true);

        $this->assertArrayNotHasKey('partner', $d);
        $this->assertArrayNotHasKey('author', $d);
        $this->assertArrayNotHasKey('archetype', $d);
        $this->assertSame('Mother of Runes', $d['commander']);
    }

    public function testSlugAndPathAreSafe(): void
    {
        $sub = DecklistSubmission::from_input($this->input(array(
            'commander' => '../../etc/passwd <script>',
        )));

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sub->slug());
        $path = $sub->repo_path();
        $this->assertStringStartsWith('site/content/decklists/', $path);
        $this->assertStringEndsWith('.json', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function testRejectsMissingCommander(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecklistSubmission::from_input(array('decklist' => '99 Plains'));
    }

    public function testRejectsEmptyDecklist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecklistSubmission::from_input(array('commander' => 'Mother of Runes', 'decklist' => '   '));
    }
}
