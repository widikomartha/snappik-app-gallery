<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('snappik:archives-maintain')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('snappik:payments-reconcile')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('snappik:operations-heartbeat')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('snappik:backup')->dailyAt('02:00')->timezone('UTC')->withoutOverlapping(1440)
    ->when(fn () => config('backup.enabled'))->runInBackground();
