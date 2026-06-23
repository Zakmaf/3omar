<?php

namespace App\Http\Controllers;

class ApiDocumentationController extends Controller
{
    public function index()
    {
        $spec = json_decode(file_get_contents(public_path('api/docs/openapi.json')), true);

        return view('api.index', [
            'spec' => $spec,
            'endpoints' => $spec['paths'] ?? [],
            'schemas' => $spec['components']['schemas'] ?? [],
        ]);
    }
}
