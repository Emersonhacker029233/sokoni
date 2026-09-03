<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\SellerOnboardBusinessRequest;
use App\Http\Requests\SellerOnboardIdentityRequest;
use App\Http\Requests\SellerOnboardLicenceRequest;
use App\Http\Requests\SellerOnboardLocationRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * The website's registration form is one page, not the app's 4-step
 * wizard, but the same seller profile with the same rules either way — so
 * this composes its validation directly from the four step requests the
 * API already uses (`rules()` only, no route-bound state needed for any of
 * them) rather than re-typing the same regexes/constraints a second time.
 * `lat`/`lng` are already nullable on the shared location request (see its
 * own docblock) — the web form has no map pin to supply them from.
 */
class SellerWebRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! Auth::user()->isSeller();
    }

    public function rules(): array
    {
        return [
            ...(new SellerOnboardBusinessRequest)->rules(),
            ...(new SellerOnboardLocationRequest)->rules(),
            ...(new SellerOnboardIdentityRequest)->rules(),
            ...(new SellerOnboardLicenceRequest)->rules(),
        ];
    }
}
