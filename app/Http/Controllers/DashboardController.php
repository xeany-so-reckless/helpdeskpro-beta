<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Satu URL /dashboard, tampilannya mengikuti role pengguna.
     */
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $user = $request->user();

        return match ($user->role) {
            Role::Admin => Inertia::render('dashboard/Admin', $stats->forAdmin($request->integer('days'))),
            Role::ItSupport => Inertia::render('dashboard/ItSupport', $stats->forItSupport($user)),
            Role::Employee => Inertia::render('dashboard/Employee', $stats->forEmployee($user)),
        };
    }
}
