<?php

namespace App\Services\Drive;

final readonly class DrivePage
{
    /** @param list<DriveItem> $items */
    public function __construct(public array $items, public ?string $nextPageToken = null) {}
}
