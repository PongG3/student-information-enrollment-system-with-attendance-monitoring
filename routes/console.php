<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A backup every evening, keeping two weeks of them (needs the scheduler: see docs/BACKUP.md).
Illuminate\Support\Facades\Schedule::command('sies:backup --keep=14')->dailyAt('18:00')->withoutOverlapping();
