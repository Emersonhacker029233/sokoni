<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Support\Legal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TermsAcceptanceController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user->terms_accepted_at !== null && $user->terms_version === Legal::TERMS_VERSION) {
            return redirect()->route('web.account.dashboard');
        }

        return view('web.auth.terms', ['title' => 'Before you continue']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['accepted' => ['accepted']]);

        $user = Auth::user();
        // forceFill: terms_accepted_at/terms_version are recorded exactly
        // like the app's own `POST /auth/terms/accept` — the same two
        // fields, the same version constant (App\Support\Legal).
        $user->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
        ])->save();

        return redirect()->route('web.auth.intent');
    }
}
