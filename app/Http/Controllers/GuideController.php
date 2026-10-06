<?php

namespace App\Http\Controllers;

use App\Services\GuideService;

class GuideController extends Controller
{
    public function __construct(private GuideService $guides) {}

    public function show(string $slug)
    {
        $guide = $this->guides->find($slug);

        abort_if($guide === null, 404);

        return view('guides.show', compact('guide'));
    }
}
