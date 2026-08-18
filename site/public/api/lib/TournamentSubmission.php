<?php
/**
 * A validated tournament submission — pure, no I/O.
 *
 * Turns raw form input into the exact JSON shape of the `tournaments` content
 * collection (see src/content.config.ts), plus the decklists of the top 8 as
 * DecklistSubmission objects with their `decklistSlug` wired back into the
 * tournament. Every path is built from a fixed prefix plus a sanitized slug, so
 * it can never escape its content directory.
 *
 * This form submits RESULTS (top 8 + metagame), not announcements: an upcoming
 * tournament still needs `signupUrl` / `details`, which stay hand-edited. A date
 * in the future is refused for that reason — the detail page hides the whole
 * results block until the date has passed, so such a submission would silently
 * publish a page showing none of what the organizer typed.
 *
 * Rejections carry a stable CODE, not prose: the message reaches the organizer's
 * browser, and the three locales resolve it there (see `submitTournament.js.form*`).
 *
 * @package PDC_API
 * @since 2.3.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

final class TournamentSubmission {

    const MAX_FIELD      = 200;
    const MAX_TOP8       = 8;
    const MAX_META_LINES = 200;

    /**
     * French ordinals, matching the titles and slugs already in content/decklists/
     * (`gut-animmagic-19-07-26-1er`). The decklist `title` is a single
     * non-localized string in the collection, so it follows the existing data
     * rather than the reader's language.
     */
    private static $ORDINALS = array(
        1 => '1er', 2 => '2eme', 3 => '3eme', 4 => '4eme',
        5 => '5eme', 6 => '6eme', 7 => '7eme', 8 => '8eme',
    );

    private $title;
    private $date;
    private $location;     // string|null
    private $city;         // string|null
    private $participants; // int
    private $entries = array(); // list of array{place,playerName,commanderName,score,decklist:string|null}
    private $meta    = array(); // list of array{name,count}
    private $slug;

    private function __construct() {}

    /**
     * @param array $in keys: title, date, location?, city?, participants?, top8[], meta?
     * @return self
     * @throws InvalidArgumentException on a missing or malformed field
     */
    public static function from_input(array $in) {
        $clean = function ($v, $max) {
            $v = is_string($v) ? trim(strip_tags($v)) : '';
            return $v === '' ? null : mb_substr($v, 0, $max);
        };

        $title = $clean(isset($in['title']) ? $in['title'] : '', self::MAX_FIELD);
        if ($title === null) {
            throw new InvalidArgumentException('title_required');
        }

        $date = $clean(isset($in['date']) ? $in['date'] : '', 10);
        if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('date_required');
        }
        // Reject 2026-02-31 and friends: checkdate() confirms the day exists.
        list($y, $m, $d) = array_map('intval', explode('-', $date));
        if (!checkdate($m, $d, $y)) {
            throw new InvalidArgumentException('date_invalid');
        }
        // Results only. One day of slack absorbs the gap between the organizer's
        // timezone and the server's rather than rejecting a same-evening report.
        if ($date > gmdate('Y-m-d', time() + 86400)) {
            throw new InvalidArgumentException('date_future');
        }

        $s = new self();
        $s->title        = $title;
        $s->date         = $date;
        $s->location     = $clean(isset($in['location']) ? $in['location'] : '', self::MAX_FIELD);
        $s->city         = $clean(isset($in['city']) ? $in['city'] : '', self::MAX_FIELD);
        $s->participants = self::read_participants(isset($in['participants']) ? $in['participants'] : null);
        $s->entries      = self::read_top8(isset($in['top8']) ? $in['top8'] : array(), $clean);
        $s->meta         = self::read_meta(isset($in['meta']) ? $in['meta'] : '');
        // Clean, readable slug — it becomes the public URL (/fr/tournois/<slug>/).
        // No random suffix here, unlike a decklist submission: a tournament title
        // is already distinctive, and a resubmission SHOULD land on the same path
        // so the PR shows a correction as a modified file rather than a duplicate.
        $s->slug = pdc_sanitize_key($title);
        if ($s->slug === '') {
            throw new InvalidArgumentException('title_no_alnum');
        }
        return $s;
    }

    /** @return int participants, 0 when not provided ("not announced" in the schema) */
    private static function read_participants($raw) {
        if ($raw === null || $raw === '') {
            return 0;
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('participants_nan');
        }
        $n = (int) $raw;
        if ($n < 0 || $n > 10000) {
            throw new InvalidArgumentException('participants_range');
        }
        return $n;
    }

    /**
     * Top 8 rows. A row with neither a player nor a commander is a blank line in
     * the form and is dropped rather than rejected.
     */
    private static function read_top8($raw, callable $clean) {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('top8_malformed');
        }
        $entries = array();
        $seen    = array();
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $player    = $clean(isset($row['playerName']) ? $row['playerName'] : '', self::MAX_FIELD);
            $commander = $clean(isset($row['commanderName']) ? $row['commanderName'] : '', self::MAX_FIELD);
            $decklist  = isset($row['decklist']) && is_string($row['decklist']) ? trim(strip_tags($row['decklist'])) : '';

            if ($player === null && $commander === null && $decklist === '') {
                continue; // untouched row
            }
            if ($player === null || $commander === null) {
                throw new InvalidArgumentException('top8_row_incomplete');
            }

            $place = isset($row['place']) && is_numeric($row['place']) ? (int) $row['place'] : count($entries) + 1;
            if ($place < 1 || $place > self::MAX_TOP8) {
                throw new InvalidArgumentException('top8_place_range');
            }
            if (isset($seen[$place])) {
                throw new InvalidArgumentException('top8_place_duplicate');
            }
            $seen[$place] = true;

            $score = $clean(isset($row['score']) ? $row['score'] : '', 50);
            $entries[] = array(
                'place'         => $place,
                'playerName'    => $player,
                'commanderName' => $commander,
                // The collection requires the key; "" is what existing data uses
                // when the organizer did not record scores.
                'score'         => $score === null ? '' : $score,
                'decklist'      => $decklist === '' ? null : str_replace("\r\n", "\n", $decklist),
            );
        }

        if (count($entries) > self::MAX_TOP8) {
            throw new InvalidArgumentException('top8_too_many');
        }

        usort($entries, function ($a, $b) { return $a['place'] - $b['place']; });
        return $entries;
    }

    /**
     * Metagame textarea -> [{name, count}]. One commander per line, optionally
     * prefixed by how many players ran it ("2 Baleful Strix"); a bare name counts
     * as one.
     */
    private static function read_meta($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $meta  = array();
        $lines = preg_split('/\r\n|\r|\n/', trim(strip_tags($raw)));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (count($meta) >= self::MAX_META_LINES) {
                break;
            }
            if (preg_match('/^(\d+)\s*x?\s+(.+)$/i', $line, $m)) {
                $count = (int) $m[1];
                $name  = trim($m[2]);
            } else {
                $count = 1;
                $name  = $line;
            }
            if ($name === '' || $count < 1) {
                continue;
            }
            $meta[] = array('name' => mb_substr($name, 0, self::MAX_FIELD), 'count' => $count);
        }
        return $meta;
    }

    public function title()        { return $this->title; }
    public function date()         { return $this->date; }
    public function slug()         { return $this->slug; }
    public function participants() { return $this->participants; }
    public function entries()      { return $this->entries; }
    public function meta_list()    { return $this->meta; }

    /** Repo-relative path — fixed prefix + sanitized slug, no traversal possible. */
    public function repo_path() {
        return 'site/content/tournaments/' . $this->slug . '.json';
    }

    /** Places that came with a decklist, in order. @return int[] */
    public function places_with_decklist() {
        $places = array();
        foreach ($this->entries as $e) {
            if ($e['decklist'] !== null) {
                $places[] = $e['place'];
            }
        }
        return $places;
    }

    /**
     * The decklist submitted for one place, as a DecklistSubmission carrying this
     * tournament's date and a slug tied to the tournament — so the whole shape of
     * a decklist file stays defined in exactly one class.
     *
     * @return DecklistSubmission
     * @throws InvalidArgumentException if that place has no decklist
     */
    public function decklist_for($place) {
        foreach ($this->entries as $e) {
            if ($e['place'] !== $place || $e['decklist'] === null) {
                continue;
            }
            // "Gut, True Soul Zealot // Inspiring Leader" -> commander + partner.
            $parts     = preg_split('#\s+//\s+#', $e['commanderName'], 2);
            $commander = trim($parts[0]);
            $partner   = isset($parts[1]) ? trim($parts[1]) : '';

            return DecklistSubmission::from_input(
                array(
                    'commander' => $commander,
                    'partner'   => $partner,
                    'decklist'  => $e['decklist'],
                    'author'    => $e['playerName'],
                ),
                $this->date,
                pdc_sanitize_key($commander) . '-' . $this->slug . '-' . $this->ordinal($place),
                $commander . ' – ' . $this->title . ' (' . $this->ordinal($place) . ')'
            );
        }
        throw new InvalidArgumentException('no_decklist_for_place');
    }

    private function ordinal($place) {
        return isset(self::$ORDINALS[$place]) ? self::$ORDINALS[$place] : (string) $place;
    }

    /**
     * JSON in the exact shape of the tournaments collection.
     *
     * @param array $decklist_slugs place => slug, for the decklists that made it
     *                              into the commit. Any other place gets null,
     *                              which is the collection's normal "no list yet".
     */
    public function to_json(array $decklist_slugs = array()) {
        $top8 = array();
        foreach ($this->entries as $e) {
            $top8[] = array(
                'place'         => $e['place'],
                'playerName'    => $e['playerName'],
                'commanderName' => $e['commanderName'],
                'score'         => $e['score'],
                'decklistSlug'  => isset($decklist_slugs[$e['place']]) ? $decklist_slugs[$e['place']] : null,
            );
        }

        $data = array(
            'title'    => $this->title,
            'date'     => $this->date,
            'location' => $this->location === null ? '' : $this->location,
            'city'     => $this->city === null ? '' : $this->city,
            // Results, not an announcement: the number of seats offered is not
            // what the organizer reports, the turnout is. `playerCount` stays 0
            // ("capacity not announced"); every display falls back to it only
            // when actualPlayerCount is absent.
            'playerCount'       => 0,
            'actualPlayerCount' => $this->participants,
            'top8'              => $top8,
            'metaList'          => $this->meta,
        );

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
