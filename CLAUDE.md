# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mon Bulletin de Paie Marocain** — A pedagogical Moroccan net salary calculator for private-sector employees. Covers the 2026 fiscal year (CGI 2026, CNSS, AMO, IR, CIMR, heures supplémentaires, indemnités).

No database. No user data stored. 100% stateless computation.

---

## Commands

### Backend (Node.js + Express + TypeScript) — port 3001

```bash
cd backend
npm install
npm run dev      # ts-node-dev with hot reload
npm run build    # compiles to dist/
npm start        # production (after build)
npx tsc --noEmit # type check only
```

### Frontend (React + TypeScript + Vite + Tailwind CSS) — port 5173

```bash
cd frontend
npm install
npm run dev      # Vite dev server (proxies /api/* → localhost:3001)
npm run build    # production build → dist/
npm run preview  # serve production build
./node_modules/.bin/tsc --noEmit  # type check only
```

### Node.js setup (WSL2 — first time)

```bash
export NVM_DIR="$HOME/.nvm" && [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
# Add above line to ~/.bashrc to make permanent
```

---

## Architecture

```
bulletindepaiemaroc/
├── backend/                    # Express API — pure computation
│   └── src/
│       ├── data/
│       │   ├── constants.ts    # All 2026 rates (CNSS 4.48%, AMO 2.26%, IR brackets…)
│       │   ├── conventions.ts  # 10 conventions collectives marocaines
│       │   └── references.ts   # Legal references (CGI articles, Dahirs, décrets)
│       ├── services/
│       │   └── payrollCalculator.ts  # Core calculation engine
│       ├── routes/
│       │   ├── calculate.ts    # POST /api/calculate
│       │   └── rules.ts        # GET /api/rules, /api/rules/conventions
│       ├── types/payroll.ts    # All TypeScript interfaces
│       └── index.ts            # Express server
│
└── frontend/                   # React SPA
    └── src/
        ├── api/calculator.ts   # API client (transforms frontend ↔ backend formats)
        ├── context/PayrollContext.tsx  # Wizard state (useReducer)
        ├── types/payroll.ts    # Frontend types (different from backend — see below)
        ├── components/
        │   ├── Calculator/
        │   │   ├── WizardContainer.tsx   # Step rendering + navigation
        │   │   ├── StepIndicator.tsx     # Progress bar
        │   │   ├── ResultTable.tsx       # Breakdown table with tooltips
        │   │   ├── SalaryChart.tsx       # Recharts donut chart
        │   │   └── steps/               # Steps 1-6
        │   ├── Layout/                  # Header, Footer
        │   └── UI/                      # Tooltip, LegalBadge, InfoBox
        └── pages/                       # HomePage, CalculatorPage, DocumentationPage
```

---

## Key Architecture Decision: Frontend ↔ Backend Type Mismatch

The frontend and backend use **different field names and formats**. The transformation happens in `frontend/src/api/calculator.ts` (`buildPayload` function):

| Frontend (`PayrollInput`) | Backend (`PayrollInput`) |
|---|---|
| `cimr: { active, taux }` (taux as %) | `cimrActif: boolean, cimrTaux: number` (taux as ratio 0–1) |
| `indemnites: { transport, panier, … }` (object) | `indemnitesExonerees: [{ type, montantDeclare }]` (array) |
| `autresRetenues: { avanceSalaire, mutuelle, … }` (object) | `autresRetenues: number` (sum) |
| `conjointACharge: boolean` | `conjointCharge: boolean` |
| `conventionCollective: string` | `conventionCollectiveId: string` |

**Always update `calculator.ts` when changing field formats on either side.**

---

## Calculation Sequence (implemented in `payrollCalculator.ts`)

```
1. SBI = salaireBase + primesImposables + heuresSup_amounts
2. CNSS = min(SBI, 6000) × 4.48%               → max 268.80 MAD/mois
3. AMO  = SBI × 2.26%                          → sans plafond
4. CIMR = SBI × cimrTaux                       → si actif (3%–10%)
5. SNC  = SBI − CNSS − AMO − CIMR
6. FP   = min(SNC × taux_fp, plafond_fp)       → taux 35% si SBI≤6500, 25% sinon
7. RNI  = SNC − FP
8. IR_brut_annuel = progressiveTax(RNI × 12)   → barème Article 73 CGI
9. charges_famille = min(nb_personnes × 50, 300)
10. IR_net = max(0, IR_brut_mensuel − charges_famille)
11. salaireNet = SBI − CNSS − AMO − CIMR − IR_net + indemnitesRetenues − autresRetenues
```

---

## 2026 Key Rates (hardcoded in `backend/src/data/constants.ts`)

| Prélèvement | Taux salarié | Plafond |
|---|---|---|
| CNSS | 4.48% | 6 000 MAD/mois |
| AMO | 2.26% | aucun |
| CIMR | 3%–10% (choix) | aucun |
| Frais pro (≤6 500/mois) | 35% | 2 500 MAD/mois |
| Frais pro (>6 500/mois) | 25% | 2 916,67 MAD/mois |

**IR Barème annuel** (Article 73 CGI) :
- 0–40 000 : 0% | 40 001–60 000 : 10% (−4 000) | 60 001–80 000 : 20% (−10 000)
- 80 001–100 000 : 30% (−18 000) | 100 001–180 000 : 34% (−22 000) | >180 000 : 37% (−27 400)

**SMIG 2026** : 17,92 MAD/h — 3 422 MAD/mois (Décret n° 2.25.983)

---

## Legal References

All rates and rules include legal references in API responses (`referenceId` field → `backend/src/data/references.ts`):
- **CNSS** : Dahir portant loi n° 1-72-184 du 27 juillet 1972
- **AMO** : Loi n° 65-00 (couverture médicale de base)
- **IR barème** : Article 73 CGI 2026
- **Frais pro** : Article 59 I-A CGI
- **Charges famille** : Article 74 CGI
- **CIMR déductibilité** : Article 28-III CGI
- **Heures sup** : Article 201 Code du Travail (Loi 65-99)
- **Indemnités exonérées** : Arrêté n° 1314-25 / BO n° 7443 du 29/09/2025
- **SMIG** : Décret n° 2.25.983

---

## Adding a New Convention Collective

Edit `backend/src/data/conventions.ts`. Each convention has:
```typescript
{ id, nom, secteur, description, avantages: string[], sources: string[] }
```
The frontend dropdown reads from `GET /api/rules/conventions`.

## Adding a New Indemnité Type

1. Add the type to `TypeIndemnite` union in `backend/src/types/payroll.ts`
2. Add the cap in `INDEMNITES_PLAFONDS` in `backend/src/data/constants.ts`
3. Add validation in `backend/src/routes/calculate.ts`
4. Add transform in `frontend/src/api/calculator.ts` (`transformIndemnites`)
5. Add UI field in `frontend/src/components/Calculator/steps/Step4Variables.tsx`
