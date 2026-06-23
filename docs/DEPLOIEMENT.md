# Déploiement

L'image de production est un conteneur autonome (PHP-FPM + Nginx + Supervisor) publiée sur GitHub Container Registry à chaque release GitHub depuis `docker/release/Dockerfile`.

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
| `ADSENSE_CLIENT` | Si AdSense | Identifiant client AdSense (`ca-pub-xxx`). |
| `ADSENSE_PUBLISHER_ID` | Si AdSense | Identifiant editeur pour le fichier `ads.txt` (`pub-xxx`). |
| `ADSENSE_SLOT_HEADER` | Si AdSense | ID du slot publicitaire du header. |
| `ADSENSE_SLOT_FOOTER` | Si AdSense | ID du slot publicitaire du footer. |

## Tags disponibles

| Tag | Usage recommandé |
|-----|-----------------|
| `latest` | Dernière version stable — mise à jour automatique |
| `v2` | Majeure 2.x.x — suit les mises à jour mineures et correctifs |
| `v2.2` | Mineure 2.2.x — correctifs uniquement |
| `v2.2.1` | Version exacte — reproductible, recommandé pour la production |

```bash
docker pull ghcr.io/zakmaf/3omar:v2.2.1
```

## Reverse proxy (Traefik, Nginx…)

Le conteneur écoute sur le port **80**. Il fait confiance aux en-têtes `X-Forwarded-*` transmis par n'importe quel proxy amont (`TrustProxies` activé côté Laravel). Passer `APP_URL` avec le schéma `https://` suffit pour que les URL générées et les cookies soient cohérents.

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
