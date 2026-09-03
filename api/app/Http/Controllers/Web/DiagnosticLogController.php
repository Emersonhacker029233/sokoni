<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * TEMPORARY — added to chase the production-only /c/{category} 500 that
 * doesn't reproduce locally, so it can be read from a browser with no SSH
 * access to the host. Delete this file and its route in routes/web.php
 * (search for "TEMPORARY DIAGNOSTIC") once the cause is confirmed — it's
 * exactly these two things, nothing else was touched to add it.
 *
 * Gated on DIAGNOSTIC_LOG_TOKEN in .env (read via env() directly, not
 * config(), so it works immediately after being set with no config:cache
 * step) — with no token configured, the route always 404s. Uses env()
 * outside a config file, which is against normal Laravel convention;
 * accepted here specifically because this is temporary, removable
 * scaffolding, not part of the app's real configuration surface.
 */
class DiagnosticLogController extends Controller
{
    private const LINES = 50;

    public function __invoke(Request $request): Response
    {
        $token = env('DIAGNOSTIC_LOG_TOKEN');

        abort_unless(
            filled($token) && hash_equals($token, (string) $request->query('token')),
            404,
        );

        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return response("No log file at {$path} yet.", 200)->header('Content-Type', 'text/plain');
        }

        return response(self::tail($path, self::LINES), 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Reads the last $lines lines without loading the whole file into
     * memory — laravel.log on a long-running install can be large, and a
     * single Laravel exception entry (stack trace included) can itself
     * span dozens of lines, so this walks backward in chunks rather than
     * doing a naive file(...) + array_slice.
     */
    private static function tail(string $path, int $lines): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return "Could not open {$path}.";
        }

        $chunkSize = 4096;
        $buffer = '';
        $lineCount = 0;
        $pos = filesize($path);

        while ($pos > 0 && $lineCount <= $lines) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize);
            $buffer = $chunk.$buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        $allLines = explode("\n", $buffer);

        return implode("\n", array_slice($allLines, -$lines));
    }
}
