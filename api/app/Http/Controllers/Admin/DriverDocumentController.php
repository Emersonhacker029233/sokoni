<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Support\DriverDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /admin/driver-documents/{driver}/{document}[?download=1]
 *
 * Registered inside the Filament panel's authenticated routes, so a guest
 * is sent to the admin login and a non-panel user gets 403 before this
 * runs. Staff can use the panel but not driver identity documents: this
 * checks the admin role itself.
 */
class DriverDocumentController extends Controller
{
    public function __invoke(Request $request, DriverProfile $driver, string $document): StreamedResponse
    {
        abort_unless($request->user()?->isAdminRole(), 403);

        $path = DriverDocuments::path($driver, $document);
        $disk = Storage::disk('local');
        abort_if($path === null || ! $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';

        return $disk->response(
            $path,
            "driver-{$driver->id}-{$document}.{$extension}",
            [
                // Identity documents must not sit in a shared or browser cache.
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }
}
