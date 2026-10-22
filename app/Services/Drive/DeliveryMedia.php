<?php

namespace App\Services\Drive;

use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveRangeSource;
use App\Contracts\DriveSource;
use App\Services\ProviderFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DeliveryMedia
{
    public function __construct(private DriveSource $source, private DrivePreviewSource $previews, private DriveRangeSource $ranges) {}

    public function response(Request $request, DriveItem $item, bool $thumbnail = false, bool $attachment = false)
    {
        $etag = '"'.hash('sha256', json_encode([$item->reference->id, $item->version, $item->md5, $item->size, $item->modifiedTime, $item->mimeType])).'"';
        // If-Range dates/weak or stale validators fall back to the complete representation.
        $range = ! $thumbnail && $request->isMethod('GET') && (! $request->hasHeader('If-Range') || $request->header('If-Range') === $etag)
            ? ByteRange::parse($request->header('Range'), $item->size) : null;
        $stream = null;
        try {
            if ($thumbnail) {
                $image = $this->previews->thumbnail($item);
                [$stream, $type, $length] = [$image->stream, $image->mimeType, $image->length];
            } elseif ($request->isMethod('HEAD')) {
                // HEAD never downloads an original, but still checks current provider rights/version.
                $current = $this->source->metadata($item->reference);
                if (! $current->isPhoto() || ! $current->canDownload || $current->snapshot() !== $item->snapshot()) {
                    throw new ProviderFailure('drive_source_unavailable');
                }
                [$type, $length] = [$item->mimeType, $item->size];
            } else {
                $stream = $range ? $this->ranges->originalRange($item, $range) : $this->source->original($item);
                [$type, $length] = [$item->mimeType, $range?->length() ?? $item->size];
            }
            // Read before sending headers: reject provider HTML and early transfer failures as text errors.
            $first = $stream?->read(65536) ?? '';
            $complete = $stream?->eof() ?? true;
            while ($stream !== null && strlen($first) < 64 && ! $complete) {
                $chunk = $stream->read(64 - strlen($first));
                if ($chunk === '') {
                    throw new ProviderFailure('drive_transfer_failed');
                }
                $first .= $chunk;
                $complete = $stream->eof();
            }
            if (($range === null || $range->start === 0) && preg_match('/\A\s*(?:<!doctype\s+html|<html|<\?xml)/i', $first)) {
                throw new ProviderFailure('drive_invalid_media_response');
            }
        } catch (\Throwable) {
            $stream?->close();

            return response('Foto tidak dapat diunduh atau ditampilkan. Sumber mungkin berubah, tidak tersedia, atau izin unduh dicabut. Coba lagi atau hubungi fotografer.', 502,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $name = $this->filename($item);
        $headers = array_filter(['Content-Type' => $type, 'Content-Length' => $length === null ? null : (string) $length,
            'Content-Disposition' => HeaderUtils::makeDisposition($attachment ? 'attachment' : 'inline', $name, $this->fallback($name)),
            'Cache-Control' => 'no-store, private', 'Accept-Ranges' => $thumbnail ? null : 'bytes',
            'ETag' => $thumbnail ? null : $etag, 'Content-Range' => $range ? "bytes {$range->start}-{$range->end}/{$item->size}" : null], fn ($value) => $value !== null);

        if ($request->isMethod('HEAD')) {
            $stream?->close();

            return response('', 200, $headers);
        }

        return response()->stream(function () use ($stream, $first, $complete) {
            if ($stream === null) {
                return;
            }
            try {
                // Keep one chunk pending until the next read/EOF validation succeeds. A late
                // integrity failure leaves Content-Length unsatisfied, never appends an HTML error.
                $pending = $first;
                while (! $complete) {
                    $next = $stream->read(65536);
                    $complete = $stream->eof();
                    echo $pending;
                    $pending = $next;
                    if (connection_aborted()) {
                        return;
                    }
                }
                echo $pending;
            } catch (\Throwable) {
                // The native browser reports an incomplete transfer; raw provider errors stay private.
            } finally {
                $stream->close();
            }
        }, $range ? 206 : 200, $headers);
    }

    private function filename(DriveItem $item): string
    {
        $name = preg_replace('/[\x00-\x1f\x7f\/\\\\<>:"|?*\p{Cf}]/u', '_', $item->name) ?? '';
        $name = trim($name, " .\t");
        $pattern = $item->mimeType === 'image/png' ? '/\.png\z/i' : '/\.jpe?g\z/i';
        $extension = preg_match($pattern, $name, $match) ? $match[0] : ($item->mimeType === 'image/png' ? '.png' : '.jpg');
        $name = preg_replace($pattern, '', $name);
        $name = mb_strcut($name ?: 'photo', 0, 180, 'UTF-8');
        if (preg_match('/\A(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|\z)/i', $name)) {
            $name = '_'.$name;
        }

        return $name.$extension;
    }

    private function fallback(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._ -]/', '_', Str::ascii($name)) ?: 'photo.jpg';
    }
}
