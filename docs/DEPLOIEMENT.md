# Déploiement

L'image de production est un conteneur autonome (PHP-FPM + Nginx + Supervisor) publiée sur GitHub Container Registry à chaque release GitHub depuis `docker/release/Dockerfile`.

> Une release GitHub publiée, une image poussée sur GHCR et une instance de production réellement à jour sont trois états distincts. Voir la section "États de publication" et la checklist pré-release dans [CONTRIBUTING.md](../CONTRIBUTING.md#releases).

## Démarrage rapide

```bash
docker run -d \
  -e APP_KEY="$(docker run --rm ghcr.io/zakmaf/3omar:latest php artisan key:generate --show)" \
  -e APP_URL=https://votre-domaine.com \
  -p 80:80 \
  ghcr.io/zakmaf/3omar:latest
```

## Variables d'environnement

| Variable | Obligatoire | Description |
|----------|-------------|-------------|
| `APP_KEY` | Oui | Clé de chiffrement des sessions et cookies. Générer avec `php artisan key:generate --show`. Une clé raw base64 sans préfixe (`openssl rand -base64 32`) est aussi acceptée — le préfixe `base64:` est ajouté automatiquement au démarrage. |
| `APP_URL` | Recommandé | URL publique complète, ex : `https://3omar.ma`. Utilisée pour la génération des URL absolues et la cohérence des cookies. |
| `APP_DEBUG` | Non | `false` par défaut. Mettre à `true` uniquement pour déboguer — affiche les erreurs en clair. |
| `ADSENSE_ENABLED` | Non | `false` par défaut. Mettre à `true` pour activer Google AdSense (necessite les variables ci-dessous). Les pages du calculateur (routes `calculator.*`, qui portent des montants de salaire) n'affichent jamais de publicité, quelle que soit cette valeur (#283). |
| `ADSENSE_PUBLISHER_ID` | Si AdSense | Identifiant editeur AdSense (`pub-xxx`). Sert au fichier `ads.txt`, a la balise meta de verification et au script (client `ca-pub-xxx` derive automatiquement). |
| `ADSENSE_SLOT_HEADER` | Si AdSense | ID du slot publicitaire du header. |
| `ADSENSE_SLOT_FOOTER` | Si AdSense | ID du slot publicitaire du footer. |

## Tags disponibles

| Tag | Usage recommandé |
|-----|-----------------|
| `latest` | Dernière version stable - mise à jour automatique |
| `v3` | Majeure 3.x.x - suit les mises à jour mineures et correctifs |
| `v3.7` | Mineure 3.7.x - fonctionnalités et correctifs de la série 3.7 |
| `v3.7.0` | Version exacte - reproductible, recommandé pour la production |
| `dev` | Dernière pré-release de développement (`vX.Y.Z-dev.N`) - pour les tests, jamais pour la production |

> **Tag, source et publication sont distincts.** Cette table décrit les tags que publie la release stable v3.7.0. La version inscrite dans les sources (`config/app.php`) ou une entrée de `docs/RELEASES.md` ne prouvent pas qu'un tag existe sur GHCR. Avant d'utiliser un tag, vérifier qu'une release GitHub de ce nom est publiée et que le run `docker-release.yml` a vérifié ses tags (section suivante). Un run absent, en cours ou en échec ne prouve ni l'absence d'un tag ni la préservation des tags mobiles : un échec après le push peut laisser des tags déplacés. Relire donc les digests de `v3.7.0`, `v3.7`, `v3`, `latest` et `v3.6` avant tout emploi (commandes ci-dessous). Le registre ne renseigne pas sur un déploiement : `/api/v1/health` indique la version déclarée, que `APP_VERSION` peut surcharger, et seule la comparaison du digest du conteneur au digest du tag sur GHCR atteste de son image (voir « Tag OCI et APP_VERSION »). `dev` désigne la dernière pré-release `vX.Y.Z-dev.N`, jamais une stable. Repère historique : v3.6.1 a été préparée mais jamais publiée (aucune release GitHub de ce nom, donc aucune image publiée par le workflow sous ce tag) ; ses changements font partie de v3.7.0.

```bash
# Seulement si la release v3.7.0 est publiée et ses tags vérifiés :
docker pull ghcr.io/zakmaf/3omar:v3.7.0
# Sinon, stable précédente :
docker pull ghcr.io/zakmaf/3omar:v3.6.0
```

### Tags publiés par mode de release

Le workflow `docker-release.yml` se déclenche uniquement sur une release GitHub publiée ; il n'a pas de déclenchement manuel (`workflow_dispatch`). Il refuse tout couple tag / type de release hors des deux modes suivants, avant de construire quoi que ce soit.

| Mode | Tag de release | Tags OCI publiés (même digest) | Tags OCI inchangés |
|------|----------------|--------------------------------|--------------------|
| Stable | `vX.Y.Z`, release non marquée pré-release | `vX.Y.Z`, `vX.Y`, `vX`, `latest` | `dev`, `X.Y.Z` (sans `v`) |
| Pré-release | `vX.Y.Z-dev.N`, release marquée pré-release | `vX.Y.Z-dev.N`, `dev` | `latest`, `vX`, `vX.Y`, `X.Y.Z-dev.N` (sans `v`) |

Un tag « inchangé » garde le digest qu'il avait avant la publication ; s'il n'existait pas, il doit toujours ne pas exister. Les formes sans `v` ne sont jamais publiées : `3.7.0-dev.1`, émis par erreur par la publication de `v3.7.0-dev.1`, est le défaut corrigé par #288.

Deux contrôles automatiques, sans publication :

- le job CI `Tags image release` rejoue, sur chaque PR, la vraie `docker/metadata-action` avec la configuration lue dans `docker-release.yml`, pour une stable et deux pré-releases, et exige exactement les tags du tableau ;
- le job CI `Vérification post-publication des tags` exerce `.github/scripts/verifier-tags-image.sh` contre des registres locaux jetables : publications conformes et fautives des deux modes, chacune avec son code de sortie exact (0 conforme, 1 non conforme, 2 lecture impossible) ; vraie connexion refusée et vrai refus d'authentification, qui doivent sortir en 2 sans déclarer aucun tag absent ; enfin la chaîne réelle `docker/build-push-action` (mêmes versions que la publication, attestation de provenance par défaut) poussée vers le registre local et vérifiée avec le digest que l'action renvoie.

À chaque publication réelle, `docker-release.yml` appelle le même script : avant le push, il relève le digest des tags à préserver ; après le push, il relit `ghcr.io/zakmaf/3omar` et échoue si un tag publié manque ou ne porte pas le digest poussé, ou si un tag à préserver a changé (apparition comprise). Le résumé du run liste chaque tag, son digest et le verdict. Un registre illisible (authentification, réseau) fait échouer le script avec le code 2 et le message « Lecture du registre impossible » : il n'est jamais pris pour un tag absent. Un échec de la capture, avant le push, arrête le workflow sans rien publier. Un échec de la vérification survient après le push : l'image est publiée, l'écart de tags est à corriger par le propriétaire.

Vérification manuelle équivalente, après une pré-release (exemple : `v3.7.0-dev.2`, première pré-release publiée après #288) :

```bash
for t in v3.7.0-dev.2 dev latest v3 v3.7; do
  printf '%s ' "$t"; docker buildx imagetools inspect "ghcr.io/zakmaf/3omar:$t" --format '{{json .Manifest}}' | jq -r .digest
done
```

Après la publication d'une stable (exemple : `v3.7.0`, une fois publiée), `v3.7.0`, `v3.7`, `v3` et `latest` doivent porter le même digest, et `dev` garder le sien :

```bash
for t in v3.7.0 v3.7 v3 latest dev; do
  printf '%s ' "$t"; docker buildx imagetools inspect "ghcr.io/zakmaf/3omar:$t" --format '{{json .Manifest}}' | jq -r .digest
done
```

### Tag OCI et APP_VERSION

Ce sont deux contrats distincts :

- **Tag OCI** : nom sous lequel le registre expose une image. Plusieurs tags désignent le même digest, et les tags mobiles (`latest`, `vX`, `vX.Y`, `dev`) changent de digest au fil des releases. Seul le digest identifie une image.
- **`APP_VERSION`** : paramètre applicatif, la version que l'application annonce (pied de page, `version` de `/api/v1/health`). Le workflow la fixe au build à partir du nom du tag de release (`APP_VERSION=vX.Y.Z` ou `vX.Y.Z-dev.N`) ; hors release, la valeur par défaut de `config/app.php` s'applique, et une variable `APP_VERSION` passée au démarrage du conteneur la remplace.

L'une ne prouve pas l'autre : une image tirée par `latest` ou `dev` annonce la version avec laquelle elle a été construite, pas le nom du tag utilisé, et `APP_VERSION` peut être surchargée à l'exécution. Pour savoir quelle image tourne, comparer le digest du conteneur à celui du tag dans le registre ; `/api/v1/health` indique seulement la version déclarée.

## Reverse proxy (Traefik, Nginx…)

Le conteneur écoute sur le port **80**. Passer `APP_URL` avec le schéma `https://`.

### Adresse réelle et HTTPS

L'adresse du visiteur sert de clé aux limiteurs de débit (30 calculs et 60 appels API par minute). Elle ne doit donc jamais venir d'un en-tête que le client peut écrire lui-même.

Dans l'image de release, c'est le Nginx embarqué qui résout l'adresse réelle :

- Il lit `X-Forwarded-For` uniquement depuis les plages privées `10.0.0.0/8`, `172.16.0.0/12` et `192.168.0.0/16` (`set_real_ip_from` dans `docker/release/nginx.conf`).
- Il transmet `HTTPS=on` à PHP-FPM seulement si ce même pair privé annonce `X-Forwarded-Proto: https`. Un client public qui envoie cet en-tête n'est pas cru.
- Il redirige en `301` vers `https://` (même chemin, même chaîne de requête) toute requête dont ce même pair privé annonce `X-Forwarded-Proto: http`, c'est-à-dire une visite en HTTP simple derrière Cloudflare ou Traefik. Sans cet en-tête (accès direct en `http://localhost`, `HEALTHCHECK` de l'image), rien n'est redirigé. Un proxy qui remplacerait `X-Forwarded-Proto` par `http` sur une visite HTTPS ferait boucler la redirection : il doit transmettre le schéma d'origine, comme décrit ci-dessous.
- PHP-FPM reçoit l'adresse déjà résolue. Laravel n'a donc besoin de faire confiance à aucun proxy : **laisser `TRUSTED_PROXIES` vide**.

`TRUSTED_PROXIES` (liste d'adresses ou de plages CIDR séparées par des virgules) ne sert que si Laravel est placé directement derrière un proxy, sans le Nginx de l'image : pile de développement modifiée, hébergement PHP-FPM tiers. Exemple : `TRUSTED_PROXIES=172.18.0.0/16`. La valeur `*` (faire confiance au pair direct, quel qu'il soit) reste possible mais ne doit être posée que si ce pair ne peut pas être joint autrement. Ne jamais la combiner avec le Nginx de l'image : l'adresse du visiteur deviendrait alors falsifiable.

**Traefik seul** : rien à renseigner. Traefik est sur le réseau Docker (plage privée) et ajoute l'adresse du visiteur à `X-Forwarded-For`.

**Tunnel Cloudflare (`cloudflared`)** : rien à renseigner côté conteneur. Le réseau Cloudflare ajoute l'adresse du visiteur à `X-Forwarded-For` (après toute valeur envoyée par le client) et `cloudflared` la relaie depuis le réseau Docker, donc depuis une plage privée.

- `cloudflared` pointe directement sur le conteneur : le Nginx de l'image retrouve l'adresse du visiteur sans autre réglage.
- `cloudflared` passe par Traefik : Traefik doit faire confiance au réseau de `cloudflared` (`entryPoints.<nom>.forwardedHeaders.trustedIPs`, par exemple la plage du réseau Docker partagé). Sans ce réglage, Traefik remplace `X-Forwarded-For` par l'adresse de `cloudflared`, et tous les visiteurs partagent un seul compteur de débit.

Pour vérifier en production : dans `docker logs` du conteneur, la première colonne des logs d'accès doit montrer des adresses publiques variées, pas une adresse privée unique.

**Cloudflare en proxy classique (sans tunnel), puis Traefik** : le pair vu par Traefik est un nœud Cloudflare, donc une adresse publique. Il faut faire confiance aux plages Cloudflare publiées (https://www.cloudflare.com/ips/) côté Traefik, sans quoi le Nginx de l'image retient l'adresse du nœud Cloudflare comme adresse du visiteur.

Exemple minimal avec Traefik (labels sur le conteneur) :

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.3omar.rule=Host(`3omar.ma`)"
  - "traefik.http.routers.3omar.entrypoints=websecure"
  - "traefik.http.routers.3omar.tls.certresolver=letsencrypt"
  - "traefik.http.services.3omar.loadbalancer.server.port=80"
```

## En-têtes de sécurité

Deux sources, sans recouvrement :

- `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` et `Referrer-Policy` : posés une seule fois par le Nginx de l'image (`docker/release/nginx.conf`), sur les pages, l'API et les fichiers statiques. Les mêmes en-têtes émis par le middleware `SecurityHeaders` sont masqués (`fastcgi_hide_header`) pour ne pas être envoyés deux fois. Les valeurs Nginx doivent rester celles du middleware : `SecurityHeadersTest` le vérifie.
- `Referrer-Policy` est différenciée (#283, #295) : `no-referrer` sur les pages du calculateur (`/calculateur`, résultats, comparaison, gain 2027, et leurs redirections), dont l'URL porte les montants saisis, et sur le changement de langue (`/lang/...`), dont le paramètre `retour` peut porter l'adresse d'une simulation ; `strict-origin-when-cross-origin` partout ailleurs. Les journaux d'accès de l'image n'enregistrent que le chemin, jamais la chaîne de requête. Nginx choisit la valeur par une `map` sur `$request_uri` (URI d'origine, non réécrite vers `index.php`), reprend sinon la valeur du middleware, et à défaut la valeur éditoriale. La CI vérifie un seul en-tête et sa valeur sur l'image release. Un proxy placé devant l'image ne doit pas réécrire cet en-tête.
- `Content-Security-Policy` et, en HTTPS, `Strict-Transport-Security` : posés par le middleware `SecurityHeaders` sur les pages web.

Le job CI `Build image release` démarre l'image et échoue si l'un des trois en-têtes Nginx manque ou apparaît deux fois sur `/`, `/api/v1/health` ou une image de `public/img/`. Pour vérifier en production :

```bash
curl -sSI https://3omar.ma/ | grep -i -E "frame-options|referrer-policy|content-type-options"
```

Chaque en-tête doit apparaître une seule fois. Un proxy placé devant le conteneur (Traefik, Cloudflare) ne doit pas les ajouter à nouveau.

## Health check

Le conteneur embarque un `HEALTHCHECK` Docker via l'endpoint `/up` de Laravel :

- Intervalle : 30 s
- Période de grâce au démarrage : 30 s
- Tentatives : 3

`docker ps` affiche `(healthy)` dès que l'application répond. Traefik et les orchestrateurs peuvent s'appuyer sur ce statut pour ne pas router avant que le conteneur soit prêt.

## Déboguer en production

Les erreurs Laravel sont redirigées vers **stderr** et apparaissent directement dans `docker logs` :

```bash
docker logs <nom_du_conteneur>
# ou en temps réel
docker logs -f <nom_du_conteneur>
```

Pour voir l'erreur exacte sans accès aux logs, passer temporairement `APP_DEBUG=true` au redémarrage.
