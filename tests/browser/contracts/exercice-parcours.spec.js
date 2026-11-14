// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { setLocale, openResultPage, stabilize } = require('../helpers/simulation');
const { measureHorizontalOverflow } = require('../helpers/layout');

/**
 * Parcours de l'exercice fiscal dans l'application reelle (issue #281).
 *
 * L'application servie est celle de production, sans aucune fixture : seul
 * l'exercice 2026 y est calculable. Ce contrat le verifie et n'active jamais
 * un profil futur. Les profils futurs rendus calculables par une configuration
 * technique ne sont exerces que par PHPUnit (tests/Feature/ExerciceParcoursTest).
 *
 * Parcours : formulaire reel (choix 2026 et soumission), resultat, lien de
 * partage puis reprise, comparaison d'une simulation sans exercice avec la meme
 * simulation en 2026 explicite (aucun ecart d'entree), page Fiabilite. Chaque
 * etape verifie l'absence de debordement horizontal ; le resultat est aussi
 * verifie a l'impression. Les libelles traduits ne sont pas recopies ici : le
 * contrat porte sur les marqueurs data-fiscal-year / data-fiscal-status, les
 * etats du champ et la geometrie.
 *
 * Les cellules marquees `capture` produisent des captures deterministes
 * relues comme preuves, dans tests/browser/.output/preuves-exercice/ (publiees
 * par la CI dans l'artefact preuves-exercice).
 */

const CAPTURE_DIR = path.join(__dirname, '..', '.output', 'preuves-exercice');

/** Le zoom 200 % est reproduit par le viewport CSS equivalent (voir helpers/matrix.js). */
const CELLS = [
    { id: 'fr-desktop-1440x900', viewport: { width: 1440, height: 900 }, locale: 'fr', capture: true, print: true },
    { id: 'en-laptop-1280x800', viewport: { width: 1280, height: 800 }, locale: 'en' },
    { id: 'es-laptop-1280x800', viewport: { width: 1280, height: 800 }, locale: 'es' },
    { id: 'fr-mobile-390x844', viewport: { width: 390, height: 844 }, locale: 'fr' },
    { id: 'fr-zoom200-720x450', viewport: { width: 720, height: 450 }, locale: 'fr' },
    { id: 'ar-rtl-mobile-390x844', viewport: { width: 390, height: 844 }, locale: 'ar', capture: true },
    { id: 'ar-rtl-desktop-1440x900', viewport: { width: 1440, height: 900 }, locale: 'ar' },
];

/** Exercice de repli, seul calculable dans l'application reelle. */
const EXERCICE_REPLI = '2026';

const BLOCKING_IMPACTS = ['critical', 'serious'];

/** Relatif a l'application : le lien partage peut porter l'URL absolue de APP_URL. */
function relatif(url) {
    const u = new URL(url, 'http://localhost');
    return `${u.pathname}${u.search}`;
}

async function capture(page, cell, step) {
    if (!cell.capture) return;
    await page.screenshot({ path: path.join(CAPTURE_DIR, `${cell.id}-${step}.png`), fullPage: true });
}

async function assertNoOverflow(page, where) {
    const overflow = await measureHorizontalOverflow(page);
    expect(
        overflow.overflow,
        `${where} : debordement horizontal de ${overflow.overflow}px `
        + `(scrollWidth ${overflow.scrollWidth} > clientWidth ${overflow.clientWidth}).`,
    ).toBeLessThanOrEqual(1);
}

async function assertAxeClean(page, selector, where) {
    const results = await new AxeBuilder({ page })
        .include(selector)
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    const blocking = results.violations
        .filter((violation) => BLOCKING_IMPACTS.includes(violation.impact))
        .map((violation) => ({ id: violation.id, impact: violation.impact, nodes: violation.nodes.slice(0, 5).map((n) => n.target.join(' ')) }));

    expect(blocking, `${where} : violations bloquantes\n${JSON.stringify(blocking, null, 2)}`).toEqual([]);
}

/** Etat du champ exercice tel que rendu par le formulaire. */
async function readExerciceField(page) {
    const select = page.locator('select[name="exercice"]');
    await expect(select, 'le formulaire doit proposer un champ exercice').toHaveCount(1);
    await expect(select).toBeVisible();

    return select.evaluate((el) => ({
        value: /** @type {HTMLSelectElement} */ (el).value,
        required: el.hasAttribute('required'),
        options: Array.from(/** @type {HTMLSelectElement} */ (el).options).map((o) => ({
            value: o.value,
            disabled: o.disabled,
            selected: o.selected,
        })),
    }));
}

/** Bloc de provenance unique d'un exercice, visible, dans une zone donnee. */
async function assertProvenance(scope, exercice, statut, where) {
    const bloc = scope.locator(`[data-fiscal-year="${exercice}"]`);
    await expect(bloc, `${where} : un bloc de provenance ${exercice}`).toHaveCount(1);
    await expect(bloc).toBeVisible();
    await expect(bloc).toHaveAttribute('data-fiscal-status', statut);

    // Un projet date sa version source ; le profil en vigueur n'a pas de date de version.
    const dates = bloc.locator('time[datetime]');
    if (statut === 'projet') {
        expect(await dates.count(), `${where} : date du texte source du projet ${exercice}`).toBeGreaterThan(0);
    } else {
        await expect(dates, `${where} : aucune date de projet pour ${exercice}`).toHaveCount(0);
    }

    return bloc;
}

for (const cell of CELLS) {
    test.describe(`Exercice fiscal - ${cell.id}`, () => {
        test.use({ viewport: cell.viewport });

        test('formulaire, resultat, partage, comparaison et Fiabilite restent en 2026', async ({ page }) => {
            // 1. Formulaire reel : 2026 seul activable, selectionne par defaut.
            await setLocale(page, cell.locale);
            await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
            await stabilize(page);

            const champ = await readExerciceField(page);
            const declares = champ.options.map((o) => o.value);
            expect(champ.value, 'repli 2026 selectionne').toBe(EXERCICE_REPLI);
            expect(champ.required, 'un choix explicite est toujours exige').toBe(true);
            expect(declares.length, 'plusieurs exercices declares').toBeGreaterThan(1);
            expect(declares).not.toContain('');
            expect(
                champ.options.filter((o) => !o.disabled).map((o) => o.value),
                'aucun profil futur activable dans l\'application reelle',
            ).toEqual([EXERCICE_REPLI]);

            await assertNoOverflow(page, 'formulaire');
            await assertAxeClean(page, 'select[name="exercice"]', 'champ exercice');
            await page.locator('select[name="exercice"]').scrollIntoViewIfNeeded();
            await capture(page, cell, '01-formulaire');

            await page.locator('#salaire_base').fill('10000');
            await page.locator('select[name="exercice"]').selectOption(EXERCICE_REPLI);
            await Promise.all([
                page.waitForURL('**/calculateur/calculer'),
                page.locator('#payrollForm .quick-submit-panel button[type="submit"]').click(),
            ]);
            await page.locator('h1').first().waitFor({ state: 'visible' });
            await stabilize(page);

            // 2. Resultat : un seul bloc de provenance, celui de 2026 en vigueur.
            await expect(page.locator('[data-fiscal-year]')).toHaveCount(1);
            await assertProvenance(page, EXERCICE_REPLI, 'en_vigueur', 'resultat');
            await assertNoOverflow(page, 'resultat');
            await assertAxeClean(page, '[data-fiscal-year]', 'provenance du resultat');
            await capture(page, cell, '02-resultat');

            if (cell.print) {
                await page.emulateMedia({ media: 'print' });
                await stabilize(page);
                await assertProvenance(page, EXERCICE_REPLI, 'en_vigueur', 'resultat imprime');
                await assertNoOverflow(page, 'resultat imprime');
                await capture(page, cell, '03-resultat-impression');
                await page.emulateMedia({ media: 'screen' });
            }

            // 3. Partage puis reprise : l'exercice revient selectionne dans le formulaire.
            const lienPartage = relatif(await page.locator('#shareUrl').inputValue());
            expect(lienPartage).toContain('s=');
            await page.goto(lienPartage, { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            expect((await readExerciceField(page)).value, 'exercice repris depuis le lien').toBe(EXERCICE_REPLI);
            await assertNoOverflow(page, 'formulaire repris');

            // 4. Comparaison : sans exercice contre 2026 explicite, memes autres entrees.
            await openResultPage(page, { locale: cell.locale });
            const sansExercice = new URL(await page.locator('#shareUrl').inputValue()).searchParams.get('s');
            await openResultPage(page, { locale: cell.locale, fields: { exercice: EXERCICE_REPLI } });
            const avecExercice = new URL(await page.locator('#shareUrl').inputValue()).searchParams.get('s');
            expect(sansExercice, 'lien sans exercice').toBeTruthy();
            expect(avecExercice, 'les deux liens different par le seul exercice explicite').not.toBe(sansExercice);

            await page.goto(
                `/calculateur/comparer?a=${encodeURIComponent(String(sansExercice))}&b=${encodeURIComponent(String(avecExercice))}`,
                { waitUntil: 'domcontentloaded' },
            );
            await page.waitForURL('**/calculateur/comparer**');
            await stabilize(page);

            const entrees = page.locator('section[aria-labelledby="cmp-inputs-title"]');
            await expect(entrees).toHaveCount(1);
            await expect(entrees.locator('table'), 'entrees identiques : aucun tableau d\'ecarts').toHaveCount(0);
            const blocs = entrees.locator('[data-fiscal-year]');
            await expect(blocs, 'un bloc de provenance par scenario').toHaveCount(2);
            for (const index of [0, 1]) {
                await expect(blocs.nth(index)).toHaveAttribute('data-fiscal-year', EXERCICE_REPLI);
                await expect(blocs.nth(index)).toHaveAttribute('data-fiscal-status', 'en_vigueur');
                await expect(blocs.nth(index)).toBeVisible();
            }
            await assertNoOverflow(page, 'comparaison');
            await assertAxeClean(page, 'section[aria-labelledby="cmp-inputs-title"] [data-fiscal-year]', 'provenance de la comparaison');
            await capture(page, cell, '04-comparaison');

            // 5. Fiabilite : un bloc par exercice declare, 2026 en vigueur, les autres en projet date.
            await page.goto('/fiabilite', { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            await expect(page.locator('[data-fiscal-year]')).toHaveCount(declares.length);
            for (const exercice of declares) {
                await assertProvenance(page, exercice, exercice === EXERCICE_REPLI ? 'en_vigueur' : 'projet', `Fiabilite ${exercice}`);
            }
            await assertNoOverflow(page, 'Fiabilite');
            await assertAxeClean(page, '#trust-fiscal-years [data-fiscal-year]', 'exercices de la page Fiabilite');
            await page.locator('#trust-fiscal-years').scrollIntoViewIfNeeded();
            await capture(page, cell, '05-fiabilite');
        });
    });
}
