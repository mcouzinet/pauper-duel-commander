<?php

use PHPUnit\Framework\TestCase;

/**
 * Rules under test (see DeckValidator docblock):
 *   1 commander required + found   5 all cards found on Scryfall
 *   2 commander is a Creature      6 no duplicates except basic lands
 *   3 commander is uncommon        7 Pauper legality
 *   4 deck size 99 / 98            8 color identity
 *                                  9 ban list
 *
 * Fixture cards (real Scryfall data, see tests/fixtures/scryfall/):
 *   Mother of Runes    uncommon Creature, W       -> legal commander
 *   Gorilla Shaman     uncommon Creature, R       -> legal partner
 *   Bastion Protector  rare Creature, W           -> banned as commander
 *   Balance            mythic Sorcery, W          -> pauper not_legal
 *   Plains             Basic Land, W, legal       -> filler for a mono-W deck
 *   Prophetic Prism    common Artifact, colorless -> non-basic filler
 *   Lightning Bolt     R, pauper legal            -> off-colour for a W commander
 *   Goliath Paladin    common Creature, W, legal  -> banned in deck
 *
 * Each fixture isolates one rule: Lightning Bolt is Pauper-legal so it can only
 * trip rule 8, Goliath Paladin is legal and on-colour so it can only trip rule 9.
 *
 * Rule 5 is not covered here: an unresolvable name is by definition absent from the
 * fixture cache, so asserting on it would make the suite hit the live Scryfall API.
 * Covering it needs an injectable HTTP transport in ScryfallService.
 */
class DeckValidatorTest extends TestCase
{
    const COMMANDER = 'Mother of Runes';
    const PARTNER   = 'Gorilla Shaman';

    protected function tearDown(): void
    {
        DeckValidator::$banlist_path = null;
    }

    /**
     * Build an MTGO-format decklist from ['Card name' => quantity].
     */
    private function deck(array $cards): string
    {
        $lines = [];
        foreach ($cards as $name => $qty) {
            $lines[] = $qty . ' ' . $name;
        }
        return implode("\n", $lines);
    }

    /**
     * A 99-card mono-white deck that breaks no rule.
     */
    private function validDeck(): string
    {
        return $this->deck(['Plains' => 99]);
    }

    private function ruleNames(array $result): array
    {
        return array_column($result['errors'], 'rule');
    }

    // -- Happy paths ---------------------------------------------------------

    public function testValidDeckPasses(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, '', $this->validDeck());

        $this->assertSame([], $result['errors'], 'expected no errors');
        $this->assertTrue($result['is_valid']);
        $this->assertSame(99, $result['stats']['total_cards']);
        $this->assertSame(1, $result['stats']['unique_cards']);
    }

    public function testValidDeckWithPartnerPasses(): void
    {
        // With a partner the deck is 98 cards, and the colour identity widens to WR.
        $result = DeckValidator::validate(self::COMMANDER, self::PARTNER, $this->deck(['Plains' => 98]));

        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['is_valid']);
        $this->assertSame(98, $result['stats']['total_cards']);
    }

    public function testCardLegalUnderPartnerColorIdentity(): void
    {
        // Lightning Bolt is illegal under a mono-W commander but legal once a red
        // partner widens the identity — this is what makes rule 8 partner-aware.
        $deck = $this->deck(['Plains' => 97, 'Lightning Bolt' => 1]);

        $solo = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98, 'Lightning Bolt' => 1]));
        $this->assertContains('color_identity', $this->ruleNames($solo));

        $withPartner = DeckValidator::validate(self::COMMANDER, self::PARTNER, $deck);
        $this->assertNotContains('color_identity', $this->ruleNames($withPartner));
    }

    // -- Rule 1: commander required / found ----------------------------------

    public function testMissingCommanderIsRejected(): void
    {
        $result = DeckValidator::validate('', '', $this->validDeck());

        $this->assertFalse($result['is_valid']);
        $this->assertSame(['commander'], $this->ruleNames($result));
        $this->assertNull($result['stats']);
    }

    public function testEmptyDecklistIsRejected(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, '', '');

        $this->assertFalse($result['is_valid']);
        $this->assertSame(['format'], $this->ruleNames($result));
    }

    // -- Rule 2: commander type ----------------------------------------------

    public function testNonCreatureCommanderIsRejected(): void
    {
        // Balance is a Sorcery — not a creature, vehicle or spacecraft.
        $result = DeckValidator::validate('Balance', '', $this->validDeck());

        $this->assertFalse($result['is_valid']);
        $this->assertContains('commander_type', $this->ruleNames($result));
    }

    /**
     * The command zone is not restricted to creatures: the format framing
     * document admits Vehicles and Spacecraft on the same terms.
     */
    public function testVehicleCommanderIsAcceptedOnType(): void
    {
        $result = DeckValidator::validate("Smuggler's Copter", '', $this->deck(['Island' => 99]));

        $this->assertNotContains('commander_type', $this->ruleNames($result));
        // ...but it has never been uncommon, so rule 3 still rejects it.
        $this->assertContains('commander_rarity', $this->ruleNames($result));
    }

    public function testSpacecraftCommanderIsAccepted(): void
    {
        // Fell Gravship is an uncommon Spacecraft: eligible on both type and rarity.
        $result = DeckValidator::validate('Fell Gravship', '', $this->deck(['Swamp' => 99]));

        $this->assertNotContains('commander_type', $this->ruleNames($result));
        $this->assertNotContains('commander_rarity', $this->ruleNames($result));
    }

    // -- Rule 3: commander must have been uncommon at least once -------------

    public function testCommanderNeverPrintedAtUncommonIsRejected(): void
    {
        // Grave Titan is a creature that only exists at mythic.
        $result = DeckValidator::validate('Grave Titan', '', $this->deck(['Swamp' => 99]));

        $this->assertFalse($result['is_valid']);
        $this->assertContains('commander_rarity', $this->ruleNames($result));
    }

    /**
     * Regression: the rule is "has ever been uncommon", not "this printing is
     * uncommon". Baleful Strix defaults to rare on Scryfall but has an uncommon
     * printing, and is a legal commander played in the tournaments listed on the
     * site — the validator used to reject it.
     */
    public function testCommanderWithAnUncommonReprintIsAccepted(): void
    {
        $result = DeckValidator::validate('Baleful Strix', '', $this->deck(['Island' => 99]));

        $this->assertNotContains('commander_rarity', $this->ruleNames($result));
    }

    /**
     * The flip side of the rule above: Arena rebalances rarities for its own
     * play patterns, so an Arena-only uncommon does not make a card eligible.
     * Abhorrent Overlord is rare in every paper and MTGO printing.
     */
    public function testArenaOnlyUncommonPrintingDoesNotQualify(): void
    {
        $result = DeckValidator::validate('Abhorrent Overlord', '', $this->deck(['Swamp' => 99]));

        $this->assertContains('commander_rarity', $this->ruleNames($result));
    }

    // -- Rule 4: deck size ---------------------------------------------------

    public function testDeckSizeMustBe99WithoutPartner(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98]));

        $this->assertFalse($result['is_valid']);
        $this->assertContains('deck_size', $this->ruleNames($result));
    }

    public function testDeckSizeMustBe98WithPartner(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, self::PARTNER, $this->deck(['Plains' => 99]));

        $this->assertFalse($result['is_valid']);
        $this->assertContains('deck_size', $this->ruleNames($result));
    }

    // -- Rule 6: no duplicates except basic lands ----------------------------

    public function testDuplicateNonBasicIsRejected(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 97, 'Prophetic Prism' => 2]));

        $this->assertFalse($result['is_valid']);
        $this->assertContains('duplicates', $this->ruleNames($result));
    }

    public function testDuplicateBasicLandIsAllowed(): void
    {
        // 99 Plains is the valid deck above — asserted here as the explicit
        // counterpart to testDuplicateNonBasicIsRejected.
        $result = DeckValidator::validate(self::COMMANDER, '', $this->validDeck());

        $this->assertNotContains('duplicates', $this->ruleNames($result));
    }

    // -- Rule 7: Pauper legality ---------------------------------------------

    public function testCardNeverPrintedAtCommonIsRejected(): void
    {
        // Balance: legalities.pauper === 'not_legal'.
        $result = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98, 'Balance' => 1]));

        $this->assertFalse($result['is_valid']);
        $this->assertContains('rarity', $this->ruleNames($result));
    }

    // -- Rule 8: color identity ----------------------------------------------

    public function testOffColorCardIsRejected(): void
    {
        $result = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98, 'Lightning Bolt' => 1]));

        $this->assertFalse($result['is_valid']);

        $errors = array_column($result['errors'], null, 'rule');
        $this->assertArrayHasKey('color_identity', $errors);
        $this->assertSame(['Lightning Bolt'], $errors['color_identity']['cards']);
    }

    // -- Rule 9: ban list ----------------------------------------------------

    public function testBannedCardInDeckIsRejected(): void
    {
        // Goliath Paladin is common, white and Pauper-legal, so the ban list is the
        // only thing that can reject it.
        $result = DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98, 'Goliath Paladin' => 1]));

        $this->assertFalse($result['is_valid']);

        $errors = array_column($result['errors'], null, 'rule');
        $this->assertArrayHasKey('ban_list', $errors);
        $this->assertSame(['Goliath Paladin'], $errors['ban_list']['cards']);
    }

    public function testBannedCommanderIsRejected(): void
    {
        $result = DeckValidator::validate('Bastion Protector', '', $this->validDeck());

        $this->assertFalse($result['is_valid']);

        $errors = array_column($result['errors'], null, 'rule');
        $this->assertArrayHasKey('ban_list', $errors);
        $this->assertSame(['Bastion Protector'], $errors['ban_list']['cards']);
    }

    /**
     * Regression: the ban list used to be skipped with a warning when it could not
     * be loaded, so a deck full of banned cards came back is_valid = true. The
     * deployed path was wrong, so this was the live behaviour in production.
     */
    public function testValidationFailsLoudlyWhenBanListIsUnavailable(): void
    {
        DeckValidator::$banlist_path = '/nonexistent/banlist.json';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ban list not found');

        DeckValidator::validate(self::COMMANDER, '', $this->deck(['Plains' => 98, 'Goliath Paladin' => 1]));
    }
}
