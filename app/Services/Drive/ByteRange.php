<?php

namespace App\Services\Drive;

final readonly class ByteRange
{
    public function __construct(public int $start, public int $end) {}

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    /** Ignore unsupported/malformed ranges; an unsatisfiable byte range is 416. */
    public static function parse(?string $header, int $size): ?self
    {
        if ($header === null || ! preg_match('/\Abytes=(\d*)-(\d*)\z/', $header, $parts) || ($parts[1] === '' && $parts[2] === '')) {
            return null;
        }
        $number = static function (string $value): int {
            $value = ltrim($value, '0');
            $max = (string) PHP_INT_MAX;

            return strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)
                ? PHP_INT_MAX : (int) $value;
        };
        if ($parts[1] === '') {
            $suffix = $number($parts[2]);
            abort_if($suffix === 0 || $size === 0, 416, 'Rentang berkas tidak tersedia.', ['Content-Range' => 'bytes */'.$size]);

            return new self(max(0, $size - $suffix), $size - 1);
        }
        $start = $number($parts[1]);
        $end = $parts[2] === '' ? $size - 1 : $number($parts[2]);
        abort_if($start >= $size || $end < $start, 416, 'Rentang berkas tidak tersedia.', ['Content-Range' => 'bytes */'.$size]);

        return new self($start, min($end, $size - 1));
    }
}
