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
        'privacy_detail' => 'Cada simulación se calcula bajo demanda.', 'license' => 'Proyecto de código abierto con licencia MIT',
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
        'intro' => 'Empieza por tu salario base. Muestra las opciones avanzadas solo si las necesitas.', 'simple_title' => '¿Necesitas un cálculo sencillo?',
        'simple_text' => 'El salario base es suficiente. Muestra los complementos solo si son necesarios.', 'advanced_show' => 'Mostrar opciones avanzadas',
        'mode_label' => 'Modo de cálculo', 'mode_gross_to_net' => 'Conozco el bruto', 'mode_net_to_gross' => 'Conozco el neto',
        'net_target_label' => 'Neto a pagar objetivo', 'net_target_help' => 'Importe neto mensual negociado. El simulador reconstruye el salario base bruto correspondiente.',
        'advanced_hide' => 'Ocultar opciones avanzadas', 'submit' => 'Simular mi nómina', 'reset' => 'Restablecer', 'errors' => 'Errores de entrada:',
    ],
    'result' => ['eyebrow' => 'Resultado de la simulación', 'title' => 'Tu nómina, explicada claramente', 'intro' => 'Empieza por los importes clave y abre el detalle para verificar cada línea.', 'edit' => 'Modificar simulación', 'print' => 'Imprimir', 'details' => 'Ver el detalle completo del cálculo', 'net_to_gross_badge' => 'Reconstrucción desde el neto', 'net_to_gross_title' => 'Del neto negociado al presupuesto del empleador', 'net_to_gross_intro' => 'El salario base bruto se resuelve por iteraciones a partir del neto a pagar objetivo y las hipótesis introducidas.', 'net_target' => 'Neto objetivo', 'net_resolved' => 'Neto obtenido', 'resolved_base_salary' => 'Base reconstruida', 'resolution_gap' => 'Diferencia'],
    'documentation' => ['eyebrow' => 'Entender los parámetros', 'title' => 'Documentación de reglas', 'intro' => 'Parámetros, límites y tramos utilizados por 3omar para el sector privado marroquí.', 'badge' => 'Hipótesis de simulación', 'warning' => 'Las referencias indican el origen declarado de los parámetros. No sustituyen una validación adaptada a cada situación.'],
    'validation' => ['base_required' => 'El salario base es obligatorio.', 'base_positive' => 'El salario base debe ser positivo.', 'net_target_required' => 'El neto a pagar objetivo es obligatorio.', 'net_target_positive' => 'El neto a pagar objetivo debe ser positivo.', 'category_invalid' => 'Categoría profesional no válida.', 'cimr_min' => 'La tasa CIMR debe ser al menos :min%.', 'cimr_max' => 'La tasa CIMR no puede superar :max%.', 'allowance_distinct' => 'Cada tipo de indemnización solo puede declararse una vez.'],
];
