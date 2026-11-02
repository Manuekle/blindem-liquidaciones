<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Settlement;
use App\Models\Worker;

class DashboardController
{
    public function index()
    {
        $recent = Contract::with(['worker.documentType', 'company', 'contractType'])->latest()->limit(7)->get();
        $months = collect(range(5, 0))->map(function ($i) {
            $date = now()->startOfMonth()->subMonths($i);

            return ['label' => $date->translatedFormat('M'), 'total' => Contract::whereYear('created_at', $date->year)->whereMonth('created_at', $date->month)->count()];
        });

        // Work queue: retirements still waiting for a settlement, and fixed-term contracts ending within 30 days.
        $pending = Contract::with('worker')->where('status', 'terminated')->doesntHave('settlements');
        $expiring = Contract::with('worker')->where('status', 'active')->whereBetween('agreed_end_date', [today(), today()->addDays(30)]);

        return view('dashboard.index', [
            'workers' => Worker::count(),
            'companies' => Company::count(),
            'contracts' => Contract::count(),
            'active' => Contract::where('status', 'active')->count(),
            'terminated' => Contract::where('status', 'terminated')->count(),
            'netSettlements' => Settlement::sum('net_amount'),
            'settlementCount' => Settlement::count(),
            'monthSettlements' => Settlement::where('created_at', '>=', now()->startOfMonth())->sum('net_amount'),
            'pendingCount' => (clone $pending)->count(),
            'pendingList' => $pending->latest('end_date')->limit(4)->get(),
            'expiringCount' => (clone $expiring)->count(),
            'expiringList' => $expiring->orderBy('agreed_end_date')->limit(4)->get(),
            'recent' => $recent,
            'months' => $months,
        ]);
    }
}
