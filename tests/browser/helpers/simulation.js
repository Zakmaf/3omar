// @ts-check

/**
 * Jeu de donnees ENTIEREMENT SYNTHETIQUE utilise par tous les contrats
 * navigateur et par les captures durables.
 *
 * Regles :
 * - montants ronds, choisis pour exercer un maximum de lignes de tableau ;
 * - aucune donnee reelle, aucune donnee tracable, aucun cas isole ;
 * - `nb_annees_anciennete` doit rester un debut de tranche de
 *   `config('payroll.anciennete.tranches')`, sinon le formulaire ne le
 *   restitue pas (voir CLAUDE.md, "Prefilling the calculator form").
 */
const SYNTHETIC_SIMULATION = {
    mode: 'gross_to_net',
    salaire_base: 10000,
    nb_annees_anciennete: 5,
    prime_bilan: 1000,
    prime_rendement: 500,
    type_frais_pro: 'commun',
    nb_enfants: 2,
    conjoint_charge: 1,
    cimr_taux: 6,
    cimr_taux_employeur: 6,
    retraite_complementaire_mensuel: 500,
    rc_part_employeur: 500,
    mutuelle_salarie: 200,
    mutuelle_patronale: 300,
    assurance_at_taux: 1,
    assurance_rc_pro: 100,
    retenues_imposees_ir: 100,
    jours_travailles: 26,
    'heures_sup[0][type]': 'semaine_diurne',
    'heures_sup[0][nb_heures]': 10,
    'indemnites[0][type]': 'transport',
    'indemnites[0][montant]': 500,
    'indemnites[1][type]': 'panier',
    'indemnites[1][montant]': 600,
};

/** Locales supportees et sens d'ecriture attendu. */
const LOCALE_DIRECTION = { fr: 'ltr', en: 'ltr', ar: 'rtl', es: 'ltr' };

/**
 * Fixe la locale applicative pour la session du navigateur.
 * @param {import('@playwright/test').Page} page
 * @param {string} locale
 */
async function setLocale(page, locale) {
    await page.goto(`/lang/${locale}`, { waitUntil: 'domcontentloaded' });
    const dir = await page.getAttribute('html', 'dir');
    if (dir !== LOCALE_DIRECTION[locale]) {
        throw new Error(`Locale ${locale} : dir attendu ${LOCALE_DIRECTION[locale]}, obtenu ${dir}`);
    }
}

/**
 * Ouvre la page resultat en rejouant le vrai POST /calculateur/calculer.
 *
 * Le formulaire reel est soumis (jeton CSRF inclus) plutot que simule, pour
 * que la page mesuree soit exactement celle que voit un utilisateur.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ locale?: string, fields?: Record<string, string|number> }} [options]
 */
async function openResultPage(page, options = {}) {
    const locale = options.locale || 'fr';
    const fields = { ...SYNTHETIC_SIMULATION, ...(options.fields || {}) };

    await setLocale(page, locale);
    await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });

    const token = await page.locator('input[name="_token"]').first().inputValue();

    await page.evaluate(({ token, fields }) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/calculateur/calculer';
        const append = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value);
            form.appendChild(input);
        };
        append('_token', token);
        for (const [name, value] of Object.entries(fields)) {
            append(name, value);
        }
        document.body.appendChild(form);
        form.submit();
    }, { token, fields });

    await page.waitForURL('**/calculateur/calculer');

    const heading = page.locator('h1');
    await heading.first().waitFor({ state: 'visible' });

    await stabilize(page);
    return page;
}

/**
 * Rend la page deterministe avant mesure ou capture :
 * polices chargees, graphique dessine, animations neutralisees.
 * @param {import('@playwright/test').Page} page
 */
async function stabilize(page) {
    await page.addStyleTag({
        content: `
            *, *::before, *::after {
                transition-duration: 0s !important;
                animation-duration: 0s !important;
                animation-delay: 0s !important;
                caret-color: transparent !important;
            }
            html { scroll-behavior: auto !important; }
        `,
    });

    // Bootstrap arrive par CDN : sans lui aucune mesure de grille n'a de sens.
    await page.waitForFunction(() => {
        const probe = document.createElement('div');
        probe.className = 'container';
        document.body.appendChild(probe);
        const applied = getComputedStyle(probe).paddingLeft !== '0px';
        probe.remove();
        return applied;
    }, null, { timeout: 15_000 });

    await page.evaluate(() => document.fonts.ready);

    // Chart.js anime le trace du donut : on fige l'etat final.
    await page.evaluate(() => {
        if (!window.Chart) return;
        for (const chart of Object.values(window.Chart.instances)) {
            chart.stop();
            chart.update('none');
        }
    });

    // Chart.js dessine le donut apres le premier frame.
    await page.evaluate(() => new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve(null)));
    }));
}

module.exports = { SYNTHETIC_SIMULATION, LOCALE_DIRECTION, setLocale, openResultPage, stabilize };
