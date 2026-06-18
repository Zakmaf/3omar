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
