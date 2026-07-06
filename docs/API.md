# API REST - 3omar Simulateur de paie marocaine

## Vue d'ensemble

L'API REST v1 permet d'integrer le simulateur de salaire net marocain dans des applications tierces. Tous les endpoints sont prefixes par `/api/v1`.

Specification OpenAPI disponible : `/api/docs/openapi.json`

Documentation interactive avec essai en direct : page `/api-documentation` de l'application.

## Authentification

Aucune authentification requise. L'API est publique.

## Endpoints

### POST /api/v1/simuler/brut-vers-net

Calcule le bulletin de paie complet a partir du salaire de base brut.

**Requete :**

```json
{
  "salaire_base": 10000,
  "type_frais_pro": "commun",
  "nb_enfants": 2,
  "conjoint_charge": true
}
```

**Reponse (200) :**

```json
{
  "mode": "gross_to_net",
  "sbi": 10000,
  "salaire_net": 8234.56,
  "ir_net": 1012.33,
  "cotisation_cnss": 268.80,
  "cotisation_amo": 226.00,
  "frais_pro": 2500.00,
  "rni": 7005.20,
  "repartition": { "..." : "..." },
  "avertissements": []
}
```

**Champs requis :** `salaire_base`, `type_frais_pro`

### POST /api/v1/simuler/net-vers-brut

Recherche le salaire de base brut qui produit le net cible souhaite (resolution par dichotomie).

**Requete :**

```json
{
  "net_cible": 8000,
  "type_frais_pro": "commun"
}
```

**Reponse (200) :**

```json
{
  "mode": "net_to_gross",
  "sbi": 9723.45,
  "salaire_net": 8000.00,
  "resolution_net": {
    "net_cible": 8000.00,
    "net_obtenu": 8000.00,
    "ecart": 0.00,
    "iterations": 25,
    "converge": true
  },
  "avertissements": []
}
```

**Champs requis :** `net_cible`, `type_frais_pro`

### GET /api/v1/parametres

Retourne la configuration reglementaire complete du simulateur (taux CNSS, AMO, bareme IR, plafonds, indemnites, etc.).

**Reponse (200) :**

```json
{
  "year": 2026,
  "cnss": { "taux": 0.0448, "plafond": 6000 },
  "amo": { "taux": 0.0226 },
  "ir": { "baremes": ["..."] },
  "indemnites": { "..." : "..." }
}
```

### GET /api/v1/health

Verification de l'etat du service.

**Reponse (200) :**

```json
{
  "status": "ok",
  "version": "v3.0.1",
  "timestamp": "2026-06-22T12:00:00+00:00"
}
```

Le champ `version` reflete toujours la version de l'application actuellement deployee (`config('app.version')`), pas une valeur figee.

## Parametres optionnels des simulations

| Champ | Type | Description |
|-------|------|-------------|
| `nb_annees_anciennete` | integer (0-50) | Nombre d'annees d'anciennete |
| `prime_bilan` | number | Prime de bilan mensuelle (MAD) |
| `prime_rendement` | number | Prime de rendement mensuelle (MAD) |
| `autres_primes` | number | Autres primes imposables (MAD) |
| `nb_enfants` | integer (0-20) | Nombre d'enfants a charge |
| `conjoint_charge` | boolean | Conjoint a charge |
| `cimr_actif` | boolean | Activer la cotisation CIMR |
| `cimr_taux` | number | Taux CIMR en pourcentage |
| `cimr_repartition` | string | `salarie`, `employeur` ou `partage` |
| `cimr_taux_employeur` | number | Taux CIMR employeur (si partage) |
| `retraite_complementaire_mensuel` | number | Retraite complementaire (MAD) |
| `rc_part_employeur` | number | Part employeur retraite complementaire (MAD) |
| `mutuelle_salarie` | number | Cotisation mutuelle salarie (MAD) |
| `mutuelle_patronale` | number | Cotisation mutuelle patronale (MAD) |
| `autres_retenues` | number | Autres retenues mensuelles (MAD) |
| `jours_travailles` | integer (1-31) | Jours travailles dans le mois |
| `heures_sup` | array | Heures supplementaires (max 10) |
| `indemnites` | array | Indemnites exonerees (max 10) |
| `prime_scolarite` | number | Prime de scolarite (MAD) |
| `prime_aid` | number | Prime des Aid (MAD) |
| `autres_avantages_cnss` | number | Autres avantages CNSS exoneres (MAD) |

## Limitation de debit (rate limiting)

Les endpoints de simulation (`/simuler/brut-vers-net` et `/simuler/net-vers-brut`) sont limites a **60 requetes par minute** par adresse IP.

Les endpoints GET (`/parametres`, `/health`) ne sont pas soumis a cette limite.

En cas de depassement, l'API retourne un statut **429** :

```json
{
  "type": "about:blank",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded. Try again later."
}
```

## Format d'erreur (RFC 7807)

Toutes les erreurs API suivent le format RFC 7807 (Problem Details for HTTP APIs).

### Erreur de validation (422)

```json
{
  "type": "about:blank",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "The salaire_base field is required.",
  "errors": {
    "salaire_base": ["The salaire_base field is required."]
  }
}
```

### Ressource non trouvee (404)

```json
{
  "type": "about:blank",
  "title": "Not Found",
  "status": 404,
  "detail": "The requested resource was not found."
}
```

### Methode non autorisee (405)

```json
{
  "type": "about:blank",
  "title": "Method Not Allowed",
  "status": 405,
  "detail": "The HTTP method is not allowed for this endpoint."
}
```

## CORS

L'API accepte les requetes cross-origin depuis toute origine (`*`).

Methodes autorisees : `GET`, `POST`, `OPTIONS`

En-tetes autorises : `Content-Type`, `Accept`, `X-Requested-With`

Les credentials ne sont pas pris en charge (`supports_credentials: false`).
