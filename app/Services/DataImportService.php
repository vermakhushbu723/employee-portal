<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\User;
use App\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Turns an uploaded calling list into an (unassigned) batch of leads.
 * Rows with a bad mobile number are counted as invalid; numbers that
 * already exist (in the portal or earlier in the same file) are skipped.
 */
class DataImportService
{
    public const MAX_ROWS = 5000;

    /** Header names we understand → lead field. */
    private const HEADERS = [
        'name' => ['name', 'customer name', 'full name', 'customer', 'client name', 'client', 'lead name'],
        'mobile' => ['mobile', 'mobile number', 'mobile no', 'mobile no.', 'phone', 'phone number', 'phone no', 'number', 'contact', 'contact number', 'contact no', 'whatsapp'],
        'alternate_mobile' => ['alternate', 'alternate number', 'alternate mobile', 'alt mobile', 'alt number', 'phone 2', 'mobile 2', 'second number'],
        'email' => ['email', 'email id', 'e-mail', 'mail'],
        'city' => ['city', 'location', 'town'],
        'state' => ['state'],
        'source' => ['source', 'lead source'],
        'interest' => ['interest', 'interested in', 'product', 'service', 'requirement'],
        'remarks' => ['remarks', 'remark', 'notes', 'note', 'comment', 'comments'],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function import(User $admin, UploadedFile $file, string $batchName, string $defaultSource, string $defaultInterest): LeadBatch
    {
        try {
            $rows = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension(), self::MAX_ROWS);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $rows = array_values(array_filter($rows, fn ($r) => implode('', $r) !== ''));
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        [$map, $hasHeader] = $this->columnMap($rows[0]);
        if ($hasHeader) {
            array_shift($rows);
        }
        if (! isset($map['mobile'])) {
            throw ValidationException::withMessages(['file' => 'Could not find a Mobile / Phone column. Use the sample template.']);
        }
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maximum '.self::MAX_ROWS.' rows per upload. Split the file and upload again.']);
        }

        // Normalise and drop bad / duplicate numbers
        $invalid = 0;
        $records = [];
        foreach ($rows as $row) {
            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
            $mobile = self::normaliseMobile($get('mobile'));

            if (! $mobile) {
                $invalid++;
                continue;
            }

            $records[$mobile] ??= [
                'name' => mb_substr($get('name') ?: 'Customer', 0, 100),
                'mobile' => $mobile,
                'alternate_mobile' => self::normaliseMobile($get('alternate_mobile')),
                'email' => filter_var($get('email'), FILTER_VALIDATE_EMAIL) ? mb_substr($get('email'), 0, 150) : null,
                'city' => mb_substr($get('city'), 0, 60) ?: null,
                'state' => mb_substr($get('state'), 0, 60) ?: null,
                'source' => mb_substr($get('source') ?: $defaultSource, 0, 50),
                'interest' => mb_substr($get('interest') ?: $defaultInterest, 0, 100),
                'remarks' => mb_substr($get('remarks'), 0, 1000) ?: null,
            ];
        }

        $validCount = count($rows) - $invalid;
        $existing = collect(array_keys($records))->chunk(500)
            ->flatMap(fn ($chunk) => Lead::whereIn('mobile', $chunk->all())->pluck('mobile'))
            ->flip();
        $fresh = array_diff_key($records, $existing->all());
        $duplicates = $validCount - count($fresh);

        $batch = DB::transaction(function () use ($admin, $file, $batchName, $rows, $fresh, $duplicates, $invalid) {
            $batch = LeadBatch::create([
                'name' => $batchName,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'uploaded_by' => $admin->id,
                'total_rows' => count($rows),
                'imported' => count($fresh),
                'duplicates' => $duplicates,
                'invalid' => $invalid,
            ]);

            $now = now();
            foreach (array_chunk(array_values($fresh), 500) as $chunk) {
                Lead::insert(array_map(fn ($r) => $r + [
                    'batch_id' => $batch->id,
                    'created_by' => $admin->id,
                    'status' => LeadStatus::New->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return $batch;
        });

        $this->audit->log('data.upload', "Uploaded \"{$batch->name}\": {$batch->imported} imported, {$batch->duplicates} duplicate, {$batch->invalid} invalid", ['batch_id' => $batch->id], $admin);

        return $batch;
    }

    /** Indian mobile → 10 digits (strips +91 / 0 / spaces), or null if invalid. */
    public static function normaliseMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    /** @return array{0: array<string,int>, 1: bool} field => column index, and whether row 0 is a header */
    private function columnMap(array $firstRow): array
    {
        $map = [];
        foreach ($firstRow as $i => $cell) {
            $label = strtolower(trim(preg_replace('/[_\s]+/', ' ', (string) $cell)));
            foreach (self::HEADERS as $field => $names) {
                if (! isset($map[$field]) && in_array($label, $names, true)) {
                    $map[$field] = $i;
                }
            }
        }

        if ($map) {
            return [$map, true];
        }

        // No header row: assume Name, Mobile, City
        return [['name' => 0, 'mobile' => 1, 'city' => 2], false];
    }
}
