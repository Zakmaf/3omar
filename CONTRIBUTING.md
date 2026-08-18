# Conventions de contribution

Ces règles s'appliquent à tous les contributeurs : humains, Claude et Codex.

---

## Branches

| Préfixe | Usage |
|---------|-------|
| `feat/<sujet>` | Nouvelle fonctionnalité |
| `fix/<sujet>` | Correction de bug |
| `docs/<sujet>` | Documentation uniquement |
| `chore/<sujet>` | Outillage, CI, dépendances |
| `feature/v<X.Y>-<sujet>` | Branche de version mineure (ex: `feature/v1.1-net-to-gross`) |

- Toujours partir de `main` à jour.
- Une branche = un sujet. Ne pas mélanger fonctionnalité et refactoring.
- Supprimer la branche après merge.

## Collaboration multi-agents

Ces règles sont obligatoires quand plusieurs intervenants travaillent en parallèle sur le dépôt.

- `main` reste la branche stable de référence : ne pas y travailler directement.
- Une issue = une branche = un sujet = un agent.
- Ne jamais faire travailler deux agents sur la même branche.
- Ne jamais réutiliser une branche déjà active pour un autre sujet.
- Toute branche de travail doit référencer un numéro d'issue quand il existe.
- Avant de commencer, vérifier `git status` et signaler immédiatement tout changement inattendu.
- Si un autre agent modifie déjà un fichier central (`app/Services/PayrollCalculatorService.php`, `config/payroll.php`, `README.md`, vues principales), ouvrir une branche séparée et éviter le travail concurrent sur le même fichier.
- Pas de refactor opportuniste hors périmètre du ticket traité.
- Toute livraison doit annoncer clairement les fichiers touchés et les commandes de vérification exécutées.
- Merge rapide des petits sujets terminés pour réduire les conflits de dérive.

## Commits

Format : `type(scope): description courte en français`

```
feat(calcul): ajoute le solver net → brut
fix(prod): corrige les permissions du cache Docker
docs(i18n): ajoute les clés V1.1 dans les quatre langues
chore(ci): met à jour actions/checkout vers v4
```

Types acceptés : `feat`, `fix`, `docs`, `chore`, `test`, `refactor`.  
Message en français, impératif présent, pas de point final.

## Pull requests

- Titre = message de commit principal (même format).
- Description : ce qui change, pourquoi, et comment tester.
- Une PR = un sujet. Pas de PR fourre-tout.
- Toute PR touchant `app/Services/PayrollCalculatorService.php` doit inclure ou mettre à jour des tests unitaires.
- Toute PR ajoutant une interface doit ajouter les clés i18n dans les **quatre** langues.
- Faire merger par l'auteur une fois les checks verts. Pas de merge sans review si la PR modifie le moteur de calcul ou le Dockerfile de production.

## Releases

- Versionnement sémantique : `vMAJEUR.MINEUR.PATCH`.
- La release GitHub déclenche automatiquement le build et le push de l'image Docker sur GHCR.
- Toujours créer la release depuis `main` après merge.
- Mettre à jour la table des tags dans [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) lors d'une nouvelle mineure.
- **Mettre à jour le numéro de version** dans `config/app.php` (`'version' => env('APP_VERSION', 'VX.Y.Z')`) **avant chaque release**. Ce numéro est affiché dans le footer du site.
- **Approbation obligatoire** : aucun agent (Claude, Codex, etc.) ne peut réaliser, sans l'accord explicite et documenté du propriétaire du dépôt, l'une des actions suivantes :
  - créer ou publier une release GitHub (`gh release create`, ou publication d'un brouillon de release existant) ;
  - créer ou pousser un tag Git, local ou distant (`git tag vX.Y.Z`, `git push origin vX.Y.Z`) ;
  - déclencher manuellement le workflow de publication (`workflow_dispatch` sur `.github/workflows/docker-release.yml`, via `gh workflow run` ou l'interface GitHub) ;
  - déployer ou mettre à jour une instance de production externe (hors du dépôt, hors CI).

  Demander confirmation explicite avant chacune de ces actions, même si un pas précédent a déjà été approuvé.

### États de publication

Une release traverse quatre états distincts. Ne pas confondre "code prêt" et "en production" : chaque état doit être vérifié séparément, jamais supposé à partir du précédent.

1. **Source préparée** : le code est mergé sur `main`, la version est mise à jour dans `config/app.php`, une entrée est ajoutée dans [docs/RELEASES.md](docs/RELEASES.md). Rien n'est publié.
2. **Release publiée** : une release GitHub existe et est publiée. Ceci déclenche automatiquement le workflow `docker-release.yml`.
3. **Image GHCR** : l'image a été construite et poussée sur `ghcr.io/zakmaf/3omar` avec les tags `latest` / `vMAJEUR` / `vMAJEUR.MINEUR` / `vMAJEUR.MINEUR.PATCH`. Se vérifie avec `gh run list --workflow=docker-release.yml --limit 1`.
4. **Production déployée** : une instance externe exécute effectivement l'image publiée. C'est une action séparée, hors du dépôt et hors CI, à confirmer explicitement par le propriétaire ou un opérateur autorisé.

Voir aussi [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) pour le détail du déploiement de l'image publiée.

### Checklist pré-release

Avant de proposer la publication d'une release, vérifier chacun des points suivants avec la commande réelle exécutée et son résultat réel rapporté. Ne jamais déclarer un point vérifié sans l'avoir exécuté :

- [ ] Suite PHPUnit complète verte : `vendor/bin/phpunit`
- [ ] Laravel Pint sans erreur en mode vérification : `vendor/bin/pint --test`
- [ ] Image Docker de production construite avec succès : `docker build -f docker/release/Dockerfile .`
- [ ] Configuration Nginx validée : `nginx -t`
- [ ] Conteneur démarré et rapporté `(healthy)` par `docker ps` (HEALTHCHECK `/up`, voir [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md))
- [ ] Réponse HTTP 200 sur la page d'accueil
- [ ] Version exposée par `/api/v1/health` correspond à la version de la release
- [ ] `docs/RELEASES.md` contient une entrée conforme au format documenté pour la version
- [ ] Confidentialité des logs vérifiée : les journaux d'accès de l'image de production ne journalisent pas la chaîne de requête ni le référent (les liens de simulation/partage embarquent des données dans l'URL)

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

- Le `README.md` reste court : intro produit, tableau de liens, quickstart minimal, contribution, licence. Pas de détail technique.
- Chaque nouvelle fonctionnalité doit mettre à jour le doc correspondant **dans la même PR**.
- Ne pas dupliquer le contenu entre fichiers. Lier plutôt que copier.
- Les valeurs chiffrées (taux, plafonds) viennent de `config/payroll.php`, jamais des docs.
- Un doc technique doit décrire ce qui est **implémenté**, pas ce qui était prévu.
- Le backlog produit visible dans `README.md` sert de référence publique pour les fonctions à venir.
- Quand une fonctionnalité du backlog est implémentée, son item doit être mis à jour **dans la même PR** :
  - si la fonctionnalité est entièrement livrée, barrer l'item avec la syntaxe Markdown `- [x] ~~Fonctionnalité~~`;
  - si la formulation doit être affinée après livraison, conserver le lien vers l'issue ou la PR associée ;
  - ne pas supprimer l'item immédiatement : le texte barré sert d'historique lisible des livraisons.

## Calcul et règles métier

- Source unique des paramètres : `config/payroll.php`. Toute valeur réglementaire vit ici.
- Toute modification de formule → mettre à jour `docs/CALCUL.md` et ajouter un test de limite.
- Ne jamais dupliquer un taux dans une vue ou un test. Utiliser `config('payroll.*')`.
- Les nouvelles règles doivent préciser leur source légale ou leur statut d'hypothèse en commentaire PHP.

## Docker et CI

- L'image de développement (`docker/php/Dockerfile`) et l'image de production (`docker/release/Dockerfile`) sont indépendantes. Ne pas mélanger leurs configurations.
- Toute modification de `docker/release/` doit être testée localement (`docker build` + `docker run` + vérification HTTP 200) avant PR.
- Le workflow `.github/workflows/docker-release.yml` se déclenche uniquement sur release GitHub publiée. Ne pas le modifier sans en comprendre les effets sur GHCR.
