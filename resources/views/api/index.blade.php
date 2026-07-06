@extends('layouts.app')

@section('title', '3omar · '.__('ui.api.title'))

@section('content')
<div class="container">

    <div class="row mb-4">
        <div class="col">
            <div class="eyebrow mb-2">{{ __('ui.api.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1"><i class="bi bi-braces me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.api.title') }}</h1>
            <p style="color:var(--ink-2)">
                {{ __('ui.api.intro') }}
                <span class="badge rounded-pill ms-1 px-2 py-1" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">v1</span>
            </p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-info-bg);color:var(--s-info)">
                    <i class="bi bi-unlock me-1" aria-hidden="true"></i>{{ __('ui.api.no_auth') }}
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-succ-bg);color:var(--s-succ)">
                    <i class="bi bi-globe me-1" aria-hidden="true"></i>{{ __('ui.api.cors') }}
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-warn-bg);color:var(--s-warn)">
                    <i class="bi bi-speedometer me-1" aria-hidden="true"></i>{{ __('ui.api.rate_limit') }}
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
                <h2 class="h6 fw-bold mb-2"><i class="bi bi-link-45deg me-1" style="color:var(--s-info)" aria-hidden="true"></i>{{ __('ui.api.base_url') }}</h2>
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

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h3 class="h6 fw-bold mb-0">{{ __('ui.api.example_request') }}</h3>
                        <button type="button" class="btn btn-sm py-0 px-2 api-copy-btn" data-copy-target="curl-brut-vers-net" data-copied-label="{{ __('ui.api.copy_done') }}" style="border:1px solid var(--hairline-strong);font-size:.75rem">
                            <i class="bi bi-clipboard me-1"></i>{{ __('ui.api.copy_button') }}
                        </button>
                    </div>
                    <pre id="curl-brut-vers-net" class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl -X POST {{ url('/api/v1/simuler/brut-vers-net') }} \
  -H "Content-Type: application/json" \
  -d '{!! json_encode($endpoints['/simuler/brut-vers-net']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}'</code></pre>

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h3 class="h6 fw-bold mb-0">{{ __('ui.api.js_example') }}</h3>
                        <button type="button" class="btn btn-sm py-0 px-2 api-copy-btn" data-copy-target="js-brut-vers-net" data-copied-label="{{ __('ui.api.copy_done') }}" style="border:1px solid var(--hairline-strong);font-size:.75rem">
                            <i class="bi bi-clipboard me-1"></i>{{ __('ui.api.copy_button') }}
                        </button>
                    </div>
                    <pre id="js-brut-vers-net" class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>fetch("{{ url('/api/v1/simuler/brut-vers-net') }}", {
  method: "POST",
  headers: {"Content-Type": "application/json"},
  body: JSON.stringify({!! json_encode($endpoints['/simuler/brut-vers-net']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!})
}).then(r => r.json()).then(console.log);</code></pre>

                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('ui.api.full_response_example') }}</h3>
                    <pre class="p-3 rounded mb-0" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode([
    'mode' => 'gross_to_net',
    'sbi' => 10000,
    'salaire_net' => 8234.56,
    'ir_net' => 1012.33,
    'cotisation_cnss' => 268.80,
    'cotisation_amo' => 226.00,
    'frais_pro' => 2500.00,
    'rni' => 7005.20,
    'repartition' => ['...' => '...'],
    'avertissements' => [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>

                    <details class="mt-3">
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)"><i class="bi bi-play-circle me-1"></i>{{ __('ui.api.try_it') }}</summary>
                        <p class="text-muted small mt-2 mb-2">{{ __('ui.api.try_it_desc') }}</p>
                        <form class="api-try-form" data-api-url="{{ url('/api/v1/simuler/brut-vers-net') }}" data-api-method="POST" data-error-label="{{ __('ui.api.try_it_error') }}">
                            <label class="form-label small fw-semibold" for="try-body-brut-vers-net">{{ __('ui.api.try_it_body_label') }}</label>
                            <textarea id="try-body-brut-vers-net" class="form-control form-control-sm mb-2 api-try-body" rows="8" style="font-family:var(--f-mono);font-size:.8rem" spellcheck="false">{{ json_encode($endpoints['/simuler/brut-vers-net']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
                            <button type="submit" class="btn btn-sm text-white api-try-submit" data-sending-label="{{ __('ui.api.try_it_sending') }}" style="background:var(--g-500)">
                                <i class="bi bi-send me-1"></i>{{ __('ui.api.try_it_send') }}
                            </button>
                            <div class="mt-2 d-none api-try-result-wrap">
                                <div class="small fw-semibold mb-1">{{ __('ui.api.try_it_response_label') }} <span class="api-try-status"></span></div>
                                <pre class="p-3 rounded mb-0 api-try-output" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem;max-height:320px;overflow:auto"><code></code></pre>
                            </div>
                        </form>
                    </details>
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

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h3 class="h6 fw-bold mb-0">{{ __('ui.api.example_request') }}</h3>
                        <button type="button" class="btn btn-sm py-0 px-2 api-copy-btn" data-copy-target="curl-net-vers-brut" data-copied-label="{{ __('ui.api.copy_done') }}" style="border:1px solid var(--hairline-strong);font-size:.75rem">
                            <i class="bi bi-clipboard me-1"></i>{{ __('ui.api.copy_button') }}
                        </button>
                    </div>
                    <pre id="curl-net-vers-brut" class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl -X POST {{ url('/api/v1/simuler/net-vers-brut') }} \
  -H "Content-Type: application/json" \
  -d '{!! json_encode($endpoints['/simuler/net-vers-brut']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}'</code></pre>

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h3 class="h6 fw-bold mb-0">{{ __('ui.api.js_example') }}</h3>
                        <button type="button" class="btn btn-sm py-0 px-2 api-copy-btn" data-copy-target="js-net-vers-brut" data-copied-label="{{ __('ui.api.copy_done') }}" style="border:1px solid var(--hairline-strong);font-size:.75rem">
                            <i class="bi bi-clipboard me-1"></i>{{ __('ui.api.copy_button') }}
                        </button>
                    </div>
                    <pre id="js-net-vers-brut" class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>fetch("{{ url('/api/v1/simuler/net-vers-brut') }}", {
  method: "POST",
  headers: {"Content-Type": "application/json"},
  body: JSON.stringify({!! json_encode($endpoints['/simuler/net-vers-brut']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!})
}).then(r => r.json()).then(console.log);</code></pre>

                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('ui.api.full_response_example') }}</h3>
                    <pre class="p-3 rounded mb-0" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>{!! json_encode([
    'mode' => 'net_to_gross',
    'sbi' => 9723.45,
    'salaire_net' => 8000.00,
    'resolution_net' => [
        'net_cible' => 8000.00,
        'net_obtenu' => 8000.00,
        'ecart' => 0.00,
        'iterations' => 25,
        'converge' => true,
    ],
    'avertissements' => [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>

                    <details class="mt-3">
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)"><i class="bi bi-play-circle me-1"></i>{{ __('ui.api.try_it') }}</summary>
                        <p class="text-muted small mt-2 mb-2">{{ __('ui.api.try_it_desc') }}</p>
                        <form class="api-try-form" data-api-url="{{ url('/api/v1/simuler/net-vers-brut') }}" data-api-method="POST" data-error-label="{{ __('ui.api.try_it_error') }}">
                            <label class="form-label small fw-semibold" for="try-body-net-vers-brut">{{ __('ui.api.try_it_body_label') }}</label>
                            <textarea id="try-body-net-vers-brut" class="form-control form-control-sm mb-2 api-try-body" rows="6" style="font-family:var(--f-mono);font-size:.8rem" spellcheck="false">{{ json_encode($endpoints['/simuler/net-vers-brut']['post']['requestBody']['content']['application/json']['example'] ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
                            <button type="submit" class="btn btn-sm text-white api-try-submit" data-sending-label="{{ __('ui.api.try_it_sending') }}" style="background:var(--g-500)">
                                <i class="bi bi-send me-1"></i>{{ __('ui.api.try_it_send') }}
                            </button>
                            <div class="mt-2 d-none api-try-result-wrap">
                                <div class="small fw-semibold mb-1">{{ __('ui.api.try_it_response_label') }} <span class="api-try-status"></span></div>
                                <pre class="p-3 rounded mb-0 api-try-output" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem;max-height:320px;overflow:auto"><code></code></pre>
                            </div>
                        </form>
                    </details>
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
                    <pre id="curl-parametres" class="p-3 rounded" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem"><code>curl {{ url('/api/v1/parametres') }}</code></pre>

                    <details class="mt-3">
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)"><i class="bi bi-play-circle me-1"></i>{{ __('ui.api.try_it') }}</summary>
                        <p class="text-muted small mt-2 mb-2">{{ __('ui.api.try_it_desc') }}</p>
                        <form class="api-try-form" data-api-url="{{ url('/api/v1/parametres') }}" data-api-method="GET" data-error-label="{{ __('ui.api.try_it_error') }}">
                            <button type="submit" class="btn btn-sm text-white api-try-submit" data-sending-label="{{ __('ui.api.try_it_sending') }}" style="background:var(--g-500)">
                                <i class="bi bi-send me-1"></i>{{ __('ui.api.try_it_send') }}
                            </button>
                            <div class="mt-2 d-none api-try-result-wrap">
                                <div class="small fw-semibold mb-1">{{ __('ui.api.try_it_response_label') }} <span class="api-try-status"></span></div>
                                <pre class="p-3 rounded mb-0 api-try-output" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem;max-height:320px;overflow:auto"><code></code></pre>
                            </div>
                        </form>
                    </details>
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
                    <p class="text-muted small mt-2 mb-0">{{ __('ui.api.health_version_note') }}</p>

                    <details class="mt-3">
                        <summary class="fw-semibold mb-2" style="color:var(--g-600)"><i class="bi bi-play-circle me-1"></i>{{ __('ui.api.try_it') }}</summary>
                        <p class="text-muted small mt-2 mb-2">{{ __('ui.api.try_it_desc') }}</p>
                        <form class="api-try-form" data-api-url="{{ url('/api/v1/health') }}" data-api-method="GET" data-error-label="{{ __('ui.api.try_it_error') }}">
                            <button type="submit" class="btn btn-sm text-white api-try-submit" data-sending-label="{{ __('ui.api.try_it_sending') }}" style="background:var(--g-500)">
                                <i class="bi bi-send me-1"></i>{{ __('ui.api.try_it_send') }}
                            </button>
                            <div class="mt-2 d-none api-try-result-wrap">
                                <div class="small fw-semibold mb-1">{{ __('ui.api.try_it_response_label') }} <span class="api-try-status"></span></div>
                                <pre class="p-3 rounded mb-0 api-try-output" style="background:var(--g-50);font-family:var(--f-mono);font-size:.8rem;max-height:320px;overflow:auto"><code></code></pre>
                            </div>
                        </form>
                    </details>
                </div>
            </div>

            {{-- Errors --}}
            <div class="card section-card mb-4" id="errors">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-tax)"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
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
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-info-circle me-1" style="color:var(--s-info)" aria-hidden="true"></i>{{ __('ui.api.sidebar_info') }}</h2>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><strong>{{ __('ui.api.format') }} :</strong> JSON</li>
                    <li class="mb-2"><strong>{{ __('ui.api.auth') }} :</strong> {{ __('ui.api.none') }}</li>
                    <li class="mb-2"><strong>CORS :</strong> {{ __('ui.api.cors_detail') }}</li>
                    <li class="mb-2"><strong>{{ __('ui.api.rate_limit_label') }} :</strong> {{ __('ui.api.rate_limit_detail') }}</li>
                    <li class="mb-0"><strong>{{ __('ui.api.error_format') }} :</strong> RFC 7807</li>
                </ul>
            </div>

            <div class="section-card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-file-earmark-code me-1" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.api.openapi_spec') }}</h2>
                <p class="small mb-2" style="color:var(--ink-2)">{{ __('ui.api.openapi_desc') }}</p>
                <a href="{{ asset('api/docs/openapi.json') }}" class="btn btn-sm w-100 mb-2" style="border:1px solid var(--g-500);color:var(--g-500)" target="_blank" rel="noopener">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>openapi.json
                </a>
                <a href="https://github.com/Zakmaf/3omar/blob/main/docs/API.md" class="btn btn-sm w-100" style="border:1px solid var(--hairline-strong)" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('ui.api.markdown_reference') }}
                </a>
            </div>

            <div class="section-card p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-check me-1" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.api.sidebar_limits') }}</h2>
                <div class="small" style="color:var(--ink-2)">
                    <p class="mb-2">{{ __('ui.api.limit_simulation') }}</p>
                    <p class="mb-0">{{ __('ui.api.limit_get') }}</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.api-copy-btn').forEach(btn => {
    const source = document.getElementById(btn.dataset.copyTarget);
    if (!source) return;
    const defaultHtml = btn.innerHTML;

    btn.addEventListener('click', () => {
        navigator.clipboard.writeText(source.innerText).then(() => {
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>' + btn.dataset.copiedLabel;
            setTimeout(() => { btn.innerHTML = defaultHtml; }, 1500);
        });
    });
});

document.querySelectorAll('.api-try-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const url = form.dataset.apiUrl;
        const method = form.dataset.apiMethod;
        const errorLabel = form.dataset.errorLabel;
        const bodyField = form.querySelector('.api-try-body');
        const submitBtn = form.querySelector('.api-try-submit');
        const resultWrap = form.querySelector('.api-try-result-wrap');
        const output = form.querySelector('.api-try-output code');
        const statusEl = form.querySelector('.api-try-status');
        const defaultHtml = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.textContent = submitBtn.dataset.sendingLabel;
        resultWrap.classList.remove('d-none');
        statusEl.textContent = '';
        statusEl.style.color = '';
        output.textContent = '...';

        try {
            const options = { method, headers: { Accept: 'application/json' } };
            if (bodyField) {
                options.headers['Content-Type'] = 'application/json';
                options.body = bodyField.value;
            }

            const response = await fetch(url, options);
            const text = await response.text();

            statusEl.textContent = response.status + ' ' + response.statusText;
            statusEl.style.color = response.ok ? 'var(--s-succ)' : 'var(--s-tax)';

            try {
                output.textContent = JSON.stringify(JSON.parse(text), null, 2);
            } catch {
                output.textContent = text;
            }
        } catch (err) {
            statusEl.textContent = '';
            output.textContent = errorLabel;
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = defaultHtml;
        }
    });
});
</script>
@endpush
