# 3omar · Le bulletin de paie Marocain open source

![Logotype 3omar](public/img/logo_banner_1200x630.png)

3omar est un simulateur pédagogique qui aide à comprendre les principaux calculs d'un salaire marocain. Chaque simulation affiche les taux, assiettes et références réglementaires utilisés. Aucune donnée personnelle n'est collectée.

> **Pourquoi « 3omar » ?** En darija marocaine, عمر (Omar) s'écrit « 3omar », le chiffre 3 dessine le ع.

3omar reste une simulation pédagogique. Pour un bulletin officiel ou une situation particulière, consultez votre employeur ou un professionnel.

## Fonctionnalités

- **Brut → Net** : CNSS, AMO, CIMR, IR progressif, frais professionnels, charges de famille, retraite complémentaire, prime d'ancienneté, heures supplémentaires, indemnités exonérées.
- **Net → Brut** *(V1.1)* : reconstitution du salaire de base à partir d'un net à payer cible — mêmes hypothèses disponibles que le mode direct.
- Coût total employeur, détail complet des retenues, affichage des références réglementaires.
- Interface disponible en français, anglais, arabe (RTL) et espagnol.

## Backlog produit

Le backlog ci-dessous sert à rendre visibles les fonctions à venir. Lorsqu'une fonctionnalité est livrée, elle doit être **barrée** dans cette liste au moment de la PR ou du merge correspondant, afin de garder un historique lisible de l'avancement.

### V1.3

- [ ] Séparer explicitement les simulations **mensuelles** et **annuelles** avec 4 parcours dédiés : `brut → net` et `net → brut` pour chaque période ([#44](https://github.com/Zakmaf/3omar/issues/44)).
- [ ] Afficher l'unité courante partout dans le simulateur et les résultats : `MAD/mois` ou `MAD/an` ([#45](https://github.com/Zakmaf/3omar/issues/45)).
- [ ] Ajouter des golden tests sur des bulletins complets de référence pour sécuriser les nouveaux parcours ([#46](https://github.com/Zakmaf/3omar/issues/46)).
- [ ] Ajouter un mode employeur centré sur le **coût total employeur** ([#48](https://github.com/Zakmaf/3omar/issues/48)).
- [ ] Ajouter un export PDF propre, lisible et partageable de la simulation ([#49](https://github.com/Zakmaf/3omar/issues/49)).

### V2.0

- [ ] Ajouter une comparaison de scénarios de paie côte à côte ([#47](https://github.com/Zakmaf/3omar/issues/47)).
- [ ] Sauvegarder temporairement et partager une simulation via URL compacte ou stockage local ([#50](https://github.com/Zakmaf/3omar/issues/50)).
- [ ] Ajouter des profils de simulation prêts à l'emploi : `SMIG`, salarié standard, cadre, journaliste, avec CIMR, avec primes ([#51](https://github.com/Zakmaf/3omar/issues/51)).

### Livrés en V1.2

- [x] ~~CIMR : saisie libre du taux + choix de la prise en charge (salarié, employeur, partagé)~~
- [x] ~~Avantages CNSS exonérés : prime de scolarité, prime des Aïd, autres (imposables IR, exclus CNSS/AMO)~~
- [x] ~~Retraite complémentaire : part employeur distincte~~
- [x] ~~Montée Laravel 12 — correctif CVE-2026-48019~~

### En continu

- [ ] Protéger `POST /calculateur/calculer` contre le déni de service applicatif ([#42](https://github.com/Zakmaf/3omar/issues/42)).
- [ ] Ajouter une `Content-Security-Policy` et les en-têtes de sécurité navigateur ([#43](https://github.com/Zakmaf/3omar/issues/43)).
- [ ] Traduire exhaustivement les libellés métier et détails réglementaires ([#17](https://github.com/Zakmaf/3omar/issues/17)).
- [ ] Tester les parcours mobiles et automatiser l'accessibilité ([#16](https://github.com/Zakmaf/3omar/issues/16)).

## Documentation

| Document | Audience |
|----------|----------|
| [Développement](docs/DEVELOPPEMENT.md) | Développeurs — setup local, architecture, tests |
| [Déploiement](docs/DEPLOIEMENT.md) | Ops — image Docker, variables, reverse proxy |
| [Règles de calcul](docs/CALCUL.md) | Développeurs & RH — formules, solver net→brut |
| [Internationalisation](docs/I18N.md) | Développeurs — conventions de traduction |
| [UX & accessibilité](docs/UX.md) | Product & design — principes, suivi recommandé |

## Démarrage rapide

**Développement :**
```bash
cp .env.example .env && docker compose up -d --build
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor \
  -w /app composer:2.7 composer install
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

## Contribution

Les corrections de calcul doivent inclure un scénario de test reproductible (voir [docs/CALCUL.md](docs/CALCUL.md)).  
Les changements visuels doivent respecter la [charte de marque](charte-graphique-editoriale.html).

## Licence

Le code est distribué sous licence [MIT](LICENSE).  
Le nom « 3omar », les logos et la charte graphique sont soumis à des conditions distinctes : voir [LICENSE-ASSETS.md](LICENSE-ASSETS.md).
