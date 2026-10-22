<?php

namespace App\Contracts;

use App\Services\Drive\DriveItem;
use App\Services\Drive\PreviewImage;

interface DrivePreviewSource
{
    public function thumbnail(DriveItem $expected): PreviewImage;
}
