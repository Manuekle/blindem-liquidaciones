<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\LaborParameter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaborParameterController
{
    public function index()
    {
        return view('labor-parameters.index', ['parameters' => LaborParameter::orderByDesc('year')->get()]);
    }

    private function data(Request $request, ?LaborParameter $parameter = null): array
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:1900,2100', Rule::unique('labor_parameters')->ignore($parameter?->id)],
            'min_wage' => 'required|integer|min:1|max:99999999999',
            'transport_allowance' => 'required|integer|min:0|max:99999999999',
            'health_percent' => 'required|numeric|between:0,100',
            'pension_percent' => 'required|numeric|between:0,100',
            'interest_percent' => 'required|numeric|between:0,100',
            'wage_source' => 'required|url:http,https|max:500',
            'transport_source' => 'required|url:http,https|max:500',
        ]);
        foreach (['health', 'pension', 'interest'] as $rate) {
            $data[$rate.'_rate'] = $data[$rate.'_percent'] / 100;
            unset($data[$rate.'_percent']);
        }

        return $data;
    }

    public function store(Request $request)
    {
        LaborParameter::create($this->data($request));

        return back()->with('success', 'Vigencia laboral registrada.');
    }

    public function update(Request $request, LaborParameter $laborParameter)
    {
        $data = $this->data($request, $laborParameter);
        if ((int) $data['year'] !== $laborParameter->year && $this->inUse($laborParameter)) {
            return back()->with('error', 'La vigencia tiene contratos asociados: puedes corregir sus valores, pero no cambiar su año.');
        }
        $laborParameter->update($data);

        return back()->with('success', 'Vigencia actualizada. Las liquidaciones históricas conservan sus parámetros originales.');
    }

    private function inUse(LaborParameter $parameter): bool
    {
        return Contract::whereYear('end_date', $parameter->year)
            ->when($parameter->year === now()->year, fn ($query) => $query->orWhereNull('end_date'))->exists();
    }

    public function destroy(LaborParameter $laborParameter)
    {
        if ($this->inUse($laborParameter)) {
            return back()->with('error', 'No se puede eliminar una vigencia utilizada por contratos.');
        }
        $laborParameter->delete();

        return back()->with('success', 'Vigencia eliminada.');
    }
}
