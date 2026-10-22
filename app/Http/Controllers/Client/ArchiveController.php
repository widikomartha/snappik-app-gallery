<?php

namespace App\Http\Controllers\Client;

use App\Contracts\DeliveryArchiveStorage;
use App\Http\Controllers\Controller;
use App\Models\ArchiveJob;
use App\Services\Archives\DeliveryArchives;
use App\Services\Drive\ByteRange;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function status(Request $request, string $capability, DeliveryArchives $archives)
    {
        return $archives->authorized($request, $capability, fn ($module) => response()->json(['archive' => $archives->state($archives->current($module))]));
    }

    public function store(Request $request, string $capability, DeliveryArchives $archives)
    {
        return $archives->authorized($request, $capability, fn ($module) => response()->json(['archive' => $archives->state($archives->request($module))], 202));
    }

    public function download(Request $request, string $capability, string $archive, DeliveryArchives $archives)
    {
        $job = $archives->authorized($request, $capability, function ($module) use ($archive, $archives) {
            $job = $archives->current($module);
            abort_unless($job && $job->id === $archive && $job->state === 'ready' && $job->expires_at?->isFuture(), 404);

            return $job;
        });
        $size = $job->zip_bytes;
        $etag = '"'.hash('sha256', $job->id.':'.$job->attempt_id.':'.$size).'"';
        $range = $request->isMethod('GET') && (! $request->hasHeader('If-Range') || $request->header('If-Range') === $etag)
            ? ByteRange::parse($request->header('Range'), $size) : null;
        $length = $range?->length() ?? $size;
        $stream = null;
        try {
            $stream = app(DeliveryArchiveStorage::class)->slice($job->object_key, $range ?? new ByteRange(0, $size - 1), $size);
            $first = $request->isMethod('HEAD') ? '' : $stream->read(min(65536, $length));
            if (! $request->isMethod('HEAD') && $first === '') {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            $stream?->close();
            // A missing/unreadable ready object must not trap recreation behind
            // ready deduplication for the remainder of its lifetime.
            ArchiveJob::whereKey($job->id)->where('attempt_id', $job->attempt_id)->where('state', 'ready')
                ->update(['state' => 'failed', 'failure' => 'archive_output_unavailable']);

            return response('ZIP tidak tersedia. Periksa status, coba lagi atau hubungi fotografer.', 502, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $headers = ['Content-Type' => 'application/zip', 'Content-Length' => (string) $length,
            'Content-Disposition' => 'attachment; filename="SnapPik-hasil-akhir.zip"', 'Accept-Ranges' => 'bytes', 'ETag' => $etag,
            'Cache-Control' => 'no-store, private'];
        if ($range) {
            $headers['Content-Range'] = "bytes {$range->start}-{$range->end}/{$size}";
        }
        if ($request->isMethod('HEAD')) {
            $stream->close();

            return response('', 200, $headers);
        }

        return response()->stream(function () use ($stream, $first, $length) {
            try {
                $remaining = $length - strlen($first);
                $pending = $first;
                while ($remaining > 0) {
                    $next = $stream->read(min(65536, $remaining));
                    if ($next === '' || strlen($next) > $remaining) {
                        return;
                    }
                    $remaining -= strlen($next);
                    echo $pending;
                    $pending = $next;
                    if (connection_aborted()) {
                        return;
                    }
                }
                if ($stream->read(1) === '' && $stream->eof()) {
                    echo $pending;
                }
            } catch (\Throwable) {
                // An incomplete transfer stays incomplete; never append exception HTML to a ZIP.
            } finally {
                $stream->close();
            }
        }, $range ? 206 : 200, $headers);
    }
}
