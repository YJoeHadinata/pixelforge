<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\AdminUsageReportQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminReportController extends Controller
{
    public function index(Request $request, AdminUsageReportQuery $reportQuery): Response
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return Inertia::render('Admin/Reports', [
            'report' => $reportQuery->build(
                startDate: $validated['start_date'] ?? null,
                endDate: $validated['end_date'] ?? null,
            ),
        ]);
    }
}
