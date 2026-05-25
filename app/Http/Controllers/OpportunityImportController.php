<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOpportunityImportJob;
use App\Models\OpportunityImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OpportunityImportController extends Controller
{
    public function index(): View
    {
        $imports = $this->tenantQuery(OpportunityImport::class)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('opportunity-imports.index', compact('imports'));
    }

    public function create(): View
    {
        return view('opportunity-imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');

        $importDir = storage_path('app/private/opportunity-imports');
        if (! is_dir($importDir)) {
            mkdir($importDir, 0775, true);
        }

        $path = $file->store('opportunity-imports', 'local');

        if (! $path) {
            return back()->withErrors(['csv_file' => 'Failed to save the uploaded file. Please try again.']);
        }

        $fullPath = storage_path('app/private/' . $path);
        if (! file_exists($fullPath) || filesize($fullPath) === 0) {
            return back()->withErrors(['csv_file' => 'Uploaded file appears to be empty or could not be saved. Please try again.']);
        }

        $import = OpportunityImport::create($this->tenantData([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status'    => 'pending',
        ]));

        ProcessOpportunityImportJob::dispatch($import);

        return redirect()->route('opportunity-imports.show', $import->id)
            ->with('success', 'Import queued. Opportunities will be created in the background.');
    }

    public function show(int $id): View
    {
        $import = $this->tenantQuery(OpportunityImport::class)->findOrFail($id);
        $rows   = $import->rows()->orderBy('row_number')->paginate(50);

        return view('opportunity-imports.show', compact('import', 'rows'));
    }

    public function template(): Response
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="opportunity-import-template.csv"',
            'Cache-Control'       => 'no-store',
        ];

        $rows = [
            ['title', 'type', 'organization', 'description', 'url', 'status', 'priority', 'deadline', 'notes', 'contact_emails'],
            ['Senior Backend Engineer @ Acme', 'job', 'Acme Corp', 'Backend role, Go + Postgres, fully remote', 'https://acme.com/jobs/be-eng', 'active', 'high', '2026-06-30', 'Referral from John', 'jane@acme.com;recruiter@acme.com'],
            ['Founding Engineer @ Beta Labs',  'job', 'Beta Labs', 'Series A, equity-heavy', 'https://betalabs.co/careers',         'waiting_reply', 'medium', '2026-07-15', 'Applied via Wellfound', 'cto@betalabs.co'],
            ['Hertie Scholarship Application', 'scholarship', 'Hertie School', '2-yr master with full stipend', 'https://hertie-school.org/apply', 'active', 'urgent', '2026-08-01', '', 'admissions@hertie-school.org'],
        ];

        $csv = implode("\n", array_map(fn ($r) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $r)), $rows));

        return response($csv, 200, $headers);
    }
}
