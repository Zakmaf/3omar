<?php

namespace App\Http\Controllers;

class ApiDocumentationController extends Controller
{
    public function index()
    {
        $spec = json_decode(file_get_contents(public_path('api/docs/openapi.json')), true);

        // Le fichier openapi.json est statique : on force la version reelle
        // pour que l'exemple affiche ne devienne jamais obsolete (issue #118).
        if (isset($spec['paths']['/health']['get']['responses']['200']['content']['application/json']['example']['version'])) {
            $spec['paths']['/health']['get']['responses']['200']['content']['application/json']['example']['version'] = config('app.version');
        }

        return view('api.index', [
            'spec' => $spec,
            'endpoints' => $spec['paths'] ?? [],
            'schemas' => $spec['components']['schemas'] ?? [],
        ]);
    }
}
