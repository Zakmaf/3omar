// @ts-check
const { test, expect } = require('@playwright/test');
const { openResultPage, stabilize } = require('../helpers/simulation');

/**
 * Cout employeur incomplet sur la page resultat (issue #208).
 *
 * Quand des charges patronales sont declarees inconnues dans le formulaire,
 * le total affiche n'est qu'un minimum. Contrat de rendu :
 *
 * - la carte de synthese `.result-kpi-employer`, la ligne total
 *   `tr.row-employer` et le rappel `[data-result-block="employer-note"]`
 *   indiquent « Au moins » ; la carte porte un badge « Incomplet » ;
 * - la carte nomme exactement les charges manquantes (libelle de leur ligne
 *   dans le detail employeur), jamais une charge connue, et propose un lien
 *   vers la rubrique du formulaire concernee, simulation retablie par le `s`
 *   original du lien de partage ;
 * - le detail reste exact a l'impression, liens d'ecran masques ;
 * - les hypotheses (charges manquantes, AMO personnalisee) quittent le bloc
 *   « Avertissements reglementaires » pour un bloc informatif distinct
 *   `[data-result-block="assumptions"]`, titre « Hypotheses de votre
 *   simulation » ; une vraie alerte reglementaire y reste.
 *
 * Etape RED : chaque symptome a son propre test pour distinguer les cas
 * reellement rouges. Avant d'asserter un manque, chaque test verifie que la
 * page resultat est fonctionnelle (titre, ancre de synthese, blocs existants)
 * et que le moteur a bien traite la charge comme inconnue (ligne « Non
 * renseigne » du detail, comportement existant). Locale fr uniquement :
 * les controles RTL mobile et les captures viennent avec GREEN.
 *
 * Donnees : fixture entierement synthetique de tests/browser/helpers/simulation.js,
 * ou toutes les charges patronales optionnelles sont connues et non nulles.
 */

const DESKTOP = { width: 1440, height: 900 };

const TITRE_HYPOTHESES = 'Hypothèses de votre simulation';

/** Texte d'une indication de minimum. */
const AU_MOINS = /Au moins/i;

/** Mention d'une information manquante, quelle que soit sa formulation. */
const INFO_MANQUANTE = /partiel|non renseign|inconnu|manquant/i;

/**
 * Les cinq charges patronales optionnelles. Le libelle complet est lu dans le
 * detail employeur de la page : seul son debut sert a reperer la ligne.
 */
const CHARGES = {
    cimr_taux_employeur: { prefixe: /^CIMR/, rubrique: 'step-cimr' },
    rc_part_employeur: { prefixe: /^Retraite compl/, rubrique: 'step-cimr' },
    mutuelle_patronale: { prefixe: /^Mutuelle/, rubrique: 'step-mutuelle' },
    assurance_at_taux: { prefixe: /^Assurance accident/, rubrique: 'step-mutuelle' },
    assurance_rc_pro: { prefixe: /^Assurance RC/, rubrique: 'step-mutuelle' },
};

/** Valeur numerique restituee par le formulaire, decimales eventuelles comprises. */
const nombre = (n) => new RegExp(`^${n}(\\.0+)?$`);

/**
 * Cas partiels. Chaque rubrique du formulaire est exercee ; les valeurs
 * connues sont celles de la fixture synthetique.
 */
const CAS_PARTIELS = [
    {
        id: 'mutuelle et RC pro inconnues',
        inconnus: ['mutuelle_patronale', 'assurance_rc_pro'],
        valeursConnues: { '#assurance_at_taux': nombre(1), '#mutuelle_salarie': nombre(200), '#cimrTauxEmployeur': nombre(6) },
    },
    {
        id: 'CIMR et retraite employeur inconnues',
        inconnus: ['cimr_taux_employeur', 'rc_part_employeur'],
        valeursConnues: { '#cimrTaux': nombre(6), '#mutuelle_patronale': nombre(300), '#assurance_rc_pro': nombre(100) },
    },
];

function champsInconnus(inconnus) {
    return Object.fromEntries(inconnus.map((champ) => [`${champ}_inconnu`, 1]));
}

function rubriquesAttendues(inconnus) {
    return [...new Set(inconnus.map((champ) => CHARGES[champ].rubrique))].sort();
}

/** Chiffres seuls : compare des montants sans dependre du format ni d'un taux. */
const chiffres = (texte) => String(texte).replace(/\D/g, '');

/**
 * Ouvre la page resultat par le vrai POST et verifie qu'elle est
 * fonctionnelle avant toute assertion sur un manque.
 */
async function ouvrirResultat(page, fields = {}) {
    await openResultPage(page, { locale: 'fr', fields });

    expect(new URL(page.url()).pathname, 'page resultat').toBe('/calculateur/calculer');
    await expect(page.locator('h1').first(), 'titre de la page resultat').toBeVisible();
    await expect(page.locator('#result-summary-title'), 'ancre de synthese').toBeVisible();
    await expect(page.locator('.result-kpi-employer'), 'carte cout employeur').toHaveCount(1);
    await expect(page.locator('tr.row-employer'), 'ligne total employeur').toHaveCount(1);
    await expect(page.locator('[data-result-block="employer-note"]'), 'rappel employeur').toHaveCount(1);

    return {
        carte: page.locator('.result-kpi-employer'),
        ligneTotal: page.locator('tr.row-employer'),
        rappel: page.locator('[data-result-block="employer-note"]'),
    };
}

/** Lignes patronales du detail : libelle (sans badge legal) et montant. */
async function lignesPatronales(page) {
    return page.locator('[data-result-block="employer-table"] tr.row-patron').evaluateAll((rows) => rows.map((row) => {
        const cells = row.querySelectorAll('td');
        const libelle = Array.from(cells[0].childNodes)
            .filter((node) => node.nodeType === Node.TEXT_NODE)
            .map((node) => node.textContent)
            .join(' ')
            .replace(/\s+/g, ' ')
            .trim();
        const montant = (cells[cells.length - 1].textContent || '').replace(/\s+/g, ' ').trim();
        return { libelle, montant };
    }));
}

/**
 * Libelles des charges manquantes et connues, lus dans le detail employeur.
 * Verifie au passage le comportement existant du moteur : chaque charge
 * inconnue y est « Non renseigne », chaque charge connue y a un montant.
 */
async function libellesDuCas(page, inconnus) {
    const lignes = await lignesPatronales(page);
    const manquants = [];
    const connus = [];

    for (const [champ, { prefixe }] of Object.entries(CHARGES)) {
        const ligne = lignes.find((l) => prefixe.test(l.libelle));
        expect(ligne, `ligne du detail employeur pour ${champ}`).toBeTruthy();
        if (inconnus.includes(champ)) {
            expect(ligne.montant, `${champ} traite comme inconnu par le moteur`).toBe('Non renseigné');
            manquants.push(ligne.libelle);
        } else {
            expect(ligne.montant, `${champ} connu dans la fixture`).toMatch(/^\+\s*\d/);
            connus.push(ligne.libelle);
        }
    }

    return { manquants, connus };
}

/** Valeur du parametre `s` du lien de partage de la page resultat. */
async function payloadPartage(page) {
    const s = new URL(await page.locator('#shareUrl').inputValue()).searchParams.get('s');
    expect(s, 'lien de partage de la simulation').toBeTruthy();
    return String(s);
}

/** Liens de la carte vers une rubrique du formulaire. */
function liensRubrique(carte) {
    return carte.locator('a[href*="#step-"]');
}

test.describe('Cout employeur incomplet - page resultat (desktop fr)', () => {
    test.use({ viewport: DESKTOP });

    for (const cas of CAS_PARTIELS) {
        test.describe(cas.id, () => {
            test('controle : montants coherents et charges inconnues dans le detail', async ({ page }) => {
                const { carte, ligneTotal, rappel } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);

                await expect(carte).toContainText('Coût total employeur');
                const montantCarte = chiffres(await carte.locator('.result-kpi-value').innerText());
                expect(montantCarte.length, 'montant de la carte').toBeGreaterThan(0);
                expect(chiffres(await ligneTotal.innerText()), 'ligne total : meme montant que la carte').toContain(montantCarte);
                expect(chiffres(await rappel.innerText()), 'rappel : meme montant que la carte').toContain(montantCarte);
            });

            test('carte de synthese : « Au moins » et badge « Incomplet »', async ({ page }) => {
                const { carte } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);

                await expect(carte, 'indication de minimum dans la carte').toContainText(AU_MOINS);
                await expect(carte.locator('.badge', { hasText: /^\s*Incomplet\s*$/ }), 'badge Incomplet').toHaveCount(1);
                await expect(carte.locator('.badge', { hasText: /^\s*Incomplet\s*$/ })).toBeVisible();
            });

            test('ligne total du bulletin : « Au moins »', async ({ page }) => {
                const { ligneTotal } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);

                await expect(ligneTotal, 'indication de minimum sur la ligne total').toContainText(AU_MOINS);
            });

            test('rappel de diagnostic employeur : « Au moins »', async ({ page }) => {
                const { rappel } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);

                await expect(rappel, 'indication de minimum dans le rappel').toContainText(AU_MOINS);
            });

            test('carte : nomme exactement les charges manquantes, aucune charge connue', async ({ page }) => {
                const { carte } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                const { manquants, connus } = await libellesDuCas(page, cas.inconnus);

                for (const libelle of manquants) {
                    await expect.soft(carte, `charge manquante citee : ${libelle}`).toContainText(libelle);
                }
                for (const libelle of connus) {
                    await expect.soft(carte, `charge connue non citee : ${libelle}`).not.toContainText(libelle);
                }
            });

            test('carte : lien vers la rubrique du formulaire avec le s original', async ({ page }) => {
                const { carte } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);
                const s = await payloadPartage(page);
                const attendues = rubriquesAttendues(cas.inconnus);

                const liens = liensRubrique(carte);
                await expect(liens, 'un lien par rubrique concernee').toHaveCount(attendues.length);

                const hrefs = await liens.evaluateAll((as) => as.map((a) => a.getAttribute('href') || ''));
                const rubriques = hrefs.map((href) => new URL(href, 'http://localhost').hash.replace('#', '')).sort();
                expect(rubriques, 'rubriques ciblees : seulement celles des charges manquantes').toEqual(attendues);
                for (const href of hrefs) {
                    const url = new URL(href, 'http://localhost');
                    expect(url.pathname, 'lien vers le formulaire du calculateur').toBe('/calculateur');
                    expect(url.searchParams.get('s'), 'simulation retablie par le s original').toBe(s);
                }
            });

            test('vrai clic : la rubrique s\'ouvre, valeurs et etats inconnus conserves', async ({ page }) => {
                const { carte } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);
                const s = await payloadPartage(page);
                const [rubrique] = rubriquesAttendues(cas.inconnus);

                const lien = carte.locator(`a[href*="#${rubrique}"]`);
                await expect(lien, `lien vers #${rubrique} dans la carte`).toHaveCount(1);
                await expect(lien).toBeVisible();

                const navigation = page.waitForEvent('framenavigated', { predicate: (frame) => frame === page.mainFrame() });
                await lien.click();
                await navigation;
                await page.waitForLoadState('domcontentloaded');
                await stabilize(page);

                const arrivee = new URL(page.url());
                expect(arrivee.pathname, 'formulaire du calculateur').toBe('/calculateur');
                expect(arrivee.searchParams.get('s'), 's original conserve').toBe(s);
                expect(arrivee.hash, 'ancre de la rubrique').toBe(`#${rubrique}`);
                await expect(page.locator(`#${rubrique}`), `rubrique #${rubrique} ouverte`).toHaveAttribute('open', '');

                for (const champ of cas.inconnus) {
                    await expect(page.locator(`#${champ}_toggle`), `${champ} : case « inconnu » cochee`).toBeChecked();
                    await expect(page.locator(`#${champ}_inconnu`), `${champ} : drapeau transmis`).toHaveValue('1');
                    await expect(page.locator(`[data-inconnu-wrap="${champ}"]`), `${champ} : saisie masquee`).toBeHidden();
                }
                for (const [selecteur, valeur] of Object.entries(cas.valeursConnues)) {
                    await expect(page.locator(selecteur), `valeur connue restituee : ${selecteur}`).toHaveValue(valeur);
                }
            });

            test('impression : detail exact, liens d\'ecran masques', async ({ page }) => {
                const { carte, ligneTotal, rappel } = await ouvrirResultat(page, champsInconnus(cas.inconnus));
                const { manquants, connus } = await libellesDuCas(page, cas.inconnus);
                const attendues = rubriquesAttendues(cas.inconnus);

                await page.emulateMedia({ media: 'print' });
                await stabilize(page);

                await expect(carte).toBeVisible();
                await expect.soft(carte, 'impression : minimum dans la carte').toContainText(AU_MOINS);
                await expect.soft(carte.locator('.badge', { hasText: /^\s*Incomplet\s*$/ }), 'impression : badge Incomplet').toBeVisible();
                await expect.soft(ligneTotal, 'impression : minimum sur la ligne total').toContainText(AU_MOINS);
                await expect.soft(rappel, 'impression : minimum dans le rappel').toContainText(AU_MOINS);
                for (const libelle of manquants) {
                    await expect.soft(carte, `impression : charge manquante citee : ${libelle}`).toContainText(libelle);
                }
                for (const libelle of connus) {
                    await expect.soft(carte, `impression : charge connue non citee : ${libelle}`).not.toContainText(libelle);
                }

                // Le lien d'ecran existe mais n'est pas imprime.
                const liens = liensRubrique(carte);
                await expect(liens, 'impression : liens de rubrique presents dans le document').toHaveCount(attendues.length);
                for (let i = 0; i < attendues.length; i += 1) {
                    await expect(liens.nth(i), 'impression : lien d\'ecran masque').toBeHidden();
                }
            });

            test('hypotheses : l\'information manquante quitte les avertissements reglementaires', async ({ page }) => {
                await ouvrirResultat(page, champsInconnus(cas.inconnus));
                await libellesDuCas(page, cas.inconnus);
                await expect(page.locator('[data-result-section="diagnostic"]'), 'section diagnostic').toHaveCount(1);

                await expect.soft(
                    page.locator('[data-result-block="warnings"]', { hasText: INFO_MANQUANTE }),
                    'aucune information manquante sous « Avertissements reglementaires »',
                ).toHaveCount(0);

                const hypotheses = page.locator('[data-result-section="diagnostic"] [data-result-block="assumptions"]');
                await expect(hypotheses, 'bloc « Hypotheses de votre simulation »').toHaveCount(1);
                await expect.soft(hypotheses.getByRole('heading', { name: TITRE_HYPOTHESES, exact: true })).toBeVisible();
                await expect.soft(hypotheses, 'information manquante dans les hypotheses').toContainText(INFO_MANQUANTE);
            });
        });
    }

    test('cas mixte : vraie alerte reglementaire conservee, hypotheses classees a part', async ({ page }) => {
        // Saisie synthetique : base sous le SMIG (alerte reglementaire), AMO
        // personnalisee et mutuelle patronale inconnue (hypotheses), retenues
        // superieures a la remuneration (net negatif : categorie hors scope).
        await ouvrirResultat(page, {
            salaire_base: 1000,
            amo_taux_salarie_personnalise: 1,
            amo_taux_salarie: 3,
            mutuelle_patronale_inconnu: 1,
            retenues_imposees_ir: 5000,
        });
        await libellesDuCas(page, ['mutuelle_patronale']);

        const diagnostic = page.locator('[data-result-section="diagnostic"]');
        const avertissements = page.locator('[data-result-block="warnings"]');
        const hypotheses = diagnostic.locator('[data-result-block="assumptions"]');

        // Controles : les messages existent deja sur la page.
        await expect(diagnostic, 'alerte SMIG produite par le moteur').toContainText(/SMIG/);
        await expect(diagnostic, 'message AMO personnalisee produit par le moteur').toContainText(/\bAMO\b/);

        // La vraie alerte reste sous son titre reglementaire.
        await expect(avertissements, 'bloc des avertissements reglementaires').toHaveCount(1);
        await expect(avertissements).toContainText('Avertissements réglementaires');
        await expect(avertissements, 'alerte SMIG conservee').toContainText(/SMIG/);

        // Categorie hors scope : ni disparue, ni classee par ce contrat.
        await expect(diagnostic, 'net negatif toujours signale').toContainText(/Net à payer négatif/);

        // Classification des hypotheses.
        await expect.soft(avertissements, 'AMO personnalisee hors avertissements reglementaires').not.toContainText(/\bAMO\b/);
        await expect.soft(avertissements, 'information manquante hors avertissements reglementaires').not.toContainText(INFO_MANQUANTE);

        await expect(hypotheses, 'bloc informatif distinct').toHaveCount(1);
        await expect.soft(hypotheses.getByRole('heading', { name: TITRE_HYPOTHESES, exact: true })).toBeVisible();
        await expect.soft(hypotheses, 'AMO personnalisee dans les hypotheses').toContainText(/\bAMO\b/);
        await expect.soft(hypotheses, 'information manquante dans les hypotheses').toContainText(INFO_MANQUANTE);
        await expect.soft(hypotheses, 'alerte SMIG absente des hypotheses').not.toContainText(/SMIG/);
        await expect.soft(hypotheses, 'bloc informatif, pas une alerte').not.toHaveClass(/\balert-(warning|danger)\b/);
        await expect.soft(
            avertissements.locator('[data-result-block="assumptions"]'),
            'hypotheses hors du bloc reglementaire',
        ).toHaveCount(0);
    });

    test('controle negatif : saisie complete, aucune mention de cout incomplet ni lien de rubrique', async ({ page }) => {
        const { carte, ligneTotal, rappel } = await ouvrirResultat(page);
        await libellesDuCas(page, []);

        await expect(carte).not.toContainText(AU_MOINS);
        await expect(carte.locator('.badge', { hasText: /Incomplet/ })).toHaveCount(0);
        await expect(liensRubrique(carte)).toHaveCount(0);
        await expect(ligneTotal).not.toContainText(AU_MOINS);
        await expect(rappel).not.toContainText(AU_MOINS);
        await expect(page.locator('[data-result-block="warnings"]', { hasText: INFO_MANQUANTE })).toHaveCount(0);
        await expect(page.locator('[data-result-block="assumptions"]', { hasText: INFO_MANQUANTE })).toHaveCount(0);
    });
});
