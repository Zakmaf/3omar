# Releases

## v3.3.0 - 2026-07-26

### Nouveautés

- Profils de simulation prêts à l'emploi sur la page calculateur : SMIG, salarié standard, cadre, avec primes, avec CIMR et journaliste. Un clic préremplit le formulaire, tous les champs restent modifiables avant le calcul. #51
- Reprise et partage d'une simulation par lien : le lien reconstruit la totalité des saisies, y compris les indemnités et les heures supplémentaires, sans qu'aucune donnée ne soit enregistrée sur le serveur. #50
- Comparaison de deux scénarios de paie : écarts absolus et relatifs sur le net à payer, l'IR, les cotisations et le coût employeur, avec mise en avant du net et du coût employeur. #47
- Tableau des entrées modifiées sur la page de comparaison : les lignes qui expliquent les écarts sont mises en évidence, et les écarts portés par une entrée avancée sont signalés explicitement. #47
- Lien de comparaison partageable et rejouable : les deux scénarios voyagent dans l'URL, ce qui permet de transmettre ou de conserver une comparaison. #47

### Améliorations

- Avertissement de confidentialité affiché à côté de chaque lien de partage : les montants saisis sont inscrits dans le lien lui-même et ne doivent être transmis qu'à des personnes autorisées. #50
- Les avertissements réglementaires des deux scénarios sont regroupés sans doublon sur la page de comparaison. #47
- Un lien de simulation illisible ou périmé est signalé à l'utilisateur au lieu d'ouvrir un formulaire vide sans explication. #50
- Les profils prêts à l'emploi lisent le SMIG et le plafond d'indemnité de transport dans la configuration réglementaire, ce qui évite toute duplication des règles. #51
- 49 nouveaux tests couvrant les profils, l'encodage des liens partagés, les écarts de comparaison, le rendu de la comparaison dans les quatre langues et l'absence de libellé non traduit sur les nouveaux écrans. #47 #50 #51 #139
- Golden test de démonstration 2026 ajouté : un profil fictif exerce en un seul cas l'ancienneté, une prime imposable, une indemnité exonérée, la mutuelle pré-fiscale, la CIMR et les charges de famille, avec des montants vérifiables à la main. #139

### Correctifs

- Ancienneté des profils calée sur les débuts de tranche définis dans la configuration : une autre valeur était réaffichée à zéro par le sélecteur du formulaire, et la simulation chargée ne correspondait plus au profil annoncé. #51
- Entrée v3.2.0 absente de `docs/RELEASES.md` ajoutée.

### Sécurité

- Tout lien de simulation ou de comparaison repasse par les règles de validation du formulaire avant d'être utilisé : un lien fabriqué à la main ne peut ni injecter un champ inconnu, ni forcer une catégorie professionnelle ou un type d'indemnité hors liste. #50
- Longueur des liens et taille après décompression bornées, pour écarter les charges utiles hostiles. #50
- La comparaison est soumise à la même limitation de débit que le calcul, puisqu'elle exécute le moteur deux fois. #47
- Les logs d'accès de l'image de production ne journalisent plus la chaîne de requête ni le référent : les montants portés par un lien de simulation ou de comparaison ne peuvent plus se retrouver dans les journaux du serveur. #50

### Mise à jour de la stack

- Passe `laravel/framework` de 13.18.1 à 13.25.0. #148
- Passe `laravel/pint` de 1.29.3 à 1.30.5. #149

### Migration

Aucune action requise. Mise à jour transparente depuis v3.2.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.3.0
```

---

## v3.2.0 - 2026-07-08

### Nouveautés

- Option avancée de taux AMO salarié personnalisé : permet de reproduire un bulletin réel dont le taux appliqué diffère du taux légal, avec un avertissement explicite rappelant le caractère dérogatoire de cette saisie. #138

### Mise à jour de la stack

- Passe `laravel/framework` de 13.16.1 à 13.18.1. #125
- Passe `actions/cache` de la version 5 à la version 6 dans les workflows CI. #110

### Migration

Aucune action requise. Mise à jour transparente depuis v3.1.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.2.0
```

---

## v3.1.0 - 2026-07-06

### Nouveautés

- Documentation API enrichie avec schémas de requête et réponse, exemples complets, exemples JavaScript, boutons de copie et panneaux d'essai interactif pour les endpoints publics #118
- Appels à l'action contextuels entre calculateur, résultat et documentation pour relancer une simulation, comprendre les règles ou vérifier les limites du simulateur #119
- Page Fiabilité complétée avec le flux de données saisi, la distinction entre simulation pédagogique et bulletin officiel, le lien de sécurité et un signalement d'erreur de calcul visible sur mobile #121

### Améliorations

- Icônes Bootstrap décoratives masquées aux lecteurs d'écran dans les vues principales, avec conservation des icônes porteuses de sens via libellés accessibles #120
- Endpoint `/api/v1/health` et exemples OpenAPI alignés sur la version applicative affichée, pour éviter la dérive de version dans la documentation développeur #118
- Tests de rendu étendus pour les CTA, la documentation API, la page Fiabilité, les icônes accessibles et les quatre locales FR, EN, ES, AR #118 #119 #120 #121

### Correctifs

- Mutuelle salariale traitée comme retenue pré-fiscale dans l'assiette IR lorsque saisie, ce qui aligne le calcul sur le simulateur Excel BP av2026 pour ce cas confirmé #126
- Documentation de calcul mise à jour pour préciser le traitement fiscal de la mutuelle salariale #126

### Migration

Aucune action requise. Mise à jour transparente depuis v3.0.1.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.1.0
```

---

## v3.0.1 - 2026-07-06

### Nouveautés

- Page Fiabilité (`/fiabilite`) : présente les engagements de confidentialité, les limites du simulateur et le statut open source, avec une matrice listant chaque règle de calcul couverte (source légale, taux, plafond et niveau de confiance). #123
- Cartes personas sur la page d'accueil : quatre cartes dédiées (Salarié, RH/Paie, Développeur/Intégrateur, Employeur/Décideur) décrivent le cas d'usage et proposent un appel à l'action contextuel.
- Panneau de confiance avant la simulation : affiche en quatre cases ce que le calculateur produit (CNSS, AMO, IR, frais pro, indemnités, coût employeur), la garantie de confidentialité, les limites connues et un lien vers la page Fiabilité, avant toute saisie.
- Section Diagnostic sur la page de résultat : trois cartes de ratios - taux effectif global (cotisations + IR / brut), ratio net/brut et surcoût employeur - pour situer la simulation en un coup d'oeil.
- Points clés dynamiques sur le résultat : jusqu'à trois insights contextuels adaptés à la situation calculée (IR non prélevé ce mois-ci, plafond CNSS atteint, suggestion CIMR si taux marginal élevé, coût employeur potentiellement sous-estimé).
- Section "Et maintenant ?" sur le résultat : liens directs vers une nouvelle simulation, la documentation des règles, la page Fiabilité, l'API REST et l'impression.

### Améliorations

- Bandeau de confidentialité sur la page de résultat : rappel discret que la simulation n'est pas conservée, avec lien vers la page Fiabilité.
- Lien Fiabilité ajouté dans la barre de navigation et le pied de page, accessible depuis toutes les pages.
- Cohérence du mode sombre : tous les nouveaux éléments (page Fiabilité, personas, panneau pré-simulation, diagnostic, points clés) respectent les tokens CSS existants.
- 10 nouveaux tests de fumée couvrant la page Fiabilité, les personas, le panneau pré-simulation, les cartes de diagnostic, les points clés, les CTAs de résultat, le cas IR nul, le bouton impression et le rendu dans les quatre langues.

### Correctifs

- Numéro de version dans le pied de page corrigé : affichait V2.2.2 au lieu de v3.0.0 sur les déploiements sans variable `APP_VERSION`.
- Section V3 du README nettoyée : suppression de la mention "en cours" et du nom de branche devenus obsolètes après la publication de la release.
- Table des tags Docker dans `docs/DEPLOIEMENT.md` mise à jour de v2.2.2 vers v3.0.x.
- Entrée v3.0.0 absente de `docs/RELEASES.md` ajoutée.

### Migration

Aucune action requise. Mise à jour transparente depuis v3.0.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.0.1
```

---

## v3.0.0 - 2026-07-03

Release initiale de la série 3.x. Notes incomplètes - voir v3.0.1 pour le détail complet des fonctionnalités livrées dans cette série.

---

## v2.2.2 - 2026-06-30

### Accessibilité

- Ajout d'une alternative tabulaire accessible au graphique de répartition du salaire brut sur la page résultat.
- Les libellés du graphique utilisent désormais les traductions existantes au lieu de chaînes codées en dur.
- Ajout des libellés de l'alternative accessible en français, anglais, espagnol et arabe.

### Vérification

- `./vendor/bin/phpunit --filter ResultPageTest`
- `./vendor/bin/phpunit`

## v1.4.0 - 2026-06-21

### Nouveautés

- Refondre la page résultat pour séparer la synthèse, l'explication pédagogique et le détail bulletin.
- Rendre le formulaire plus lisible avec des choix contraints pour l'ancienneté, les enfants à charge et la CIMR.
- Clarifier le bouton de lancement du calcul pour éviter l'ambiguïté avec les liens de navigation.

### Correctifs

- Uniformiser l'affichage des unités sur les montants du résultat.
- Regrouper les primes imposables en une seule saisie.
- Vérifier la déduction fiscale de la retraite complémentaire dans l'IR.
- Documenter les parcours de calcul avec des tests de rendu dédiés.
