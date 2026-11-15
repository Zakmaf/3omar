// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { setLocale, stabilize } = require('../helpers/simulation');
const { measureHorizontalOverflow, findFragmentedAmounts } = require('../helpers/layout');

/**
 * Page « Mon gain fiscal en janvier 2027 » dans l'application reelle (issue #283).
 *
 * Parcours de chaque cellule, sans fixture :
 * 1. resultat reel 2026 (POST /calculateur/calculer, 20 000 MAD synthetiques) ;
 * 2. lien a[data-gain2027-cta] vers /calculateur/gain-2027?s=... ;
 * 3. page de gain : montants (data-gain2027 / data-montant) compares aux
 *    resultats du moteur obtenus par la route publique
 *    /api/v1/simuler/brut-vers-net pour 2026 et 2027 ; la decomposition est
 *    verifiee par ses identites exactes au centime (aucune valeur recopiee) ;
 *    titres accessibles, provenance 2026 / 2027 datee, tableaux atteints au
 *    clavier, axe sur toute la page sans regle desactivee, aucun debordement,
 *    aucun montant fragmente, montants lisibles en RTL ;
 * 4. impression (cellules `print`, FR et AR) : montants visibles, partage masque ;
 * 5. lien de partage rejoue : meme comparaison.
 * Aucune erreur console ni exception de page sur tout le parcours.
 *
 * Captures deterministes prefixees gain2027- dans
 * tests/browser/.output/preuves-exercice/ (artefact preuves-exercice existant).
 *
 * Limitation de debit : voir l'en-tete de exercice-parcours.spec.js
 * (CACHE_STORE=array), ce contrat ne prouve pas throttle:calculer.
 */

const CAPTURE_DIR = path.join(__dirname, '..', '.output', 'preuves-exercice');

/** Le zoom 200 % est reproduit par le viewport CSS equivalent (voir helpers/matrix.js). */
const CELLS = [
    { id: 'fr-desktop-1440x900', viewport: { width: 1440, height: 900 }, locale: 'fr', capture: true, print: true },
    { id: 'en-laptop-1280x800', viewport: { width: 1280, height: 800 }, locale: 'en' },
    { id: 'es-laptop-1280x800', viewport: { width: 1280, height: 800 }, locale: 'es' },
    { id: 'fr-mobile-390x844', viewport: { width: 390, height: 844 }, locale: 'fr', capture: true },
    { id: 'fr-zoom200-720x450', viewport: { width: 720, height: 450 }, locale: 'fr', capture: true },
    { id: 'ar-rtl-mobile-390x844', viewport: { width: 390, height: 844 }, locale: 'ar', capture: true },
    { id: 'ar-rtl-desktop-1440x900', viewport: { width: 1440, height: 900 }, locale: 'ar', capture: true, print: true },
];

/** Saisie synthetique : cas d'acceptation de l'issue. */
const SAISIE = { mode: 'gross_to_net', salaire_base: 20000, type_frais_pro: 'commun' };

const BLOCKING_IMPACTS = ['critical', 'serious'];

/** Montant au format des vues (number_format(..., 2, ',', ' ')). */
function formatMontant(montant) {
    const [entier, fraction] = Number(montant).toFixed(2).split('.');
    return `${entier.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')},${fraction}`;
}

/** Centimes entiers, pour des identites exactes sans flottant. */
function centimes(valeur) {
    return Math.round(Number(valeur) * 100);
}

function normaliser(texte) {
    return String(texte || '').replace(/\s+/g, ' ').trim();
}

function relatif(url) {
    const u = new URL(url, 'http://localhost');
    return `${u.pathname}${u.search}`;
}

async function capture(page, cell, step) {
    if (!cell.capture) return;
    await page.screenshot({ path: path.join(CAPTURE_DIR, `gain2027-${cell.id}-${step}.png`), fullPage: true });
}

async function assertNoOverflow(page, where) {
    const overflow = await measureHorizontalOverflow(page);
    expect(overflow.overflow, `${where} : debordement horizontal de ${overflow.overflow}px`).toBeLessThanOrEqual(1);
}

/** Axe sur toute la page, aucune regle desactivee. */
async function assertAxePage(page, where) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();
    const blocking = results.violations
        .filter((v) => BLOCKING_IMPACTS.includes(v.impact))
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.slice(0, 5).map((n) => n.target.join(' ')) }));
    expect(blocking, `${where} : violations bloquantes\n${JSON.stringify(blocking, null, 2)}`).toEqual([]);
}

/** Resultat du moteur via la route publique, pour un exercice donne. */
async function moteur(page, exercice) {
    const reponse = await page.request.post('/api/v1/simuler/brut-vers-net', {
        data: { salaire_base: SAISIE.salaire_base, type_frais_pro: SAISIE.type_frais_pro, exercice },
    });
    expect(reponse.ok(), `/api/v1/simuler/brut-vers-net ${exercice}`).toBe(true);
    const r = await reponse.json();
    expect(r.exercice, `exercice applique ${exercice}`).toBe(exercice);
    return r;
}

async function nbMois(page) {
    const reponse = await page.request.get('/api/v1/parametres');
    expect(reponse.ok()).toBe(true);
    return (await reponse.json()).ir.nb_mois;
}

/** Lit un montant de la page : valeur machine et texte visible. */
async function montant(page, cle) {
    const el = page.locator(`[data-gain2027="${cle}"]`);
    await expect(el, `montant ${cle} unique`).toHaveCount(1);
    await expect(el, `montant ${cle} visible`).toBeVisible();
    return {
        el,
        valeur: Number(await el.getAttribute('data-montant')),
        texte: normaliser(await el.textContent()),
    };
}

/**
 * Zone defilante d'un tableau : region nommee par le titre de sa section,
 * atteinte par de vraies frappes Tab, focus visible, defilement aux fleches
 * si elle deborde (meme methode que exercice-parcours.spec.js).
 */
async function assertRegionDefilableAuClavier(page, titreId, where) {
    const region = page.locator(`section[aria-labelledby="${titreId}"] [role="region"][data-table-scroll]`);
    await expect(region, `${where} : une zone defilante`).toHaveCount(1);
    await expect(region).toHaveAttribute('tabindex', '0');
    await expect(region).toHaveAttribute('aria-labelledby', titreId);
    const nom = normaliser(await page.locator(`#${titreId}`).textContent());
    expect(nom, `${where} : titre non vide`).not.toBe('');
    await expect(region).toHaveAccessibleName(nom);

    await page.evaluate(() => /** @type {HTMLElement} */ (document.activeElement)?.blur());
    let atteinte = false;
    for (let i = 0; i < 150 && !atteinte; i++) {
        await page.keyboard.press('Tab');
        atteinte = await region.evaluate((el) => el === document.activeElement);
    }
    expect(atteinte, `${where} : zone atteinte par Tab`).toBe(true);

    const focus = await region.evaluate((el) => {
        const s = getComputedStyle(el);
        return { style: s.outlineStyle, width: parseFloat(s.outlineWidth) || 0 };
    });
    expect(focus.style, `${where} : focus visible`).not.toBe('none');
    expect(focus.width).toBeGreaterThan(0);

    const g = await region.evaluate((el) => ({
        debordement: el.scrollWidth - el.clientWidth,
        depart: el.scrollLeft,
        rtl: getComputedStyle(el).direction === 'rtl',
    }));
    if (g.debordement > 1) {
        const avance = g.rtl ? 'ArrowLeft' : 'ArrowRight';
        for (let i = 0; i < 5; i++) await page.keyboard.press(avance);
        await expect.poll(
            () => region.evaluate((el, d) => Math.abs(el.scrollLeft - d), g.depart),
            { message: `${where} : defilement au clavier` },
        ).toBeGreaterThan(0);
        await region.evaluate((el, d) => { el.scrollLeft = d; }, g.depart);
    }
}

for (const cell of CELLS) {
    test.describe(`Gain 2027 - ${cell.id}`, () => {
        test.use({ viewport: cell.viewport });

        test('resultat, page de gain, impression et partage rejoue', async ({ page }) => {
            const erreurs = [];
            page.on('console', (m) => { if (m.type() === 'error') erreurs.push(`console: ${m.text()}`); });
            page.on('pageerror', (e) => erreurs.push(`page: ${e.message}`));

            await setLocale(page, cell.locale);
            const [r2026, r2027, mois] = [await moteur(page, 2026), await moteur(page, 2027), await nbMois(page)];

            // 1. Resultat reel 2026 par le formulaire.
            await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            await page.locator('#salaire_base').fill(String(SAISIE.salaire_base));
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
            await expect(cta).toBeVisible();
            expect(normaliser(await cta.textContent()), 'lien nomme').not.toBe('');
            await Promise.all([
                page.waitForURL('**/calculateur/gain-2027**'),
                cta.click(),
            ]);
            await page.locator('[data-gain2027-page]').waitFor({ state: 'visible' });
            await stabilize(page);

            // 3. Page de gain.
            const racine = page.locator('[data-gain2027-page]');
            await expect(racine).toHaveAttribute('data-source-exercice', '2026');
            await expect(racine).toHaveAttribute('data-mode', 'gross_to_net');
            await expect(page.getByRole('heading', { level: 1 }), 'un titre de page').toHaveCount(1);
            expect(normaliser(await page.getByRole('heading', { level: 1 }).textContent())).not.toBe('');

            const valeurs = {};
            for (const cle of ['gain-mensuel', 'gain-annuel', 'net-2026', 'net-2027', 'ir-2026', 'ir-2027', 'fp-2026', 'fp-2027', 'brut-2026', 'brut-2027', 'net-intermediaire', 'part-bareme', 'part-frais-pro']) {
                const m = await montant(page, cle);
                expect(m.texte, `${cle} : montant lisible au format des vues`).toContain(formatMontant(m.valeur));
                valeurs[cle] = m;
            }

            // Valeurs du moteur (route publique), jamais recopiees.
            expect(centimes(valeurs['net-2026'].valeur), 'net 2026 du moteur').toBe(centimes(r2026.salaire_net));
            expect(centimes(valeurs['net-2027'].valeur), 'net 2027 du moteur').toBe(centimes(r2027.salaire_net));
            expect(centimes(valeurs['ir-2026'].valeur)).toBe(centimes(r2026.ir_net));
            expect(centimes(valeurs['ir-2027'].valeur)).toBe(centimes(r2027.ir_net));
            expect(centimes(valeurs['fp-2026'].valeur)).toBe(centimes(r2026.frais_pro));
            expect(centimes(valeurs['fp-2027'].valeur)).toBe(centimes(r2027.frais_pro));
            expect(centimes(valeurs['brut-2026'].valeur), 'brut constant').toBe(centimes(r2026.salaire_brut_total));
            expect(centimes(valeurs['brut-2027'].valeur), 'brut constant').toBe(centimes(r2026.salaire_brut_total));

            // Identites exactes au centime.
            const gain = centimes(valeurs['gain-mensuel'].valeur);
            expect(gain, 'gain = net 2027 - net 2026').toBe(centimes(r2027.salaire_net) - centimes(r2026.salaire_net));
            expect(centimes(valeurs['part-bareme'].valeur) + centimes(valeurs['part-frais-pro'].valeur), 'bareme + frais pro = gain').toBe(gain);
            expect(centimes(valeurs['net-intermediaire'].valeur) - centimes(r2026.salaire_net), 'part bareme').toBe(centimes(valeurs['part-bareme'].valeur));
            expect(centimes(valeurs['gain-annuel'].valeur), 'projection = nb_mois x gain mensuel').toBe(gain * mois);

            // Le gain mensuel est lu en premier : au-dessus de tous les autres montants.
            const hauts = await page.locator('[data-gain2027]').evaluateAll((els) => els.map((el) => ({
                cle: el.getAttribute('data-gain2027'),
                top: el.getBoundingClientRect().top + window.scrollY,
            })));
            const premier = hauts.reduce((a, b) => (b.top < a.top ? b : a));
            expect(premier.cle, 'gain mensuel en tete').toBe('gain-mensuel');

            // Provenance : 2026 en vigueur, 2027 projet date, rien au-dela.
            await expect(page.locator('[data-fiscal-year]')).toHaveCount(2);
            await expect(page.locator('[data-fiscal-year="2026"]')).toHaveAttribute('data-fiscal-status', 'en_vigueur');
            const bloc2027 = page.locator('[data-fiscal-year="2027"]');
            await expect(bloc2027).toHaveAttribute('data-fiscal-status', 'projet');
            expect(await bloc2027.locator('time[datetime]').count(), 'date du texte source').toBeGreaterThan(0);

            // Lisibilite des nombres, RTL compris.
            expect(await findFragmentedAmounts(page), 'aucun montant coupe sur deux lignes').toEqual([]);
            if (cell.locale === 'ar') {
                const directions = await page.locator('[data-gain2027]').evaluateAll((els) => els.map((el) => getComputedStyle(el).direction));
                expect(new Set(directions), 'montants isoles de gauche a droite en RTL').toEqual(new Set(['ltr']));
            }

            await assertRegionDefilableAuClavier(page, 'gain2027-details-title', 'tableau 2026 / 2027');
            await assertRegionDefilableAuClavier(page, 'gain2027-decomposition-title', 'decomposition');
            await assertNoOverflow(page, 'page de gain');
            await assertAxePage(page, 'page de gain');
            await page.evaluate(() => window.scrollTo(0, 0));
            await capture(page, cell, '01-page');

            // 4. Impression.
            if (cell.print) {
                await page.emulateMedia({ media: 'print' });
                await stabilize(page);
                for (const cle of ['gain-mensuel', 'gain-annuel', 'part-bareme', 'part-frais-pro']) {
                    await expect(page.locator(`[data-gain2027="${cle}"]`), `${cle} imprime`).toBeVisible();
                }
                await expect(page.locator('#gain2027ShareUrl'), 'partage non imprime').toBeHidden();
                await expect(page.locator('[data-fiscal-year="2027"]'), 'provenance imprimee').toBeVisible();
                await assertNoOverflow(page, 'page de gain imprimee');
                await capture(page, cell, '02-impression');
                await page.emulateMedia({ media: 'screen' });
                await stabilize(page);
            }

            // 5. Partage rejoue.
            const partage = page.locator('#gain2027ShareUrl');
            await expect(partage).toHaveCount(1);
            const lien = relatif(await partage.inputValue());
            expect(lien).toContain('/calculateur/gain-2027?s=');
            await page.goto(lien, { waitUntil: 'domcontentloaded' });
            await page.locator('[data-gain2027-page]').waitFor({ state: 'visible' });
            await stabilize(page);
            expect(centimes((await montant(page, 'gain-mensuel')).valeur), 'le partage rejoue le meme gain').toBe(gain);
            expect(centimes((await montant(page, 'net-2026')).valeur)).toBe(centimes(r2026.salaire_net));
            await assertNoOverflow(page, 'page de gain rejouee');

            expect(erreurs, `aucune erreur console\n${erreurs.join('\n')}`).toEqual([]);
        });
    });
}
