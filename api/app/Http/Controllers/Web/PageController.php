<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Static/trust pages (CLAUDE.md Section 7) — content lives in the views themselves, nothing dynamic to fetch. */
class PageController extends Controller
{
    public function about(): View
    {
        return view('web.pages.about', ['title' => 'About Sokoni', 'description' => 'Sokoni is Tanzania\'s marketplace for verified sellers, built for how people actually buy and sell — near you, in your language.']);
    }

    public function howItWorks(): View
    {
        return view('web.pages.how-it-works', ['title' => 'How Sokoni works', 'description' => 'How buying and selling on Sokoni works, from browsing to a completed order.']);
    }

    public function safety(): View
    {
        return view('web.pages.safety', ['title' => 'Safety tips', 'description' => 'Real, practical advice for buying and selling safely on Sokoni.']);
    }

    public function terms(): View
    {
        return view('web.pages.terms', ['title' => 'Terms of Service', 'description' => 'Sokoni\'s terms of service.']);
    }

    public function privacy(): View
    {
        return view('web.pages.privacy', ['title' => 'Privacy Policy', 'description' => 'Sokoni\'s privacy policy.']);
    }

    public function contact(): View
    {
        return view('web.pages.contact', ['title' => 'Contact us', 'description' => 'Get in touch with the Sokoni team.']);
    }

    public function sell(): View
    {
        return view('web.pages.sell', ['title' => 'Start selling on Sokoni', 'description' => 'Reach buyers across Tanzania. Verified shop profile, no listing fees, cash on delivery.']);
    }
}
