<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:saas', function (): void {
    $this->line('AccesoSitios: solicitudes y aprobaciones de acceso a sitios físicos.');
})->purpose('Muestra información breve del proyecto');
