// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Harness navigateur du depot (issue #122).
 *
 * L'application n'est jamais demarree par Playwright : elle est servie par un
 * conteneur dedie, en boucle locale uniquement. Voir docs/DEVELOPPEMENT.md, section
 * "Tests navigateur".
 *
 * BROWSER_BASE_URL permet de pointer une autre instance locale. La valeur par
 * defaut correspond au conteneur documente.
 */
const baseURL = process.env.BROWSER_BASE_URL || 'http://127.0.0.1:49222';

module.exports = defineConfig({
    testDir: __dirname,
    testMatch: '**/*.spec.js',
    outputDir: `${__dirname}/.output`,
    snapshotPathTemplate: '{testDir}/__structure__/{testFileName}/{arg}{ext}',

    // Determinisme : une seule worker, aucun parallelisme, aucun retry.
    // Un contrat de mise en page qui ne passe qu'apres retry n'est pas un contrat.
    workers: 1,
    fullyParallel: false,
    retries: 0,
    forbidOnly: !!process.env.CI,

    reporter: [
        ['list'],
        ['html', { outputFolder: `${__dirname}/.report`, open: 'never' }],
    ],

    expect: {
        timeout: 10_000,
    },

    use: {
        baseURL,
        // Les captures et les mesures doivent etre stables : aucune animation.
        // reducedMotion n'est pas une fixture de test : il passe par le contexte.
        contextOptions: { reducedMotion: 'reduce' },
        colorScheme: 'light',
        timezoneId: 'Africa/Casablanca',
        trace: 'retain-on-failure',
        screenshot: 'off',
        video: 'off',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
