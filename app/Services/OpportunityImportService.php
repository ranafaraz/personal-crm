<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\OpportunityImport;
use App\Models\OpportunityImportRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Throwable;

class OpportunityImportService
{
    private const COLUMN_MAP = [
        'title'           => 'title',
        'opportunity'     => 'title',
        'name'            => 'title',
        'opportunity_name'=> 'title',

        'type'            => 'type',
        'category'        => 'type',
        'kind'            => 'type',

        'organization'    => 'organization',
        'organisation'    => 'organization',
        'company'         => 'organization',
        'employer'        => 'organization',
        'institution'     => 'organization',

        'description'     => 'description',
        'details'         => 'description',
        'summary'         => 'description',

        'url'             => 'url',
        'link'            => 'url',
        'website'         => 'url',
        'job_url'         => 'url',

        'status'          => 'status',
        'stage'           => 'status',
        'state'           => 'status',

        'priority'        => 'priority',
        'urgency'         => 'priority',

        'deadline'        => 'deadline',
        'due_date'        => 'deadline',
        'closing_date'    => 'deadline',
        'application_deadline' => 'deadline',
        'due date'        => 'deadline',
        'closing date'    => 'deadline',

        'notes'           => 'notes',
        'note'            => 'notes',
        'comments'        => 'notes',
        'comment'         => 'notes',

        // Linking: semicolon or comma separated list of contact emails
        // (header normalisation collapses underscores to spaces — store
        // both forms so direct hits + space-collapsed hits both work)
        'contact_emails'   => 'contact_emails',
        'contact emails'   => 'contact_emails',
        'contact_email'    => 'contact_emails',
        'contact email'    => 'contact_emails',
        'contacts'         => 'contact_emails',
        'linked_contacts'  => 'contact_emails',
        'linked contacts'  => 'contact_emails',

        // Auto-create draft + scheduled follow-up emails per linked contact
        'draft_email'      => 'draft_email',
        'draft email'      => 'draft_email',
        'initial_email'    => 'draft_email',
        'initial email'    => 'draft_email',

        'followup_email'   => 'followup_email',
        'followup email'   => 'followup_email',
        'follow_up_email'  => 'followup_email',
        'follow up email'  => 'followup_email',
    ];

    private const VALID_TYPES    = ['job', 'scholarship', 'research', 'grant', 'networking'];
    private const VALID_STATUSES = ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'];
    private const VALID_PRIORITIES = ['urgent', 'high', 'medium', 'low'];

    public function parseAndStore(OpportunityImport $import): void
    {
        $import->update(['status' => 'parsing']);

        try {
            $csv = Reader::createFromPath(Storage::disk('local')->path($import->file_path), 'r');
            $csv->setHeaderOffset(0);

            $headers   = $csv->getHeader();
            $columnMap = $this->getColumnMapping($headers);
            $rowNumber = 1;
            $batch     = [];

            foreach ($csv->getRecords() as $record) {
                $mapped = [];
                foreach ($columnMap as $csvHeader => $field) {
                    $mapped[$field] = isset($record[$csvHeader]) ? trim($record[$csvHeader]) : null;
                }

                $batch[] = [
                    'opportunity_import_id' => $import->id,
                    'row_number'            => $rowNumber,
                    'raw_data'              => json_encode($mapped),
                    'status'                => 'pending',
                    'error_message'         => null,
                    'opportunity_id'        => null,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];

                $rowNumber++;

                if (count($batch) >= 500) {
                    OpportunityImportRow::insert($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                OpportunityImportRow::insert($batch);
            }

            $totalRows = $rowNumber - 1;

            $import->update([
                'total_rows'     => $totalRows,
                'processed_rows' => 0,
                'imported_rows'  => 0,
                'failed_rows'    => 0,
                'skipped_rows'   => 0,
                'status'         => 'parsed',
            ]);

        } catch (Throwable $e) {
            Log::error('OpportunityImportService: parseAndStore failed', [
                'opportunity_import_id' => $import->id,
                'error'                 => $e->getMessage(),
            ]);

            $import->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function processImport(OpportunityImport $import): void
    {
        $import->update(['status' => 'processing']);

        $importedRows  = 0;
        $failedRows    = 0;
        $skippedRows   = 0;
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
                    $result = $this->processRow($row, $import);

                    $processedRows++;

                    match ($result) {
                        'imported' => $importedRows++,
                        'skipped'  => $skippedRows++,
                        'failed'   => $failedRows++,
                        default    => null,
                    };
                }

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

    public function getColumnMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalised = strtolower(trim($header));
            $normalised = preg_replace('/[\s_]+/', ' ', $normalised);
            $normalised = trim($normalised ?? '');

            if (isset(self::COLUMN_MAP[$normalised])) {
                $mapping[$header] = self::COLUMN_MAP[$normalised];
            }
        }

        return $mapping;
    }

    private function processRow(OpportunityImportRow $row, OpportunityImport $import): string
    {
        try {
            $data  = is_array($row->raw_data) ? $row->raw_data : json_decode($row->raw_data, true);
            $title = trim($data['title'] ?? '');

            if ($title === '') {
                $row->update([
                    'status'        => 'skipped',
                    'error_message' => 'Missing required field: title.',
                ]);
                return 'skipped';
            }

            // Skip if an opportunity with the same title already exists for this user
            $existing = Opportunity::where('user_id', $import->user_id)
                ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                ->first();

            if ($existing) {
                $row->update([
                    'status'         => 'skipped',
                    'opportunity_id' => $existing->id,
                    'error_message'  => 'Opportunity with this title already exists.',
                ]);
                return 'skipped';
            }

            $type     = $this->resolveEnum($data['type'] ?? null, self::VALID_TYPES, 'job');
            $status   = $this->resolveEnum($data['status'] ?? null, self::VALID_STATUSES, 'active');
            $priority = $this->resolveEnum($data['priority'] ?? null, self::VALID_PRIORITIES, 'medium');
            $deadline = $this->resolveDate($data['deadline'] ?? null);

            $opportunity = Opportunity::create([
                'user_id'      => $import->user_id,
                'tenant_id'    => $import->tenant_id,
                'title'        => $title,
                'type'         => $type,
                'organization' => $data['organization'] ?? null ?: null,
                'description'  => $data['description'] ?? null ?: null,
                'url'          => $data['url'] ?? null ?: null,
                'status'       => $status,
                'priority'     => $priority,
                'deadline'     => $deadline,
                'notes'        => $data['notes'] ?? null ?: null,
            ]);

            // Link contacts by email (semicolon or comma separated). Emails that
            // don't match an existing contact get a stub contact created (just
            // email + name derived from local part), so the linkage is always
            // preserved end-to-end without manual follow-up. Email is treated
            // as the unique key per user.
            $contactEmails = $this->parseEmails($data['contact_emails'] ?? '');
            $contacts      = [];
            if (! empty($contactEmails)) {
                foreach ($contactEmails as $email) {
                    $contact = Contact::firstOrCreate(
                        ['user_id' => $import->user_id, 'email' => $email],
                        [
                            'tenant_id'  => $import->tenant_id,
                            'first_name' => ucfirst(strstr($email, '@', true) ?: $email),
                            'last_name'  => '',
                            'company'    => $data['organization'] ?? null ?: null,
                            'source'     => 'opportunity_import',
                            'status'     => 'active',
                            'notes'      => 'Auto-created from opportunity CSV import (' . $import->file_name . ').',
                        ]
                    );
                    $contacts[] = $contact;
                }
                if ($contacts) {
                    $opportunity->contacts()->syncWithoutDetaching(array_map(fn ($c) => $c->id, $contacts));
                }
            }

            // Auto-create draft + scheduled follow-up EmailMessages for each
            // linked contact when draft_email / followup_email columns are
            // populated. Uses the user's default account (or first available).
            $this->createImportedEmails($import, $opportunity, $contacts, $data);

            $row->update([
                'status'         => 'imported',
                'opportunity_id' => $opportunity->id,
            ]);

            return 'imported';

        } catch (Throwable $e) {
            Log::warning('OpportunityImportService: row processing failed', [
                'opportunity_import_row_id' => $row->id,
                'error'                     => $e->getMessage(),
            ]);

            $row->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    private function resolveEnum(?string $value, array $valid, string $default): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $normalised = strtolower(trim($value));
        return in_array($normalised, $valid, true) ? $normalised : $default;
    }

    /**
     * Parse a delimited list of email addresses into a normalised array.
     * Accepts comma or semicolon separators; trims whitespace and lowercases.
     *
     * @return array<int, string>
     */
    private function parseEmails(?string $raw): array
    {
        if (! $raw || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[;,\s]+/', $raw) ?: [];
        $emails = [];
        foreach ($parts as $p) {
            $e = strtolower(trim($p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[$e] = true;
            }
        }
        return array_keys($emails);
    }

    /**
     * For each linked contact, optionally create a draft EmailMessage from the
     * draft_email column and a scheduled (status=scheduled, ~5 days out)
     * EmailMessage from the followup_email column. No-op when the columns are
     * blank, when no contacts are linked, or when the user has no email account.
     *
     * @param  array<int, Contact>  $contacts
     */
    private function createImportedEmails(OpportunityImport $import, Opportunity $opportunity, array $contacts, array $data): void
    {
        $draftBody    = trim((string) ($data['draft_email'] ?? ''));
        $followupBody = trim((string) ($data['followup_email'] ?? ''));

        if ($draftBody === '' && $followupBody === '') return;
        if (empty($contacts)) return;

        $account = EmailAccount::where('user_id', $import->user_id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_default', true)->orWhereNotNull('id');
            })
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $account) return;

        foreach ($contacts as $contact) {
            $toName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: $contact->email;

            if ($draftBody !== '') {
                EmailMessage::create([
                    'tenant_id'        => $import->tenant_id,
                    'user_id'          => $import->user_id,
                    'email_account_id' => $account->id,
                    'contact_id'       => $contact->id,
                    'opportunity_id'   => $opportunity->id,
                    'to_email'         => $contact->email,
                    'to_name'          => $toName,
                    'subject'          => 'Outreach: ' . $opportunity->title,
                    'body'             => $this->bodyToHtml($draftBody),
                    'direction'        => 'outbound',
                    'status'           => 'draft',
                ]);
            }

            if ($followupBody !== '') {
                EmailMessage::create([
                    'tenant_id'        => $import->tenant_id,
                    'user_id'          => $import->user_id,
                    'email_account_id' => $account->id,
                    'contact_id'       => $contact->id,
                    'opportunity_id'   => $opportunity->id,
                    'to_email'         => $contact->email,
                    'to_name'          => $toName,
                    'subject'          => 'Following up: ' . $opportunity->title,
                    'body'             => $this->bodyToHtml($followupBody),
                    'direction'        => 'outbound',
                    'status'           => 'scheduled',
                    'scheduled_at'     => now()->addDays(5),
                    'is_follow_up'     => true,
                    'follow_up_number' => 1,
                ]);
            }
        }
    }

    /**
     * Convert plain-text body (CSV cell) into the HTML the rest of the app expects.
     * Escapes HTML special chars then converts newlines to <br>.
     */
    private function bodyToHtml(string $raw): string
    {
        return nl2br(e($raw), false);
    }

    private function resolveDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse(trim($value))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
