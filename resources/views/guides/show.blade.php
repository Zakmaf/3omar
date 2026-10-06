@extends('layouts.app')

@php
    $t = 'ui.guides.'.$guide['key'];
    $p = $guide['placeholders'];
    $ctaUrl = route('calculator.index', ['profil' => $guide['profil']]);
    $format = fn (array $v) => match ($v['type']) {
        'money' => number_format($v['value'], 2, ',', ' ').__('ui.result.unit_mad_month'),
        'pct' => rtrim(rtrim(number_format($v['value'] * 100, 2, ',', ' '), '0'), ',').' %',
        'years' => __('ui.guides.common.years', ['count' => $v['value']]),
    };
@endphp

@section('title', __($t.'.meta_title'))
@section('meta_description', __($t.'.meta_description', $p))

@push('head')
    <link rel="canonical" href="{{ route('guides.'.$guide['slug']) }}">
@endpush

@section('content')
<div class="container">

    {{-- En-tête --}}
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8 text-center">
            <div class="eyebrow mb-2">{{ __('ui.guides.common.eyebrow') }}</div>
            <h1 class="display-5 fw-bold mb-3" style="letter-spacing:-0.03em">{{ __($t.'.title') }}</h1>
            <p class="lead mb-4" style="color:var(--ink-2);max-width:40rem;margin-inline:auto">{{ __($t.'.intro', $p) }}</p>
            <a href="{{ $ctaUrl }}" class="btn btn-lg px-4 text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-calculator me-2" aria-hidden="true"></i>{{ __($t.'.cta') }}
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            {{-- Explication --}}
            <section class="section-card p-4 p-lg-5 mb-4" aria-labelledby="guide-explain-title">
                <h2 class="h4 fw-bold mb-3" id="guide-explain-title">
                    <i class="bi {{ $guide['icon'] }} me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __($t.'.explain_title') }}
                </h2>
                @foreach (__($t.'.explain', $p) as $paragraph)
                    <p @class(['mb-0' => $loop->last]) style="color:var(--ink-2)">{{ $paragraph }}</p>
                @endforeach
            </section>

            {{-- Exemple chiffré, calculé par le moteur --}}
            <section class="section-card overflow-hidden mb-4" aria-labelledby="guide-example-title">
                <div class="p-4 p-lg-5 pb-3">
                    <div class="eyebrow mb-1">{{ __('ui.guides.common.example_eyebrow') }}</div>
                    <h2 class="h4 fw-bold mb-2" id="guide-example-title">{{ __('ui.guides.common.example_title') }}</h2>
                    <p class="mb-0" style="color:var(--ink-2)">{{ __($t.'.example_intro', $p) }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" aria-labelledby="guide-example-title">
                        <tbody>
                            @foreach ($guide['example'] as $row)
                            <tr @if ($row['highlight']) style="background:var(--s-succ-bg)" @endif>
                                <th scope="row" @class(['px-4 py-2', 'fw-normal' => ! $row['highlight'], 'fw-bold' => $row['highlight']])>{{ __('ui.guides.rows.'.$row['key']) }}</th>
                                <td @class(['px-4 py-2 text-end', 'fw-bold' => $row['highlight']]) style="font-family:var(--f-mono);white-space:nowrap">{{ $format($row['value']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    <p class="small mb-3" style="color:var(--ink-3)">
                        <i class="bi bi-cpu me-1" aria-hidden="true"></i>{{ __('ui.guides.common.example_note') }}
                    </p>
                    <a href="{{ $ctaUrl }}" class="btn fw-semibold" style="color:var(--g-500);border:1px solid var(--g-500)">
                        <i class="bi bi-sliders me-2" aria-hidden="true"></i>{{ __('ui.guides.common.example_cta') }}
                    </a>
                </div>
            </section>

            {{-- Limites --}}
            <section class="section-card p-4 p-lg-5 mb-4" aria-labelledby="guide-limits-title">
                <h2 class="h4 fw-bold mb-3" id="guide-limits-title">
                    <i class="bi bi-exclamation-triangle me-2" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.guides.common.limits_title') }}
                </h2>
                <ul class="mb-3" style="color:var(--ink-2)">
                    @foreach (__($t.'.limits', $p) as $limit)
                    <li class="mb-2">{{ $limit }}</li>
                    @endforeach
                </ul>
                <div class="p-3 rounded-3" style="background:var(--s-warn-bg);border:1px solid color-mix(in srgb, var(--s-warn) 30%, transparent)">
                    <p class="mb-0 small"><i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.trust.official_payslip_notice') }}</p>
                </div>
            </section>

        </div>

        {{-- Colonne latérale : repères, sources, guides liés --}}
        <aside class="col-lg-4">
            <section class="section-card p-4 mb-4" aria-labelledby="guide-facts-title">
                <h2 class="h6 fw-bold mb-3" id="guide-facts-title" style="font-family:var(--f-display)">{{ __('ui.guides.common.facts_title') }}</h2>
                <dl class="small mb-0">
                    @foreach ($guide['facts'] as $fact)
                    <div class="d-flex justify-content-between gap-3 py-1 border-bottom" style="border-color:var(--hairline)!important">
                        <dt class="fw-normal" style="color:var(--ink-2)">{{ $fact['label'] }}</dt>
                        <dd class="mb-0 fw-semibold text-end" style="font-family:var(--f-mono)">{{ $fact['value'] }}</dd>
                    </div>
                    @endforeach
                </dl>
            </section>

            <section class="section-card p-4 mb-4" aria-labelledby="guide-sources-title">
                <h2 class="h6 fw-bold mb-3" id="guide-sources-title" style="font-family:var(--f-display)">{{ __('ui.guides.common.sources_title') }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($guide['sources'] as $source)
                    <span class="badge-legal">{{ $source }}</span>
                    @endforeach
                </div>
                <ul class="list-unstyled small mb-0">
                    @foreach ($guide['docs'] as $anchor)
                    <li class="mb-2">
                        <a href="{{ route('documentation') }}#{{ $anchor }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-journal-text me-2" aria-hidden="true"></i>{{ __('ui.guides.docs.'.$anchor) }}
                        </a>
                    </li>
                    @endforeach
                    <li class="mb-0">
                        <a href="{{ route('trust') }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-shield-check me-2" aria-hidden="true"></i>{{ __('ui.guides.common.trust_link') }}
                        </a>
                    </li>
                </ul>
            </section>

            <nav class="section-card p-4 mb-4" aria-labelledby="guide-related-title">
                <h2 class="h6 fw-bold mb-3" id="guide-related-title" style="font-family:var(--f-display)">{{ __('ui.guides.common.related_title') }}</h2>
                <ul class="list-unstyled small mb-0">
                    @foreach ($guide['related'] as $link)
                    <li @class(['mb-2' => ! $loop->last])>
                        <a href="{{ route('guides.'.$link['slug']) }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi {{ $link['icon'] }} me-2" aria-hidden="true"></i>{{ __('ui.guides.'.$link['key'].'.nav_label') }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    </div>

    {{-- CTA final --}}
    <section class="text-center my-5 py-4" aria-labelledby="guide-cta-title">
        <h2 id="guide-cta-title" class="h4 fw-bold mb-2">{{ __('ui.guides.common.cta_title') }}</h2>
        <p class="mb-4" style="color:var(--ink-3)">{{ __('ui.guides.common.cta_text') }}</p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="{{ $ctaUrl }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-calculator me-2" aria-hidden="true"></i>{{ __($t.'.cta') }}
            </a>
            <a href="{{ route('documentation') }}" class="btn btn-lg px-4 fw-semibold" style="color:var(--g-500);border:1px solid var(--g-500)">
                <i class="bi bi-journal-text me-2" aria-hidden="true"></i>{{ __('ui.home.rules') }}
            </a>
        </div>
    </section>

</div>
@endsection
