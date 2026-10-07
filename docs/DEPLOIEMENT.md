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
| `ADSENSE_ENABLED` | Non | `false` par défaut. Mettre à `true` pour activer Google AdSense (necessite les variables ci-dessous). |
| `ADSENSE_PUBLISHER_ID` | Si AdSense | Identifiant editeur AdSense (`pub-xxx`). Sert au fichier `ads.txt`, a la balise meta de verification et au script (client `ca-pub-xxx` derive automatiquement). |
| `ADSENSE_SLOT_HEADER` | Si AdSense | ID du slot publicitaire du header. |
| `ADSENSE_SLOT_FOOTER` | Si AdSense | ID du slot publicitaire du footer. |

## Tags disponibles

| Tag | Usage recommandé |
|-----|-----------------|
| `latest` | Dernière version stable — mise à jour automatique |
| `v3` | Majeure 3.x.x — suit les mises à jour mineures et correctifs |
| `v3.4` | Mineure 3.4.x - fonctionnalités et correctifs de la série 3.4 |
| `v3.4.0` | Version exacte - reproductible, recommandé pour la production |

```bash
docker pull ghcr.io/zakmaf/3omar:v3.4.0
```

## Reverse proxy (Traefik, Nginx…)

Le conteneur écoute sur le port **80**. Passer `APP_URL` avec le schéma `https://`.

### Adresse réelle et HTTPS

L'adresse du visiteur sert de clé aux limiteurs de débit (30 calculs et 60 appels API par minute). Elle ne doit donc jamais venir d'un en-tête que le client peut écrire lui-même.

Dans l'image de release, c'est le Nginx embarqué qui résout l'adresse réelle :

- Il lit `X-Forwarded-For` uniquement depuis les plages privées `10.0.0.0/8`, `172.16.0.0/12` et `192.168.0.0/16` (`set_real_ip_from` dans `docker/release/nginx.conf`).
- Il transmet `HTTPS=on` à PHP-FPM seulement si ce même pair privé annonce `X-Forwarded-Proto: https`. Un client public qui envoie cet en-tête n'est pas cru.
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
