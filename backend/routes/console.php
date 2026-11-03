<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notifikasi kendala yang tenggatnya sudah lewat (setiap hari 07:00 WIB).
Schedule::command('notify:due-issues')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta');
