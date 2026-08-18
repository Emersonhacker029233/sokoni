<?php

namespace App\Support;

use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain synchronous CSV streaming, not Filament's built-in async
 * `ExportAction` — that dispatches to a queue, and this app's production
 * target (cPanel shared hosting, see docs/DEPLOY.md) has no queue worker
 * process, only a once-a-minute `schedule:run` cron. A queued export
 * would sit in the `jobs` table until something happens to process it,
 * which is worse than no export button at all. Streaming the CSV directly
 * in the request is the reliable choice given that constraint — the same
 * reasoning CLAUDE.md itself already applied to chat (polling, not
 * WebSockets) for the same hosting limitation.
 */
class CsvExporter
{
    /**
     * @param  array<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows  each item is a plain array of column values, same order as $headers
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /** Wraps a query in a memory-safe cursor so a large filtered export doesn't load everything into memory at once. */
    public static function cursorRows(iterable $records, callable $toRow): LazyCollection
    {
        return LazyCollection::make(function () use ($records, $toRow) {
            foreach ($records as $record) {
                yield $toRow($record);
            }
        });
    }
}
