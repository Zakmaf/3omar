# Politique de sécurité

Merci de contribuer à la sécurité de **3omar**.

## Versions supportées

Seule la dernière version publiée (image `ghcr.io/zakmaf/3omar:latest` et tag mineur le plus récent)
reçoit des correctifs de sécurité. Les versions antérieures ne sont pas maintenues.

| Version | Supportée |
|---------|-----------|
| Dernière mineure (V1.x) | ✅ |
| Versions antérieures | ❌ |

## Signaler une vulnérabilité

**N'ouvrez pas d'issue publique pour une faille de sécurité.**

Privilégiez l'un de ces canaux privés :

1. **GitHub Private Vulnerability Reporting** — onglet *Security* du dépôt →
   *Report a vulnerability* (canal recommandé).
2. À défaut, par email : **zakaria.maftah@gmail.com** avec l'objet `[SECURITY] 3omar`.

Merci d'inclure, dans la mesure du possible :

- une description de la faille et de son impact ;
- les étapes de reproduction (ou un PoC) ;
- la version / le tag d'image concerné ;
- toute proposition de correctif éventuelle.

## Délais

- **Accusé de réception** : sous 72 heures.
- **Première évaluation** : sous 7 jours.
- **Correctif** : selon la gravité ; une divulgation coordonnée sera proposée
  une fois le correctif disponible.

## Périmètre

3omar est un simulateur pédagogique **sans base de données et sans collecte de
données personnelles**. Sont particulièrement bienvenus les signalements portant
sur : l'injection (XSS, etc.), le déni de service applicatif sur
`POST /calculateur/calculer`, l'absence d'en-têtes de sécurité, ou la
configuration de l'image Docker de production.
