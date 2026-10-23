<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\View\View;

class PlatformAdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'organizationCount' => Organization::query()->count(),
            'userCount' => User::query()->count(),
            'requestCount' => AccessRequest::query()->count(),
        ]);
    }
}
