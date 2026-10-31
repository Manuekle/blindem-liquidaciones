<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $companies = Company::withCount('contracts')->when($search, fn ($q) => $q->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('nit', "%{$search}%")))->latest()->paginate(12)->withQueryString();

        return view('companies.index', compact('companies', 'search'));
    }

    public function create()
    {
        return view('companies.form', ['company' => new Company]);
    }

    public function edit(Company $company)
    {
        return view('companies.form', compact('company'));
    }

    private function data(Request $request, ?Company $company = null): array
    {
        return $request->validate(['name' => 'required|string|max:190', 'nit' => ['required', 'string', 'max:40', Rule::unique('companies', 'nit')->ignore($company?->id)], 'email' => 'nullable|email|max:190', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:255']);
    }

    public function store(Request $request)
    {
        Company::create($this->data($request));

        return redirect()->route('companies.index')->with('success', 'Empresa registrada.');
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->data($request, $company));

        return redirect()->route('companies.index')->with('success', 'Empresa actualizada.');
    }

    public function destroy(Company $company)
    {
        if ($company->contracts()->exists()) {
            return back()->with('error', 'La empresa tiene contratos asociados y no puede eliminarse.');
        }$company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada.');
    }
}
