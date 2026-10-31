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

        return view('dashboard.index', ['workers' => Worker::count(), 'companies' => Company::count(), 'contracts' => Contract::count(), 'active' => Contract::where('status', 'active')->count(), 'terminated' => Contract::where('status', 'terminated')->count(), 'netSettlements' => Settlement::sum('net_amount'), 'recent' => $recent, 'months' => $months]);
    }
}
