<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;

/** Every banner link routes through here so a click can be counted server-side before handing off to the real (often external) destination. */
class BannerClickController extends Controller
{
    public function __invoke(Banner $banner): RedirectResponse
    {
        $banner->recordClick();

        return redirect()->away($banner->link_url);
    }
}
