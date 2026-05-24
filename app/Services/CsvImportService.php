<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactImportRow;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Throwable;

class CsvImportService
{
    /**
     * Canonical mapping: lowercase/normalised CSV header → Contact field name.
     * Longer, more-specific patterns come first so the match is greedy.
     */
    private const COLUMN_MAP = [
        'first_name'  => 'first_name',
        'firstname'   => 'first_name',
        'first name'  => 'first_name',
        'given_name'  => 'first_name',
        'givenname'   => 'first_name',

        'last_name'   => 'last_name',
        'lastname'    => 'last_name',
        'last name'   => 'last_name',
        'surname'     => 'last_name',
        'family_name' => 'last_name',

        'email'         => 'email',
        'email_address' => 'email',
        'emailaddress'  => 'email',
        'e-mail'        => 'email',

        'phone'        => 'phone',
        'phone_number' => 'phone',
        'phonenumber'  => 'phone',
        'mobile'       => 'phone',
        'tel'          => 'phone',

        'company'       => 'company',
        'organization'  => 'company',
        'organisation'  => 'company',
        'employer'      => 'company',

        'job_title'  => 'job_title',
        'jobtitle'   => 'job_title',
        'job title'  => 'job_title',
        'title'      => 'job_title',
        'position'   => 'job_title',
        'role'       => 'job_title',

        'linkedin'      => 'linkedin_url',
        'linkedin_url'  => 'linkedin_url',
        'linkedinurl'   => 'linkedin_url',
        'linkedin_profile' => 'linkedin_url',

        'website'    => 'website',
        'url'        => 'website',
        'web'        => 'website',

        'country'    => 'country',
        'city'       => 'city',
        'location'   => 'city',

        'industry'   => 'industry',
        'sector'     => 'industry',
        'vertical'   => 'industry',

        'source'     => 'source',
        'lead source'=> 'source',
        'lead_source'=> 'source',
        'origin'     => 'source',

        'notes'      => 'notes',
        'note'       => 'notes',
        'comments'   => 'notes',
        'comment'    => 'notes',
    ];

    /**
     * Read the CSV file associated with the import, detect columns, and create
     * ContactImportRow records for every data row.
     */
    public function parseAndStore(ContactImport $import): void
    {
        try {
            $csv = Reader::createFromPath(storage_path('app/' . $import->file_path), 'r');
            $csv->setHeaderOffset(0);

            $headers    = $csv->getHeader();
            $columnMap  = $this->getColumnMapping($headers);
            $rowNumber  = 1;
            $batch      = [];

            foreach ($csv->getRecords() as $record) {
                $mapped = [];
                foreach ($columnMap as $csvHeader => $contactField) {
                    $mapped[$contactField] = isset($record[$csvHeader])
                        ? trim($record[$csvHeader])
                        : null;
                }

                $batch[] = [
                    'contact_import_id' => $import->id,
                    'row_number'        => $rowNumber,
                    'raw_data'          => json_encode($mapped),
                    'status'            => 'pending',
                    'error_message'     => null,
                    'contact_id'        => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];

                $rowNumber++;

                // Insert in batches of 500 to avoid memory issues
                if (count($batch) >= 500) {
                    ContactImportRow::insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                ContactImportRow::insert($batch);
            }

            $totalRows = $rowNumber - 1;

            $import->update([
                'total_rows'     => $totalRows,
                'processed_rows' => 0,
                'imported_rows'  => 0,
                'failed_rows'    => 0,
                'skipped_rows'   => 0,
            ]);

        } catch (Throwable $e) {
            Log::error('CsvImportService: parseAndStore failed', [
                'contact_import_id' => $import->id,
                'error'             => $e->getMessage(),
            ]);

            $import->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process all pending ContactImportRow records for an import, creating or
     * updating Contact records as appropriate.
     */
    public function processImport(ContactImport $import): void
    {
        $import->update(['status' => 'processing']);

        $importedRows = 0;
        $failedRows   = 0;
        $skippedRows  = 0;
        $processedRows = 0;

        $import->rows()
            ->where('status', 'pending')
            ->orderBy('row_number')
            ->chunk(100, function ($rows) use (
                $import,
                &$importedRows,
                &$failedRows,
                &$skippedRows,
                &$processedRows,
            ) {
                foreach ($rows as $row) {
                    $result = $this->processRow($row, $import->user_id);

                    $processedRows++;

                    match ($result) {
                        'imported' => $importedRows++,
                        'skipped'  => $skippedRows++,
                        'failed'   => $failedRows++,
                        default    => null,
                    };
                }

                // Persist running counts periodically
                $import->update([
                    'processed_rows' => $processedRows,
                    'imported_rows'  => $importedRows,
                    'failed_rows'    => $failedRows,
                    'skipped_rows'   => $skippedRows,
                ]);
            });

        $import->update([
            'status'         => 'completed',
            'processed_rows' => $processedRows,
            'imported_rows'  => $importedRows,
            'failed_rows'    => $failedRows,
            'skipped_rows'   => $skippedRows,
        ]);
    }

    /**
     * Map CSV column headers to Contact field names.
     *
     * @param  string[] $headers  Raw header names from the CSV.
     * @return array<string, string>  CSV header → Contact field name (only matched columns).
     */
    public function getColumnMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalised = strtolower(trim($header));
            // Replace multiple spaces/underscores with a single space for lookup
            $normalised = preg_replace('/[\s_]+/', ' ', $normalised);
            $normalised = trim($normalised ?? '');

            if (isset(self::COLUMN_MAP[$normalised])) {
                $mapping[$header] = self::COLUMN_MAP[$normalised];
            }
        }

        return $mapping;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Process a single import row. Returns 'imported', 'skipped', or 'failed'.
     */
    private function processRow(ContactImportRow $row, int $userId): string
    {
        try {
            $data = is_array($row->raw_data) ? $row->raw_data : json_decode($row->raw_data, true);

            // Email is required
            $email = trim($data['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row->update([
                    'status'        => 'skipped',
                    'error_message' => 'Missing or invalid email address.',
                ]);
                return 'skipped';
            }

            $email = strtolower($email);

            // Skip if a contact with this email already exists for this user
            $existing = Contact::where('user_id', $userId)
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

            $contactData = array_filter([
                'user_id'      => $userId,
                'email'        => $email,
                'first_name'   => $data['first_name'] ?? null,
                'last_name'    => $data['last_name'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'company'      => $data['company'] ?? null,
                'job_title'    => $data['job_title'] ?? null,
                'industry'     => $data['industry'] ?? null,
                'source'       => $data['source'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'website'      => $data['website'] ?? null,
                'country'      => $data['country'] ?? null,
                'city'         => $data['city'] ?? null,
                'notes'        => $data['notes'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $contact = Contact::create(array_merge($contactData, [
                'status' => 'active',
                'source' => $contactData['source'] ?? 'csv_import',
            ]));

            $row->update([
                'status'     => 'imported',
                'contact_id' => $contact->id,
            ]);

            return 'imported';

        } catch (Throwable $e) {
            Log::warning('CsvImportService: row processing failed', [
                'contact_import_row_id' => $row->id,
                'error'                 => $e->getMessage(),
            ]);

            $row->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
