<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:saas', function (): void {
    $this->line('Acceso SaaS: base de solicitudes y aprobaciones multiempresa.');
})->purpose('Muestra información breve del proyecto');
