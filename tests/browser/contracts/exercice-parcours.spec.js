// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { setLocale, openResultPage, stabilize } = require('../helpers/simulation');
const { measureHorizontalOverflow } = require('../helpers/layout');

/**
 * Parcours de l'exercice fiscal dans l'application reelle (issues #281 et #282).
 *
 * L'application servie est celle de production, sans aucune fixture : 2026 (en
 * vigueur) et 2027 (projet, frais professionnels proposes, issue #282) y sont
 * calculables ; 2028 a 2031 ne le sont pas. Chaque cellule execute deux
 * parcours reels :
 *
 * - parcours 2026 (#281) : formulaire reel (choix 2026 et soumission),
 *   resultat, lien de partage puis reprise, comparaison d'une simulation sans
 *   exercice avec la meme simulation en 2026 explicite (aucun ecart d'entree),
 *   page Fiabilite ;
 * - parcours 2027 (#282) : choix explicite de 2027 dans le formulaire reel,
 *   bulletin au taux unique propose (valeurs lues sur /api/v1/parametres),
 *   lien de partage puis reprise, meme saisie recalculee en 2026, comparaison
 *   2026 / 2027 dont la ligne categorie professionnelle reste neutre (aucun
 *   taux dans les libelles de categorie), page Fiabilite avec la note des
 *   frais professionnels proposes.
 *
 * Les cas limites et les variantes de configuration de 2027 restent couverts
 * par PHPUnit (tests/Feature/FraisPro2027AffichageTest, FraisPro2027CategoriesWebTest,
 * FraisProCategorieLibellesTest). Chaque etape verifie l'absence de debordement
 * horizontal ; dans les cellules `print`, les resultats sont aussi verifies a
 * l'impression. Les libelles
 * traduits ne sont pas recopies ici : le contrat porte sur les marqueurs
 * data-fiscal-year / data-fiscal-status, les etats des champs, les valeurs
 * lues sur la route publique des parametres, les libelles rendus par le
 * formulaire lui-meme et la geometrie.
 *
 * Limitation de debit : le serveur de test tourne avec CACHE_STORE=array
 * (.github/workflows/ci.yml, docs/DEVELOPPEMENT.md), qui ne conserve pas
 * l'etat du limiteur d'une requete a l'autre. Ce contrat ne verifie donc pas
 * throttle:calculer ; il ne doit pas etre lu comme une preuve de son
 * comportement avec un cache persistant.
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

/** Exercice de repli, en vigueur. */
const EXERCICE_REPLI = '2026';

/** Exercices calculables dans l'application reelle : repli et projet 2027 (#282). */
const EXERCICES_ACTIVABLES = [EXERCICE_REPLI, '2027'];

/** Projet dont le parcours reel est verifie de bout en bout (#282). */
const EXERCICE_PROJET = '2027';

/** Montant au format des vues (number_format(..., ',', ' ')) : 2 916,67. */
function formatMontant(montant, decimales) {
    const [entier, fraction] = Number(montant).toFixed(decimales).split('.');
    const groupe = entier.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return fraction ? `${groupe},${fraction}` : groupe;
}

/** Taux au format des vues : 0.45 donne 45, 0.355 donne 35,5. */
function formatTaux(taux) {
    return formatMontant(Number(taux) * 100, 2).replace(/,?0+$/, '');
}

/** Espaces normalises (y compris insecables) pour comparer des textes rendus. */
function normaliser(texte) {
    return String(texte || '').replace(/\s+/g, ' ').trim();
}

/**
 * Valeurs attendues lues sur la route publique /api/v1/parametres, source
 * unique cote application (config/payroll.php) : aucun taux n'est recopie ici.
 */
async function attenduProjet(page) {
    const reponse = await page.request.get('/api/v1/parametres');
    expect(reponse.ok(), '/api/v1/parametres repond').toBe(true);
    const parametres = await reponse.json();

    const fraisPro = parametres.exercices[EXERCICE_PROJET].frais_pro;
    expect(fraisPro, 'profil 2027 a taux unique complet').toEqual(expect.objectContaining({
        taux: expect.any(Number),
        plafond_annuel: expect.any(Number),
        reference: expect.any(String),
    }));

    const plafondMensuel = Math.round((fraisPro.plafond_annuel / parametres.ir.nb_mois) * 100) / 100;

    return {
        taux: formatTaux(fraisPro.taux),
        plafondMensuel: formatMontant(plafondMensuel, 2),
        plafondAnnuel: formatMontant(fraisPro.plafond_annuel, 0),
        reference: fraisPro.reference,
        // Seuil mensuel 2026 (cle historique) : ne doit jamais apparaitre dans le detail 2027.
        seuil2026: formatMontant(parametres.frais_pro.seuil_mensuel, 0),
    };
}

/**
 * Ligne des frais professionnels du bulletin : premiere ligne de la section
 * impot (libelle et detail, assiette, taux, montant deduit).
 */
async function ligneFraisPro(page) {
    const ligne = page.locator('tr.row-impot').first();
    await expect(ligne).toBeVisible();
    const cellules = ligne.locator('td');

    return {
        ligne,
        detail: normaliser(await ligne.locator('td').first().locator('small').textContent()),
        taux: normaliser(await cellules.nth(2).textContent()),
        montant: normaliser(await cellules.nth(3).textContent()),
    };
}

/** Le bulletin applique le taux unique du projet, plafonne, sans le seuil 2026. */
async function assertFraisProProjet(page, attendu, where) {
    const fp = await ligneFraisPro(page);
    expect(fp.taux, `${where} : taux du projet`).toBe(`${attendu.taux}%`);
    expect(fp.montant, `${where} : montant plafonne`).toContain(attendu.plafondMensuel);
    expect(fp.detail, `${where} : detail cite le taux`).toContain(`${attendu.taux}%`);
    expect(fp.detail, `${where} : aucun seuil de salaire 2026 dans le detail`).not.toContain(attendu.seuil2026);
    return fp;
}

/** Categories de frais professionnels saisissables (champ type_frais_pro). */
const CATEGORIES = ['commun', 'journaliste', 'artiste'];

/**
 * Un libelle de categorie ne porte aucun taux (#282, LE-1) : ni chiffre, quel
 * que soit le systeme de chiffres, ni signe de pourcentage (%, arabe, petit ou
 * pleine chasse). Le taux applique depend de l'exercice et se lit au resultat.
 */
const MOTIF_TAUX = /[\p{Nd}%\u{066A}\u{FE6A}\u{FF05}]/u;

/**
 * Libelles rendus par les options du champ type_frais_pro, par valeur. Chaque
 * libelle est non vide et neutre vis-a-vis du taux.
 */
async function libellesCategories(page, where) {
    const select = page.locator('select[name="type_frais_pro"]');
    await expect(select, `${where} : champ categorie professionnelle`).toHaveCount(1);

    const options = await select.evaluate((el) => Array.from(/** @type {HTMLSelectElement} */ (el).options)
        .map((o) => ({ value: o.value, label: o.textContent || '' })));
    expect(options.map((o) => o.value), `${where} : une option par categorie`).toEqual(CATEGORIES);

    const libelles = {};
    for (const option of options) {
        const libelle = normaliser(option.label);
        expect(libelle, `${where} : libelle ${option.value} non vide`).not.toBe('');
        expect(libelle, `${where} : libelle ${option.value} sans taux`).not.toMatch(MOTIF_TAUX);
        libelles[option.value] = libelle;
    }

    return libelles;
}

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
            // 1. Formulaire reel : 2026 et 2027 activables, 2026 selectionne par defaut.
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
                'seuls 2026 et le projet 2027 sont activables dans l\'application reelle',
            ).toEqual(EXERCICES_ACTIVABLES);

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

        /*
         * Parcours reel du projet 2027 (#282) : choix explicite dans le
         * formulaire, bulletin au taux unique propose, lien de partage puis
         * reprise, meme saisie recalculee en 2026, comparaison 2026 / 2027 a
         * entrees identiques hors exercice, page Fiabilite avec la note des
         * frais professionnels proposes. Captures prefixees 2027- dans
         * preuves-exercice. Le parcours emet trois requetes soumises a
         * throttle:calculer (deux calculs et une comparaison) ; avec
         * CACHE_STORE=array (voir l'en-tete), le limiteur ne conserve pas leur
         * cumul d'une requete a l'autre : ce compte reste indicatif.
         */
        test('parcours projet 2027 : frais professionnels proposes, partage, comparaison et Fiabilite', async ({ page }) => {
            await setLocale(page, cell.locale);
            const attendu = await attenduProjet(page);

            // 1. Formulaire reel : 2027 activable et choisi explicitement.
            await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
            await stabilize(page);

            const champ = await readExerciceField(page);
            const option2027 = champ.options.find((o) => o.value === EXERCICE_PROJET);
            expect(option2027, 'option 2027 proposee').toBeTruthy();
            expect(option2027.disabled, '2027 activable').toBe(false);
            expect(champ.value, 'repli 2026 toujours preselectionne').toBe(EXERCICE_REPLI);

            await page.locator('#salaire_base').fill('10000');
            await page.locator('select[name="exercice"]').selectOption(EXERCICE_PROJET);
            expect((await readExerciceField(page)).value, 'choix 2027 effectif').toBe(EXERCICE_PROJET);
            // Categories du formulaire reel : trois libelles neutres, quel que soit l'exercice choisi.
            const categories = await libellesCategories(page, 'formulaire 2027');
            expect(
                await page.locator('select[name="type_frais_pro"]').inputValue(),
                'categorie par defaut du formulaire',
            ).toBe('commun');
            await assertNoOverflow(page, 'formulaire 2027');
            await page.locator('select[name="exercice"]').scrollIntoViewIfNeeded();
            await capture(page, cell, '2027-01-formulaire');

            await Promise.all([
                page.waitForURL('**/calculateur/calculer'),
                page.locator('#payrollForm .quick-submit-panel button[type="submit"]').click(),
            ]);
            await page.locator('h1').first().waitFor({ state: 'visible' });
            await stabilize(page);

            // 2. Resultat : provenance projet et frais professionnels proposes.
            await expect(page.locator('[data-fiscal-year]')).toHaveCount(1);
            await assertProvenance(page, EXERCICE_PROJET, 'projet', 'resultat 2027');
            const fp = await assertFraisProProjet(page, attendu, 'resultat 2027');
            await assertNoOverflow(page, 'resultat 2027');
            await assertAxeClean(page, '[data-fiscal-year]', 'provenance du resultat 2027');
            await assertAxeClean(page, 'tr.row-impot', 'lignes impot du resultat 2027');
            await fp.ligne.scrollIntoViewIfNeeded();
            await capture(page, cell, '2027-02-resultat');

            if (cell.print) {
                await page.emulateMedia({ media: 'print' });
                await stabilize(page);
                await assertProvenance(page, EXERCICE_PROJET, 'projet', 'resultat 2027 imprime');
                await assertFraisProProjet(page, attendu, 'resultat 2027 imprime');
                await assertNoOverflow(page, 'resultat 2027 imprime');
                await capture(page, cell, '2027-03-resultat-impression');
                await page.emulateMedia({ media: 'screen' });
            }

            // 3. Partage puis reprise : le formulaire restaure 2027 et la saisie.
            const lienPartage = relatif(await page.locator('#shareUrl').inputValue());
            expect(lienPartage).toContain('s=');
            const partage2027 = new URL(lienPartage, 'http://localhost').searchParams.get('s');
            expect(partage2027, 'lien 2027').toBeTruthy();

            await page.goto(lienPartage, { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            expect((await readExerciceField(page)).value, 'exercice 2027 repris depuis le lien').toBe(EXERCICE_PROJET);
            await expect(page.locator('#salaire_base'), 'salaire repris depuis le lien').toHaveValue(/^10000(\.0+)?$/);
            await assertNoOverflow(page, 'formulaire 2027 repris');
            await page.locator('select[name="exercice"]').scrollIntoViewIfNeeded();
            await capture(page, cell, '2027-04-formulaire-repris');

            // Meme saisie recalculee en 2026 depuis le formulaire repris : le
            // bulletin 2026 garde son seuil, ce qui donne tout son sens au
            // controle d'absence du seuil dans le detail 2027.
            await page.locator('select[name="exercice"]').selectOption(EXERCICE_REPLI);
            await Promise.all([
                page.waitForURL('**/calculateur/calculer'),
                page.locator('#payrollForm .quick-submit-panel button[type="submit"]').click(),
            ]);
            await page.locator('h1').first().waitFor({ state: 'visible' });
            await stabilize(page);
            await assertProvenance(page, EXERCICE_REPLI, 'en_vigueur', 'resultat 2026 depuis le formulaire repris');
            const fp2026 = await ligneFraisPro(page);
            expect(fp2026.detail, 'le detail 2026 cite son seuil de salaire').toContain(attendu.seuil2026);
            expect(fp2026.taux, 'taux 2026 distinct du projet').not.toBe(`${attendu.taux}%`);
            const partage2026 = new URL(await page.locator('#shareUrl').inputValue()).searchParams.get('s');
            expect(partage2026, 'lien 2026').toBeTruthy();

            // 4. Comparaison : 2026 contre 2027, seule l'entree exercice differe.
            await page.goto(
                `/calculateur/comparer?a=${encodeURIComponent(String(partage2026))}&b=${encodeURIComponent(String(partage2027))}`,
                { waitUntil: 'domcontentloaded' },
            );
            await page.waitForURL('**/calculateur/comparer**');
            await stabilize(page);

            const entrees = page.locator('section[aria-labelledby="cmp-inputs-title"]');
            await expect(entrees).toHaveCount(1);
            await expect(entrees.locator('table'), 'l\'exercice differe : un tableau d\'ecarts').toHaveCount(1);
            await expect(entrees.locator('tr.cmp-row-changed'), 'seule l\'entree exercice differe').toHaveCount(1);
            const blocs = entrees.locator('[data-fiscal-year]');
            await expect(blocs, 'un bloc de provenance par scenario').toHaveCount(2);
            await expect(blocs.nth(0)).toHaveAttribute('data-fiscal-year', EXERCICE_REPLI);
            await expect(blocs.nth(0)).toHaveAttribute('data-fiscal-status', 'en_vigueur');
            await expect(blocs.nth(1)).toHaveAttribute('data-fiscal-year', EXERCICE_PROJET);
            await expect(blocs.nth(1)).toHaveAttribute('data-fiscal-status', 'projet');
            await expect(blocs.nth(1)).toBeVisible();

            // Ligne categorie professionnelle : reperee par le libelle rendu par
            // le formulaire, identique dans les deux scenarios, sans taux, et non
            // marquee comme modifiee (seul l'exercice differe).
            const ligneCategorie = entrees.locator('tbody tr').filter({
                has: page.locator('td').filter({ hasText: categories.commun }),
            });
            await expect(ligneCategorie, 'une ligne categorie professionnelle').toHaveCount(1);
            await expect(ligneCategorie).toBeVisible();
            await expect(ligneCategorie, 'categorie inchangee entre 2026 et 2027').not.toHaveClass(/cmp-row-changed/);
            const cellulesCategorie = ligneCategorie.locator('td');
            await expect(cellulesCategorie, 'une cellule par scenario').toHaveCount(2);
            const categorieA = normaliser(await cellulesCategorie.nth(0).textContent());
            const categorieB = normaliser(await cellulesCategorie.nth(1).textContent());
            expect(categorieA, 'scenario A (2026) : libelle du formulaire').toBe(categories.commun);
            expect(categorieB, 'scenario B (2027) : meme libelle que A').toBe(categorieA);
            expect(categorieA, 'scenario A (2026) : aucun taux dans la categorie').not.toMatch(MOTIF_TAUX);
            expect(categorieB, 'scenario B (2027) : aucun taux dans la categorie').not.toMatch(MOTIF_TAUX);

            await assertNoOverflow(page, 'comparaison 2026 / 2027');
            await assertAxeClean(page, 'section[aria-labelledby="cmp-inputs-title"]', 'entrees de la comparaison 2026 / 2027');
            await entrees.scrollIntoViewIfNeeded();
            await capture(page, cell, '2027-05-comparaison');

            // 5. Fiabilite : la seule entree 2027 porte la note des frais professionnels proposes.
            await page.goto('/fiabilite', { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            const entree2027 = page.locator('#trust-fiscal-years li').filter({ has: page.locator(`[data-fiscal-year="${EXERCICE_PROJET}"]`) });
            await expect(entree2027).toHaveCount(1);
            await assertProvenance(entree2027, EXERCICE_PROJET, 'projet', 'Fiabilite 2027');
            const note = normaliser(await entree2027.textContent());
            expect(note, 'Fiabilite 2027 : taux propose').toContain(`${attendu.taux}%`);
            expect(note, 'Fiabilite 2027 : plafond annuel propose').toContain(attendu.plafondAnnuel);
            expect(note, 'Fiabilite 2027 : reference du projet').toContain(normaliser(attendu.reference));

            const entree2026 = page.locator('#trust-fiscal-years li').filter({ has: page.locator(`[data-fiscal-year="${EXERCICE_REPLI}"]`) });
            await expect(entree2026).toHaveCount(1);
            expect(normaliser(await entree2026.textContent()), 'Fiabilite 2026 : aucune reference du projet').not.toContain(normaliser(attendu.reference));

            await assertNoOverflow(page, 'Fiabilite 2027');
            await assertAxeClean(page, '#trust-fiscal-years', 'exercices de la page Fiabilite (note 2027)');
            await entree2027.scrollIntoViewIfNeeded();
            await capture(page, cell, '2027-06-fiabilite');
        });
    });
}
