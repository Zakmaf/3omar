<?php

namespace App\Services;

/**
 * Pages éditoriales thématiques (issue #117).
 *
 * Chaque guide répond à une recherche courante (salaire net, coût employeur,
 * CNSS/AMO, prime d'ancienneté) et renvoie vers le calculateur. L'exemple
 * chiffré n'est jamais écrit à la main : il est produit par le moteur de calcul
 * à partir d'un profil de SimulationProfileService, si bien que le bouton
 * « reproduire cet exemple » préremplit exactement les mêmes valeurs. Les taux
 * et plafonds cités dans le texte sont lus depuis config/payroll.php.
 *
 * Les textes vivent dans lang/*\/ui.php sous ui.guides.<key>.
 */
class GuideService
{
    /**
     * slug d'URL => définition. L'ordre est celui des liens « guides » du site.
     */
    public const GUIDES = [
        'calcul-salaire-net-maroc' => [
            'key' => 'salaire_net',
            'icon' => 'bi-cash-stack',
            'profil' => 'standard',
            'rows' => ['salaire_base', 'prime_anciennete', 'salaire_brut_total', 'cotisation_cnss', 'cotisation_amo', 'frais_pro', 'ir_net', 'salaire_net'],
            'highlight' => 'salaire_net',
            'facts' => ['cnss_employee', 'amo_employee', 'professional_expenses', 'smig'],
            'docs' => ['cotisations', 'impot'],
            'sources' => ['Dahir 1-72-184', 'Loi 65-00', 'Art. 59 I-A CGI', 'Art. 73 CGI'],
        ],
        'cout-employeur-maroc' => [
            'key' => 'cout_employeur',
            'icon' => 'bi-building',
            'profil' => 'cadre',
            'rows' => ['salaire_brut_total', 'cout_cnss_patronal', 'cout_amo_patronal', 'cout_af_patronal', 'cout_tfp_patronal', 'cout_total_employeur', 'salaire_net'],
            'highlight' => 'cout_total_employeur',
            'facts' => ['cnss_employer', 'amo_employer', 'family_allowances', 'tfp'],
            'docs' => ['charges-patronales'],
            'sources' => ['Dahir 1-72-184', 'Loi 65-00', 'Code du Travail'],
        ],
        'cnss-amo-maroc' => [
            'key' => 'cnss_amo',
            'icon' => 'bi-shield-plus',
            'profil' => 'cadre',
            'rows' => ['sbi', 'assiette_cnss', 'cotisation_cnss', 'cotisation_amo', 'cout_cnss_patronal', 'cout_amo_patronal'],
            'highlight' => 'cotisation_cnss',
            'facts' => ['cnss_employee', 'cnss_employer', 'amo_employee', 'amo_employer'],
            'docs' => ['cotisations', 'amo'],
            'sources' => ['Dahir 1-72-184', 'Loi 65-00'],
        ],
        'prime-anciennete-maroc' => [
            'key' => 'prime_anciennete',
            'icon' => 'bi-hourglass-split',
            'profil' => 'cadre',
            'rows' => ['salaire_base', 'nb_annees_anciennete', 'taux_anciennete', 'prime_anciennete', 'sbi'],
            'highlight' => 'prime_anciennete',
            'facts' => ['seniority_brackets'],
            'docs' => ['remuneration'],
            'sources' => ['Art. 350 Code du Travail'],
        ],
    ];

    public function __construct(
        private PayrollCalculatorService $calculator,
        private SimulationProfileService $profiles,
    ) {}

    /**
     * Liste légère pour les liens de navigation (pied de page, guides liés).
     *
     * @return array<string, array{slug: string, key: string, icon: string}>
     */
    public function links(): array
    {
        $links = [];

        foreach (self::GUIDES as $slug => $guide) {
            $links[$slug] = ['slug' => $slug, 'key' => $guide['key'], 'icon' => $guide['icon']];
        }

        return $links;
    }

    /**
     * Guide complet prêt pour la vue, ou null si le slug est inconnu.
     */
    public function find(string $slug): ?array
    {
        $guide = self::GUIDES[$slug] ?? null;

        if ($guide === null) {
            return null;
        }

        $input = $this->profiles->find($guide['profil'])['input'];
        $result = $this->calculator->calculer($input);

        // Les clés calculées (facts) remplacent leur définition brute.
        return array_merge($guide, [
            'slug' => $slug,
            'placeholders' => $this->placeholders() + [
                // Valeurs d'entrée du profil, citées dans l'introduction de l'exemple.
                'salaire_base' => $this->money($input['salaire_base']),
                'annees' => (string) ($input['nb_annees_anciennete'] ?? 0),
                'enfants' => (string) ($input['nb_enfants'] ?? 0),
            ],
            'example' => array_map(fn (string $row) => [
                'key' => $row,
                'value' => $this->rowValue($row, $result),
                'highlight' => $row === $guide['highlight'],
            ], $guide['rows']),
            'facts' => $this->facts($guide['facts']),
            'related' => array_values(array_filter(
                $this->links(),
                fn (array $link) => $link['slug'] !== $slug,
            )),
        ]);
    }

    /**
     * Valeurs réglementaires injectées dans les textes traduits (:cnss_taux...).
     *
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        $tranches = array_values(config('payroll.anciennete.tranches'));

        return [
            'cnss_taux' => $this->pct(config('payroll.cnss.taux')),
            'cnss_taux_patronal' => $this->pct(config('payroll.cnss.taux_patronal')),
            'cnss_plafond' => $this->money(config('payroll.cnss.plafond')),
            'amo_taux' => $this->pct(config('payroll.amo.taux')),
            'amo_taux_patronal' => $this->pct(config('payroll.amo.taux_patronal')),
            'fp_plafond' => $this->money(config('payroll.frais_pro.commun.bas.plafond')),
            'smig' => $this->money(config('payroll.smig.mensuel')),
            'anciennete_min' => (string) $tranches[0]['min_annees'],
            'anciennete_taux_min' => $this->pct($tranches[0]['taux']),
            'anciennete_taux_max' => $this->pct(end($tranches)['taux']),
        ];
    }

    private function rowValue(string $row, array $result): array
    {
        return match ($row) {
            'salaire_base' => ['type' => 'money', 'value' => (float) $result['input']['salaire_base']],
            'nb_annees_anciennete' => ['type' => 'years', 'value' => (int) $result['nb_annees_anciennete']],
            'taux_anciennete' => ['type' => 'pct', 'value' => (float) $result['taux_anciennete']],
            default => ['type' => 'money', 'value' => (float) $result[$row]],
        };
    }

    /**
     * Repères réglementaires affichés à côté de l'exemple. Les libellés
     * réutilisent ceux de la matrice de fiabilité (ui.trust.matrix_rows).
     *
     * @return list<array{label: string, value: string}>
     */
    private function facts(array $keys): array
    {
        $facts = [];

        foreach ($keys as $key) {
            if ($key === 'seniority_brackets') {
                foreach (config('payroll.anciennete.tranches') as $tranche) {
                    $facts[] = [
                        'label' => $tranche['max_annees'] === null
                            ? __('ui.guides.common.years_from', ['min' => $tranche['min_annees']])
                            : __('ui.guides.common.years_range', ['min' => $tranche['min_annees'], 'max' => $tranche['max_annees']]),
                        'value' => $this->pct($tranche['taux']),
                    ];
                }

                continue;
            }

            $facts[] = [
                'label' => __("ui.trust.matrix_rows.{$key}.rule"),
                'value' => match ($key) {
                    'cnss_employee' => $this->pct(config('payroll.cnss.taux')).' · '.__('ui.guides.common.ceiling', ['amount' => $this->money(config('payroll.cnss.plafond'))]),
                    'cnss_employer' => $this->pct(config('payroll.cnss.taux_patronal')).' · '.__('ui.guides.common.ceiling', ['amount' => $this->money(config('payroll.cnss.plafond'))]),
                    'amo_employee' => $this->pct(config('payroll.amo.taux')),
                    'amo_employer' => $this->pct(config('payroll.amo.taux_patronal')),
                    'family_allowances' => $this->pct(config('payroll.allocations_familiales.taux_patronal')),
                    'tfp' => $this->pct(config('payroll.taxe_formation.taux_patronal')),
                    'professional_expenses' => __('ui.guides.common.ceiling', ['amount' => $this->money(config('payroll.frais_pro.commun.bas.plafond'))]),
                    'smig' => $this->money(config('payroll.smig.mensuel')),
                },
            ];
        }

        return $facts;
    }

    private function pct(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 2, ',', ' '), '0'), ',').' %';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' MAD';
    }
}
