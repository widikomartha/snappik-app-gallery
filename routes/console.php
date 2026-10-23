<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('snappik:archives-maintain')->everyMinute()->withoutOverlapping(5);
Schedule::command('snappik:payments-reconcile')->everyMinute()->withoutOverlapping(5);
