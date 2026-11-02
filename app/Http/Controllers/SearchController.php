<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Records for the command palette (⌘K): a few matches per kind, each with the page it opens. */
class SearchController
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = '%'.trim($request->validate(['q' => 'required|string|min:2|max:80'])['q']).'%';

        $contracts = Contract::with(['worker', 'company'])
            ->where(fn ($query) => $query
                ->whereHas('worker', fn ($worker) => $worker->whereLike('full_name', $term)->orWhereLike('document_number', $term))
                ->orWhereHas('company', fn ($company) => $company->whereLike('name', $term)))
            ->latest()->limit(self::LIMIT)->get()
            ->map(fn (Contract $contract) => [
                'kind' => 'contract',
                'title' => $contract->worker->full_name,
                'detail' => $contract->company->name.' · '.$contract->display_status,
                'href' => route('contracts.show', $contract),
            ]);

        $workers = Worker::whereLike('full_name', $term)->orWhereLike('document_number', $term)
            ->orderBy('full_name')->limit(self::LIMIT)->get()
            ->map(fn (Worker $worker) => [
                'kind' => 'worker',
                'title' => $worker->full_name,
                'detail' => 'Documento '.$worker->document_number,
                'href' => route('workers.show', $worker),
            ]);

        // Companies only open their edit form, which is the administrator's.
        $companies = $request->user()->isAdmin()
            ? Company::whereLike('name', $term)->orWhereLike('nit', $term)
                ->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Company $company) => [
                    'kind' => 'company',
                    'title' => $company->name,
                    'detail' => 'NIT '.$company->nit,
                    'href' => route('companies.edit', $company),
                ])
            : collect();

        return response()->json(['data' => [...$contracts, ...$workers, ...$companies]]);
    }
}
