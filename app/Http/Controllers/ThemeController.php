<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Persist the user's preferred color theme in the session.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->session()->put('theme', $validated['theme']);

        return back();
    }
}
