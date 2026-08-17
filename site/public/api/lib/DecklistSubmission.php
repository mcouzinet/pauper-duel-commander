<?php
/**
 * A validated decklist submission — pure, no I/O.
 *
 * Turns raw form input into the exact JSON shape of the `decklists` content
 * collection (see src/content.config.ts), plus a safe slug and repo path. The
 * slug is built with pdc_sanitize_key(), so the file path can never escape the
 * decklists directory.
 *
 * @package PDC_API
 * @since 2.2.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

final class DecklistSubmission {

    const MAX_FIELD = 200;      // commander / partner / author / archetype
    const MAX_CARDS = 20000;    // decklist text

    private $commander;
    private $partner;   // string|null
    private $cards;
    private $author;    // string|null
    private $archetype; // string|null
    private $date;      // YYYY-MM-DD
    private $slug;

    private function __construct() {}

    /**
     * @param array $in keys: commander, partner?, decklist, author?, archetype?
     * @return self
     * @throws InvalidArgumentException on a missing/empty required field
     */
    public static function from_input(array $in) {
        $clean = function ($v, $max) {
            $v = is_string($v) ? trim(strip_tags($v)) : '';
            return $v === '' ? null : mb_substr($v, 0, $max);
        };

        $commander = $clean(isset($in['commander']) ? $in['commander'] : '', self::MAX_FIELD);
        if ($commander === null) {
            throw new InvalidArgumentException('Le nom du general est obligatoire.');
        }

        $cards_raw = isset($in['decklist']) && is_string($in['decklist']) ? $in['decklist'] : '';
        $cards = trim(strip_tags($cards_raw));
        if ($cards === '') {
            throw new InvalidArgumentException('La decklist est obligatoire.');
        }
        // Normalise line endings; the parser and renderer accept \n.
        $cards = str_replace("\r\n", "\n", $cards);
        $cards = mb_substr($cards, 0, self::MAX_CARDS);

        $s = new self();
        $s->commander = $commander;
        $s->partner   = $clean(isset($in['partner']) ? $in['partner'] : '', self::MAX_FIELD);
        $s->author    = $clean(isset($in['author']) ? $in['author'] : '', self::MAX_FIELD);
        $s->archetype = $clean(isset($in['archetype']) ? $in['archetype'] : '', self::MAX_FIELD);
        $s->cards     = $cards;
        $s->date      = date('Y-m-d');
        // Safe slug: sanitized commander + date + short random anti-collision suffix.
        $suffix   = substr(bin2hex(random_bytes(3)), 0, 4);
        $s->slug  = pdc_sanitize_key($commander) . '-' . $s->date . '-' . $suffix;
        return $s;
    }

    public function commander()     { return $this->commander; }
    public function partner()       { return $this->partner; }
    public function decklist_text() { return $this->cards; }
    public function slug()          { return $this->slug; }

    /** Display title, e.g. "Gut, True Soul Zealot // Inspiring Leader". */
    public function title() {
        return $this->partner ? $this->commander . ' // ' . $this->partner : $this->commander;
    }

    /** Repo-relative path — fixed prefix + sanitized slug, no traversal possible. */
    public function repo_path() {
        return 'site/content/decklists/' . $this->slug . '.json';
    }

    /** JSON in the exact shape of the decklists collection. */
    public function to_json() {
        $data = array('title' => $this->title(), 'commander' => $this->commander);
        if ($this->partner !== null) {
            $data['partner'] = $this->partner;
        }
        $data['date'] = $this->date;
        if ($this->author !== null) {
            $data['author'] = $this->author;
        }
        if ($this->archetype !== null) {
            $data['archetype'] = $this->archetype;
        }
        $data['cards'] = $this->cards;

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
