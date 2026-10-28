<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function __invoke(Request $request): ViewContract
    {
        $organization = $request->attributes->get('tenant');
        $organization->loadCount(['memberships', 'sites']);

        return view('client.dashboard', compact('organization'));
    }
}
