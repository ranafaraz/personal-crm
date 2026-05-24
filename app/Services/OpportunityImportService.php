<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityImport;
use App\Models\OpportunityImportRow;
use Illuminate\Support\Facades\Log;
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
    ];

    private const VALID_TYPES    = ['job', 'scholarship', 'research', 'grant', 'networking'];
    private const VALID_STATUSES = ['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'];
    private const VALID_PRIORITIES = ['urgent', 'high', 'medium', 'low'];

    public function parseAndStore(OpportunityImport $import): void
    {
        $import->update(['status' => 'parsing']);

        try {
            $csv = Reader::createFromPath(storage_path('app/' . $import->file_path), 'r');
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
