<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Worker;
use App\Services\NotificationCenter;
use App\Support\Table;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerController
{
    public function __construct(private NotificationCenter $notifications) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $workers = Worker::with('documentType')->withCount(['contracts', 'contracts as active_contracts_count' => fn ($q) => $q->where('status', 'active')])->when($search, fn ($q) => $q->where(fn ($q) => $q->whereLike('full_name', "%{$search}%")->orWhereLike('document_number', "%{$search}%")))
            ->tap(fn ($query) => Table::sort($query, $request, ['name' => 'full_name', 'email' => 'email', 'status' => 'active_contracts_count', 'contracts' => 'contracts_count']))
            ->paginate(Table::perPage($request))->withQueryString();

        $summary = ['total' => Worker::count(), 'active' => Worker::whereHas('contracts', fn ($q) => $q->where('status', 'active'))->count(), 'without_email' => Worker::whereNull('email')->orWhere('email', '')->count()];

        return view('workers.index', compact('workers', 'search', 'summary'));
    }

    public function show(Worker $worker)
    {
        $worker->load('documentType', 'contracts.company', 'contracts.contractType');

        return view('workers.show', compact('worker'));
    }

    public function create()
    {
        return view('workers.form', ['worker' => new Worker, 'documentTypes' => DocumentType::orderBy('name')->get()]);
    }

    public function edit(Worker $worker)
    {
        return view('workers.form', ['worker' => $worker, 'documentTypes' => DocumentType::orderBy('name')->get()]);
    }

    private function data(Request $request, ?Worker $worker = null): array
    {
        return $request->validate(['full_name' => 'required|string|max:180', 'document_type_id' => 'required|exists:document_types,id', 'document_number' => ['required', 'string', 'max:40', Rule::unique('workers')->where(fn ($q) => $q->where('document_type_id', $request->input('document_type_id')))->ignore($worker?->id)], 'email' => 'nullable|email|max:190', 'phone' => 'nullable|string|max:40']);
    }

    public function store(Request $request)
    {
        $worker = Worker::create($this->data($request));
        $this->notifications->announce($request->user(), 'Nuevo trabajador', $request->user()->name.' registró a '.$worker->full_name.'.', route('workers.show', $worker, false), 'users-round');

        return redirect()->route('workers.show', $worker)->with('success', 'Trabajador registrado correctamente.');
    }

    public function update(Request $request, Worker $worker)
    {
        $worker->update($this->data($request, $worker));

        return redirect()->route('workers.show', $worker)->with('success', 'Trabajador actualizado.');
    }

    public function destroy(Worker $worker)
    {
        if ($worker->contracts()->exists()) {
            return back()->with('error', 'No es posible eliminar un trabajador que tiene contratos.');
        }$worker->delete();

        return redirect()->route('workers.index')->with('success', 'Trabajador eliminado.');
    }
}
