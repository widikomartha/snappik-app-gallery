<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('snappik:archives-maintain')->everyMinute()->withoutOverlapping(5);
