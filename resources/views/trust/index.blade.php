@extends('layouts.app')

@section('title', '3omar · '.__('ui.trust.title'))

@section('content')
<div class="container">

    {{-- Page header --}}
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8 text-center">
            <div class="eyebrow mb-2">{{ __('ui.trust.eyebrow') }}</div>
            <h1 class="display-5 fw-bold mb-3" style="letter-spacing:-0.03em">{{ __('ui.trust.title') }}</h1>
            <p class="lead mb-0" style="color:var(--ink-2);max-width:38rem;margin-inline:auto">{{ __('ui.trust.intro') }}</p>
        </div>
    </div>

    <div class="row g-4">

        {{-- Main column --}}
        <div class="col-lg-8">

            {{-- Privacy section --}}
            <section class="section-card p-4 p-lg-5 mb-4" aria-labelledby="trust-privacy-title">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span style="font-size:2rem;line-height:1" aria-hidden="true"><i class="bi bi-shield-check" style="color:var(--s-succ)"></i></span>
                    <div>
                        <h2 class="h4 fw-bold mb-1" id="trust-privacy-title">{{ __('ui.trust.privacy_title') }}</h2>
                        <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.trust.privacy_intro') }}</p>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    @foreach (__('ui.trust.privacy_points') as $point)
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill flex-shrink-0 mt-1" style="color:var(--s-succ)"></i>
                            <span>{{ $point }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- Open Source section --}}
            <section class="section-card p-4 p-lg-5 mb-4" aria-labelledby="trust-oss-title">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span style="font-size:2rem;line-height:1" aria-hidden="true"><i class="bi bi-github" style="color:var(--ink)"></i></span>
                    <div>
                        <h2 class="h4 fw-bold mb-1" id="trust-oss-title">{{ __('ui.trust.oss_title') }}</h2>
                        <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.trust.oss_intro') }}</p>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    @foreach (__('ui.trust.oss_points') as $point)
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill flex-shrink-0 mt-1" style="color:var(--s-succ)"></i>
                            <span>{{ $point }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 d-flex flex-wrap gap-3">
                    <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener"
                       class="btn fw-semibold" style="border:1px solid var(--hairline-strong);color:var(--ink)">
                        <i class="bi bi-github me-2"></i>{{ __('ui.trust.oss_github_cta') }}
                    </a>
                    <a href="https://github.com/Zakmaf/3omar/blob/main/LICENSE" target="_blank" rel="noopener"
                       class="btn fw-semibold" style="border:1px solid var(--hairline-strong);color:var(--ink)">
                        <i class="bi bi-file-text me-2"></i>{{ __('ui.trust.oss_license_cta') }}
                    </a>
                    <a href="https://github.com/Zakmaf/3omar/blob/main/SECURITY.md" target="_blank" rel="noopener"
                       class="btn fw-semibold" style="border:1px solid var(--hairline-strong);color:var(--ink)">
                        <i class="bi bi-shield-lock me-2"></i>{{ __('ui.trust.oss_security_cta') }}
                    </a>
                </div>
            </section>

            {{-- Limitations section --}}
            <section class="section-card p-4 p-lg-5 mb-4" aria-labelledby="trust-limits-title">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span style="font-size:2rem;line-height:1" aria-hidden="true"><i class="bi bi-exclamation-triangle" style="color:var(--s-warn)"></i></span>
                    <div>
                        <h2 class="h4 fw-bold mb-1" id="trust-limits-title">{{ __('ui.trust.limits_title') }}</h2>
                        <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.trust.limits_intro') }}</p>
                        <p class="mb-0 mt-2 fw-semibold" style="color:var(--ink)">{{ __('ui.trust.official_payslip_notice') }}</p>
                    </div>
                </div>
                <ul class="mb-0 mt-3" style="color:var(--ink-2)">
                    @foreach (__('ui.trust.limits_list') as $limit)
                    <li class="mb-2">{{ $limit }}</li>
                    @endforeach
                </ul>
                <div class="mt-4 p-3 rounded-3" style="background:var(--s-warn-bg);border:1px solid color-mix(in srgb, var(--s-warn) 30%, transparent)">
                    <p class="mb-0 small"><i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--s-warn)"></i>{{ __('ui.trust.limits_disclaimer') }}</p>
                </div>
                <div class="mt-3">
                    <a href="https://github.com/Zakmaf/3omar/issues/new" target="_blank" rel="noopener"
                       class="small fw-semibold text-decoration-none" style="color:var(--g-600)">
                        <i class="bi bi-bug me-1" aria-hidden="true"></i>{{ __('ui.trust.report_error_cta') }}
                    </a>
                </div>
            </section>

            {{-- Reliability Matrix --}}
            <section class="section-card overflow-hidden mb-4" aria-labelledby="trust-matrix-title">
                <div class="p-4 p-lg-5 pb-3">
                    <div class="eyebrow mb-1">{{ __('ui.trust.matrix_eyebrow') }}</div>
                    <h2 class="h4 fw-bold mb-1" id="trust-matrix-title">{{ __('ui.trust.matrix_title') }}</h2>
                    <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.trust.matrix_intro') }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="min-width:600px">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-2" style="width:30%">{{ __('ui.trust.matrix_col_rule') }}</th>
                                <th class="px-3 py-2" style="width:20%">{{ __('ui.trust.matrix_col_value') }}</th>
                                <th class="px-3 py-2" style="width:15%">{{ __('ui.trust.matrix_col_source') }}</th>
                                <th class="px-3 py-2 text-center" style="width:15%">{{ __('ui.trust.matrix_col_checked') }}</th>
                                <th class="px-3 py-2 text-center" style="width:10%">{{ __('ui.trust.matrix_col_confidence') }}</th>
                                <th class="px-3 py-2 text-center" style="width:10%">{{ __('ui.trust.matrix_col_coverage') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matrix as $row)
                            <tr>
                                <td class="px-4 py-2 fw-semibold">{{ $row['rule'] }}</td>
                                <td class="px-3 py-2 small" style="font-family:var(--f-mono)">{{ $row['taux'] }}<br><span class="text-muted">{{ $row['plafond'] }}</span></td>
                                <td class="px-3 py-2 small">
                                    <span class="badge-legal">{{ $row['source'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-center small" style="font-family:var(--f-mono);color:var(--ink-3)">{{ $row['derniere_verification'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($row['confiance'] === 'high')
                                        <span class="badge rounded-pill" style="background:var(--s-succ-bg);color:var(--s-succ);border:1px solid currentColor">
                                            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('ui.trust.confidence_high') }}
                                        </span>
                                    @elseif($row['confiance'] === 'medium')
                                        <span class="badge rounded-pill" style="background:var(--s-warn-bg);color:var(--s-warn);border:1px solid currentColor">
                                            <i class="bi bi-dash-circle-fill me-1" aria-hidden="true"></i>{{ __('ui.trust.confidence_medium') }}
                                        </span>
                                    @else
                                        <span class="badge rounded-pill" style="background:var(--s-tax-bg);color:var(--s-tax);border:1px solid currentColor">
                                            <i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>{{ __('ui.trust.confidence_low') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($row['couverture'] === 'full')
                                        <i class="bi bi-circle-fill" style="color:var(--s-succ);font-size:.55rem" title="{{ __('ui.trust.coverage_full') }}" role="img" aria-label="{{ __('ui.trust.coverage_full') }}"></i>
                                    @else
                                        <i class="bi bi-circle-half" style="color:var(--s-warn);font-size:.55rem" title="{{ __('ui.trust.coverage_partial') }}" role="img" aria-label="{{ __('ui.trust.coverage_partial') }}"></i>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 small" style="color:var(--ink-3)">
                    <span class="me-4"><i class="bi bi-circle-fill me-1" style="color:var(--s-succ);font-size:.55rem"></i>{{ __('ui.trust.coverage_full') }}</span>
                    <span class="me-4"><i class="bi bi-circle-half me-1" style="color:var(--s-warn);font-size:.55rem"></i>{{ __('ui.trust.coverage_partial') }}</span>
                    <span class="me-4"><i class="bi bi-check-circle-fill me-1" style="color:var(--s-succ)"></i>{{ __('ui.trust.confidence_high') }}</span>
                    <span><i class="bi bi-dash-circle-fill me-1" style="color:var(--s-warn)"></i>{{ __('ui.trust.confidence_medium') }}</span>
                </div>
            </section>

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4 d-none d-lg-block">

            {{-- Quick nav --}}
            <div class="section-card p-4 mb-4 position-sticky" style="top:1.5rem">
                <h3 class="h6 fw-bold mb-3" style="font-family:var(--f-display)">{{ __('ui.trust.sidebar_nav_title') }}</h3>
                <ul class="list-unstyled small mb-4">
                    <li class="mb-2">
                        <a href="#trust-privacy-title" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-shield-check me-2"></i>{{ __('ui.trust.privacy_title') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#trust-oss-title" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-github me-2"></i>{{ __('ui.trust.oss_title') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#trust-limits-title" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-exclamation-triangle me-2"></i>{{ __('ui.trust.limits_title') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#trust-matrix-title" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-table me-2"></i>{{ __('ui.trust.matrix_title') }}
                        </a>
                    </li>
                </ul>

                <hr style="border-color:var(--hairline)">

                <h3 class="h6 fw-bold mb-3 mt-3" style="font-family:var(--f-display)">{{ __('ui.trust.sidebar_links_title') }}</h3>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2">
                        <a href="{{ route('calculator.index') }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-calculator me-2"></i>{{ __('ui.nav.calculator') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('documentation') }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-journal-text me-2"></i>{{ __('ui.nav.documentation') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('api.documentation') }}" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-braces me-2"></i>{{ __('ui.nav.api') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-github me-2"></i>{{ __('ui.footer.source') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar/blob/main/SECURITY.md" target="_blank" rel="noopener" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-shield-lock me-2"></i>{{ __('ui.trust.oss_security_cta') }}
                        </a>
                    </li>
                    <li class="mb-0">
                        <a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener" style="color:var(--g-600);text-decoration:none">
                            <i class="bi bi-bug me-2"></i>{{ __('ui.footer.report') }}
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    {{-- Bottom CTA --}}
    <section class="text-center my-5 py-4" aria-labelledby="trust-cta-title">
        <h2 id="trust-cta-title" class="h4 fw-bold mb-2">{{ __('ui.trust.cta_title') }}</h2>
        <p class="mb-4" style="color:var(--ink-3)">{{ __('ui.trust.cta_text') }}</p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-calculator me-2"></i>{{ __('ui.home.simulate') }}
            </a>
            <a href="{{ route('documentation') }}" class="btn btn-lg px-4 fw-semibold" style="color:var(--g-500);border:1px solid var(--g-500)">
                <i class="bi bi-journal-text me-2"></i>{{ __('ui.home.rules') }}
            </a>
        </div>
    </section>

</div>
@endsection
