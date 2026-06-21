<!--
Titre de la PR = message de commit principal : type(scope): description courte en français
Types acceptés : feat, fix, docs, chore, test, refactor
-->

## Ce qui change

<!-- Décris brièvement le changement. -->

## Pourquoi

<!-- Contexte / problème résolu. Lie l'issue : Closes #00 -->

## Comment tester

<!-- Étapes de vérification, commandes exécutées. -->

## Checklist

- [ ] Une PR = un sujet (pas de fourre-tout, pas de refactor opportuniste hors périmètre)
- [ ] Branche partie de `main` à jour
- [ ] Tests verts en local (`vendor/bin/phpunit`)
- [ ] Si `app/Services/PayrollCalculatorService.php` est touché → tests unitaires ajoutés/mis à jour
- [ ] Si une valeur réglementaire change → elle vit dans `config/payroll.php` (jamais en dur)
- [ ] Si l'interface change → clés i18n ajoutées dans les **quatre** langues (fr, en, ar, es)
- [ ] Documentation correspondante mise à jour dans la **même** PR (voir `CONTRIBUTING.md`)
- [ ] Item du backlog `README.md` mis à jour si la fonctionnalité y figure
