<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.supported_locales')), 404);

        $request->session()->put('locale', $locale);

        $target = url()->previous(route('home'));
        $targetHost = parse_url($target, PHP_URL_HOST);
        $currentHost = $request->getHost();
        $targetPath = parse_url($target, PHP_URL_PATH) ?: '/';

        if ($targetHost !== $currentHost || str_starts_with($targetPath, '/lang/')) {
            $target = route('home');
        }

        return redirect()->to($target);
    }
}
