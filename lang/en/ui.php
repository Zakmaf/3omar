<?php

return [
    'meta_description' => '3omar helps explain the main calculations in a Moroccan payslip, including the parameters and references used.',
    'meta_title' => '3omar · The Moroccan payslip, open source',
    'meta_social' => 'Explained calculations, explicit assumptions, no personal data stored.',
    'skip' => 'Skip to main content',
    'ads' => ['label' => 'Advertisement'],
    'nav' => ['home' => 'Home', 'calculator' => 'Calculator', 'documentation' => 'Documentation', 'language' => 'Language'],
    'footer' => [
        'tagline' => 'Payroll simulator, built for Morocco', 'navigation' => 'Navigation', 'simulate' => 'Simulate my payslip',
        'rules' => 'Calculation rules', 'source' => 'GitHub source code', 'report' => 'Report an issue', 'warning' => 'Warning',
        'warning_text' => '3omar is an educational and informational tool. Results use documented parameters but may contain inaccuracies.',
        'consult' => 'For an official payslip, consult your employer or an accountant.', 'privacy' => 'No personal data is stored.',
        'privacy_detail' => 'Each simulation is calculated on demand.', 'license' => 'Open-source project under the MIT license', 'version' => 'Version',
    ],
    'home' => [
        'badge' => 'Up-to-date parameters', 'open_source' => 'Free and open source', 'title' => 'The Moroccan payslip, open source',
        'intro' => 'Simulate your net salary and understand every calculation: key amounts, deduction details and assumptions used.',
        'simulate' => 'Simulate my payslip', 'rules' => 'View the calculation rules', 'benefits_title' => 'Understand, not just calculate',
        'benefits' => [
            ['title' => 'Explained calculations', 'text' => 'Each step shows its basis, rate and amount.'],
            ['title' => 'Explicit assumptions', 'text' => 'Declared parameters and references remain visible and verifiable.'],
            ['title' => 'Verifiable code', 'text' => 'The calculation engine and its parameters are public.'],
        ],
        'coverage_title' => 'What 3omar covers', 'coverage' => ['Employee and employer CNSS and AMO', 'Progressive income tax and family deductions', 'CIMR and supplementary retirement', 'Professional expenses and allowances treated as exempt', 'Seniority bonus and overtime', 'Net salary and total employer cost'], 'ready' => 'Ready to understand your payslip?', 'free' => 'Educational, free and without registration.',
    ],
    'calculator' => [
        'title' => 'Simulate my payslip', 'eyebrow' => 'Educational simulation · about 2 minutes',
        'intro' => 'Choose your starting point, then move section by section. You can skip anything that does not apply.', 'simple_title' => 'Need a simple calculation?',
        'simple_text' => 'The base salary is enough. Show additional fields only when needed.', 'advanced_show' => 'Show advanced options',
        'journey_title' => 'Guided step-by-step simulation', 'journey_text' => 'Fill only the sections that matter. Skipped sections stay at zero.',
        'mode_label' => 'Calculation mode', 'mode_gross_to_net' => 'I know the gross', 'mode_net_to_gross' => 'I know the net',
        'net_target_label' => 'Target net pay', 'net_target_help' => 'Negotiated monthly net amount. The simulator reconstructs the matching gross base salary.',
        'step_required' => 'Required', 'step_optional' => 'Optional', 'step_continue' => 'Continue', 'step_skip' => 'Skip this section',
        'result_direct_access_notice' => 'There is no result to show yet. Start a simulation from this form to get the calculation details.',
        'advanced_hide' => 'Hide advanced options', 'submit' => 'Run payslip calculation', 'submit_hint' => 'A starting salary is enough to run a simulation; the following sections stay optional.', 'reset' => 'Reset', 'errors' => 'Input errors:',
    ],
    'result' => ['eyebrow' => 'Simulation result', 'title' => 'Your payslip, clearly explained', 'intro' => 'Start with the key amounts, then open the details to verify each line.', 'edit' => 'Edit simulation', 'print' => 'Print', 'details' => 'View full calculation details', 'unit_mad_month' => ' MAD/month', 'unit_mad_year' => ' MAD/year', 'unit_mad_month_label' => 'MAD/month', 'unit_mad_year_label' => 'MAD/year', 'summary_eyebrow' => 'Summary', 'summary_title' => 'Key amounts', 'gross_salary' => 'Gross salary', 'gross_salary_help' => 'Total gross paid before employee deductions.', 'net_pay' => 'Net pay', 'total_employer_cost' => 'Total employer cost', 'total_employer_cost_help' => 'Gross paid + employer contributions.', 'net_formula_title' => 'Quick net reading', 'net_formula_intro' => 'The summary follows the real payslip flow without repeating every calculation line.', 'taxable_gross_salary' => 'Taxable gross salary', 'monthly_taxable_base' => 'Monthly taxable base', 'employee_contributions' => 'Employee contributions', 'income_tax' => 'Income tax', 'employer_contributions' => 'Employer contributions', 'employer_formula_hint' => 'Added to gross pay to reach employer cost.', 'ir_bracket' => ':rate% bracket — Art. 73 CGI', 'explanation_eyebrow' => 'Educational explanation', 'explanation_title' => 'From gross to net, step by step', 'step_gross_title' => '1. Determine the taxable base', 'step_gross_text' => 'The taxable gross salary used is :amount.', 'step_contributions_title' => '2. Deduct employee contributions', 'step_contributions_text' => 'Employee contributions total :amount.', 'step_tax_title' => '3. Calculate withheld income tax', 'step_tax_text' => 'The net monthly income tax withheld at source is :amount.', 'step_employer_title' => '4. Read employer cost separately', 'step_employer_text' => 'The total employer budget is :amount, without changing the net paid to the employee.', 'detail_eyebrow' => 'Payslip detail', 'detail_title' => 'Every calculation line', 'net_to_gross_badge' => 'Net-to-gross reconstruction', 'net_to_gross_title' => 'From negotiated net to employer budget', 'net_to_gross_intro' => 'The gross base salary is resolved iteratively from the target net pay and entered assumptions.', 'net_target' => 'Target net', 'net_resolved' => 'Resolved net', 'resolved_base_salary' => 'Resolved base', 'resolution_gap' => 'Gap'],
    'documentation' => ['eyebrow' => 'Understand the parameters', 'title' => 'Rules documentation', 'intro' => 'Parameters, ceilings and brackets used by 3omar for the Moroccan private sector.', 'badge' => 'Simulation assumptions', 'warning' => 'References indicate the declared origin of parameters. They do not replace validation for each situation.'],
    'validation' => ['base_required' => 'The base salary is required.', 'base_positive' => 'The base salary must be positive.', 'net_target_required' => 'The target net pay is required.', 'net_target_positive' => 'The target net pay must be positive.', 'category_invalid' => 'Invalid professional category.', 'cimr_min' => 'The CIMR rate must be at least :min%.', 'cimr_max' => 'The CIMR rate cannot exceed :max%.', 'allowance_distinct' => 'Each allowance type can only be declared once.'],
];
