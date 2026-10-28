# 3omar · Le bulletin de paie marocain open source

![Logotype 3omar](public/img/logo_banner_1200x630.png)

3omar est un simulateur pédagogique qui aide à comprendre les principaux calculs d'un salaire marocain. Chaque simulation affiche les taux, assiettes et références réglementaires utilisés. Aucune donnée personnelle n'est collectée.

> **Pourquoi « 3omar » ?** En darija marocaine, عمر (Omar) s'écrit « 3omar », le chiffre 3 dessine le ع.

Les résultats restent une simulation. Pour un bulletin de paie officiel ou une situation particulière, consultez votre employeur ou un professionnel.

Version en ligne : [3omar.ma](https://3omar.ma)

## Fonctionnalités

- **Brut → Net** : CNSS, AMO, CIMR, IR progressif, frais professionnels, charges de famille, prime d'ancienneté, heures supplémentaires, indemnités exonérées.
- **Net → Brut** : reconstitution du salaire de base à partir d'un net à payer cible, avec les mêmes hypothèses que le mode direct.
- **Coût employeur** : cotisations patronales, coût total et détail complet des retenues, avec leurs références réglementaires.
- **Parcours guidé** : saisie par étapes avec récapitulatif en temps réel.
- **Profils prêts à l'emploi** : profils pédagogiques chargeables en un clic, puis librement modifiables.
- **Comparaison de scénarios** : deux simulations côte à côte, avec écarts absolus et relatifs sur le net, l'IR, les cotisations et le coût employeur.
- **Partage par lien** : une simulation ou une comparaison se retrouve et se transmet via son URL, sans stockage serveur.
- **Fiabilité** : la page `/fiabilite` documente chaque règle avec sa source et son niveau de confiance.
- **Guides thématiques** : salaire net, coût employeur, CNSS et AMO, prime d'ancienneté, chacun illustré par un exemple calculé et un lien vers le simulateur prérempli.
- **API REST** : endpoints JSON publics sous `/api/v1/` pour intégrer la simulation dans d'autres applications.
- **Interface** : français, anglais, arabe (RTL) et espagnol, mode sombre automatique ou manuel.

La feuille de route est gérée dans les [issues GitHub](https://github.com/Zakmaf/3omar/issues). Les changements de chaque version sont décrits dans l'[historique des versions](docs/RELEASES.md).

## Démarrage rapide

**Développement :**
```bash
cp .env.example .env && docker compose up -d --build
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor \
  -w /app composer:2 composer install
docker compose exec app php artisan key:generate
# → http://localhost:49173
```

**Production :**
```bash
docker run -d -p 80:80 \
  -e APP_KEY="$(docker run --rm ghcr.io/zakmaf/3omar:latest php artisan key:generate --show)" \
  -e APP_URL=https://votre-domaine.com \
  ghcr.io/zakmaf/3omar:latest
```

→ Voir [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) pour les variables, tags et reverse proxy.

## Documentation

| Document | Audience |
|----------|----------|
| [Développement](docs/DEVELOPPEMENT.md) | Développeurs : setup local, architecture, tests |
| [Déploiement](docs/DEPLOIEMENT.md) | Ops : image Docker, variables, reverse proxy |
| [Règles de calcul](docs/CALCUL.md) | Développeurs & RH : formules, solver net→brut |
| [API REST](docs/API.md) | Développeurs : endpoints, requêtes, réponses |
| [Internationalisation](docs/I18N.md) | Développeurs : conventions de traduction |
| [UX & accessibilité](docs/UX.md) | Product & design : principes, suivi recommandé |
| [Historique des versions](docs/RELEASES.md) | Tous : notes de chaque release |

## Contribution

Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour les conventions de contribution, le format des commits et des release notes.

Les corrections de calcul doivent inclure un scénario de test reproductible (voir [docs/CALCUL.md](docs/CALCUL.md)).
Les changements visuels doivent respecter la [charte de marque](docs/charte-graphique-editoriale.html).
Pour signaler une vulnérabilité, suivre la procédure décrite dans [SECURITY.md](SECURITY.md).

## Licence

Le code est distribué sous licence [MIT](LICENSE).
Le nom « 3omar », les logos et la charte graphique sont soumis à des conditions distinctes : voir [LICENSE-ASSETS.md](LICENSE-ASSETS.md).
