// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { setLocale, stabilize } = require('../helpers/simulation');
const { measureHorizontalOverflow, findFragmentedAmounts } = require('../helpers/layout');

/**
 * Cas complementaires de la page « Mon gain fiscal en janvier 2027 » (issue
 * #283, revue independante 2), en complement de la matrice de gain-2027.spec.js
 * (cas 20 000, part frais professionnels nulle).
 *
 * Deux cellules FR seulement :
 * - cas10000 (bureau 1440x900) : 10 000 MAD brut 2026. Les deux parts de la
 *   decomposition (bareme et frais professionnels) sont non nulles et
 *   affichees ; premisse lue sur le moteur (FP 2026 non plafonnes, FP 2027
 *   plafonnes) ;
 * - netcible2026 (mobile 390x844) : net cible 12 000 MAD en 2026. Brut fige
 *   visible (data-gain2027="brut-fige"), egal au salaire de base resolu par le
 *   moteur, net 2026 egal a la cible au centime, net 2027 a brut constant.
 *
 * Valeurs attendues lues sur le moteur par les routes publiques existantes
 * /api/v1/simuler/brut-vers-net et /api/v1/simuler/net-vers-brut : aucune
 * formule de paie en JS, aucun montant recopie. Seules des identites exactes
 * en centimes entiers sont verifiees.
 *
 * Parcours reel sans fixture : formulaire, resultat, lien a[data-gain2027-cta],
 * page de gain. Axe sans regle desactivee, aucun debordement, aucun montant
 * fragmente, aucune erreur console.
 *
 * Captures deterministes gain2027-<cellule>-01-page.png dans
 * tests/browser/.output/preuves-exercice/ (meme artefact que gain-2027.spec.js).
 */

const CAPTURE_DIR = path.join(__dirname, '..', '.output', 'preuves-exercice');

const BLOCKING_IMPACTS = ['critical', 'serious'];

const MONTANTS = ['gain-mensuel', 'gain-annuel', 'net-2026', 'net-2027', 'ir-2026', 'ir-2027', 'fp-2026', 'fp-2027', 'brut-2026', 'brut-2027', 'net-intermediaire', 'part-bareme', 'part-frais-pro'];

const CELLS = [
    {
        id: 'cas10000-fr-desktop-1440x900',
        viewport: { width: 1440, height: 900 },
        locale: 'fr',
        saisie: { mode: 'gross_to_net', salaire_base: 10000, type_frais_pro: 'commun' },
    },
    {
        id: 'netcible2026-fr-mobile-390x844',
        viewport: { width: 390, height: 844 },
        locale: 'fr',
        saisie: { mode: 'net_to_gross', net_cible: 12000, type_frais_pro: 'commun' },
    },
];

/** Montant au format des vues (number_format(..., 2, ',', ' ')). */
function formatMontant(montant) {
    const [entier, fraction] = Number(montant).toFixed(2).split('.');
    return `${entier.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')},${fraction}`;
}

function centimes(valeur) {
    return Math.round(Number(valeur) * 100);
}

function normaliser(texte) {
    return String(texte || '').replace(/\s+/g, ' ').trim();
}

/**
 * Axe sur toute la page, aucune regle desactivee, dans une page dediee du MEME
 * contexte (meme session, locale, viewport) chargee par la vraie URL courante.
 * Le prechargement CSSOM cross-origin d'axe (fonts.googleapis.com, bloque par
 * connect-src 'self') emet une erreur console propre a l'outil : elle reste
 * confinee a la page d'audit, fermee dans tous les cas. La page fonctionnelle
 * garde son observation console / pageerror integrale, sans filtrage.
 */
async function assertAxePage(page, where) {
    const url = page.url();
    const audit = await page.context().newPage();
    try {
        await audit.goto(url, { waitUntil: 'domcontentloaded' });
        expect(audit.url(), `${where} : audit sur la meme URL`).toBe(url);
        await audit.locator('[data-gain2027-page]').waitFor({ state: 'visible' });
        await stabilize(audit);
        const results = await new AxeBuilder({ page: audit })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
            .analyze();
        const blocking = results.violations
            .filter((v) => BLOCKING_IMPACTS.includes(v.impact))
            .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.slice(0, 5).map((n) => n.target.join(' ')) }));
        expect(blocking, `${where} : violations bloquantes\n${JSON.stringify(blocking, null, 2)}`).toEqual([]);
    } finally {
        await audit.close();
    }
}

async function brutVersNet(page, salaireBase, exercice) {
    const reponse = await page.request.post('/api/v1/simuler/brut-vers-net', {
        data: { salaire_base: salaireBase, type_frais_pro: 'commun', exercice },
    });
    expect(reponse.ok(), `/api/v1/simuler/brut-vers-net ${exercice}`).toBe(true);
    const r = await reponse.json();
    expect(r.exercice).toBe(exercice);
    return r;
}

async function netVersBrut(page, netCible, exercice) {
    const reponse = await page.request.post('/api/v1/simuler/net-vers-brut', {
        data: { net_cible: netCible, type_frais_pro: 'commun', exercice },
    });
    expect(reponse.ok(), `/api/v1/simuler/net-vers-brut ${exercice}`).toBe(true);
    const r = await reponse.json();
    expect(r.exercice).toBe(exercice);
    expect(r.resolution_net.converge, 'premisse : cible atteinte par le moteur').toBe(true);
    return r;
}

async function nbMois(page) {
    const reponse = await page.request.get('/api/v1/parametres');
    expect(reponse.ok()).toBe(true);
    return (await reponse.json()).ir.nb_mois;
}

async function montant(page, cle) {
    const el = page.locator(`[data-gain2027="${cle}"]`);
    await expect(el, `montant ${cle} unique`).toHaveCount(1);
    await el.scrollIntoViewIfNeeded();
    await expect(el, `montant ${cle} visible`).toBeVisible();
    const valeur = Number(await el.getAttribute('data-montant'));
    const texte = normaliser(await el.textContent());
    expect(texte, `${cle} : montant lisible au format des vues`).toContain(formatMontant(valeur));
    return valeur;
}

/** Valeurs attendues lues sur le moteur, par cellule. */
async function attendus(page, cell) {
    if (cell.saisie.mode === 'gross_to_net') {
        return {
            r2026: await brutVersNet(page, cell.saisie.salaire_base, 2026),
            r2027: await brutVersNet(page, cell.saisie.salaire_base, 2027),
            base: cell.saisie.salaire_base,
        };
    }
    const resolu = await netVersBrut(page, cell.saisie.net_cible, 2026);
    const base = resolu.input.salaire_base;
    return { r2026: resolu, r2027: await brutVersNet(page, base, 2027), base };
}

for (const cell of CELLS) {
    test.describe(`Gain 2027 cas - ${cell.id}`, () => {
        test.use({ viewport: cell.viewport });

        test('formulaire, resultat, page de gain', async ({ page }) => {
            const erreurs = [];
            page.on('console', (m) => { if (m.type() === 'error') erreurs.push(`console: ${m.text()}`); });
            page.on('pageerror', (e) => erreurs.push(`page: ${e.message}`));

            await setLocale(page, cell.locale);
            const { r2026, r2027, base } = await attendus(page, cell);
            const mois = await nbMois(page);

            // 1. Formulaire reel, exercice 2026.
            await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            if (cell.saisie.mode === 'net_to_gross') {
                await page.locator('label[for="modeNetToGross"]').click();
                await expect(page.locator('#net_cible'), 'champ net cible affiche').toBeVisible();
                await page.locator('#net_cible').fill(String(cell.saisie.net_cible));
            } else {
                await page.locator('#salaire_base').fill(String(cell.saisie.salaire_base));
            }
            await page.locator('select[name="exercice"]').selectOption('2026');
            await Promise.all([
                page.waitForURL('**/calculateur/calculer'),
                page.locator('#payrollForm .quick-submit-panel button[type="submit"]').click(),
            ]);
            await page.locator('h1').first().waitFor({ state: 'visible' });
            await stabilize(page);

            // 2. Lien vers la page de gain.
            const cta = page.locator('a[data-gain2027-cta]');
            await expect(cta, 'un lien vers le gain 2027').toHaveCount(1);
            await cta.scrollIntoViewIfNeeded();
            await Promise.all([
                page.waitForURL('**/calculateur/gain-2027**'),
                cta.click(),
            ]);
            await page.locator('[data-gain2027-page]').waitFor({ state: 'visible' });
            await stabilize(page);

            // 3. Page de gain.
            const racine = page.locator('[data-gain2027-page]');
            await expect(racine).toHaveAttribute('data-source-exercice', '2026');
            await expect(racine).toHaveAttribute('data-mode', cell.saisie.mode);

            const v = {};
            for (const cle of MONTANTS) v[cle] = await montant(page, cle);

            expect(centimes(v['net-2026']), 'net 2026 du moteur').toBe(centimes(r2026.salaire_net));
            expect(centimes(v['net-2027']), 'net 2027 du moteur').toBe(centimes(r2027.salaire_net));
            expect(centimes(v['ir-2026'])).toBe(centimes(r2026.ir_net));
            expect(centimes(v['ir-2027'])).toBe(centimes(r2027.ir_net));
            expect(centimes(v['fp-2026'])).toBe(centimes(r2026.frais_pro));
            expect(centimes(v['fp-2027'])).toBe(centimes(r2027.frais_pro));
            expect(centimes(v['brut-2026']), 'brut constant').toBe(centimes(r2026.salaire_brut_total));
            expect(centimes(v['brut-2027']), 'brut constant').toBe(centimes(r2026.salaire_brut_total));

            const gain = centimes(v['gain-mensuel']);
            expect(gain, 'gain = net 2027 - net 2026').toBe(centimes(r2027.salaire_net) - centimes(r2026.salaire_net));
            expect(centimes(v['part-bareme']), 'part bareme = intermediaire - net 2026').toBe(centimes(v['net-intermediaire']) - centimes(r2026.salaire_net));
            expect(centimes(v['part-frais-pro']), 'part frais pro = net 2027 - intermediaire').toBe(centimes(r2027.salaire_net) - centimes(v['net-intermediaire']));
            expect(centimes(v['part-bareme']) + centimes(v['part-frais-pro']), 'bareme + frais pro = gain').toBe(gain);
            expect(centimes(v['gain-annuel']), 'projection = nb_mois x gain mensuel').toBe(gain * mois);

            if (cell.saisie.mode === 'gross_to_net') {
                // Premisses du moteur, puis deux parts non nulles a l'ecran.
                expect(r2026.fp_plafonne, 'premisse : FP 2026 sous le plafond a 10 000').toBe(false);
                expect(r2027.fp_plafonne, 'premisse : FP 2027 plafonnes a 10 000').toBe(true);
                expect(centimes(v['part-bareme']), 'part bareme non nulle').toBeGreaterThan(0);
                expect(centimes(v['part-frais-pro']), 'part frais professionnels non nulle').toBeGreaterThan(0);
                await expect(page.locator('[data-gain2027="brut-fige"]'), 'aucun brut fige en brut vers net').toHaveCount(0);
            } else {
                // Net cible 2026 : brut resolu une fois par le moteur, puis fige.
                const brutFige = await montant(page, 'brut-fige');
                expect(centimes(brutFige), 'brut fige = salaire de base resolu').toBe(centimes(base));
                expect(Math.abs(centimes(v['net-2026']) - centimes(cell.saisie.net_cible)), 'net 2026 = cible au centime').toBeLessThanOrEqual(1);
                await expect(page.getByText(formatMontant(cell.saisie.net_cible), { exact: false }).first(), 'net cible rappele').toBeVisible();
            }

            // Le gain mensuel est lu en premier.
            const hauts = await page.locator('[data-gain2027]').evaluateAll((els) => els.map((el) => ({
                cle: el.getAttribute('data-gain2027'),
                top: el.getBoundingClientRect().top + window.scrollY,
            })));
            expect(hauts.reduce((a, b) => (b.top < a.top ? b : a)).cle, 'gain mensuel en tete').toBe('gain-mensuel');

            await expect(page.locator('[data-fiscal-year]')).toHaveCount(2);
            await expect(page.locator('[data-fiscal-year="2027"]')).toHaveAttribute('data-fiscal-status', 'projet');

            expect(await findFragmentedAmounts(page), 'aucun montant coupe sur deux lignes').toEqual([]);
            const overflow = await measureHorizontalOverflow(page);
            expect(overflow.overflow, `debordement horizontal de ${overflow.overflow}px`).toBeLessThanOrEqual(1);
            await assertAxePage(page, `page de gain ${cell.id}`);

            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: path.join(CAPTURE_DIR, `gain2027-${cell.id}-01-page.png`), fullPage: true });

            expect(erreurs, `aucune erreur console\n${erreurs.join('\n')}`).toEqual([]);
        });
    });
}
