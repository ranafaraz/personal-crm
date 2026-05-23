<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImportJob;
use App\Models\ContactImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = $this->tenantQuery(ContactImport::class)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('imports.index', compact('imports'));
    }

    public function create(): View
    {
        return view('imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10 MB
        ]);

        $file = $request->file('csv_file');
        $path = $file->store('imports', 'local');

        $import = ContactImport::create($this->tenantData([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status'    => 'pending',
        ]));

        ProcessContactImportJob::dispatch($import);

        return redirect()->route('imports.show', $import->id)
            ->with('success', 'Import queued. Rows will be processed in the background.');
    }

    public function show(Request $request, int $id): View
    {
        $import = $this->tenantQuery(ContactImport::class)->findOrFail($id);
        $rows = $import->rows()->orderBy('row_number')->paginate(50);

        return view('imports.show', compact('import', 'rows'));
    }
}
