<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revisa periódicamente qué partidos entraron en la ventana de 24 horas.
Schedule::command('partidos:recordar')
    ->everyTenMinutes()
    ->withoutOverlapping();
