<?php

namespace App\Http\Controllers;

use App\Services\ReportBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Laporan hanya untuk Administrator (PRD FR-10). */
    public function index(Request $request, ReportBuilder $reports): Response
    {
        abort_unless($request->user()->isAdmin(), 403, 'Anda tidak memiliki akses ke halaman ini.');

        ['from' => $from, 'to' => $to] = $reports->range($request);

        return Inertia::render('reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ...$reports->build($from, $to),
        ]);
    }
}
