<?php

return [
    'meta_description' => '3omar ayuda a entender los principales cálculos de una nómina marroquí y los parámetros utilizados.',
    'meta_title' => '3omar · La nómina marroquí, de código abierto',
    'meta_social' => 'Cálculos explicados, hipótesis explícitas, sin almacenar datos personales.',
    'skip' => 'Ir al contenido principal',
    'ads' => ['label' => 'Publicidad'],
    'nav' => ['home' => 'Inicio', 'calculator' => 'Calculadora', 'documentation' => 'Documentación', 'language' => 'Idioma'],
    'footer' => [
        'tagline' => 'Simulador de nómina, pensado para Marruecos', 'navigation' => 'Navegación', 'simulate' => 'Simular mi nómina',
        'rules' => 'Reglas de cálculo', 'source' => 'Código fuente en GitHub', 'report' => 'Informar de un error', 'warning' => 'Advertencia',
        'warning_text' => '3omar es una herramienta educativa e informativa. Los resultados usan parámetros documentados, pero pueden contener imprecisiones.',
        'consult' => 'Para una nómina oficial, consulta a tu empleador o a un contable.', 'privacy' => 'No se almacenan datos personales.',
        'privacy_detail' => 'Cada simulación se calcula bajo demanda.', 'license' => 'Proyecto de código abierto con licencia MIT', 'version' => 'Versión',
    ],
    'home' => [
        'badge' => 'Parámetros actualizados', 'open_source' => 'Gratis y de código abierto', 'title' => 'La nómina marroquí, de código abierto',
        'intro' => 'Simula tu salario neto y entiende cada cálculo: importes clave, detalle de las deducciones e hipótesis utilizadas.',
        'simulate' => 'Simular mi nómina', 'rules' => 'Consultar las reglas de cálculo', 'benefits_title' => 'Entender, no solo calcular',
        'benefits' => [
            ['title' => 'Cálculos explicados', 'text' => 'Cada etapa muestra su base, tasa e importe.'],
            ['title' => 'Hipótesis explícitas', 'text' => 'Los parámetros y referencias declarados siguen visibles y verificables.'],
            ['title' => 'Código verificable', 'text' => 'El motor de cálculo y sus parámetros son públicos.'],
        ],
        'coverage_title' => 'Qué cubre 3omar', 'coverage' => ['CNSS y AMO del empleado y empleador', 'Impuesto progresivo y cargas familiares', 'CIMR y jubilación complementaria', 'Gastos profesionales e indemnizaciones tratadas como exentas', 'Prima de antigüedad y horas extra', 'Salario neto y coste total del empleador'], 'ready' => '¿Listo para entender tu nómina?', 'free' => 'Simulación educativa, gratuita y sin registro.',
    ],
    'calculator' => [
        'title' => 'Simular mi nómina', 'eyebrow' => 'Simulación educativa · unos 2 minutos',
        'intro' => 'Elige tu punto de partida y avanza sección por sección. Puedes omitir lo que no aplique.', 'simple_title' => '¿Necesitas un cálculo sencillo?',
        'simple_text' => 'El salario base es suficiente. Muestra los complementos solo si son necesarios.', 'advanced_show' => 'Mostrar opciones avanzadas',
        'journey_title' => 'Simulación guiada paso a paso', 'journey_text' => 'Rellena solo las secciones útiles. Las secciones omitidas se quedan en cero.',
        'mode_label' => 'Modo de cálculo', 'mode_gross_to_net' => 'Conozco el bruto', 'mode_net_to_gross' => 'Conozco el neto',
        'net_target_label' => 'Neto a pagar objetivo', 'net_target_help' => 'Importe neto mensual negociado. El simulador reconstruye el salario base bruto correspondiente.',
        'step_required' => 'Obligatorio', 'step_optional' => 'Opcional', 'step_continue' => 'Continuar', 'step_skip' => 'Omitir esta sección',
        'result_direct_access_notice' => 'Aún no hay ningún resultado que mostrar. Inicia una simulación desde este formulario para obtener el detalle del cálculo.',
        'advanced_hide' => 'Ocultar opciones avanzadas', 'submit' => 'Ejecutar el cálculo de nómina', 'submit_hint' => 'Un salario inicial basta para ejecutar una simulación; las secciones siguientes siguen siendo opcionales.', 'reset' => 'Restablecer', 'errors' => 'Errores de entrada:',
    ],
    'result' => ['eyebrow' => 'Resultado de la simulación', 'title' => 'Tu nómina, explicada claramente', 'intro' => 'Empieza por los importes clave y abre el detalle para verificar cada línea.', 'edit' => 'Modificar simulación', 'print' => 'Imprimir', 'details' => 'Ver el detalle completo del cálculo', 'unit_mad_month' => ' MAD/mes', 'unit_mad_year' => ' MAD/año', 'unit_mad_month_label' => 'MAD/mes', 'unit_mad_year_label' => 'MAD/año', 'summary_eyebrow' => 'Síntesis', 'summary_title' => 'Importes clave', 'gross_salary' => 'Salario bruto', 'gross_salary_help' => 'Total bruto pagado antes de deducciones del empleado.', 'net_pay' => 'Neto a pagar', 'total_employer_cost' => 'Coste total del empleador', 'total_employer_cost_help' => 'Bruto pagado + cotizaciones patronales.', 'net_formula_title' => 'Lectura rápida del neto', 'net_formula_intro' => 'La síntesis sigue el flujo real de la nómina sin repetir todas las líneas de cálculo.', 'taxable_gross_salary' => 'Salario bruto imponible', 'monthly_taxable_base' => 'Base imponible mensual', 'employee_contributions' => 'Cotizaciones del empleado', 'income_tax' => 'Impuesto sobre la renta', 'employer_contributions' => 'Cotizaciones patronales', 'employer_formula_hint' => 'Se suman al bruto para llegar al coste empleador.', 'ir_bracket' => 'Tramo :rate% — Art. 73 CGI', 'explanation_eyebrow' => 'Explicación pedagógica', 'explanation_title' => 'Del bruto al neto, paso a paso', 'step_gross_title' => '1. Determinar la base imponible', 'step_gross_text' => 'El salario bruto imponible utilizado es :amount.', 'step_contributions_title' => '2. Deducir cotizaciones del empleado', 'step_contributions_text' => 'Las cotizaciones del empleado suman :amount.', 'step_tax_title' => '3. Calcular el impuesto retenido', 'step_tax_text' => 'El impuesto mensual neto retenido en origen es :amount.', 'step_employer_title' => '4. Leer el coste empleador por separado', 'step_employer_text' => 'El presupuesto total del empleador es :amount, sin cambiar el neto pagado al empleado.', 'detail_eyebrow' => 'Detalle de nómina', 'detail_title' => 'Todas las líneas del cálculo', 'net_to_gross_badge' => 'Reconstrucción desde el neto', 'net_to_gross_title' => 'Del neto negociado al presupuesto del empleador', 'net_to_gross_intro' => 'El salario base bruto se resuelve por iteraciones a partir del neto a pagar objetivo y las hipótesis introducidas.', 'net_target' => 'Neto objetivo', 'net_resolved' => 'Neto obtenido', 'resolved_base_salary' => 'Base reconstruida', 'resolution_gap' => 'Diferencia'],
    'documentation' => ['eyebrow' => 'Entender los parámetros', 'title' => 'Documentación de reglas', 'intro' => 'Parámetros, límites y tramos utilizados por 3omar para el sector privado marroquí.', 'badge' => 'Hipótesis de simulación', 'warning' => 'Las referencias indican el origen declarado de los parámetros. No sustituyen una validación adaptada a cada situación.'],
    'validation' => ['base_required' => 'El salario base es obligatorio.', 'base_positive' => 'El salario base debe ser positivo.', 'net_target_required' => 'El neto a pagar objetivo es obligatorio.', 'net_target_positive' => 'El neto a pagar objetivo debe ser positivo.', 'category_invalid' => 'Categoría profesional no válida.', 'cimr_min' => 'La tasa CIMR debe ser al menos :min%.', 'cimr_max' => 'La tasa CIMR no puede superar :max%.', 'allowance_distinct' => 'Cada tipo de indemnización solo puede declararse una vez.'],
];
