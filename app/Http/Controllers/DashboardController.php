<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'type',
            'email_account_id',
            'status',
            'priority',
        ]);

        $stats = $this->dashboardService->getStats(
            $request->user(),
            $filters
        );

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('dashboard.index', compact('stats', 'filters', 'emailAccounts'));
    }
}
