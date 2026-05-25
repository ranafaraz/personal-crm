<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactImportRow;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ContactImportController extends Controller
{
    // CSV header (lowercased) → Contact field name
    private const COLUMN_MAP = [
        'first_name' => 'first_name', 'firstname' => 'first_name', 'given_name' => 'first_name',
        'last_name'  => 'last_name',  'lastname'  => 'last_name',  'surname' => 'last_name', 'family_name' => 'last_name',
        'email'      => 'email',      'email_address' => 'email',  'e-mail' => 'email',
        'phone'      => 'phone',      'phone_number' => 'phone',   'mobile' => 'phone', 'tel' => 'phone',
        'company'    => 'company',    'organization' => 'company', 'organisation' => 'company', 'employer' => 'company',
        'job_title'  => 'job_title',  'jobtitle'  => 'job_title',  'title' => 'job_title', 'position' => 'job_title', 'role' => 'job_title',
        'linkedin'      => 'linkedin_url', 'linkedin_url' => 'linkedin_url', 'linkedin_profile' => 'linkedin_url',
        'website'    => 'website',    'url' => 'website', 'web' => 'website',
        'country'    => 'country',
        'city'       => 'city',       'location' => 'city',
        'industry'   => 'industry',   'sector' => 'industry', 'vertical' => 'industry',
        'source'     => 'source',     'lead_source' => 'source', 'origin' => 'source',
        'notes'      => 'notes',      'note' => 'notes', 'comments' => 'notes', 'comment' => 'notes',

        // Linking: semicolon/comma separated list of opportunity titles to attach
        'opportunity_titles'  => 'opportunity_titles',
        'opportunities'       => 'opportunity_titles',
        'linked_opportunities' => 'opportunity_titles',
    ];

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

    /**
     * Download a sample CSV template for contact imports.
     */
    public function template(): Response
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contact-import-template.csv"',
            'Cache-Control'       => 'no-store',
        ];

        $rows = [
            ['first_name','last_name','email','company','phone','job_title','industry','linkedin_url','website','city','country','source','notes','opportunity_titles'],
            ['Jane','Doe','jane@acme.com','Acme Corp','+1 555-1234','VP Engineering','SaaS','https://linkedin.com/in/janedoe','https://acme.com','San Francisco','USA','LinkedIn','Met at conference 2025','Senior Backend Engineer @ Acme;Sr Platform Role @ Acme'],
            ['Bob','Recruiter','recruiter@acme.com','Acme Corp','+1 555-9999','Technical Recruiter','SaaS','https://linkedin.com/in/bobrecruiter','https://acme.com','Austin','USA','Referral','Owns engineering hiring pipeline','Senior Backend Engineer @ Acme'],
            ['Sarah','Lin','sarah@betalabs.co','Beta Labs','','CTO','AI','https://linkedin.com/in/sarahlin','https://betalabs.co','Berlin','Germany','Wellfound','Reached out re founding eng role','Founding Engineer @ Beta Labs'],
        ];

        $csv = implode("\n", array_map(
            fn ($r) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $r)),
            $rows
        ));

        return response($csv, 200, $headers);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');

        // Move the uploaded file to a predictable location using native PHP, bypassing
        // Storage/Flysystem (which silently fails when throw=false).
        $importDir = storage_path('app/private/imports');
        if (! is_dir($importDir)) {
            mkdir($importDir, 0775, true);
        }

        $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $fullPath = $importDir . '/' . $filename;
        $file->move($importDir, $filename);

        if (! file_exists($fullPath) || filesize($fullPath) === 0) {
            return back()->withErrors(['csv_file' => 'Uploaded file is empty or could not be saved.']);
        }

        // Create the import record up front so the user always lands on a detail page,
        // even if processing throws an unexpected error.
        $import = ContactImport::create($this->tenantData([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => 'imports/' . $filename,
            'status'    => 'processing',
            'total_rows'     => 0,
            'processed_rows' => 0,
            'imported_rows'  => 0,
            'failed_rows'    => 0,
            'skipped_rows'   => 0,
        ]));

        try {
            $this->processCsv($import, $fullPath);
        } catch (Throwable $e) {
            Log::error('ContactImport inline processing failed', [
                'import_id' => $import->id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
            $import->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage() . ' (at ' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ]);
        }

        return redirect()->route('imports.show', $import->id);
    }

    public function show(Request $request, int $id): View
    {
        $import = $this->tenantQuery(ContactImport::class)->findOrFail($id);
        $rows = $import->rows()->orderBy('row_number')->paginate(50);

        return view('imports.show', compact('import', 'rows'));
    }

    /**
     * Parse and import a CSV file inline. Each row is processed immediately
     * and persisted; any per-row error becomes a failed/skipped ContactImportRow
     * but does not abort the rest of the import.
     */
    private function processCsv(ContactImport $import, string $fullPath): void
    {
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open uploaded file: {$fullPath}");
        }

        try {
            $headerRow = fgetcsv($handle);
            if (! is_array($headerRow) || count(array_filter($headerRow, fn ($v) => trim((string) $v) !== '')) === 0) {
                throw new \RuntimeException('CSV has no header row.');
            }

            // Normalise headers and map to Contact fields
            $headerFields = [];
            foreach ($headerRow as $idx => $rawHeader) {
                $norm = strtolower(trim((string) $rawHeader));
                $norm = preg_replace('/\s+/', '_', $norm);
                $norm = trim((string) $norm, "\xEF\xBB\xBF \t"); // strip BOM if present
                $headerFields[$idx] = self::COLUMN_MAP[$norm] ?? null;
            }

            if (! in_array('email', $headerFields, true)) {
                throw new \RuntimeException('CSV must contain an "email" column.');
            }

            $rowNumber  = 1;
            $imported   = 0;
            $skipped    = 0;
            $failed     = 0;
            $total      = 0;

            while (($csvRow = fgetcsv($handle)) !== false) {
                // Skip completely blank lines
                if (count(array_filter($csvRow, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $total++;

                $mapped = [];
                foreach ($headerFields as $idx => $field) {
                    if ($field === null) {
                        continue;
                    }
                    $mapped[$field] = isset($csvRow[$idx]) ? trim((string) $csvRow[$idx]) : null;
                }

                $rowRecord = ContactImportRow::create([
                    'contact_import_id' => $import->id,
                    'row_number'        => $rowNumber++,
                    'raw_data'          => json_encode($mapped),
                    'status'            => 'pending',
                ]);

                $result = $this->importRow($import, $rowRecord, $mapped);
                match ($result) {
                    'imported' => $imported++,
                    'skipped'  => $skipped++,
                    'failed'   => $failed++,
                };
            }

            $import->update([
                'total_rows'     => $total,
                'processed_rows' => $total,
                'imported_rows'  => $imported,
                'failed_rows'    => $failed,
                'skipped_rows'   => $skipped,
                'status'         => 'completed',
                'error_message'  => null,
            ]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Attempt to import a single mapped row as a Contact. Returns 'imported',
     * 'skipped', or 'failed' and updates the row record.
     */
    private function importRow(ContactImport $import, ContactImportRow $row, array $data): string
    {
        try {
            $email = strtolower(trim($data['email'] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row->update([
                    'status'        => 'skipped',
                    'error_message' => 'Missing or invalid email address.',
                ]);
                return 'skipped';
            }

            $existing = Contact::where('user_id', $import->user_id)
                ->where('email', $email)
                ->first();

            if ($existing) {
                $row->update([
                    'status'        => 'skipped',
                    'contact_id'    => $existing->id,
                    'error_message' => 'Contact with this email already exists.',
                ]);
                return 'skipped';
            }

            $contactPayload = array_filter([
                'user_id'      => $import->user_id,
                'tenant_id'    => $import->tenant_id,
                'email'        => $email,
                'first_name'   => $data['first_name'] ?? null,
                'last_name'    => $data['last_name'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'company'      => $data['company'] ?? null,
                'job_title'    => $data['job_title'] ?? null,
                'industry'     => $data['industry'] ?? null,
                'source'       => $data['source'] ?? 'csv_import',
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'website'      => $data['website'] ?? null,
                'country'      => $data['country'] ?? null,
                'city'         => $data['city'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'status'       => 'active',
            ], fn ($v) => $v !== null && $v !== '');

            $contact = Contact::create($contactPayload);

            // Link to opportunities by exact title. Titles that don't match any
            // existing opportunity get a stub opportunity created (just title +
            // sensible defaults), so the linkage is always preserved end-to-end.
            $opportunityTitles = $this->parseList($data['opportunity_titles'] ?? '');
            if (! empty($opportunityTitles)) {
                $opportunityIds = [];
                foreach ($opportunityTitles as $title) {
                    $opp = Opportunity::firstOrCreate(
                        ['user_id' => $import->user_id, 'title' => $title],
                        [
                            'tenant_id'        => $import->tenant_id,
                            'type'             => 'job',
                            'organization'     => $contact->company ?: null,
                            'status'           => 'active',
                            'priority'         => 'medium',
                            'last_activity_at' => now(),
                            'notes'            => 'Auto-created from contact CSV import (' . $import->file_name . ').',
                        ]
                    );
                    $opportunityIds[] = $opp->id;
                }
                if ($opportunityIds) {
                    $contact->opportunities()->syncWithoutDetaching($opportunityIds);
                }
            }

            $row->update([
                'status'     => 'imported',
                'contact_id' => $contact->id,
            ]);

            return 'imported';
        } catch (Throwable $e) {
            $row->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Parse a semicolon/comma-separated list of strings into a trimmed unique array.
     *
     * @return array<int, string>
     */
    private function parseList(?string $raw): array
    {
        if (! $raw || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $items = [];
        foreach ($parts as $p) {
            $v = trim($p);
            if ($v !== '') {
                $items[$v] = true;
            }
        }
        return array_keys($items);
    }
}
