<?php

namespace App\Jobs;

use App\Models\CatalogueRevision;
use App\Services\Drive\ImportProjectCatalogue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportCatalogue implements ShouldQueue
{
    use Queueable;

    // Imports have no catalogue-size wall-clock cap. PostgreSQL checkpoints allow
    // redelivery after a killed worker; the session lock excludes duplicate work.
    public $timeout = 0;

    public $tries = 0;

    public function __construct(public readonly string $revisionId) {}

    public function handle(ImportProjectCatalogue $importer): void
    {
        if (! $importer->handle($this->revisionId)) {
            $this->release(30);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        CatalogueRevision::whereKey($this->revisionId)->whereIn('state', ['queued', 'importing'])
            ->update(['state' => 'failed', 'failure' => 'catalogue_worker_failed']);
    }
}
