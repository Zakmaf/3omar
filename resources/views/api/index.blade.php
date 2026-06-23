@extends('layouts.app')

@section('title', '3omar · '.__('ui.api.title'))

@section('content')
<div class="container">

    <div class="row mb-4">
        <div class="col">
            <div class="eyebrow mb-2">{{ __('ui.api.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1"><i class="bi bi-braces me-2" style="color:var(--g-500)"></i>{{ __('ui.api.title') }}</h1>
            <p style="color:var(--ink-2)">
                {{ __('ui.api.intro') }}
                <span class="badge rounded-pill ms-1 px-2 py-1" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">v1</span>
            </p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-info-bg);color:var(--s-info)">
                    <i class="bi bi-unlock me-1"></i>{{ __('ui.api.no_auth') }}
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-succ-bg);color:var(--s-succ)">
                    <i class="bi bi-globe me-1"></i>{{ __('ui.api.cors') }}
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-warn-bg);color:var(--s-warn)">
                    <i class="bi bi-speedometer me-1"></i>{{ __('ui.api.rate_limit') }}
                </span>
            </div>
            <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('ui.api.quick_nav') }}">
                <a class="btn btn-sm" href="#brut-vers-net" style="border:1px solid var(--hairline-strong)">POST /simuler/brut-vers-net</a>
                <a class="btn btn-sm" href="#net-vers-brut" style="border:1px solid var(--hairline-strong)">POST /simuler/net-vers-brut</a>
                <a class="btn btn-sm" href="#parametres" style="border:1px solid var(--hairline-strong)">GET /parametres</a>
                <a class="btn btn-sm" href="#health" style="border:1px solid var(--hairline-strong)">GET /health</a>
                <a class="btn btn-sm" href="#errors" style="border:1px solid var(--hairline-strong)">{{ __('ui.api.nav_errors') }}</a>
            </nav>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            {{-- Base URL --}}
            <div class="section-card p-4 mb-4">
                <h2 class="h6 fw-bold mb-2"><i class="bi bi-link-45deg me-1" style="color:var(--s-info)"></i>{{ __('ui.api.base_url') }}</h2>
                <pre class="mb-0 p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.85rem"><code>{{ url('/api/v1') }}</code></pre>
            </div>

            {{-- POST /simuler/brut-vers-net --}}
            <div class="card section-card mb-4" id="brut-vers-net">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge px-2 py-1 text-white" style="background:var(--s-info);font-family:var(--f-mono);font-size:.75rem">POST</span>
                    <code style="font-family:var(--f-mono);font-size:.85rem">/simuler/brut-vers-net</code>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="mb-3">{{ __('ui.api.brut_vers_net_desc') }}</p>

                    <h3 class="h6 fw-bold mb-2">{{ __('ui.api.required_fields') }}</h3>
                    <table class="table table-sm detail-table mb-3">
                        <thead><tr><th>{{ __('ui.api.field') }}</th><th>{{ __('ui.api.type') }}</th><th>{{ __('ui.api.description') }}</th></tr></thead>
                        <tbody>
                            <tr><td><code>salaire_base</code></td><td>number</td><td>{{ __('ui.api.field_salaire_base') }}</td></tr>
                            <tr><td><code>type_frais_pro</code></td><td>string</td><td>{{ __('ui.api.field_type_frais_pro') }}</td></tr>
                        </tbody>
                    </table>

                    <details>
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)">{{ __('ui.api.optional_fields') }} ({{ count($schemas['BrutVersNetInput']['properties'] ?? []) - 2 }})</summary>
                        <table class="table table-sm detail-table mb-0 mt-2">
                            <thead><tr><th>{{ __('ui.api.field') }}</th><th>{{ __('ui.api.type') }}</th><th>{{ __('ui.api.description') }}</th></tr></thead>
                            <tbody>
                                @foreach(($schemas['BrutVersNetInput']['properties'] ?? []) as $field => $props)
                                    @if(!in_array($field, ['salaire_base', 'type_frais_pro']))
                                    <tr>
                                        <td><code>{{ $field }}</code></td>
                                        <td>{{ $props['type'] ?? 'mixed' }}@if(isset($props['enum'])) <span class="text-muted">({{ implode(', ', $props['enum']) }})</span>@endif</td>
                                        <td>{{ $props['description'] ?? '' }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </details>

                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('ui.api.example_request') }}</h3>
                    <pre class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl -X POST {{ url('/api/v1/simuler/brut-vers-net') }} \
  -H "Content-Type: application/json" \
  -d '{!! json_encode($endpoints['/simuler/brut-vers-net']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}'</code></pre>
                </div>
            </div>

            {{-- POST /simuler/net-vers-brut --}}
            <div class="card section-card mb-4" id="net-vers-brut">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge px-2 py-1 text-white" style="background:var(--s-info);font-family:var(--f-mono);font-size:.75rem">POST</span>
                    <code style="font-family:var(--f-mono);font-size:.85rem">/simuler/net-vers-brut</code>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="mb-3">{{ __('ui.api.net_vers_brut_desc') }}</p>

                    <h3 class="h6 fw-bold mb-2">{{ __('ui.api.required_fields') }}</h3>
                    <table class="table table-sm detail-table mb-3">
                        <thead><tr><th>{{ __('ui.api.field') }}</th><th>{{ __('ui.api.type') }}</th><th>{{ __('ui.api.description') }}</th></tr></thead>
                        <tbody>
                            <tr><td><code>net_cible</code></td><td>number</td><td>{{ __('ui.api.field_net_cible') }}</td></tr>
                            <tr><td><code>type_frais_pro</code></td><td>string</td><td>{{ __('ui.api.field_type_frais_pro') }}</td></tr>
                        </tbody>
                    </table>

                    <details>
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)">{{ __('ui.api.optional_fields') }} ({{ count($schemas['NetVersBrutInput']['properties'] ?? []) - 2 }})</summary>
                        <p class="text-muted small mt-2">{{ __('ui.api.same_optional') }}</p>
                    </details>

                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('ui.api.example_request') }}</h3>
                    <pre class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl -X POST {{ url('/api/v1/simuler/net-vers-brut') }} \
  -H "Content-Type: application/json" \
  -d '{!! json_encode($endpoints['/simuler/net-vers-brut']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}'</code></pre>

                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('ui.api.response_extra') }}</h3>
                    <pre class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode([
    'resolution_net' => [
        'net_cible' => 8000.00,
        'net_obtenu' => 8000.00,
        'ecart' => 0.00,
        'iterations' => 25,
        'converge' => true,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>
                </div>
            </div>

            {{-- GET /parametres --}}
            <div class="card section-card mb-4" id="parametres">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge px-2 py-1 text-white" style="background:var(--s-succ);font-family:var(--f-mono);font-size:.75rem">GET</span>
                    <code style="font-family:var(--f-mono);font-size:.85rem">/parametres</code>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="mb-3">{{ __('ui.api.parametres_desc') }}</p>
                    <h3 class="h6 fw-bold mb-2">{{ __('ui.api.example_request') }}</h3>
                    <pre class="p-3 rounded mb-0" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl {{ url('/api/v1/parametres') }}</code></pre>
                </div>
            </div>

            {{-- GET /health --}}
            <div class="card section-card mb-4" id="health">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge px-2 py-1 text-white" style="background:var(--s-succ);font-family:var(--f-mono);font-size:.75rem">GET</span>
                    <code style="font-family:var(--f-mono);font-size:.85rem">/health</code>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="mb-3">{{ __('ui.api.health_desc') }}</p>
                    <h3 class="h6 fw-bold mb-2">{{ __('ui.api.example_response') }}</h3>
                    <pre class="p-3 rounded mb-0" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode($endpoints['/health']['get']['responses']['200']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>
                </div>
            </div>

            {{-- Errors --}}
            <div class="card section-card mb-4" id="errors">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-tax)"><i class="bi bi-exclamation-triangle"></i></span>
                    <span>{{ __('ui.api.errors_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="mb-3">{{ __('ui.api.errors_intro') }}</p>

                    <h3 class="h6 fw-bold mb-2">422 - {{ __('ui.api.error_validation') }}</h3>
                    <pre class="p-3 rounded" style="background:var(--s-tax-bg);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode([
    'type' => 'about:blank',
    'title' => 'Unprocessable Content',
    'status' => 422,
    'detail' => 'The salaire_base field is required.',
    'errors' => ['salaire_base' => ['The salaire_base field is required.']],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>

                    <h3 class="h6 fw-bold mb-2">429 - {{ __('ui.api.error_rate_limit') }}</h3>
                    <pre class="p-3 rounded mb-0" style="background:var(--s-warn-bg);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode([
    'type' => 'about:blank',
    'title' => 'Too Many Requests',
    'status' => 429,
    'detail' => 'Rate limit exceeded. Try again later.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>
                </div>
            </div>

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="section-card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-1" style="color:var(--s-info)"></i>{{ __('ui.api.sidebar_info') }}</h2>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><strong>{{ __('ui.api.format') }} :</strong> JSON</li>
                    <li class="mb-2"><strong>{{ __('ui.api.auth') }} :</strong> {{ __('ui.api.none') }}</li>
                    <li class="mb-2"><strong>CORS :</strong> {{ __('ui.api.cors_detail') }}</li>
                    <li class="mb-2"><strong>{{ __('ui.api.rate_limit_label') }} :</strong> {{ __('ui.api.rate_limit_detail') }}</li>
                    <li class="mb-0"><strong>{{ __('ui.api.error_format') }} :</strong> RFC 7807</li>
                </ul>
            </div>

            <div class="section-card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-file-earmark-code me-1" style="color:var(--g-500)"></i>{{ __('ui.api.openapi_spec') }}</h2>
                <p class="small mb-2" style="color:var(--ink-2)">{{ __('ui.api.openapi_desc') }}</p>
                <a href="{{ asset('api/docs/openapi.json') }}" class="btn btn-sm w-100" style="border:1px solid var(--g-500);color:var(--g-500)" target="_blank" rel="noopener">
                    <i class="bi bi-download me-1"></i>openapi.json
                </a>
            </div>

            <div class="section-card p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-check me-1" style="color:var(--s-warn)"></i>{{ __('ui.api.sidebar_limits') }}</h2>
                <div class="small" style="color:var(--ink-2)">
                    <p class="mb-2">{{ __('ui.api.limit_simulation') }}</p>
                    <p class="mb-0">{{ __('ui.api.limit_get') }}</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
