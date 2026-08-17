# Program Design : Formulaires de soumission

Détaillé sur la **decklist** (tranches 1–3). Le tournoi (tranche 4+) réutilise le
même socle ; ses détails seront figés juste avant sa tranche.

## Files

**PHP — nouveau (`site/public/api/`)**
- `submit-decklist.php` — point d'entrée mince : construit les vraies dépendances,
  lit l'input, appelle le contrôleur, émet la réponse JSON.
- `lib/DecklistSubmissionController.php` — orchestre le flux (honeypot → rate limit
  → Turnstile → forme → validateur → build → PR). Dépendances **injectables**
  (testable sans réseau).
- `lib/DecklistSubmission.php` — pur : input validé → JSON canonique + slug sûr.
- `lib/TurnstileVerifier.php` — vérifie le token Turnstile côté serveur (HTTP injectable).
- `lib/GitHubClient.php` — API GitHub : ref de base, branche, commit multi-fichiers
  (Git Trees, 1 commit), ouverture de PR (HTTP injectable).
- `submit-tournament.php` + `lib/TournamentSubmission.php` + `lib/TournamentSubmissionController.php`
  — tranche tournoi (mêmes patterns).

**PHP — modifié**
- `lib/config.php` — ajoute : `pdc_secret()` (lit un fichier hors `www/`),
  constantes `PDC_SUBMIT_RATE_LIMIT` / `PDC_SUBMIT_RATE_WINDOW`, `PDC_GITHUB_REPO`,
  et l'autoload des nouvelles classes.

**Astro — nouveau (`site/src/`)**
- `components/pages/SubmitDecklistPage.astro` — formulaire (prop `locale`), script
  client inline (vérif en direct via `validate-deck.php`, envoi, succès/erreurs).
- `pages/fr/soumettre/decklist.astro` + `pages/en/submit/decklist.astro` — routes minces.
- (tournoi) `components/pages/SubmitTournamentPage.astro` + routes `.../tournoi` / `.../tournament`.

**Astro — modifié**
- `lib/routes.ts` — ajoute `submitDecklist` / `submitTournament` (slugs localisés).
- `i18n/fr.json` + `en.json` — namespace `submit`.
- `components/Header.astro` — lien(s) vers les formulaires (à décider : nav ou CTA).

**CI / secrets / docs**
- `.github/workflows/deploy.yml` — activer `push: [main]` (déploiement au merge).
- `docs/external/secrets.sample.php` — forme du fichier de secrets (sans valeurs).
- `docs/external/README.md` — où poser les secrets sur OVH, création du PAT/Turnstile.

**Tests (`site/tests/`)**
- `DecklistSubmissionTest.php`, `TurnstileVerifierTest.php`, `GitHubClientTest.php`,
  `DecklistSubmissionControllerTest.php`.

## Types & signatures (sans corps)

```php
// config.php
function pdc_secret(string $name): ?string;   // fichier hors www/ (PDC_SECRETS_FILE) sinon getenv

// lib/DecklistSubmission.php — pur, aucune I/O
final class DecklistSubmission {
    public static function fromInput(array $in): self;   // throws InvalidArgumentException si forme invalide
    public function commander(): string;
    public function partner(): ?string;
    public function decklistText(): string;
    public function slug(): string;                      // [a-z0-9-] uniquement, suffixe court anti-collision
    public function repoPath(): string;                  // "site/content/decklists/<slug>.json"
    public function toJson(): string;                    // schéma exact de la collection decklists
}

// lib/TurnstileVerifier.php
final class TurnstileVerifier {
    /** @param callable|null $http fn(string $url, array $fields): array{status:int, body:string} */
    public function __construct(string $secret, ?callable $http = null);
    public function verify(string $token, ?string $remoteIp): bool;   // false si échec OU réseau KO (fail-closed)
}

// lib/GitHubClient.php
final class GitHubClient {
    /** @param callable|null $http fn(string $method, string $url, ?array $json): array{status:int, body:array} */
    public function __construct(string $token, string $repo, ?callable $http = null);  // $repo = "owner/name"
    public function baseSha(string $branch = 'main'): string;
    /** @param array<string,string> $files chemin => contenu ; 1 seul commit (Git Trees) ; retourne le sha */
    public function commitFiles(string $branch, string $baseSha, array $files, string $message): string;
    /** @return array{number:int, html_url:string} */
    public function openPullRequest(string $head, string $base, string $title, string $body): array;
}

// lib/DecklistSubmissionController.php
final class DecklistSubmissionController {
    public function __construct(TurnstileVerifier $turnstile, GitHubClient $github);
    /** @return array{status:int, body:array} — n'émet rien, ne fait pas d'echo */
    public function handle(array $input, string $clientIp): array;
}
```

## Call stack (decklist, chemin principal)

```
submit-decklist.php
 ├─ require config.php ; pdc_set_cors_headers()
 ├─ garde POST-only + taille max
 ├─ lit l'input (form-encoded ou JSON)
 ├─ construit TurnstileVerifier(pdc_secret('TURNSTILE_SECRET'))
 │              GitHubClient(pdc_secret('GITHUB_TOKEN'), PDC_GITHUB_REPO)
 ├─ $r = DecklistSubmissionController->handle($input, RateLimiter::client_id())
 │        ├─ honeypot rempli ?            → {400}, stop
 │        ├─ RateLimiter::consume(ip, PDC_SUBMIT_RATE_LIMIT, PDC_SUBMIT_RATE_WINDOW) → {429}
 │        ├─ TurnstileVerifier::verify()  → false ? {403}, stop
 │        ├─ DecklistSubmission::fromInput() → invalide ? {422 "forme"}
 │        ├─ DeckValidator::validate()    → is_valid=false ? {422, errors[]}, AUCUNE PR
 │        ├─ GitHubClient::baseSha('main')
 │        ├─ GitHubClient::commitFiles('submission/decklist-<slug>', sha, [path=>json], msg)
 │        ├─ GitHubClient::openPullRequest(head, 'main', titre, corps+résumé validation)
 │        └─ return {200, { pr_url, message }}
 └─ echo json($r.body) avec $r.status
```

## Test plan (noms + ce qu'ils prouvent)

- `DecklistSubmissionTest::testBuildsCanonicalJson` — input valide → `toJson()` a
  exactement les clés de la collection, `partner` omis si vide.
- `::testSlugIsSanitizedNoTraversal` — général `"../../evil"` → slug sans `/` ni
  `.`, `repoPath()` reste sous `site/content/decklists/`.
- `::testRejectsMissingCommanderOrCards` — `fromInput` lève l'exception.
- `TurnstileVerifierTest::testTrueWhenCloudflareSuccess` — http stub `{success:true}` → true.
- `::testFalseWhenCloudflareFailure` — `{success:false}` → false.
- `::testFailsClosedOnNetworkError` — http stub status 0/5xx → false.
- `GitHubClientTest::testCommitAndPrPayloads` — http stub enregistre les appels ;
  vérifie ref de base lue, commit (tree) avec le bon chemin+contenu, PR POST
  `head/base/title`.
- `::testThrowsOnApiError` — réponse non-2xx → `RuntimeException`.
- `DecklistSubmissionControllerTest::testHappyPathOpensPr` — turnstile=true,
  validateur (fixtures) OK, github stub → `{200}`, `pr_url` présent, github appelé.
- `::testRejectsBadTurnstile` — `{403}`, github **jamais** appelé.
- `::testRejectsIllegalDeck` — deck avec carte bannie → `{422}` avec errors,
  github **jamais** appelé (le déchet ne devient pas une PR).
- `::testRejectsHoneypotFilled` — `{400}`, github jamais appelé.
- `::testRateLimited` — au-delà du quota → `{429}`.

Tests hermétiques (HTTP injecté partout, fixtures Scryfall existantes pour le validateur).

## Least confident decisions (à challenger maintenant, tant que c'est gratuit)

1. **Turnstile fail-closed** : on rejette si Cloudflare ne répond pas. Anti-spam
   plus sûr, mais bloque les soumissions si Cloudflare est down. (Alternative :
   fail-open + s'appuyer sur la modération.) → proposé **fail-closed**.
2. **Commit via Git Trees** (1 commit propre, multi-fichiers, uniforme decklist +
   tournoi) plutôt que l'API Contents (plus simple mais 1 commit/fichier). →
   proposé **Trees**.
3. **Emplacement du fichier de secrets sur OVH** : `SetEnv` Apache dans un
   `.htaccess` hors `www/`, ou chemin fixe `../pdc-secrets.php` au-dessus de la
   racine. À confirmer sur l'hébergement pendant la tranche 1.
4. **Anti-collision de slug** : suffixe aléatoire court (ex. `-a1b2`). Simple et
   suffisant, mais slugs moins « propres ».
5. **Quota soumissions** : proposé **5 / heure / IP** (à ajuster).
6. **Lien vers les formulaires** dans la nav principale ou en CTA discret ? (produit)
