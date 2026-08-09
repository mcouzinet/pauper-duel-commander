# Déploiement — bascule WordPress → Astro

Runbook de mise en production du site Astro (statique) + API PHP, en remplacement
du WordPress actuel. Le domaine ne change pas (`pauperduelcommander.fr`) : la
bascule est un **changement de racine web**, pas un changement DNS — donc le
rollback est quasi instantané.

> Rien de ce document ne s'exécute tout seul. Chaque commande serveur est à lancer
> par toi. Les valeurs entre `<…>` sont à remplacer.

---

## Déploiement automatique — OVH via GitHub Actions (méthode en place)

Le dépôt est hébergé sur **OVH mutualisé** (Apache + PHP, racine web `www/`), déployé
en **FTP**. Le workflow [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml)
build le site et synchronise `site/dist/` vers `www/` en FTPS incrémental (seuls les
fichiers modifiés partent ; le cache généré à l'exécution sous `api/cache/` n'est pas
touché). Les tests de l'API PHP bloquent le déploiement s'ils échouent.

**Réglage unique (par toi — ces secrets ne doivent jamais passer par Claude) :**
dans GitHub → le dépôt → **Settings → Secrets and variables → Actions → New repository secret**,
créer les trois secrets, avec les identifiants FTP fournis par l'espace client OVH :

| Secret | Valeur |
|---|---|
| `OVH_FTP_SERVER` | l'hôte FTP OVH (ex. `ftp.cluster0XX.hosting.ovh.net`) |
| `OVH_FTP_USERNAME` | le login FTP OVH |
| `OVH_FTP_PASSWORD` | le mot de passe FTP OVH |

Optionnel mais recommandé : GitHub → **Settings → Environments → `production`** →
ajouter un *required reviewer* (toi). Ainsi chaque déploiement attend ton approbation
d'un clic — rien ne part en prod sans validation.

**Déclencher un déploiement** (le déclenchement est manuel par défaut) :

```bash
gh workflow run deploy.yml        # depuis un terminal, ou lancé par Claude
```

ou depuis GitHub : onglet **Actions → Deploy to OVH → Run workflow**. Suivre le run :

```bash
gh run watch
```

Pour déployer automatiquement à chaque push sur `main`, décommenter le bloc `push:`
en tête du workflow.

> Le reste de ce document décrit une alternative **VPS (SSH + rsync + symlink)** qui
> n'est **pas** la méthode utilisée ici — la conserver comme référence si l'hébergement
> change un jour. Sur OVH mutualisé, il n'y a ni SSH ni symlink : tout passe par le FTP
> ci-dessus.

---

## 1. Ce qui tourne en production

| | |
|---|---|
| **Site** | 100 % statique (HTML pré-rendu). N'a besoin que d'un serveur web. |
| **Validateur** | `POST /api/validate-deck.php` — **seule** chose qui s'exécute à l'exécution. |
| **Runtime requis** | **PHP 8** avec l'extension **cURL** (ScryfallService). **Node n'est PAS requis en prod.** |
| **Écriture disque** | `api/cache/` (cache Scryfall + état du rate limit) doit être **inscriptible** par l'utilisateur PHP. |

Le build produit `site/dist/`, qui contient **déjà** `api/` et `api/data/banlist.json`.
C'est ce dossier, et lui seul, qu'on déploie.

---

## 2. Prérequis serveur (à vérifier une fois)

- [ ] PHP 8.x installé, avec `php-curl`. Vérifier : `php -v` et `php -m | grep curl`.
- [ ] Serveur web : **Apache** (avec `mod_rewrite` + `AllowOverride All` pour que les
      `.htaccess` s'appliquent) **ou** nginx (les `.htaccess` sont ignorés — voir §6).
- [ ] HTTPS actif sur le domaine (déjà le cas sous WordPress).
- [ ] Accès SSH + droit d'écrire dans `/var/www/…` et de recharger le serveur web.

---

## 3. Build (en local, pas sur le serveur)

```bash
cd site
npm ci
npm run build
```

Si l'erreur `Cannot find native binding` / `@rolldown/binding` apparaît (bug npm
des dépendances optionnelles), refaire une install propre :

```bash
cd site && rm -rf node_modules package-lock.json && npm install && npm run build
```

Vérifs post-build (doivent toutes passer) :

```bash
cd site
test -f dist/api/data/banlist.json          && echo "banlist OK"
test -f dist/api/validate-deck.php           && echo "endpoint OK"
test -f dist/.htaccess && test -f dist/404.html && test -f dist/robots.txt && echo "infra OK"
test -f dist/sitemap-index.xml               && echo "sitemap OK"
npm test                                     # 40 tests, doit être vert
```

---

## 4. Déploiement (schéma releases + symlink)

Ce schéma rend chaque déploiement **atomique** et le rollback instantané : on
rsync dans un dossier horodaté, puis on bascule un lien symbolique `current`.

Arborescence cible sur le serveur :

```
/var/www/pdc/
├── releases/
│   ├── 2026-08-09_1200/     ← contenu de dist/
│   └── 2026-08-08_1800/     ← release précédente (gardée pour rollback)
├── shared/
│   └── api-cache/           ← cache Scryfall + rate limit, PERSISTANT entre releases
└── current -> releases/2026-08-09_1200
```

### 4.1 Envoyer la nouvelle release

```bash
# Depuis la machine locale, à la racine du dépôt :
REL=$(date +%Y-%m-%d_%H%M)
rsync -avz --delete site/dist/ <user>@<host>:/var/www/pdc/releases/$REL/
```

### 4.2 Cache persistant (à faire une fois, puis à chaque release)

Le cache ne doit pas repartir de zéro à chaque déploiement. On le déporte dans
`shared/` et on le relie :

```bash
# Sur le serveur — une seule fois :
mkdir -p /var/www/pdc/shared/api-cache/scryfall /var/www/pdc/shared/api-cache/ratelimit
chown -R <php-user>:<php-user> /var/www/pdc/shared/api-cache
chmod -R 775 /var/www/pdc/shared/api-cache

# À chaque release : remplacer le cache vide de la release par le cache partagé
rm -rf /var/www/pdc/releases/$REL/api/cache
ln -s /var/www/pdc/shared/api-cache /var/www/pdc/releases/$REL/api/cache
```

`<php-user>` est en général `www-data` (Debian/Ubuntu) ou `apache` (RHEL).

### 4.3 Basculer

```bash
# Sur le serveur — bascule atomique :
ln -sfn /var/www/pdc/releases/$REL /var/www/pdc/current
```

### 4.4 Pointer le vhost sur `current` (première bascule seulement)

Modifier la config du serveur pour que la racine web soit `/var/www/pdc/current`
(voir §6), puis recharger :

```bash
sudo apachectl configtest && sudo systemctl reload apache2   # Apache
# ou
sudo nginx -t && sudo systemctl reload nginx                 # nginx
```

Les déploiements **suivants** ne touchent plus au vhost : seule la commande 4.3
(swap du symlink) suffit.

---

## 5. Permissions

```bash
# Le cache doit être inscriptible par PHP ; le reste en lecture seule.
chown -R <php-user>:<php-user> /var/www/pdc/shared/api-cache
find /var/www/pdc/current/ -type f -exec chmod 644 {} \;
find /var/www/pdc/current/ -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/pdc/shared/api-cache
```

---

## 6. Configuration serveur

### Apache

Les `.htaccess` fournis (racine + `api/`) font tout le travail — il suffit que
`AllowOverride All` soit actif :

```apache
<VirtualHost *:443>
    ServerName pauperduelcommander.fr
    DocumentRoot /var/www/pdc/current

    <Directory /var/www/pdc/current>
        AllowOverride All          # indispensable pour les .htaccess
        Require all granted
    </Directory>

    # … config SSL existante (certificats) …
</VirtualHost>
```

- Racine `.htaccess` : redirections 301 des anciennes URLs + `ErrorDocument 404`.
- `api/.htaccess` : n'autorise que `validate-deck.php` ; `lib/`, `cache/`, `data/` refusés.

### nginx (les `.htaccess` sont ignorés — tout est à recopier)

```nginx
server {
    server_name pauperduelcommander.fr;
    root /var/www/pdc/current;
    index index.html;

    # Redirections de bascule (équivalent du .htaccess racine)
    location = /                                { return 301 /fr/; }
    location = /banlist/                        { return 301 /fr/banlist/; }
    location = /validateur/                     { return 301 /fr/validateur/; }
    location = /meta/                           { return 301 /fr/meta/; }
    location = /declaration-de-confidentialite/ { return 301 /fr/confidentialite/; }
    location ^~ /tournois/ { rewrite ^/tournois/(.*)$ /fr/tournois/$1 permanent; }
    location ^~ /decklist/ { rewrite ^/decklist/(.*)$ /fr/decklist/$1 permanent; }

    error_page 404 /404.html;

    # Seul validate-deck.php est exécutable ; le reste de api/ est refusé.
    location ^~ /api/ { return 404; }
    location = /api/validate-deck.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # adapter la version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location / { try_files $uri $uri/ =404; }

    # … config SSL existante …
}
```

---

## 7. Checklist de bascule (smoke tests sur le domaine live)

À lancer **juste après** la bascule. Tout doit passer.

```bash
D=https://pauperduelcommander.fr

# Pages
curl -sI  $D/fr/ | head -1                              # 200
curl -sI  $D/en/ | head -1                              # 200
curl -sI  $D/fr/regles/ | head -1                       # 200

# Redirections 301 des anciennes URLs WordPress
curl -sI  $D/banlist/  | grep -iE "^HTTP|^location"     # 301 -> /fr/banlist/
curl -sI  $D/tournois/artefact-1/ | grep -i location    # 301 -> /fr/tournois/artefact-1/
curl -sI  $D/          | grep -i location               # 301 -> /fr/

# 404
curl -sI  $D/nexiste-pas/ | head -1                     # 404 (page 404 stylée)

# SEO
curl -sI  $D/robots.txt | head -1                       # 200
curl -sI  $D/sitemap-index.xml | head -1                # 200

# Validateur — deck valide
curl -sS -X POST $D/api/validate-deck.php \
  --data-urlencode "commander=Mother of Runes" \
  --data-urlencode "decklist=99 Plains"                 # is_valid: true

# Validateur — carte bannie (doit rejeter)
curl -sS -X POST $D/api/validate-deck.php \
  --data-urlencode "commander=Mother of Runes" \
  --data-urlencode "decklist=98 Plains
1 Goliath Paladin"                                      # ban_list, is_valid: false

# Verrou de sécurité : les internes ne doivent PAS être servis
curl -sI $D/api/lib/DeckValidator.php | head -1         # 403/404
curl -sI $D/api/data/banlist.json     | head -1         # 403/404
```

Point de vigilance : si le validateur renvoie **503**, la ban list n'est pas
trouvée (`api/data/banlist.json` absent ou chemin cassé) — c'est un garde-fou
volontaire, pas un faux positif. Vérifier la présence du fichier dans la release.

---

## 8. Rollback

Le WordPress reste intact tant qu'on n'y touche pas. Deux niveaux :

**Revenir à la release Astro précédente** (si un déploiement casse quelque chose) :

```bash
ln -sfn /var/www/pdc/releases/<release-précédente> /var/www/pdc/current
# pas de reload nécessaire : le symlink est suivi à chaud
```

**Revenir à WordPress** (si la bascule elle-même pose problème) : repointer le
`DocumentRoot` du vhost sur l'ancienne racine WordPress et recharger le serveur.
Ne rien supprimer de WordPress avant plusieurs jours de fonctionnement validé.

---

## 9. Après la bascule

- [ ] Soumettre `https://pauperduelcommander.fr/sitemap-index.xml` dans Google
      Search Console (ou attendre le prochain crawl).
- [ ] Vérifier que Google Analytics (`G-4J2Y2V33VE`) reçoit des vues **après**
      acceptation du bandeau cookies.
- [ ] Surveiller les logs serveur pour des 404 inattendus (anciennes URLs oubliées
      dans la table de redirection).
- [ ] Purger les vieilles releases au bout de quelques déploiements :
      `ls -dt /var/www/pdc/releases/*/ | tail -n +4 | xargs rm -rf`

---

## Limites connues

- Les redirections `.htaccess` **ne peuvent pas être testées sans Apache** : le
  serveur PHP intégré (`php -S`) les ignore. Elles ont été validées par simulation
  des règles, pas en exécution réelle. Le premier vrai test se fait au §7.
- Le contenu comporte quelques trous connus (4 tournois sans résultats, un deck
  gagnant à 96 cartes, deux noms de cartes tronqués) — cosmétique, non bloquant.
