<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;

final class SafeLogManager extends LogManager
{
    protected function tap($name, Logger $logger)
    {
        $logger = parent::tap($name, $logger);
        (new SafeLog)($logger);

        return $logger;
    }

    protected function createEmergencyLogger()
    {
        $logger = parent::createEmergencyLogger();
        (new SafeLog)($logger);

        return $logger;
    }
}
