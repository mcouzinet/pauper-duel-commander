# Déploiement

Le site est hébergé sur **OVH mutualisé** (Apache + PHP 8, racine web `www/`) et
déployé **automatiquement par GitHub Actions en SFTP**. Le domaine
`pauperduelcommander.fr` sert déjà la version Astro (la bascule depuis WordPress
est faite).

## Vue d'ensemble

```
push / déclenchement manuel
        │
        ▼
GitHub Actions (.github/workflows/deploy.yml)
   1. build Astro (Node 24)         → site/dist/  (contient déjà api/)
   2. tests PHPUnit de l'API        → bloquants
   3. envoi SFTP de dist/ → www/    → OVH
        │
        ▼
   https://pauperduelcommander.fr
```

- **Runtime prod** : PHP 8 uniquement (`/api/validate-deck.php`). Pas de Node en prod.
- **Écriture disque** : `api/cache/` doit rester inscriptible (cache Scryfall +
  état du rate limit). Sur OVH mutualisé, PHP tourne sous le compte FTP, donc les
  fichiers déposés sont inscriptibles par défaut.

## Déclencher un déploiement

Le déclenchement est **manuel** — rien ne part tout seul.

```bash
gh workflow run deploy.yml     # depuis un terminal (ou lancé par Claude)
gh run watch                   # suivre le run en cours
```

ou depuis GitHub : onglet **Actions → Deploy to OVH → Run workflow**.

Pour déployer automatiquement à chaque push sur `main`, décommenter le bloc
`push:` en tête du workflow.

## Configuration (déjà en place)

**Secrets** (GitHub → dépôt → Settings → Secrets and variables → Actions),
issus de l'espace client OVH → Hébergements → onglet **FTP-SSH** :

| Secret | Valeur |
|---|---|
| `OVH_FTP_SERVER` | hôte FTP OVH (ex. `ftp.cluster0XX.hosting.ovh.net`) |
| `OVH_FTP_USERNAME` | login FTP-SSH |
| `OVH_FTP_PASSWORD` | mot de passe FTP-SSH |

Le transfert se fait en **SFTP (port 22)** avec ces mêmes identifiants — le mot
de passe ne transite pas en clair. Ces secrets restent chez GitHub, chiffrés.

**Garde-fou d'approbation (optionnel, recommandé)** : GitHub → Settings →
Environments → `production` → *required reviewer*. Chaque déploiement attend alors
une approbation avant de partir.

## Rollback

La bascule est faite, mais l'ancien WordPress reste récupérable tant qu'on n'a
pas supprimé sa sauvegarde (dump SQL + fichiers, hors dépôt).

- **Revenir à une version précédente du site Astro** : repartir d'un commit sain
  et redéployer.
  ```bash
  git checkout <commit-sain> -- .        # ou git revert <commit-fautif>
  gh workflow run deploy.yml
  ```
  Le SFTP écrase les fichiers modifiés ; `delete_remote_files` est à `false`,
  donc rien n'est supprimé côté serveur.
- **Revenir à WordPress** (cas extrême) : restaurer les fichiers WP dans `www/`
  et la base depuis la sauvegarde. À ne faire que si la version Astro pose un
  problème majeur non corrigeable rapidement.

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

Si GitHub Actions est indisponible, on peut déployer à la main : build local puis
envoi SFTP du contenu de `site/dist/` vers `www/` (client SFTP type FileZilla, ou
`scp`/`sftp` sur le port 22 avec les identifiants FTP-SSH OVH).

```bash
cd site && npm ci && npm run build
# puis envoyer le CONTENU de site/dist/ dans www/ via SFTP
```

## Limites connues

- **Le SFTP n'envoie pas les fichiers cachés** (glob `dist/*`). En pratique sans
  effet : le `.htaccess` racine (redirections) et `api/.htaccess` sont déjà en
  ligne et `delete_remote_files` est à `false`, donc ils restent en place. **Mais
  si un `.htaccess` est modifié**, il faut le pousser une fois à la main (ou
  adapter le workflow). Idem pour tout futur fichier commençant par un point.
- **Le typage est vérifié sur PR, pas au déploiement.** `npm run check` est propre
  — **0 erreur, 0 avertissement, 8 hints** (mesuré sur `main` le 18 août 2026 ;
  `npm run lint`, qui y ajoute `tsc --noEmit`, l'est aussi). La dernière « erreur
  préexistante » connue, celle d'`astro.config.ts`, a été neutralisée par 812c0d7
  (cast `tailwindcss() as any`, le commentaire sur place explique le pourquoi).
  `deploy.yml` ne lance pas `check` : c'est l'étape « Type check » de `ci.yml` qui
  le fait, et **une erreur de typage bloque le merge** — le job « build & test »
  est dans les required status checks du ruleset « Protect main » (depuis le
  18 août 2026), lequel n'a aucun bypass, admin compris. « Require branches to be
  up to date » est volontairement décochée : elle imposerait un rebase dès qu'une
  autre PR passe devant.
