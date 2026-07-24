<?php

use PHPUnit\Framework\TestCase;

class DecklistParserTest extends TestCase
{
    public function testParsesMtgoLines(): void
    {
        $cards = DecklistParser::parse("1 Lightning Bolt\n4 Mountain");

        $this->assertSame(
            [
                ['quantity' => 1, 'name' => 'Lightning Bolt'],
                ['quantity' => 4, 'name' => 'Mountain'],
            ],
            $cards
        );
    }

    public function testHandlesCarriageReturns(): void
    {
        // The exported decklists in content/decklists/*.json use \r\n.
        $cards = DecklistParser::parse("1 Lightning Bolt\r\n4 Mountain\r\n");

        $this->assertCount(2, $cards);
        $this->assertSame('Lightning Bolt', $cards[0]['name']);
        $this->assertSame('Mountain', $cards[1]['name']);
    }

    public function testSkipsBlankLinesCommentsAndSectionMarkers(): void
    {
        $text = "Deck\n\n1 Lightning Bolt\n// a comment\n# another\nSideboard:\n2 Mountain";
        $cards = DecklistParser::parse($text);

        $this->assertSame(
            [
                ['quantity' => 1, 'name' => 'Lightning Bolt'],
                ['quantity' => 2, 'name' => 'Mountain'],
            ],
            $cards
        );
    }

    public function testLineWithoutQuantityCountsAsOne(): void
    {
        $cards = DecklistParser::parse('Lightning Bolt');

        $this->assertSame([['quantity' => 1, 'name' => 'Lightning Bolt']], $cards);
    }

    public function testNormalizesSplitCardSeparatorAndWhitespace(): void
    {
        $cards = DecklistParser::parse("1 Fire//Ice\n1 Lightning    Bolt");

        $this->assertSame('Fire // Ice', $cards[0]['name']);
        $this->assertSame('Lightning Bolt', $cards[1]['name']);
    }

    public function testEmptyInputReturnsNoCards(): void
    {
        $this->assertSame([], DecklistParser::parse(''));
        $this->assertSame([], DecklistParser::parse("\n\n// nothing here\n"));
    }

    public function testCountHelpers(): void
    {
        $text = "1 Lightning Bolt\n4 Mountain";

        $this->assertSame(5, DecklistParser::count_cards($text));
        $this->assertSame(2, DecklistParser::count_unique($text));
    }
}
