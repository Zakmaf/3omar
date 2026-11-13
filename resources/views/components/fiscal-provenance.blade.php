{{--
    Provenance fiscale d'un exercice (#281), commune au résultat, à la
    comparaison et à la page Fiabilité.

    $exercice  : exercice appliqué (ou décrit)
    $reference : métadonnées brutes du profil, telles qu'exposées par le moteur
                 (reference_fiscale) ou par ExercicesFiscaux::referenceFiscale().

    Les textes source et références juridiques sont lus dans config/payroll.php
    et restent en français, langue des textes officiels cités (lang="fr").
    Aucun taux ni barème n'est affiché ici.
--}}
@props(['exercice', 'reference'])

@php
    $statut = (string) data_get($reference, 'statut', '');
    $source = (array) data_get($reference, 'source', []);
    $projet = $statut === 'projet';
    $referenceIr = data_get($reference, 'reference_ir');
@endphp

@once
@push('head')
<style>
    .fiscal-provenance {
        border: 1px solid var(--hairline);
        border-inline-start: 4px solid var(--s-succ);
        border-radius: var(--radius-sm);
        background: var(--paper);
        padding: .75rem 1rem;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .fiscal-provenance[data-fiscal-status="projet"] {
        border-inline-start-color: var(--s-warn);
    }

    .fiscal-provenance-status {
        border: 1px solid var(--hairline-strong);
        color: var(--ink);
        background: transparent;
        font-weight: 600;
    }

    .fiscal-provenance-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem 1.5rem;
        margin: 0;
    }

    .fiscal-provenance-meta > div {
        min-width: 0;
    }

    .fiscal-provenance-meta dt {
        display: inline;
        font-weight: 600;
        color: var(--ink-2);
    }

    .fiscal-provenance-meta dt::after {
        content: " : ";
    }

    .fiscal-provenance-meta dd {
        display: inline;
        margin: 0;
        color: var(--ink);
    }

    .fiscal-provenance-year {
        direction: ltr;
        unicode-bidi: isolate;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
</style>
@endpush
@endonce

<div {{ $attributes->class(['fiscal-provenance']) }} data-fiscal-year="{{ $exercice }}" data-fiscal-status="{{ $statut }}">
    <p class="mb-1">
        <span class="fw-semibold">{{ __('ui.fiscal_year.label') }}</span>
        <span class="fw-bold fiscal-provenance-year">{{ $exercice }}</span>
        <span class="badge rounded-pill fiscal-provenance-status ms-1">{{ __('ui.fiscal_year.status_'.$statut) }}</span>
    </p>
    <dl class="fiscal-provenance-meta small">
        <div>
            <dt>{{ __('ui.fiscal_year.source') }}</dt>
            <dd lang="fr">
                @if (! empty($source['url']))
                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" style="color:var(--s-info);text-decoration:underline">{{ $source['texte'] ?? '' }}</a>
                @else
                {{ $source['texte'] ?? '' }}
                @endif
            </dd>
        </div>
        @if (! empty($source['version']) || ! empty($source['date_publication']))
        <div>
            <dt>{{ __('ui.fiscal_year.source_version') }}</dt>
            <dd>
                @if (! empty($source['version']))<span lang="fr">{{ $source['version'] }}</span>@endif
                @if (! empty($source['date_publication']))
                <time datetime="{{ $source['date_publication'] }}" class="fiscal-provenance-year">{{ $source['date_publication'] }}</time>
                @endif
            </dd>
        </div>
        @endif
        @if (is_string($referenceIr) && $referenceIr !== '')
        <div>
            <dt>{{ __('ui.fiscal_year.reference') }}</dt>
            <dd lang="fr">{{ $referenceIr }}</dd>
        </div>
        @endif
    </dl>
    @if ($projet)
    <p class="small mb-0 mt-2" style="color:var(--ink-2)">
        <i class="bi bi-exclamation-triangle-fill me-1" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.fiscal_year.project_reserve') }}
    </p>
    @endif
</div>
