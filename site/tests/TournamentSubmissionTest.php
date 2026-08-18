<?php

use PHPUnit\Framework\TestCase;

/**
 * TournamentSubmission is pure: raw form input in, canonical collection JSON out.
 * No network, no clock beyond the submitted date.
 */
class TournamentSubmissionTest extends TestCase
{
    private function input(array $over = array()): array
    {
        return array_merge(array(
            'title'        => 'Artefact #7',
            'date'         => '2026-05-25',
            'location'     => 'Artefact',
            'city'         => 'Bordeaux',
            'participants' => 18,
            'top8'         => array(),
            'meta'         => '',
        ), $over);
    }

    // -- shape ---------------------------------------------------------------

    public function testBuildsJsonInTheShapeOfTheCollection(): void
    {
        $s = TournamentSubmission::from_input($this->input());
        $data = json_decode($s->to_json(), true);

        $this->assertSame(
            array('title', 'date', 'location', 'city', 'playerCount', 'actualPlayerCount', 'top8', 'metaList'),
            array_keys($data)
        );
        $this->assertSame('Artefact #7', $data['title']);
        $this->assertSame('2026-05-25', $data['date']);
        // Results, not an announcement: turnout is known, capacity is not.
        $this->assertSame(0, $data['playerCount']);
        $this->assertSame(18, $data['actualPlayerCount']);
    }

    public function testSlugIsSanitizedAndPathCannotEscape(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('title' => '../../evil #1')));

        $this->assertStringNotContainsString('/', $s->slug());
        $this->assertStringNotContainsString('.', $s->slug());
        $this->assertStringStartsWith('site/content/tournaments/', $s->repo_path());
        $this->assertSame('site/content/tournaments/' . $s->slug() . '.json', $s->repo_path());
    }

    public function testSlugHasNoRandomSuffixSoAResubmissionLandsOnTheSameFile(): void
    {
        $a = TournamentSubmission::from_input($this->input());
        $b = TournamentSubmission::from_input($this->input());

        $this->assertSame('artefact-7', $a->slug());
        $this->assertSame($a->repo_path(), $b->repo_path());
    }

    // -- required fields -----------------------------------------------------

    public function testRejectsMissingTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('title' => '  ')));
    }

    public function testRejectsMalformedDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('date' => '11/10/2026')));
    }

    public function testRejectsADateThatDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('date' => '2026-02-31')));
    }

    public function testRejectsADateInTheFuture(): void
    {
        // Results only: the detail page hides the whole results block until the
        // date has passed, so a future date would publish an empty-looking page.
        $future = gmdate('Y-m-d', time() + 10 * 86400);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_future');
        TournamentSubmission::from_input($this->input(array('date' => $future)));
    }

    public function testAcceptsTodayAndTomorrowSoTimezonesDoNotRejectAValidReport(): void
    {
        foreach (array(gmdate('Y-m-d'), gmdate('Y-m-d', time() + 86400)) as $date) {
            $s = TournamentSubmission::from_input($this->input(array('date' => $date)));
            $this->assertSame($date, json_decode($s->to_json(), true)['date']);
        }
    }

    public function testRejectionsCarryAStableCodeNotProse(): void
    {
        // The message crosses the wire and is resolved per locale in the browser,
        // so it must stay a code.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('title_required');
        TournamentSubmission::from_input($this->input(array('title' => '')));
    }

    public function testRejectsTitleWithNoLetterOrDigit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('title' => '---')));
    }

    public function testParticipantsDefaultsToZeroWhenAbsent(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('participants' => '')));
        $this->assertSame(0, json_decode($s->to_json(), true)['actualPlayerCount']);
    }

    // -- top 8 ---------------------------------------------------------------

    public function testDropsUntouchedRowsAndSortsByPlace(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 2, 'playerName' => 'Guislain', 'commanderName' => 'Heartfire Hero', 'score' => '3-1'),
            array('place' => 1, 'playerName' => 'Axel', 'commanderName' => 'Baleful Strix', 'score' => '4-0'),
            array('place' => 3, 'playerName' => '', 'commanderName' => '', 'decklist' => ''),
        ))));

        $top8 = json_decode($s->to_json(), true)['top8'];
        $this->assertCount(2, $top8);
        $this->assertSame(1, $top8[0]['place']);
        $this->assertSame('Axel', $top8[0]['playerName']);
        $this->assertSame(2, $top8[1]['place']);
    }

    public function testRowMissingPlayerOrCommanderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'Axel', 'commanderName' => ''),
        ))));
    }

    public function testDuplicatePlaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'A', 'commanderName' => 'Baleful Strix'),
            array('place' => 1, 'playerName' => 'B', 'commanderName' => 'Mother of Runes'),
        ))));
    }

    public function testScoreIsAlwaysPresentBecauseTheSchemaRequiresIt(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'Axel', 'commanderName' => 'Baleful Strix'),
        ))));

        $this->assertSame('', json_decode($s->to_json(), true)['top8'][0]['score']);
    }

    public function testDecklistSlugIsNullUntilWired(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'Axel', 'commanderName' => 'Baleful Strix'),
        ))));

        $this->assertNull(json_decode($s->to_json(), true)['top8'][0]['decklistSlug']);
        $this->assertSame('x', json_decode($s->to_json(array(1 => 'x')), true)['top8'][0]['decklistSlug']);
    }

    // -- metagame ------------------------------------------------------------

    public function testMetaAcceptsCountedAndBareLines(): void
    {
        $s = TournamentSubmission::from_input($this->input(array(
            'meta' => "2 Baleful Strix\nSphinx of the Guildpact\n\n3x Sedraxis Specter\n",
        )));

        $this->assertSame(array(
            array('name' => 'Baleful Strix', 'count' => 2),
            array('name' => 'Sphinx of the Guildpact', 'count' => 1),
            array('name' => 'Sedraxis Specter', 'count' => 3),
        ), json_decode($s->to_json(), true)['metaList']);
    }

    // -- decklists -----------------------------------------------------------

    public function testDecklistForCarriesTournamentDatePlayerAndPartner(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array(
                'place'         => 1,
                'playerName'    => 'Axel Verkest',
                'commanderName' => 'Gut, True Soul Zealot // Inspiring Leader',
                'score'         => '4-0',
                'decklist'      => "1 Command Tower\n98 Mountain",
            ),
        ))));

        $d = $s->decklist_for(1);
        $json = json_decode($d->to_json(), true);

        $this->assertSame('Gut, True Soul Zealot', $json['commander']);
        $this->assertSame('Inspiring Leader', $json['partner']);
        // The tournament's date, not the day the form was filled in.
        $this->assertSame('2026-05-25', $json['date']);
        $this->assertSame('Axel Verkest', $json['author']);
        $this->assertSame('Gut, True Soul Zealot – Artefact #7 (1er)', $json['title']);
        $this->assertSame('gut-true-soul-zealot-artefact-7-1er', $d->slug());
        $this->assertSame('site/content/decklists/gut-true-soul-zealot-artefact-7-1er.json', $d->repo_path());
    }

    public function testPlacesWithDecklistListsOnlyFilledOnes(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'A', 'commanderName' => 'Baleful Strix', 'decklist' => '99 Island'),
            array('place' => 2, 'playerName' => 'B', 'commanderName' => 'Mother of Runes'),
        ))));

        $this->assertSame(array(1), $s->places_with_decklist());
    }

    public function testDecklistForThrowsWhenThatPlaceHasNone(): void
    {
        $s = TournamentSubmission::from_input($this->input(array('top8' => array(
            array('place' => 1, 'playerName' => 'A', 'commanderName' => 'Baleful Strix'),
        ))));

        $this->expectException(InvalidArgumentException::class);
        $s->decklist_for(1);
    }
}
