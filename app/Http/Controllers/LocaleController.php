<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(config('app.available_locales'))],
        ]);

        $user = $request->user();

        if ($user) {
            $user->update(['locale' => $data['locale']]);
        } else {
            Cookie::queue('locale', $data['locale'], 60 * 24 * 365, '/', null, false, false, false, 'lax');
        }

        return back();
    }
}
