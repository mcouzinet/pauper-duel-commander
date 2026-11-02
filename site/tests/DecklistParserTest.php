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

    /**
     * Regression: one entry per line let a card written on two lines pass the
     * singleton rule, which reads one entry's quantity.
     */
    public function testSameCardOnTwoLinesIsOneEntry(): void
    {
        $cards = DecklistParser::parse("1 Sacred Peaks\n4 Mountain\n1 sacred  peaks");

        $this->assertSame(
            [
                ['quantity' => 2, 'name' => 'Sacred Peaks'],
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

    /**
     * One line per deckbuilder export format: only the name and quantity survive.
     */
    public function testAcceptsDeckbuilderExportFormats(): void
    {
        $text = implode("\n", [
            '1 Lightning Bolt (2X2) 117',          // Moxfield, Arena
            '1 Counterspell (PLST) MH1-130 *F*',   // Moxfield foil, The List
            '1x Ponder (M12) 73 [Cantrip] ^Have,#37d67a^', // Archidekt
            '1x Preordain *F*',                    // TappedOut
            '1 [M20] Opt',                         // Deckstats
            '1 Brainstorm #!Draw',                 // Moxfield tag
            '1 Delver of Secrets / Insectile Aberration', // single slash
            '- 1 Island',                          // bullet
            '2 x Mountain',
        ]);

        $this->assertSame(
            ['Lightning Bolt', 'Counterspell', 'Ponder', 'Preordain', 'Opt', 'Brainstorm',
             'Delver of Secrets // Insectile Aberration', 'Island', 'Mountain'],
            array_column(DecklistParser::parse($text), 'name')
        );
        $this->assertSame(10, DecklistParser::count_cards($text));
    }

    public function testSectionHeadersAreSkippedAndMaybeboardIgnored(): void
    {
        $text = "Commander (1)\n1 Garland, Knight of Cornelia\nCreatures (2)\n1 Wall of Omens\n"
            . "1 Mulldrifter\nLands\n1 Island\n\nMaybeboard (1)\n1 Ninja of the Deep Hours\n"
            . "// Tokens\n1 Drake\nSIDEBOARD:\n1 Ponder";

        $this->assertSame(
            ['Garland, Knight of Cornelia', 'Wall of Omens', 'Mulldrifter', 'Island', 'Ponder'],
            array_column(DecklistParser::parse($text), 'name')
        );
    }
}
