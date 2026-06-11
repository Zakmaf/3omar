<?php

return [
    'meta_description' => '3omar helps explain the main calculations in a Moroccan payslip, including the parameters and references used.',
    'meta_title' => '3omar · The Moroccan payslip, open source',
    'meta_social' => 'Explained calculations, explicit assumptions, no personal data stored.',
    'skip' => 'Skip to main content',
    'nav' => ['home' => 'Home', 'calculator' => 'Calculator', 'documentation' => 'Documentation', 'language' => 'Language'],
    'footer' => [
        'tagline' => 'Payroll simulator, built for Morocco', 'navigation' => 'Navigation', 'simulate' => 'Simulate my payslip',
        'rules' => 'Calculation rules', 'source' => 'GitHub source code', 'report' => 'Report an issue', 'warning' => 'Warning',
        'warning_text' => '3omar is an educational and informational tool. Results use documented parameters but may contain inaccuracies.',
        'consult' => 'For an official payslip, consult your employer or an accountant.', 'privacy' => 'No personal data is stored.',
        'privacy_detail' => 'Each simulation is calculated on demand.', 'license' => 'Open-source project under the MIT license',
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
        'intro' => 'Start with your base salary. Show advanced options only when needed.', 'simple_title' => 'Need a simple calculation?',
        'simple_text' => 'The base salary is enough. Show additional fields only when needed.', 'advanced_show' => 'Show advanced options',
        'advanced_hide' => 'Hide advanced options', 'submit' => 'Simulate my payslip', 'reset' => 'Reset', 'errors' => 'Input errors:',
    ],
    'result' => ['eyebrow' => 'Simulation result', 'title' => 'Your payslip, clearly explained', 'intro' => 'Start with the key amounts, then open the details to verify each line.', 'edit' => 'Edit simulation', 'print' => 'Print', 'details' => 'View full calculation details'],
    'documentation' => ['eyebrow' => 'Understand the parameters', 'title' => 'Rules documentation', 'intro' => 'Parameters, ceilings and brackets used by 3omar for the Moroccan private sector.', 'badge' => 'Simulation assumptions', 'warning' => 'References indicate the declared origin of parameters. They do not replace validation for each situation.'],
    'validation' => ['base_required' => 'The base salary is required.', 'base_positive' => 'The base salary must be positive.', 'category_invalid' => 'Invalid professional category.', 'cimr_min' => 'The CIMR rate must be at least :min%.', 'cimr_max' => 'The CIMR rate cannot exceed :max%.', 'allowance_distinct' => 'Each allowance type can only be declared once.'],
];
