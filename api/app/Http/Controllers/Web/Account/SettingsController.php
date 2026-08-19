<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('web.account.settings', ['user' => Auth::user(), 'title' => __('site.account_settings')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'locale' => ['required', 'in:en,sw'],
        ]);

        $user->update($data);

        return back()->with('status', 'Profile updated.');
    }
}
