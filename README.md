# Mon Bulletin de Paie Marocain

> Simulateur pédagogique de salaire net — secteur privé marocain, exercice fiscal **2026**

[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Présentation

**Mon Bulletin de Paie Marocain** est un outil **100 % pédagogique** pour comprendre le détail du calcul de la paie au Maroc. Il couvre l'intégralité des prélèvements en vigueur en 2026 et affiche, pour chaque ligne, la référence légale exacte.

> Aucune donnée n'est stockée. Aucune base de données. Calcul stateless côté serveur.

---

## Fonctionnalités

| Fonctionnalité | Détail |
|---|---|
| **CNSS** | 4,48 % salarié · plafond 6 000 MAD/mois |
| **AMO** | 2,26 % salarié · sans plafond |
| **IR** | Barème progressif 6 tranches — Art. 73 CGI 2026 |
| **CIMR** | Retraite complémentaire 3 %–10 %, 100 % déductible IR |
| **Frais professionnels** | 35 % (≤ 6 500 MAD) ou 25 % (> 6 500 MAD) — Art. 59 CGI |
| **Charges de famille** | 50 MAD/personne, plafond 300 MAD — Art. 74 CGI |
| **Heures supplémentaires** | 4 régimes (+25 %, +50 %, +50 %, +100 %) — Art. 201 CT |
| **Prime d'ancienneté** | 5 tranches automatiques (2–25+ ans) — Art. 350 CT |
| **Indemnités exonérées** | Transport, panier, représentation, logement… — Arrêté 1314-25 |
| **Bancassurance / retraite** | Déductible IR à 50 % du SBI annuel — Art. 28-IV CGI |
| **Mutuelle santé** | Part salarié (post-fiscale) + part employeur |
| **Coût employeur total** | CNSS patronal (8,98 %) + AMO (4,11 %) + AF (6,40 %) + TFP (1,60 %) |
| **Documentation dynamique** | Tous les taux et textes de loi générés depuis la config |

---

## Stack technique

```
Laravel 11 (PHP 8.3)   →  logique de calcul + rendu Blade
Tailwind CSS           →  mise en page responsive
Docker Compose         →  PHP-FPM + Nginx, prêt à l'emploi
```

---

## Installation

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) avec intégration WSL2 activée (Windows) ou Docker Engine (Linux/macOS)

### Lancer le projet

```bash
git clone https://github.com/Zakmaf/3omar.git
cd 3omar

# Copier la configuration
cp .env.example .env

# Démarrer les conteneurs
docker-compose up -d

# Installer les dépendances PHP
docker exec paie_maroc_app composer install

# Générer la clé applicative
docker exec paie_maroc_app php artisan key:generate
```

L'application est disponible sur **http://localhost:8080**

---

## Pages

| URL | Description |
|---|---|
| `/` | Page d'accueil |
| `/calculateur` | Formulaire de simulation |
| `/documentation` | Tous les taux légaux en vigueur |

---

## Architecture

```
├── app/
│   ├── Http/Controllers/
│   │   ├── CalculatorController.php   # Validation + appel du service
│   │   ├── DocumentationController.php
│   │   └── HomeController.php
│   └── Services/
│       └── PayrollCalculatorService.php  # Moteur de calcul (séquence complète)
├── config/
│   └── payroll.php                    # Source unique de vérité — tous les taux 2026
├── resources/views/
│   ├── calculator/
│   │   ├── index.blade.php            # Formulaire
│   │   └── result.blade.php           # Bulletin détaillé + indicateurs
│   ├── documentation/index.blade.php
│   └── home.blade.php
└── docker/
    ├── php/   (Dockerfile, php.ini, entrypoint.sh)
    └── nginx/ (default.conf)
```

### Séquence de calcul

```
SBI   = salaire_base + primes_imposables + ancienneté + heures_sup
CNSS  = min(SBI, 6 000) × 4,48 %
AMO   = SBI × 2,26 %
CIMR  = SBI × taux_cimr  (si actif)
SNC   = SBI − CNSS − AMO − CIMR
FP    = min(SNC × taux_fp, plafond_fp)
RNI   = SNC − FP
IR    = barème_progressif(RNI × 12) / 12 − charges_famille
Net   = SBI − CNSS − AMO − CIMR − IR + indemnités − mutuelle − autres retenues
```

---

## Références légales

| Prélèvement | Texte |
|---|---|
| CNSS | Dahir n° 1-72-184 du 27 juillet 1972 |
| AMO | Loi n° 65-00 |
| IR — Barème | Article 73 CGI — Loi de Finances 50-25 |
| IR — Frais pro | Article 59 I-A CGI |
| IR — Charges famille | Article 74 CGI |
| CIMR | Article 28-III CGI |
| Bancassurance | Article 28-IV CGI |
| Heures supplémentaires | Article 201 Code du Travail (Loi n° 65-99) |
| Prime d'ancienneté | Article 350 Code du Travail |
| Indemnités exonérées | Arrêté n° 1314-25 / BO n° 7443 du 29/09/2025 |
| SMIG 2026 | Décret n° 2.25.983 — 3 422,72 MAD/mois |

---

## Ajouter un taux ou modifier les barèmes

Tout est centralisé dans **`config/payroll.php`**. Les vues et le service de calcul lisent depuis ce fichier — aucune valeur en dur ailleurs.

---

## Auteur

Développé par **Zakaria Maftah**  
Contact : email@zakmaf.net

---

## Licence

[MIT](LICENSE) — Projet open source à vocation pédagogique.
