# Setup externe — formulaires de soumission

Ce que **toi** dois créer/poser hors du dépôt pour activer les soumissions. Claude
ne manipule pas les secrets. Une fois ces étapes faites, bascule le feature flag
et déploie : les formulaires passent en ligne.

## 1. PAT GitHub (fine-grained)

GitHub → Settings → Developer settings → **Fine-grained tokens** → Generate :

- **Repository access** : *Only select repositories* → `pauper-duel-commander`.
- **Permissions** → Repository :
  - **Contents** : Read and write
  - **Pull requests** : Read and write
  - (rien d'autre — pas de workflow, pas d'administration)
- Expiration : au choix (prévoir le renouvellement).

Copier le token (`github_pat_…`). Il ne pourra qu'ouvrir des PR — jamais merger
ni déployer.

## 2. Cloudflare Turnstile (gratuit)

Cloudflare Dashboard → **Turnstile** → Add site :

- Domaine : `pauperduelcommander.fr`
- Widget mode : *Managed*

On obtient **deux** clés :
- **Site key** (publique) → variable de build (étape 4).
- **Secret key** → fichier de secrets (étape 3).

## 3. Poser le fichier de secrets sur OVH (hors `www/`)

Copier `docs/external/secrets.sample.php` en **`pdc-secrets.php`**, y mettre les
vraies valeurs, et le déposer par SFTP dans le dossier qui **contient** `www/`
(la racine du compte OVH) — **pas** dans `www/`.

```
/home/<compte>/
├── pdc-secrets.php     ← ici (hors www/, non servi, non déployé)
└── www/                ← racine web
```

`pdc_secret()` le trouve tout seul (un cran au-dessus de `www/`). Layout
différent ? Définir la variable d'env `PDC_SECRETS_FILE` vers son chemin absolu.

> Le déploiement SFTP n'écrase **que** le contenu de `www/` (et saute les
> dotfiles), donc ce fichier reste intact d'un déploiement à l'autre.

### 3 bis. Autoriser l'endpoint dans `api/.htaccess` (une fois)

Le SFTP **ne déploie pas les dotfiles**, donc le `www/api/.htaccess` du serveur
est resté celui qui n'autorise que `validate-deck.php` — les nouveaux endpoints
de soumission y sont bloqués (403). **Une fois**, uploader par SFTP le fichier
`site/public/api/.htaccess` du dépôt vers `www/api/.htaccess` (il autorise
`validate-deck.php`, `submit-decklist.php`, `submit-tournament.php`). À refaire
uniquement si on ajoute un endpoint.

## 4. Variables de build (publiques) sur GitHub

GitHub → Settings → Secrets and variables → **Actions** → onglet **Variables** :

| Variable | Valeur |
|---|---|
| `PUBLIC_TURNSTILE_SITE_KEY` | la *site key* Turnstile (étape 2) |
| `PUBLIC_SUBMISSIONS_ENABLED` | `true` pour afficher les formulaires |

Non secrètes (elles finissent dans le HTML). Absentes : captcha de **test** +
formulaires **masqués** (« bientôt disponible »).

## 5. Protéger `main` + déploiement au merge

- GitHub → Settings → **Branches** → protéger `main` : *Require a pull request
  before merging*. Ainsi le PAT ne peut qu'ouvrir des PR ; le merge reste humain.
- Le workflow déploie déjà **automatiquement au push sur `main`** touchant
  `site/**` (donc au merge d'une PR de soumission). Optionnel : exiger une
  approbation via Settings → Environments → `production` → *required reviewer*.

## 6. Vérifier

Après avoir posé les secrets et mis `PUBLIC_SUBMISSIONS_ENABLED=true`, déclencher
un déploiement (`gh workflow run deploy.yml`), puis :

```bash
D=https://pauperduelcommander.fr
# Deck valide -> ouvre une vraie PR (à relire, ne pas merger si c'est un test)
curl -s -X POST $D/api/submit-decklist.php \
  --data-urlencode "commander=Mother of Runes" \
  --data-urlencode "decklist=99 Plains" \
  --data-urlencode "cf-turnstile-response=<token d'un vrai widget>"
```

En pratique, la vérification se fait via le formulaire lui-même sur
`/fr/soumettre-decklist/` (le widget Turnstile y fournit le token). Une soumission
valide doit ouvrir une PR dans le dépôt.

Pour le formulaire **tournoi** (`/fr/soumettre-tournoi/`), il faut en plus que
`ORGANIZER_CODE` soit posé dans `pdc-secrets.php` : sans lui l'endpoint répond
**503**, et avec un mauvais code **403**. Une soumission valide ouvre **une seule**
PR contenant le tournoi *et* les decklists légales du top 8.

> Sans token/secret, l'endpoint répond **503** (inerte, sûr) — c'est voulu.

> Le formulaire tournoi publie des **résultats** : une date dans le futur est
> refusée (422 `date_future`). La page tournoi masque tout le bloc résultats tant
> que la date n'est pas passée, donc une telle soumission publierait une page
> vide de ce que l'organisateur a saisi. Les annonces de tournois à venir restent
> éditées à la main.
