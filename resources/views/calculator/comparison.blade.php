@extends('layouts.app')

@section('title', '3omar · '.__('ui.comparison.title'))

@push('head')
<style>
    .cmp-delta-card {
        border-top: 4px solid transparent;
        min-height: 132px;
    }

    .cmp-delta-up {
        border-top-color: var(--s-succ);
    }

    .cmp-delta-down {
        border-top-color: var(--s-tax);
    }

    .cmp-delta-flat {
        border-top-color: var(--hairline-strong);
    }

    .cmp-table th,
    .cmp-table td {
        vertical-align: middle;
    }

    .cmp-num {
        font-family: var(--f-mono);
        white-space: nowrap;
    }

    .cmp-row-changed {
        background: var(--s-info-bg);
    }

    .cmp-scroll {
        overflow-x: auto;
    }
</style>
@endpush

@section('content')
<div class="container">
    @php
        $mad = fn ($amount) => number_format((float) $amount, 2, ',', ' ');
        $madMonth = fn ($amount) => $mad($amount).__('ui.result.unit_mad_month');
        $signed = fn ($amount) => ((float) $amount > 0 ? '+ ' : ((float) $amount < 0 ? '- ' : '')).$mad(abs((float) $amount));

        // Lecture d'un écart : favorable, défavorable ou neutre selon le sens de
        // l'indicateur, jamais selon le simple signe du montant.
        $tone = function (array $metric) {
            if (abs($metric['ecart']) < 0.005 || $metric['sens'] === 'neutre') {
                return 'flat';
            }

            $favorable = $metric['sens'] === 'gain' ? $metric['ecart'] > 0 : $metric['ecart'] < 0;

            return $favorable ? 'up' : 'down';
        };

        $toneColor = ['up' => 'var(--s-succ)', 'down' => 'var(--s-tax)', 'flat' => 'var(--ink-3)'];

        // Entrées structurantes des deux scénarios, pour expliquer les écarts.
        $inputRow = function (array $r, string $key) {
            return $r['input'][$key] ?? null;
        };

        $overtime = fn (array $r) => array_sum(array_column($r['detail_hs'] ?? [], 'montant'));

        $inputRows = [
            ['label' => 'base_salary', 'a' => $inputRow($a, 'salaire_base'), 'b' => $inputRow($b, 'salaire_base'), 'format' => 'mad'],
            ['label' => 'net_target', 'a' => $inputRow($a, 'net_cible'), 'b' => $inputRow($b, 'net_cible'), 'format' => 'mad'],
            ['label' => 'seniority', 'a' => $a['nb_annees_anciennete'], 'b' => $b['nb_annees_anciennete'], 'format' => 'years'],
            ['label' => 'other_bonuses', 'a' => $a['autres_primes'], 'b' => $b['autres_primes'], 'format' => 'mad'],
            ['label' => 'overtime', 'a' => $overtime($a), 'b' => $overtime($b), 'format' => 'mad'],
            ['label' => 'allowances', 'a' => $a['total_indemnites'], 'b' => $b['total_indemnites'], 'format' => 'mad'],
            ['label' => 'cimr_rate', 'a' => $a['cimr_taux'] * 100, 'b' => $b['cimr_taux'] * 100, 'format' => 'pct'],
            ['label' => 'employee_mutuelle', 'a' => $a['mutuelle_salarie'], 'b' => $b['mutuelle_salarie'], 'format' => 'mad'],
            ['label' => 'dependents', 'a' => $a['nb_personnes'], 'b' => $b['nb_personnes'], 'format' => 'count'],
            ['label' => 'pro_expenses', 'a' => $inputRow($a, 'type_frais_pro') ?? 'commun', 'b' => $inputRow($b, 'type_frais_pro') ?? 'commun', 'format' => 'category'],
        ];

        $categoryLabels = [
            'commun' => __('ui.calculator.category_common'),
            'journaliste' => __('ui.calculator.category_journalist'),
            'artiste' => __('ui.calculator.category_artist'),
        ];

        $formatInput = function ($value, string $format) use ($mad, $categoryLabels) {
            if ($value === null || $value === '') {
                return '-';
            }

            return match ($format) {
                'mad' => (float) $value == 0.0 ? '-' : $mad($value),
                'pct' => (float) $value == 0.0 ? '-' : number_format((float) $value, 2, ',', ' ').' %',
                'years', 'count' => (int) $value === 0 ? '-' : (string) (int) $value,
                'category' => $categoryLabels[$value] ?? (string) $value,
                default => (string) $value,
            };
        };

        // Une ligne d'entrée est mise en évidence lorsque les deux scénarios diffèrent.
        $inputRows = array_map(function (array $row) use ($formatInput) {
            $row['a_label'] = $formatInput($row['a'], $row['format']);
            $row['b_label'] = $formatInput($row['b'], $row['format']);
            $row['changed'] = $row['a_label'] !== $row['b_label'];

            return $row;
        }, $inputRows);

        $visibleRows = array_values(array_filter(
            $inputRows,
            fn (array $row) => $row['a_label'] !== '-' || $row['b_label'] !== '-',
        ));

        $shownChanges = array_values(array_filter($visibleRows, fn (array $row) => $row['changed']));

        // Les scénarios peuvent différer sur une entrée avancée absente du tableau
        // ci-dessus : on le dit explicitement plutôt que d'afficher « identiques ».
        $hiddenDifferences = $shownChanges === [] ? $differences : [];

        $comparisonUrl = route('calculator.comparer', ['a' => $payload_a, 'b' => $payload_b]);
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">{{ __('ui.comparison.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1">
                <i class="bi bi-columns-gap me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.comparison.title') }}
            </h1>
            <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.comparison.intro') }}</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 no-print">
            <a href="{{ route('calculator.index', ['s' => $payload_b, 'a' => $payload_a]) }}"
               class="btn text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('ui.comparison.edit_b') }}
            </a>
            <button type="button" onclick="window.print()" class="btn fw-semibold" style="border:1px solid var(--ink-3);color:var(--ink-2)">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('ui.result.print') }}
            </button>
        </div>
    </div>

    {{-- Headline deltas --}}
    <div class="row g-3 mb-4">
        @foreach ($highlights as $metric)
        @php $t = $tone($metric); @endphp
        <div class="col-md-6">
            <div class="section-card cmp-delta-card cmp-delta-{{ $t }} p-3 p-md-4 h-100">
                <div class="eyebrow mb-2">{{ __('ui.comparison.metrics.'.$metric['label']) }}</div>
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                    <span class="h3 fw-bold mb-0 cmp-num" style="color:{{ $toneColor[$t] }}">
                        {{ $signed($metric['ecart']) }}
                    </span>
                    <span class="small" style="color:var(--ink-3)">{{ __('ui.result.unit_mad_month_label') }}</span>
                    @if ($metric['ecart_pct'] !== null)
                    <span class="badge rounded-pill" style="background:var(--g-50);color:var(--g-700)">
                        {{ $signed($metric['ecart_pct']) }} %
                    </span>
                    @endif
                </div>
                <div class="small mt-2" style="color:var(--ink-2)">
                    {{ __('ui.comparison.scenario_a') }} : <span class="cmp-num">{{ $madMonth($metric['a']) }}</span>
                    <span aria-hidden="true">&rarr;</span>
                    {{ __('ui.comparison.scenario_b') }} : <span class="cmp-num">{{ $madMonth($metric['b']) }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- What changed between the two scenarios --}}
    <section class="section-card p-3 p-md-4 mb-4" aria-labelledby="cmp-inputs-title">
        <h2 id="cmp-inputs-title" class="h6 fw-bold mb-1">
            <i class="bi bi-sliders me-2" style="color:var(--s-info)" aria-hidden="true"></i>{{ __('ui.comparison.inputs_title') }}
        </h2>
        <p class="small mb-3" style="color:var(--ink-2)">{{ __('ui.comparison.inputs_intro') }}</p>

        @if ($shownChanges === [] && $hiddenDifferences === [])
        <p class="small mb-0" style="color:var(--ink-3)">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('ui.comparison.inputs_identical') }}
        </p>
        @else
        <div class="cmp-scroll">
            <table class="table table-sm cmp-table mb-0">
                <caption class="visually-hidden">{{ __('ui.comparison.inputs_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('ui.comparison.input_label') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.scenario_a') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.scenario_b') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleRows as $row)
                    <tr class="{{ $row['changed'] ? 'cmp-row-changed' : '' }}">
                        <th scope="row" class="fw-normal">
                            {{ __('ui.comparison.inputs.'.$row['label']) }}
                            @if ($row['changed'])
                            <span class="badge rounded-pill ms-1" style="background:var(--s-info-bg);color:var(--s-info)">{{ __('ui.comparison.changed') }}</span>
                            @endif
                        </th>
                        <td class="text-end cmp-num">{{ $row['a_label'] }}</td>
                        <td class="text-end cmp-num">{{ $row['b_label'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if ($hiddenDifferences !== [])
        <p class="small mb-0 mt-3" style="color:var(--ink-2)">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            {{ __('ui.comparison.inputs_advanced_diff') }}
            <span class="cmp-num">{{ implode(', ', array_column($hiddenDifferences, 'field')) }}</span>
        </p>
        @endif
    </section>

    {{-- Full metric comparison --}}
    <section class="section-card p-3 p-md-4 mb-4" aria-labelledby="cmp-metrics-title">
        <h2 id="cmp-metrics-title" class="h6 fw-bold mb-3">
            <i class="bi bi-table me-2" style="color:var(--s-cot)" aria-hidden="true"></i>{{ __('ui.comparison.metrics_title') }}
        </h2>
        <div class="cmp-scroll">
            <table class="table table-sm cmp-table mb-0">
                <caption class="visually-hidden">{{ __('ui.comparison.metrics_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('ui.comparison.metric_label') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.scenario_a') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.scenario_b') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.delta') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.comparison.delta_pct') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($metrics as $metric)
                    @php $t = $tone($metric); @endphp
                    <tr>
                        <th scope="row" class="fw-{{ $metric['phare'] ? 'bold' : 'normal' }}">
                            {{ __('ui.comparison.metrics.'.$metric['label']) }}
                        </th>
                        <td class="text-end cmp-num">{{ $mad($metric['a']) }}</td>
                        <td class="text-end cmp-num">{{ $mad($metric['b']) }}</td>
                        <td class="text-end cmp-num fw-semibold" style="color:{{ $toneColor[$t] }}">{{ $signed($metric['ecart']) }}</td>
                        <td class="text-end cmp-num" style="color:{{ $toneColor[$t] }}">
                            {{ $metric['ecart_pct'] === null ? '-' : $signed($metric['ecart_pct']).' %' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small mb-0 mt-3" style="color:var(--ink-3)">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('ui.comparison.amounts_note') }}
        </p>
    </section>

    {{-- Warnings raised by either scenario --}}
    @php $allWarnings = array_unique([...$a['avertissements'], ...$b['avertissements']]); @endphp
    @if ($allWarnings !== [])
    <section class="section-card p-3 p-md-4 mb-4" style="background:var(--s-warn-bg)" aria-labelledby="cmp-warnings-title">
        <h2 id="cmp-warnings-title" class="h6 fw-bold mb-2">
            <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.result.warnings_title') }}
        </h2>
        <ul class="small mb-0" style="color:var(--ink-2)">
            @foreach ($allWarnings as $warning)
            <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- Share and drill down --}}
    <section class="section-card p-3 p-md-4 mb-4 no-print" aria-labelledby="cmp-share-title">
        <h2 id="cmp-share-title" class="h6 fw-bold mb-1">
            <i class="bi bi-share me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.comparison.share_title') }}
        </h2>
        <p class="small mb-3" style="color:var(--ink-2)">{{ __('ui.comparison.share_text') }}</p>

        <label for="comparisonUrl" class="form-label small fw-semibold">{{ __('ui.result.share_link_label') }}</label>
        <div class="input-group mb-2">
            <input type="text" class="form-control form-control-sm" id="comparisonUrl" value="{{ $comparisonUrl }}" readonly onfocus="this.select()">
            <button type="button" class="btn btn-sm" style="border:1px solid var(--g-500);color:var(--g-500)"
                    data-copy-target="comparisonUrl" data-copied-label="{{ __('ui.result.share_copied') }}">
                <i class="bi bi-clipboard me-1" aria-hidden="true"></i><span>{{ __('ui.result.share_copy') }}</span>
            </button>
        </div>
        <p class="small mb-3" style="color:var(--s-warn)">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('ui.result.share_privacy_warning') }}
        </p>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('calculator.index', ['s' => $payload_a]) }}" class="btn btn-sm fw-semibold" style="border:1px solid var(--g-500);color:var(--g-500)">
                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('ui.comparison.open_a') }}
            </a>
            <a href="{{ route('calculator.index', ['s' => $payload_b]) }}" class="btn btn-sm fw-semibold" style="border:1px solid var(--g-500);color:var(--g-500)">
                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('ui.comparison.open_b') }}
            </a>
            <a href="{{ route('calculator.index') }}" class="btn btn-sm fw-semibold" style="border:1px solid var(--ink-3);color:var(--ink-2)">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('ui.result.action_simulate_again') }}
            </a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
// Copie du lien de comparaison (#47, #50)
document.querySelectorAll('[data-copy-target]').forEach(button => {
    button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copyTarget);
        if (!field) return;

        try {
            await navigator.clipboard.writeText(field.value);
        } catch {
            field.focus();
            field.select();
            return;
        }

        const label = button.querySelector('span');
        const previous = label.textContent;
        label.textContent = button.dataset.copiedLabel;
        setTimeout(() => { label.textContent = previous; }, 2000);
    });
});
</script>
@endpush
