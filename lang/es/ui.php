<?php

return [
    'meta_description' => '3omar ayuda a entender los principales cálculos de una nómina marroquí y los parámetros utilizados.',
    'meta_title' => '3omar — La nómina, línea por línea',
    'meta_social' => 'Cálculos explicados, hipótesis explícitas, sin almacenar datos personales.',
    'skip' => 'Ir al contenido principal',
    'nav' => ['home' => 'Inicio', 'calculator' => 'Calculadora', 'documentation' => 'Documentación', 'language' => 'Idioma'],
    'footer' => [
        'tagline' => 'Simulador educativo de nómina marroquí', 'navigation' => 'Navegación', 'simulate' => 'Simular mi nómina',
        'rules' => 'Reglas de simulación 2026', 'source' => 'Código fuente en GitHub', 'report' => 'Informar de un error', 'warning' => 'Advertencia',
        'warning_text' => '3omar es una herramienta educativa e informativa. Los resultados usan parámetros documentados de 2026, pero pueden contener imprecisiones.',
        'consult' => 'Para una nómina oficial, consulta a tu empleador o a un contable.', 'privacy' => 'No se almacenan datos personales.',
        'privacy_detail' => 'Cada simulación se calcula bajo demanda.', 'license' => 'Proyecto de código abierto con licencia MIT',
    ],
    'home' => [
        'year' => 'Ejercicio 2026', 'open_source' => 'Gratis y de código abierto', 'title' => 'La nómina marroquí, línea por línea.',
        'intro' => 'Simula tu nómina marroquí y entiende los cálculos principales. El resultado separa importes clave, detalles e hipótesis.',
        'simulate' => 'Simular mi nómina', 'rules' => 'Consultar reglas 2026', 'benefits_title' => 'Entender, no solo calcular',
        'benefits' => [
            ['title' => 'Cálculos explicados', 'text' => 'Cada etapa muestra su base, tasa e importe.'],
            ['title' => 'Hipótesis explícitas', 'text' => 'Los parámetros y referencias declarados siguen visibles y verificables.'],
            ['title' => 'Código verificable', 'text' => 'El motor de cálculo y sus parámetros son públicos.'],
        ],
        'coverage_title' => 'Qué cubre 3omar', 'coverage' => ['CNSS y AMO del empleado y empleador', 'Impuesto progresivo y cargas familiares', 'CIMR y jubilación complementaria', 'Gastos profesionales e indemnizaciones tratadas como exentas', 'Prima de antigüedad y horas extra', 'Salario neto y coste total del empleador'], 'ready' => '¿Listo para entender tu nómina?', 'free' => 'Simulación educativa, gratuita y sin registro.',
    ],
    'calculator' => [
        'title' => 'Simular mi nómina 2026', 'eyebrow' => 'Simulación educativa · unos 2 minutos',
        'intro' => 'Empieza por tu salario base. Muestra las opciones avanzadas solo si las necesitas.', 'simple_title' => '¿Necesitas un cálculo sencillo?',
        'simple_text' => 'El salario base es suficiente. Muestra los complementos solo si son necesarios.', 'advanced_show' => 'Mostrar opciones avanzadas',
        'advanced_hide' => 'Ocultar opciones avanzadas', 'submit' => 'Simular mi nómina', 'reset' => 'Restablecer', 'errors' => 'Errores de entrada:',
    ],
    'result' => ['eyebrow' => 'Resultado de la simulación', 'title' => 'Tu nómina, explicada claramente', 'intro' => 'Empieza por los importes clave y abre el detalle para verificar cada línea.', 'edit' => 'Modificar simulación', 'print' => 'Imprimir', 'details' => 'Ver el detalle completo del cálculo'],
    'documentation' => ['eyebrow' => 'Entender los parámetros', 'title' => 'Documentación de reglas 2026', 'intro' => 'Parámetros, límites y tramos utilizados por 3omar para el sector privado marroquí.', 'badge' => 'Hipótesis de simulación — ejercicio 2026', 'warning' => 'Las referencias indican el origen declarado de los parámetros. No sustituyen una validación adaptada a cada situación.'],
    'validation' => ['base_required' => 'El salario base es obligatorio.', 'base_positive' => 'El salario base debe ser positivo.', 'category_invalid' => 'Categoría profesional no válida.', 'cimr_min' => 'La tasa CIMR debe ser al menos :min%.', 'cimr_max' => 'La tasa CIMR no puede superar :max%.', 'allowance_distinct' => 'Cada tipo de indemnización solo puede declararse una vez.'],
];
