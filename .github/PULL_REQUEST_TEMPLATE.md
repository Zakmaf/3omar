<!--
Titre de la PR : type(scope): description courte en français (#issue)
Types acceptés : feat, fix, docs, test, ci, chore, refactor
Brouillon tant que le check `gate` n'est pas vert.
-->

## Livraison

- Issue : Closes #
- Branche :
- Modèle et raison du choix :

## Besoin traité

<!-- Problème, exigence ou critères d'acceptation concernés. -->

## Modifications

- 

## Vérification

<!-- Commande exécutée : résultat réel. Lien du run CI. Rien de déclaré sans l'avoir exécuté. -->

- 

## Checklist 3omar

- [ ] Une PR = un sujet (pas de fourre-tout, pas de refactor opportuniste hors périmètre)
- [ ] Branche partie de `main` à jour
- [ ] Si `app/Services/PayrollCalculatorService.php` est touché : tests unitaires ajoutés ou mis à jour, cas limites compris
- [ ] Si une valeur réglementaire change : elle vit dans `config/payroll.php` (jamais en dur), avec sa référence légale, et `docs/CALCUL.md` est à jour
- [ ] Si l'interface change : clés i18n dans les **quatre** langues (fr, en, ar, es), rendu mobile et RTL vérifié, captures jointes
- [ ] Documentation correspondante mise à jour dans la **même** PR (voir `CONTRIBUTING.md`)
- [ ] `README.md` touché seulement si la liste des fonctionnalités change (pas de nouveautés par version, voir `CONTRIBUTING.md`)

## Gouvernance

- Aucune fusion, release, publication ni déploiement effectué : oui
- Environnements démarrés et arrêtés :

## Risques résiduels

- 
