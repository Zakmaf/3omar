# Conventions de contribution

Ces règles s'appliquent à tous les contributeurs : humains, Claude et Codex. Les règles communes à tous les projets (flux issue, branche, PR, interdits, modèles) sont dans le bloc commun d'[AGENTS.md](AGENTS.md), issu du standard Zakmaf/engineering ; ce fichier ne les contredit pas.

---

## Branches

Format : `<type>/<issue>-<sujet>`, par exemple `fix/214-calculateur-resumes-traduits`.

| Type | Usage |
|---------|-------|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correction de bug |
| `docs` | Documentation uniquement |
| `test` | Tests uniquement |
| `ci` | Intégration continue |
| `chore` | Outillage, dépendances |
| `refactor` | Restructuration sans changement de comportement |
| `feature/v<X.Y>-<sujet>` | Branche de version mineure (ex: `feature/v1.1-net-to-gross`) |

- Toujours partir de `main` à jour.
- Une branche = un sujet. Ne pas mélanger fonctionnalité et refactoring.
- La branche est supprimée après fusion.

## Collaboration multi-agents

Les règles de coordination (une issue = une branche = un sujet = un writer, jamais de travail sur `main`, préflight, PR en brouillon jusqu'au check `gate` vert, compte rendu avec résultats réels) sont dans [AGENTS.md](AGENTS.md). Spécifique à 3omar :

- Si un autre intervenant modifie déjà un fichier central (`app/Services/PayrollCalculatorService.php`, `config/payroll.php`, `README.md`, vues principales du calculateur), attendre ou se coordonner avant de le toucher.
- Merge rapide des petits sujets terminés pour réduire les conflits de dérive.

## Commits

Format : `type(scope): description courte en français`

```
feat(calcul): ajoute le solver net → brut
fix(prod): corrige les permissions du cache Docker
docs(i18n): ajoute les clés V1.1 dans les quatre langues
chore(ci): met à jour actions/checkout vers v4
```

Types acceptés : `feat`, `fix`, `docs`, `test`, `ci`, `chore`, `refactor`.  
Message en français, impératif présent, pas de point final.

## Pull requests

- Titre = message de commit principal (même format).
- Description : le compte rendu du gabarit de PR (ce qui change, pourquoi, vérifications réelles, risques résiduels).
- Une PR = un sujet. Pas de PR fourre-tout.
- Toute PR touchant `app/Services/PayrollCalculatorService.php` doit inclure ou mettre à jour des tests unitaires.
- Toute PR ajoutant une interface doit ajouter les clés i18n dans les **quatre** langues.
- Une PR d'agent reste en brouillon tant que le check `gate` n'est pas vert.
- La fusion est réservée au propriétaire du dépôt : le ruleset `protect-main` exige le check `gate` vert et une approbation. Relecture attentive obligatoire si la PR modifie le moteur de calcul ou le Dockerfile de production.

## Releases

- Versionnement sémantique, selon le standard commun (Zakmaf/engineering, section 8). Deux sortes de release seulement :
  - **stable** `vMAJEUR.MINEUR.PATCH` : release GitHub marquée « Latest » ;
  - **pré-release de développement** `vX.Y.Z-dev.N` : `X.Y.Z` est la prochaine version prévue, `N` part de 1 et augmente à chaque build (`v3.7.0-dev.1`, `v3.7.0-dev.2`, puis `v3.7.0`). Release GitHub marquée pré-release.

  Aucun autre suffixe (`-rc`, `-beta`, `-nightly`) : le workflow de publication refuse tout autre tag.
- La release GitHub déclenche automatiquement le build et le push de l'image Docker sur GHCR.
- Toujours créer la release depuis `main` après merge.
- Mettre à jour la table des tags dans [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) lors d'une nouvelle mineure.
- **Mettre à jour le numéro de version** dans `config/app.php` (`'version' => env('APP_VERSION', 'VX.Y.Z')`) **avant chaque release stable**. L'image publiée annonce de toute façon le tag de sa release (pied de page et `/api/v1/health`) : le workflow le passe au build. La valeur de `config/app.php` sert hors release et au contrat OpenAPI.
- **Approbation** : aucun agent ne crée ni ne publie de release sans l'accord du propriétaire. Ce qui vaut accord :
  - **release stable** : l'accord explicite du propriétaire, qui couvre toute la chaîne (PR de préparation, puis publication) ;
  - **pré-release de développement** : la demande du propriétaire ;
  - **correctif de sécurité** : la fusion par le propriétaire d'une PR étiquetée `security` (voir plus bas).

  Seul Daemon publie, sous le compte machine `zakmaf-bot` ; les workers (Claude Code, Codex) jamais. Aucun tag poussé à la main (`git tag`, `git push origin vX.Y.Z`) : le tag naît de la release GitHub. Le workflow de publication n'a pas de déclenchement manuel. Le déploiement d'une instance externe reste une action du propriétaire ou de l'opérateur, hors du dépôt et hors CI.

### États de publication

Une release traverse quatre états distincts. Ne pas confondre "code prêt" et "en production" : chaque état doit être vérifié séparément, jamais supposé à partir du précédent.

1. **Source préparée** : le code est mergé sur `main`, la version est mise à jour dans `config/app.php`, une entrée est ajoutée dans [docs/RELEASES.md](docs/RELEASES.md). Rien n'est publié.
2. **Release publiée** : une release GitHub existe et est publiée. Ceci déclenche automatiquement le workflow `docker-release.yml`.
3. **Image GHCR** : l'image a été construite et poussée sur `ghcr.io/zakmaf/3omar`. Release stable : tags `latest` / `vMAJEUR` / `vMAJEUR.MINEUR` / `vMAJEUR.MINEUR.PATCH`. Pré-release : le tag exact `vX.Y.Z-dev.N` et l'étiquette mobile `dev`, rien d'autre. Se vérifie avec `gh run list --workflow=docker-release.yml --limit 1`.
4. **Production déployée** : une instance externe exécute effectivement l'image publiée. C'est une action séparée, hors du dépôt et hors CI, à confirmer explicitement par le propriétaire ou un opérateur autorisé.

Voir aussi [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) pour le détail du déploiement de l'image publiée.

### Checklist pré-release

Les tests de release se font uniquement sur GitHub : CI, workflow de publication et image publiée. Aucun build Docker ni conteneur local n'est requis. Chaque point se vérifie avec la commande réelle exécutée et son résultat réel rapporté. Ne jamais déclarer un point vérifié sans l'avoir exécuté.

Avant publication :

- [ ] CI GitHub verte sur la PR de préparation et sur `main` : `gh pr checks <numéro>`, `gh run list --branch main --limit 1`. Les checks du workflow `CI` couvrent la suite PHPUnit sur la version de PHP des images Docker, `pint --test`, `composer audit --locked` et le build de l'image release (conteneur `healthy`, `/up` et `/api/v1/health` en HTTP 200)
- [ ] Laravel Pint sans erreur : check `Laravel Pint` vert
- [ ] `docs/RELEASES.md` contient une entrée conforme au format documenté pour la version

Après publication, sur GitHub et le registre :

- [ ] Workflow `docker-release.yml` terminé en succès : `gh run list --workflow=docker-release.yml --limit 1`. Il construit l'image de production : un échec ne pousse aucune image
- [ ] Tags `latest`, `vMAJEUR`, `vMAJEUR.MINEUR` et `vMAJEUR.MINEUR.PATCH` pointant vers le même digest sur `ghcr.io/zakmaf/3omar`

Après déploiement, sur l'instance :

- [ ] Réponse HTTP 200 sur la page d'accueil
- [ ] Version exposée par `/api/v1/health` correspond à la version de la release
- [ ] Confidentialité des logs vérifiée : les journaux d'accès ne journalisent pas la chaîne de requête ni le référent (les liens de simulation/partage embarquent des données dans l'URL)

### Pré-releases de développement

Elles servent à tester un état de `main` avant une release stable.

- **Cadence** : à la demande du propriétaire uniquement, jamais planifiée ni à chaque fusion, pour que l'image en test ne change pas pendant le test. Daemon en propose une quand `main` a accumulé des changements visibles par l'utilisateur depuis la release précédente (nouveautés, correctifs ; pas la CI ni les dépendances), sans jamais la publier de lui-même.
- **Publication** : depuis `main`, sur un commit dont `gate` est vert, sans PR de préparation ni entrée dans [docs/RELEASES.md](docs/RELEASES.md) ; notes générées par GitHub :

  ```bash
  gh release create v3.7.0-dev.1 --target <sha> --prerelease --generate-notes
  ```

- **Image** : le tag exact et l'étiquette mobile `dev`. Ni `latest`, ni `vMAJEUR`, ni `vMAJEUR.MINEUR` ; la release GitHub n'est jamais « Latest ». Vérifier après publication que `latest`, `vMAJEUR` et `vMAJEUR.MINEUR` gardent le digest de la dernière stable.
- L'environnement de test et le déploiement des pré-releases relèvent du propriétaire et de l'infrastructure.

### Correctif de sécurité : release immédiate

- Concerne tout correctif d'une vulnérabilité dans ce qui est livré : code de l'application, runtime, image de base, dépendances de production. Une dépendance de développement ou de CI (outil de test, action) ne déclenche pas de release : elle part avec la suivante.
- La PR porte l'étiquette `security`. Sa fusion vaut accord de release : la PR de préparation est ouverte aussitôt (son approbation reste exigée par `protect-main`), puis la release est publiée dès que `main` est vert, sans nouvelle demande.
- Version : PATCH, ou MINEUR si `main` contient déjà des nouveautés non publiées. Section `## Sécurité` dans les release notes. Plusieurs correctifs fusionnés le même jour partent dans une seule release.
- Conséquence : `main` reste publiable à tout moment. On n'y fusionne pas de travail inachevé.

### Rédaction des release notes

Les notes de version sont la vitrine du projet. Elles doivent être lisibles par un utilisateur non technique, informatives pour un contributeur, et exploitables par un opérateur.

**Structure obligatoire** (chaque section est omise si vide) :

```markdown
## Nouveautés
- Description de chaque fonctionnalité ajoutée, une ligne par item

## Améliorations
- Optimisations, refactors visibles, gains de performance

## Correctifs
- Bugs corrigés avec le symptôme observable (pas le détail technique interne)

## Sécurité
- Changements liés à la sécurité (rate limiting, headers, correctifs de vulnérabilité)

## Mise à jour de la stack
- Montées de version de dépendances, images Docker, actions CI

## Migration
Instructions de migration, ou "Aucune action requise" si transparent.
```

**Règles de rédaction** :

1. **Langue** : français. Le titre de la release peut inclure un sous-titre court en anglais si c'est un nom de thème (`v1.5.0 : Polish & DX`).
2. **Pas de tiret cadratin** (`—`). Utiliser le tiret simple (`-`) ou reformuler.
3. **Une ligne = un changement**. Commencer par un verbe d'action au présent (`Ajoute`, `Corrige`, `Passe`, `Supprime`) ou par le nom de la fonctionnalité.
4. **Référencer les issues** : mentionner `#XX` en fin de ligne quand une issue GitHub existe.
5. **Quantifier les résultats** quand c'est pertinent : taille d'image, nombre de tests, pourcentage de réduction.
6. **Ne pas exposer les détails d'implémentation**. L'utilisateur se moque du nom des fonctions ou des fichiers modifiés. Décrire l'effet visible.
7. **Section Migration** : toujours présente. Indiquer la commande `docker pull` et les éventuelles actions manuelles requises (variables d'environnement, migrations, etc.).
8. **Pas de section vide**. Si une catégorie n'a rien, ne pas l'inclure.
9. **Pas d'émojis** dans les notes de release.
10. **Relecture** : avant publication, vérifier que chaque item répond à "qu'est-ce qui change pour l'utilisateur ou l'opérateur ?".

**Exemple** :

```markdown
## Nouveautés
- Affichage systématique de l'unité MAD/mois sur tous les champs monétaires et résultats #45
- Sélecteur de langue fonctionnel pour les 4 locales (FR, EN, AR, ES) avec support RTL #26

## Améliorations
- Image Docker de production réduite de 367 Mo a 113 Mo (passage Alpine + suppression des extensions inutiles) #90
- Traductions complètes EN/AR/ES pour les vues calculateur, résultat et documentation

## Migration
Aucune action requise. Mise a jour transparente depuis v1.4.0.

    docker pull ghcr.io/zakmaf/3omar:v1.5.0
```

## Documentation

### Quoi documenter où

| Sujet | Fichier |
|-------|---------|
| Présentation produit, fonctionnalités, quickstart | `README.md` |
| Setup local, architecture, commandes dev | `docs/DEVELOPPEMENT.md` |
| Image Docker, variables, reverse proxy, healthcheck | `docs/DEPLOIEMENT.md` |
| Formules de paie, hypothèses, solver net→brut | `docs/CALCUL.md` |
| Clés i18n, conventions de traduction | `docs/I18N.md` |
| Principes UX, accessibilité, suivi | `docs/UX.md` |

### Règles de documentation

- Le `README.md` reste court : intro produit, fonctionnalités, quickstart minimal, tableau de liens, contribution, licence. Pas de détail technique.
- Le `README.md` ne contient aucune information datée : pas de section « Nouveautés vX.Y.Z », pas de numéro de version de l'application, pas de compteur (tests, profils), pas de référence d'issue. L'historique des changements vit dans `docs/RELEASES.md`, la feuille de route dans les issues GitHub.
- Chaque nouvelle fonctionnalité doit mettre à jour le doc correspondant **dans la même PR**. Le `README.md` n'est touché que si la liste des fonctionnalités change.
- Ne pas dupliquer le contenu entre fichiers. Lier plutôt que copier.
- Les valeurs chiffrées (taux, plafonds) viennent de `config/payroll.php`, jamais des docs.
- Un doc technique doit décrire ce qui est **implémenté**, pas ce qui était prévu.

## Calcul et règles métier

- Source unique des paramètres : `config/payroll.php`. Toute valeur réglementaire vit ici.
- Toute modification de formule → mettre à jour `docs/CALCUL.md` et ajouter un test de limite.
- Ne jamais dupliquer un taux dans une vue ou un test. Utiliser `config('payroll.*')`.
- Les nouvelles règles doivent préciser leur source légale ou leur statut d'hypothèse en commentaire PHP.

## Docker et CI

- Le workflow `.github/workflows/ci.yml` tourne sur chaque PR vers `main`, chaque push sur `main` et chaque jour sur `main` (`schedule`, 03:17 UTC). Il produit les checks suivants :
  - `Tests PHPUnit` : la suite complète, exécutée dans l'image de développement (`docker/php/Dockerfile`), donc sur la version de PHP livrée en production. Une application livrée en image ne teste que la version qu'elle embarque : pas de matrice de versions, `composer.json` déclare cette même version ;
  - `Tests navigateur` : les contrats Playwright et axe de `tests/browser/contracts` contre le dépôt servi dans un conteneur, avec les étapes de [docs/DEVELOPPEMENT.md](docs/DEVELOPPEMENT.md#tests-navigateur-playwright--axe). Rapport et traces publiés en artefact en cas d'échec ;
  - `Laravel Pint` : `vendor/bin/pint --test` ;
  - `Composer audit` : `composer audit --locked` contre les avis de sécurité connus ;
  - `Build image release` : construit `docker/release/Dockerfile` sans le pousser avec une version de test (`APP_VERSION=v0.0.0-dev.1`), vérifie qu'une version hors convention fait échouer le build, démarre le conteneur avec une `APP_KEY` jetable, attend le `HEALTHCHECK`, exige HTTP 200 sur `/up` et `/api/v1/health` et que ce dernier annonce la version de test, puis vérifie que `X-Content-Type-Options`, `X-Frame-Options` (valeur `DENY`) et `Referrer-Policy` sont envoyés une seule fois sur `/`, `/api/v1/health` et une image de `public/img/`, et que, derrière un proxy de confiance, `X-Forwarded-Proto: http` donne un `301` vers `https://` alors que `X-Forwarded-Proto: https` et l'absence d'en-tête donnent 200 ;
  - `Style rédactionnel` : échoue si une ligne ajoutée ou modifiée (diff de la PR contre sa base, ou du push contre le commit précédent de `main`) contient un tiret cadratin (U+2014) ou un emoji, et annote le fichier et la ligne. Les lignes existantes non touchées ne sont pas contrôlées : modifier une ligne qui en contient déjà un oblige à le remplacer. Exceptions : `composer.lock`, `package-lock.json` (générés) et, dans `AGENTS.md`, `CLAUDE.md` et `CONTRIBUTING.md`, les lignes qui énoncent la règle. Même vérification en local avant de pousser : `bash .github/scripts/style-redactionnel.sh origin/main` ;
  - `gate` : agrégat, vert seulement si tous les jobs ci-dessus sont verts. C'est le seul check exigé par le ruleset `protect-main` : tout nouveau job s'ajoute à ses `needs`, sans toucher au ruleset ; ne pas renommer `gate`.
- Le run quotidien réexécute le workflow complet sur `main` sans nouveau commit : un avis de sécurité publié après la dernière fusion, ou une dérive des images et des téléchargements, rend `main` rouge sans attendre une PR. Il a son propre groupe de concurrence et ne s'annule pas avec un run de push ; `Style rédactionnel` n'y contrôle aucune ligne. Santé de `main` : `gh run list --workflow ci.yml --branch main`. Run quotidien rouge sur un avis de sécurité : ouvrir une issue étiquetée `security`, corriger dans une PR étiquetée `security`, puis release immédiate selon [Correctif de sécurité : release immédiate](#correctif-de-sécurité--release-immédiate), avec son entrée dans [docs/RELEASES.md](docs/RELEASES.md). Un avis sur une dépendance de développement ou de CI se corrige sans release, comme indiqué dans cette même section.
- Les actions sont épinglées par SHA complet, la version en commentaire ; Dependabot les met à jour. Le jeton des workflows est en lecture seule sauf permission déclarée par le job.
- L'image de développement (`docker/php/Dockerfile`) et l'image de production (`docker/release/Dockerfile`) sont indépendantes. Ne pas mélanger leurs configurations.
- Toute modification de `docker/release/` est vérifiée par le check `Build image release` de sa PR. Un test local (`docker build` + `docker run` + vérification HTTP 200) reste possible, sans être exigé. Si la modification change un comportement que ce job ne vérifie pas encore, ajouter la vérification au job dans la même PR.
- Le workflow `.github/workflows/docker-release.yml` se déclenche uniquement sur release GitHub publiée, selon l'approbation décrite dans [Releases](#releases). Il vérifie d'abord le couple tag / type de release (`vX.Y.Z` stable, `vX.Y.Z-dev.N` pré-release), puis passe le tag au build (`APP_VERSION`). Pas de `workflow_dispatch` : sans contexte de release, la règle `latest` s'évaluerait vraie. Ne pas le modifier sans en comprendre les effets sur GHCR.
