# 3omar · Le bulletin de paie marocain open source

![Logotype 3omar](public/img/logo_banner_1200x630.png)

3omar est un simulateur pedagogique qui aide a comprendre les principaux calculs d'un salaire marocain. Chaque simulation affiche les taux, assiettes et references reglementaires utilises. Aucune donnee personnelle n'est collectee.

> **Pourquoi « 3omar » ?** En darija marocaine, عمر (Omar) s'ecrit « 3omar », le chiffre 3 dessine le ع.

3omar reste une simulation de bulletin de paie pedagogique. Pour un bulletin de paie officiel ou une situation particuliere, consultez votre employeur ou un professionnel.

## V3 (en cours - branche feature/v3-product-upgrade)

Les changements ci-dessous sont en cours de validation sur la branche `feature/v3-product-upgrade` et ne constituent pas encore une release officielle.

### Nouveautes V3

- **Page Fiabilite** (`/fiabilite`) : confidentialite, limites, open source, matrice de fiabilite des regles avec source, date de verification et niveau de confiance pour chaque taux.
- **Positionnement par persona sur la page d'accueil** : cartes dediees aux salaries, RH/paie, developpeurs/integrateurs et decideurs/employeurs, chacune avec un CTA contextuel.
- **Panneau de confiance avant simulation** : resume de ce que le calculateur va produire, engagement de confidentialite, lien vers les limites, avant de saisir le formulaire.
- **Diagnostic actionnable sur la page de resultat** : cartes de ratios (taux effectif global, ratio net/brut, surcout patronal), bandeau de confidentialite, section "Et maintenant ?" avec CTAs vers une nouvelle simulation, la page de fiabilite et la documentation.
- **Lien Fiabilite dans la navigation et le pied de page** : accessible depuis chaque page.
- **CTA contextuel enrichi** : navigation coherente entre accueil, calculateur, resultat, documentation, API et page de fiabilite.
- **Coherence dark mode** : tous les nouveaux elements respectent les tokens CSS existants.
- **Points cles dynamiques sur le resultat** : apres les cartes de diagnostic, une section "Points cles" affiche des insights contextuels (IR non prelevé, plafond CNSS atteint, suggestion CIMR, valeurs employeur manquantes).
- **Action Imprimer dans les etapes suivantes** : le resultat propose directement l'impression en plus des liens vers le simulateur, la documentation, la fiabilite et l'API.
- **Couverture de tests** : 9 nouveaux tests de fumee (trust page, personas, bandeau, panneau preview, verdict, CTAs, takeaways, IR nul, impression).

## Fonctionnalites

- **Brut → Net** : CNSS, AMO, CIMR, IR progressif, frais professionnels, charges de famille, retraite complementaire, prime d'anciennete, heures supplementaires, indemnites exonerees.
- **Net → Brut** : reconstitution du salaire de base a partir d'un net a payer cible, memes hypotheses disponibles que le mode direct.
- **API REST** : endpoints JSON publics pour integrer la simulation dans des applications tierces (`/api/v1/`).
- **Parcours guide** : progression visuelle par etapes avec recapitulatif en temps reel des saisies.
- **Mode sombre** : detection automatique de la preference systeme, toggle dans la navbar, persistance du choix.
- Cout total employeur, detail complet des retenues, affichage des references reglementaires.
- Interface disponible en francais, anglais, arabe (RTL) et espagnol.
- **Fiabilite** : page `/fiabilite` documentant chaque regle avec source et niveau de confiance.

La feuille de route est geree dans les [issues GitHub](https://github.com/Zakmaf/3omar/issues).

## Demarrage rapide

**Developpement :**
```bash
cp .env.example .env && docker compose up -d --build
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor \
  -w /app composer:2.10 composer install
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
| [Developpement](docs/DEVELOPPEMENT.md) | Developpeurs : setup local, architecture, tests |
| [Deploiement](docs/DEPLOIEMENT.md) | Ops : image Docker, variables, reverse proxy |
| [Regles de calcul](docs/CALCUL.md) | Developpeurs & RH : formules, solver net→brut |
| [API REST](docs/API.md) | Developpeurs : endpoints, requetes, reponses |
| [Internationalisation](docs/I18N.md) | Developpeurs : conventions de traduction |
| [UX & accessibilite](docs/UX.md) | Product & design : principes, suivi recommande |
| [Historique des versions](docs/RELEASES.md) | Tous : changelog par release |

## Contribution

Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour les conventions de contribution, le format des commits et des release notes.

Les corrections de calcul doivent inclure un scenario de test reproductible (voir [docs/CALCUL.md](docs/CALCUL.md)).
Les changements visuels doivent respecter la [charte de marque](docs/charte-graphique-editoriale.html).
Pour signaler une vulnerabilite, suivre la procedure decrite dans [SECURITY.md](SECURITY.md).

## Licence

Le code est distribue sous licence [MIT](LICENSE).
Le nom « 3omar », les logos et la charte graphique sont soumis a des conditions distinctes : voir [LICENSE-ASSETS.md](LICENSE-ASSETS.md).
