<?php

namespace Tests\Support;

use App\Contracts\DriveSource;
use App\Services\Drive\DriveItem;
use App\Services\Drive\DrivePage;
use App\Services\Drive\DriveReference;
use App\Services\ProviderFailure;
use Psr\Http\Message\StreamInterface;

class CatalogueDrive implements DriveSource
{
    public array $pages = [];

    public array $calls = [];

    public ?\Closure $beforePage = null;

    public function metadata(DriveReference $reference): DriveItem
    {
        $this->calls[] = [$reference->id, $reference->resourceKey];

        return DriveItem::fromArray(DriveFixtures::folder($reference->id, $reference->resourceKey));
    }

    public function children(DriveReference $folder, ?string $pageToken = null): DrivePage
    {
        if ($this->beforePage) {
            ($this->beforePage)($folder, $pageToken);
        }
        $page = $this->pages[$folder->id.':'.($pageToken ?? '')] ?? new ProviderFailure('drive_source_unavailable');
        if ($page instanceof \Throwable) {
            throw $page;
        }

        return new DrivePage(array_map(DriveItem::fromArray(...), $page[0]), $page[1] ?? null);
    }

    public function original(DriveItem $expected): StreamInterface
    {
        throw new \LogicException('Metadata imports must never download originals.');
    }
}
