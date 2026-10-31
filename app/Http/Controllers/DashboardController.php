<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Satu URL /dashboard, tampilannya mengikuti role pengguna.
     */
    public function __invoke(Request $request): Response
    {
        $component = match ($request->user()->role) {
            Role::Admin => 'dashboard/Admin',
            Role::ItSupport => 'dashboard/ItSupport',
            Role::Employee => 'dashboard/Employee',
        };

        return Inertia::render($component);
    }
}
