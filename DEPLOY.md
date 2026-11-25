# Déploiement

Le site est hébergé sur le **VPS Tolarian** (Dokploy, Traefik, HTTPS Let's
Encrypt), dans une image Docker **Apache + PHP 8.3** construite depuis le
`Dockerfile` du dépôt. Il a quitté l'hébergement mutualisé OVH en novembre 2026.
Fiche du serveur : `~/Developer/infra/README.md`.

## Vue d'ensemble

```
merge sur main / cron hebdo / déclenchement manuel
        │
        ▼
Dokploy (application `pauperduelcommander`, projet « Pauper Duel Commander »)
   docker build (Dockerfile) :
   1. étape Node 24 : npm ci + npm run build   → site/dist/ (contient api/)
   2. étape php:8.3-apache : dist/ → /var/www/html
        │
        ▼
   https://pauperduelcommander.fr
```

- **Les tests ne tournent pas au déploiement** : ils tournent sur la PR
  (`ci.yml`), et le ruleset « Protect main » interdit de merger sans eux.
- **Apache lit les `.htaccess`** comme sur OVH : redirections, en-têtes de cache,
  fermeture de `api/lib`, `api/cache`, `api/data`. Réglages du serveur dans
  `docker/zz-pdc.conf`.
- **Adresse des visiteurs** : Traefik est devant. `mod_remoteip` remplace
  `REMOTE_ADDR` par l'adresse de `X-Forwarded-For`, mais seulement quand la
  requête vient des réseaux Docker : le rate limit de l'API voit donc chaque
  visiteur, sans faire confiance à un en-tête envoyé de l'extérieur.
- **Écriture disque** : `api/cache/` (cache Scryfall + état du rate limit) est le
  volume `pdc-api-cache`, il survit aux déploiements.
- **Cache Scryfall du build** : gardé entre deux builds dans un cache BuildKit
  (`id=pdc-scryfall`). Le premier build télécharge tout, les suivants seulement
  ce qui manque ou a expiré. Le build et le validateur partagent l'adresse du VPS
  auprès de Scryfall : un build complet peut faire répondre 429 au validateur
  pendant une minute.

## Déclencher un déploiement

| Déclencheur | Quand |
|---|---|
| push sur `main` | **à chaque merge de PR** : Dokploy construit et remplace le conteneur |
| `schedule` (`deploy.yml`) | tous les lundis 05:00 UTC (rebuild hebdomadaire) |
| `workflow_dispatch` (`deploy.yml`) | à la demande |

Merger une PR suffit donc à mettre en ligne une soumission acceptée. Le
workflow `deploy.yml` déclenche le build par le webhook Dokploy quand aucun
commit ne le fait, puis contrôle la production (`site/scripts/smoke-check.sh`).

```bash
gh workflow run deploy.yml     # rebuild à la demande
gh run watch                   # suivre le contrôle
```

## Configuration

**Variables de build** (Dokploy, onglet Environment → Build-time arguments),
publiques : `PUBLIC_TURNSTILE_SITE_KEY`, `PUBLIC_SUBMISSIONS_ENABLED`.

**Secrets de l'API** (Dokploy, variables d'environnement), lus par
`pdc_secret()` : `PDC_GITHUB_TOKEN`, `PDC_TURNSTILE_SECRET`,
`PDC_ORGANIZER_CODE`. Absents, les formulaires répondent 503. Les poser sans les
afficher :

```bash
read -rs V && printf %s "$V" | node ~/Developer/infra/dokploy.mjs env q7_GNl1ITUGW2tIsjM3TI PDC_GITHUB_TOKEN=-
```

puis redéployer.

**Secret GitHub** `DOKPLOY_DEPLOY_URL` : l'URL du webhook de déploiement de
l'application (onglet Deployments de Dokploy), utilisée par `deploy.yml`.

## Rollback

Revenir à un commit sain sur `main` (`git revert <commit-fautif>`, PR, merge) :
Dokploy redéploie. En urgence, Dokploy garde les déploiements précédents
(onglet Deployments → Rollback).

## Vérifications post-déploiement (smoke tests)

```bash
D=https://pauperduelcommander.fr

# Pages
curl -sI  $D/fr/ | head -1                       # 200
curl -sI  $D/fr/regles/ | head -1                # 200

# Redirections 301 des anciennes URLs WordPress (.htaccess racine)
curl -sI  $D/banlist/ | grep -i location         # -> /fr/banlist/
curl -sI  $D/          | grep -i location         # -> /fr/

# 404 + SEO
curl -sI  $D/nexiste-pas/    | head -1           # 404
curl -sI  $D/robots.txt      | head -1           # 200
curl -sI  $D/sitemap-index.xml | head -1         # 200
curl -sI  $D/favicon.ico     | head -1           # 200

# Validateur — deck valide, puis carte bannie
curl -sS -X POST $D/api/validate-deck.php \
  --data-urlencode "commander=Mother of Runes" \
  --data-urlencode "decklist=99 Plains"          # is_valid: true
curl -sS -X POST $D/api/validate-deck.php \
  --data-urlencode "commander=Mother of Runes" \
  --data-urlencode "decklist=98 Plains
1 Goliath Paladin"                               # ban_list, is_valid: false

# Internes verrouillés
curl -sI $D/api/lib/DeckValidator.php | head -1  # 403
curl -sI $D/api/data/banlist.json     | head -1  # 403
```

Si le validateur renvoie **503**, la ban list n'est pas trouvée
(`api/data/banlist.json` absent) — garde-fou volontaire, pas un faux positif.

## Déploiement manuel de secours

Si GitHub ne prévient pas Dokploy, relancer le build depuis le Mac :

```bash
node ~/Developer/infra/dokploy.mjs application.deploy applicationId=q7_GNl1ITUGW2tIsjM3TI
```

## Prévisualisation (staging)

`deploy-staging.sh`, à la racine, publie une branche sur
**staging.pauperduelcommander.fr** (Surge) avant qu'elle n'atteigne la prod. Il
construit le checkout depuis lequel on le lance, donc depuis le worktree de la
branche à montrer :

```bash
cd .claude/worktrees/<branche>
/chemin/vers/pdc/deploy-staging.sh
```

Statique seulement : le PHP (validateur, formulaires) ne tourne pas sur Surge, le
formulaire de soumission y est masqué, et un `robots.txt` interdit l'indexation.
Il faut s'être connecté une fois (`npx surge login`) ; le script ne manipule
aucun identifiant. `main` n'y passe que si on le lance depuis `main`, et le
script prévient.

## Limites connues

- **Le typage est vérifié sur PR, pas au déploiement.** `npm run check` est propre :
  **0 erreur, 0 avertissement, 2 hints** (mesuré le 5 novembre 2026, Astro 7 ;
  `npm run lint`, qui y ajoute `tsc --noEmit`, l'est aussi).
  `deploy.yml` ne lance pas `check` : c'est l'étape « Type check » de `ci.yml` qui
  le fait, et **une erreur de typage bloque le merge** — le job « build & test »
  est dans les required status checks du ruleset « Protect main » (depuis le
  18 août 2026), lequel n'a aucun bypass, admin compris. « Require branches to be
  up to date » est volontairement décochée : elle imposerait un rebase dès qu'une
  autre PR passe devant.
