<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIntentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IntentController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::user()->account_intent !== null) {
            return redirect()->route('web.account.dashboard');
        }

        return view('web.auth.intent', ['title' => 'What brings you to Sokoni?']);
    }

    public function store(UpdateIntentRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->update(['account_intent' => $request->string('intent')->toString()]);

        return $request->string('intent')->toString() === 'sell'
            ? redirect()->route('web.account.shop')
            : redirect()->route('web.account.dashboard');
    }
}
