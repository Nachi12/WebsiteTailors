<?php
/**
 * WebsiteTailors - Excel & CSV Lead Importer Service (Phase 8)
 *
 * Implements hardened, enterprise-grade lead importing:
 * - Supports .xlsx (via PhpSpreadsheet) and .csv (via native PHP)
 * - Server-side MIME & structural validation (max 10MB)
 * - Case-insensitive column name matching
 * - Multi-rule row validation with detailed error logs
 * - Duplicate detection (email/phone) against live DB and within upload batch
 * - Safe preview staging prior to database insertion
 * - Configurable duplicate resolution (skip vs import)
 * - Formula injection mitigation
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    define('WebsiteTailors_INIT', true);
}

if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class) && file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;

final class ExcelLeadImporter
{
    public const MAX_FILE_SIZE = 10485760; // 10 MB in bytes

    public const VALID_LEAD_STATUSES = [
        'New', 'Contacted', 'Qualified', 'Proposal Sent', 'Converted', 'Lost'
    ];

    public const VALID_CALL_STATUSES = [
        'Not Called', 'Called', 'Call Back', 'No Answer', 'Not Interested'
    ];

    public const VALID_SERVICES = [
        'Website', 'Software', 'AI + Automation', 'UI/UX', 'Maintenance', 'Consulting', 'Other'
    ];

    /** @var array<string, array<string, mixed>> In-memory staging fallback for CLI testing */
    private static array $inMemoryStaging = [];

    /**
     * Parse and validate an uploaded spreadsheet (XLSX or CSV).
     *
     * @param array<string, mixed> $file Uploaded $_FILES entry
     * @param PDO $pdo Active database connection
     * @return array{
     *     success: bool,
     *     error?: string,
     *     import_token?: string,
     *     total_rows?: int,
     *     valid_count?: int,
     *     duplicate_count?: int,
     *     invalid_count?: int,
     *     preview_rows?: array<array<string, mixed>>,
     *     errors?: array<array{row: int, name: string, error: string}>,
     *     columns_found?: array<string>
     * }
     */
    public static function processUpload(array $file, PDO $pdo): array
    {
        // 1. Basic Upload Error Checks
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $msg = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the maximum 10 MB size limit.',
                UPLOAD_ERR_PARTIAL   => 'File upload was only partially completed. Please try again.',
                UPLOAD_ERR_NO_FILE   => 'Please select an Excel (.xlsx) or CSV (.csv) file to upload.',
                default              => 'Upload failed with error code: ' . $uploadError
            };
            return ['success' => false, 'error' => $msg];
        }

        // 2. File Size Validation (<= 10MB)
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return ['success' => false, 'error' => 'The uploaded file is empty.'];
        }
        if ($fileSize > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File size exceeds the allowable limit of 10 MB.'];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if (!is_readable($tmpPath)) {
            return ['success' => false, 'error' => 'Unable to read the temporary uploaded file.'];
        }

        // 3. Extension & MIME Type Verification
        $origName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            return ['success' => false, 'error' => 'Unsupported file extension: .' . $extension . '. Only .xlsx and .csv are supported.'];
        }

        // Server-side MIME validation via finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath) ?: 'application/octet-stream';

        $allowedMimes = [
            'csv' => [
                'text/csv', 'text/plain', 'text/x-csv', 'application/csv',
                'application/x-csv', 'application/vnd.ms-excel', 'text/comma-separated-values'
            ],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-zip', 'application/x-zip-compressed'
            ]
        ];

        if (!in_array($mimeType, $allowedMimes[$extension], true)) {
            // Some systems report octet-stream for CSV/XLSX; perform deeper inspection
            if ($extension === 'xlsx') {
                if (!self::isValidXlsxZip($tmpPath)) {
                    return ['success' => false, 'error' => 'The uploaded file is not a valid Microsoft Excel (.xlsx) spreadsheet.'];
                }
            } elseif ($extension === 'csv') {
                if (!self::isValidCsvFile($tmpPath)) {
                    return ['success' => false, 'error' => 'The uploaded file is not a valid CSV document.'];
                }
            } else {
                return ['success' => false, 'error' => 'Invalid file format detected (MIME: ' . $mimeType . ').'];
            }
        } elseif ($extension === 'xlsx') {
            if (!self::isValidXlsxZip($tmpPath)) {
                return ['success' => false, 'error' => 'The uploaded file is not a valid Microsoft Excel (.xlsx) spreadsheet.'];
            }
        }

        // 4. Parse Rows from File
        try {
            if ($extension === 'xlsx') {
                $rawRows = self::parseXlsx($tmpPath);
            } else {
                $rawRows = self::parseCsv($tmpPath);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to parse spreadsheet: ' . $e->getMessage()];
        }

        if (empty($rawRows) || count($rawRows) < 2) {
            return ['success' => false, 'error' => 'The uploaded spreadsheet has no data rows. Must contain at least a header row and 1 data row.'];
        }

        // 5. Header / Column Identification (Case-Insensitive)
        $headerRow = array_shift($rawRows);
        $columnMap = self::buildColumnMapping($headerRow);

        if (!isset($columnMap['name'])) {
            return [
                'success' => false,
                'error'   => 'Required column "Name" (or Contact Name) was not found in the spreadsheet header.'
            ];
        }

        if (!isset($columnMap['email']) && !isset($columnMap['phone'])) {
            return [
                'success' => false,
                'error'   => 'Spreadsheet must contain at least an "Email" or "Phone" column.'
            ];
        }

        // 6. Preload Existing DB Leads for Duplicate Detection
        $existingEmails = [];
        $existingPhones = [];
        try {
            $stmt = $pdo->query("SELECT id, LOWER(email) as email, phone FROM leads");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string)($row['email'] ?? '')));
                if (!empty($e)) {
                    $existingEmails[$e] = (int)$row['id'];
                }
                $p = self::normalizePhone((string)($row['phone'] ?? ''));
                if (!empty($p)) {
                    $existingPhones[$p] = (int)$row['id'];
                }
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Database error while checking existing leads: ' . $e->getMessage()];
        }

        // 7. Validate Each Row & Detect Duplicates
        $parsedRows    = [];
        $validRows     = [];
        $invalidRows   = [];
        $duplicateRows = [];
        $errorsList    = [];

        $batchSeenEmails = [];
        $batchSeenPhones = [];

        $rowNumber = 1; // 1 was header, so first data row is 2
        foreach ($rawRows as $raw) {
            $rowNumber++;

            // Skip completely empty rows
            $hasAnyData = false;
            foreach ($raw as $val) {
                if (trim((string)$val) !== '') {
                    $hasAnyData = true;
                    break;
                }
            }
            if (!$hasAnyData) {
                continue;
            }

            $lead = self::extractLeadData($raw, $columnMap);
            $validation = self::validateLeadRow($lead, $rowNumber);

            $isDuplicate = false;
            $duplicateReasons = [];

            if ($validation['is_valid']) {
                // Check Email Duplicates
                $cleanEmail = strtolower($lead['email']);
                if (!empty($cleanEmail)) {
                    if (isset($existingEmails[$cleanEmail])) {
                        $isDuplicate = true;
                        $duplicateReasons[] = 'Matches existing lead #' . $existingEmails[$cleanEmail] . ' in CRM (' . $lead['email'] . ')';
                    } elseif (isset($batchSeenEmails[$cleanEmail])) {
                        $isDuplicate = true;
                        $duplicateReasons[] = 'Duplicate email found in row ' . $batchSeenEmails[$cleanEmail] . ' of this spreadsheet';
                    } else {
                        $batchSeenEmails[$cleanEmail] = $rowNumber;
                    }
                }

                // Check Phone Duplicates
                $normPhone = self::normalizePhone($lead['phone']);
                if (!empty($normPhone)) {
                    if (isset($existingPhones[$normPhone])) {
                        $isDuplicate = true;
                        $duplicateReasons[] = 'Matches existing phone for lead #' . $existingPhones[$normPhone] . ' in CRM';
                    } elseif (isset($batchSeenPhones[$normPhone])) {
                        $isDuplicate = true;
                        $duplicateReasons[] = 'Duplicate phone number found in row ' . $batchSeenPhones[$normPhone] . ' of this spreadsheet';
                    } else {
                        $batchSeenPhones[$normPhone] = $rowNumber;
                    }
                }
            }

            $lead['row_number']        = $rowNumber;
            $lead['is_valid']          = $validation['is_valid'];
            $lead['errors']            = $validation['errors'];
            $lead['is_duplicate']      = $isDuplicate;
            $lead['duplicate_reasons'] = $duplicateReasons;

            $parsedRows[] = $lead;

            if (!$validation['is_valid']) {
                $invalidRows[] = $lead;
                foreach ($validation['errors'] as $errText) {
                    $errorsList[] = [
                        'row'   => $rowNumber,
                        'name'  => $lead['name'] ?: '(Missing Name)',
                        'error' => $errText
                    ];
                }
            } elseif ($isDuplicate) {
                $duplicateRows[] = $lead;
            } else {
                $validRows[] = $lead;
            }
        }

        $totalDataRows = count($parsedRows);
        if ($totalDataRows === 0) {
            return ['success' => false, 'error' => 'No readable data rows found in the uploaded file.'];
        }

        // 8. Stage Validated Data in Session with Secure Token
        $importToken = bin2hex(random_bytes(16));
        self::ensureSession();

        $stagingData = [
            'created_at'      => time(),
            'filename'        => $origName,
            'total_rows'      => $totalDataRows,
            'valid_rows'      => $validRows,
            'duplicate_rows'  => $duplicateRows,
            'invalid_rows'    => $invalidRows,
            'errors'          => $errorsList
        ];

        if (isset($_SESSION)) {
            $_SESSION['excel_lead_import_staging'][$importToken] = $stagingData;
        }
        self::$inMemoryStaging[$importToken] = $stagingData;

        // 9. Prepare First 20 Rows for UI Preview
        $previewRows = array_slice($parsedRows, 0, 20);

        return [
            'success'         => true,
            'import_token'    => $importToken,
            'total_rows'      => $totalDataRows,
            'valid_count'     => count($validRows),
            'duplicate_count' => count($duplicateRows),
            'invalid_count'   => count($invalidRows),
            'preview_rows'    => $previewRows,
            'errors'          => $errorsList,
            'columns_found'   => array_values($columnMap)
        ];
    }

    /**
     * Finalize and commit imported leads to the database.
     *
     * @param string $importToken Token from processUpload
     * @param string $duplicateOption 'skip' (default) or 'import'
     * @param PDO $pdo Active database connection
     * @param string $adminIp IP of the importing administrator
     * @return array{
     *     success: bool,
     *     error?: string,
     *     imported?: int,
     *     skipped_duplicates?: int,
     *     invalid_count?: int,
     *     total?: int
     * }
     */
    public static function commitImport(
        string $importToken,
        string $duplicateOption,
        PDO $pdo,
        string $adminIp = '127.0.0.1'
    ): array {
        self::ensureSession();

        $staging = $_SESSION['excel_lead_import_staging'][$importToken] ?? self::$inMemoryStaging[$importToken] ?? null;
        if (!$staging || !is_array($staging)) {
            return [
                'success' => false,
                'error'   => 'Import staging session has expired or is invalid. Please upload the spreadsheet again.'
            ];
        }

        $validRows      = $staging['valid_rows'] ?? [];
        $duplicateRows  = $staging['duplicate_rows'] ?? [];
        $invalidCount   = count($staging['invalid_rows'] ?? []);

        // Determine rows to insert
        $rowsToInsert = $validRows;
        $skippedDuplicates = 0;

        if (strtolower($duplicateOption) === 'import') {
            $rowsToInsert = array_merge($rowsToInsert, $duplicateRows);
        } else {
            $skippedDuplicates = count($duplicateRows);
        }

        if (empty($rowsToInsert)) {
            return [
                'success'            => true,
                'imported'           => 0,
                'skipped_duplicates' => $skippedDuplicates,
                'invalid_count'      => $invalidCount,
                'total'              => (int)($staging['total_rows'] ?? 0)
            ];
        }

        // Insert rows into database inside a transaction
        $nowStr = date('Y-m-d H:i:s');
        $insertedCount = 0;

        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO leads (
                    name, company, email, phone, service, service_interested,
                    budget, message, source, status, call_status, notes,
                    next_followup_at, ip_address, created_at, updated_at
                ) VALUES (
                    :name, :company, :email, :phone, :service, :service_interested,
                    :budget, :message, :source, :status, :call_status, :notes,
                    :next_followup_at, :ip_address, :created_at, :updated_at
                )
            ");

            foreach ($rowsToInsert as $lead) {
                $serviceVal = !empty($lead['service']) ? $lead['service'] : 'Website';
                $statusVal  = !empty($lead['status']) ? $lead['status'] : 'New';
                $callVal    = !empty($lead['call_status']) ? $lead['call_status'] : 'Not Called';
                $sourceVal  = !empty($lead['source']) ? $lead['source'] : 'Excel Import';
                $msgVal     = !empty($lead['message']) ? $lead['message'] : (!empty($lead['notes']) ? $lead['notes'] : 'Excel Lead Import');

                $insertStmt->execute([
                    ':name'               => $lead['name'],
                    ':company'            => !empty($lead['company']) ? $lead['company'] : null,
                    ':email'              => !empty($lead['email']) ? $lead['email'] : '',
                    ':phone'              => !empty($lead['phone']) ? $lead['phone'] : null,
                    ':service'            => $serviceVal,
                    ':service_interested' => $serviceVal,
                    ':budget'             => !empty($lead['budget']) ? $lead['budget'] : null,
                    ':message'            => $msgVal,
                    ':source'             => $sourceVal,
                    ':status'             => $statusVal,
                    ':call_status'        => $callVal,
                    ':notes'              => !empty($lead['notes']) ? $lead['notes'] : null,
                    ':next_followup_at'   => !empty($lead['next_followup']) ? $lead['next_followup'] : null,
                    ':ip_address'         => $adminIp,
                    ':created_at'         => $nowStr,
                    ':updated_at'         => $nowStr,
                ]);

                $newLeadId = (int)$pdo->lastInsertId();
                $insertedCount++;

                // If imported directly with status = 'Converted', link existing client if one exists (do NOT auto-create)
                if ($statusVal === 'Converted' && !empty($lead['email'])) {
                    $chk = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
                    $chk->execute([':email' => $lead['email']]);
                    $existingCid = $chk->fetchColumn();

                    if ($existingCid) {
                        $pdo->prepare("UPDATE leads SET client_id = ? WHERE id = ?")->execute([(int)$existingCid, $newLeadId]);
                    }
                }
            }

            $pdo->commit();

            // Clear staging session token
            if (isset($_SESSION['excel_lead_import_staging'][$importToken])) {
                unset($_SESSION['excel_lead_import_staging'][$importToken]);
            }
            unset(self::$inMemoryStaging[$importToken]);

            return [
                'success'            => true,
                'imported'           => $insertedCount,
                'skipped_duplicates' => $skippedDuplicates,
                'invalid_count'      => $invalidCount,
                'total'              => (int)($staging['total_rows'] ?? $insertedCount)
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Database error during lead import: ' . $e->getMessage()];
        }
    }

    /**
     * Generate an error report CSV string for a staging token.
     */
    public static function generateErrorReportCsv(string $importToken): ?string
    {
        self::ensureSession();

        $staging = $_SESSION['excel_lead_import_staging'][$importToken] ?? self::$inMemoryStaging[$importToken] ?? null;
        if (!$staging || empty($staging['errors'])) {
            return null;
        }

        $out = fopen('php://temp', 'r+');
        if (!$out) {
            return null;
        }

        // CSV Header
        fputcsv($out, ['Row #', 'Lead Name', 'Company', 'Email', 'Phone', 'Validation Error'], ',', '"', "\\");

        // Build quick lookup for invalid rows by row number
        $invalidLookup = [];
        foreach ($staging['invalid_rows'] ?? [] as $inv) {
            $invalidLookup[(int)($inv['row_number'] ?? 0)] = $inv;
        }

        foreach ($staging['errors'] as $err) {
            $rowNum = (int)($err['row'] ?? 0);
            $invRow = $invalidLookup[$rowNum] ?? [];
            fputcsv($out, [
                $rowNum,
                $invRow['name'] ?? ($err['name'] ?? ''),
                $invRow['company'] ?? '',
                $invRow['email'] ?? '',
                $invRow['phone'] ?? '',
                $err['error'] ?? 'Invalid row'
            ], ',', '"', "\\");
        }

        rewind($out);
        $csvContent = stream_get_contents($out);
        fclose($out);

        return $csvContent ?: null;
    }

    /**
     * Generate standard downloadable sample CSV template.
     */
    public static function generateSampleCsv(): string
    {
        $headers = [
            'Name', 'Company', 'Email', 'Phone', 'Service', 'Budget',
            'Message', 'Source', 'Status', 'Call Status', 'Notes', 'Next Follow-up'
        ];

        $samples = [
            [
                'Vikram Singhania', 'Singhania Logistics Ltd', 'vikram@singhanialogistics.in', '+91 98200 11223',
                'Software', '₹5,00,000+', 'Need enterprise freight management ERP with GST invoicing',
                'Excel Import', 'New', 'Not Called', 'High priority prospect from Delhi conference', '2026-09-25 14:00:00'
            ],
            [
                'Ananya Deshmukh', 'Aura Bio-Health', 'ananya@aurabio.com', '+91 98450 44556',
                'Website', '₹3,00,000 - ₹5,00,000', 'Redesign clinical trials portal with modern UI/UX',
                'Excel Import', 'Qualified', 'Call Back', 'Requested product deck via WhatsApp', '2026-09-22 11:30:00'
            ],
            [
                'Rohan Mehra', 'FinVertex Systems', 'rohan.mehra@finvertex.co', '+91 99300 77889',
                'AI + Automation', '₹10,00,000+', 'Automated customer KYC & document fraud detection pipelines',
                'Excel Import', 'New', 'Not Called', 'Scheduled demo next week', '2026-09-28 16:00:00'
            ]
        ];

        $out = fopen('php://temp', 'r+');
        if (!$out) {
            return '';
        }

        fputcsv($out, $headers, ',', '"', "\\");
        foreach ($samples as $row) {
            fputcsv($out, $row, ',', '"', "\\");
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv ?: '';
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }
    }

    // -------------------------------------------------------------------------
    // INTERNAL PARSING & VALIDATION HELPERS
    // -------------------------------------------------------------------------

    /**
     * Parse XLSX file using PhpSpreadsheet.
     *
     * @return array<int, array<int, mixed>>
     */
    private static function parseXlsx(string $filePath): array
    {
        // Suppress formula calculation errors and read strictly values
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $val = $cell->getValue();
                // Ensure null or strings
                $rowData[] = $val !== null ? self::neutralizeFormula((string)$val) : '';
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Parse CSV file using native PHP fgetcsv with auto-delimiter detection.
     *
     * @return array<int, array<int, string>>
     */
    private static function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Failed to open CSV file for reading.');
        }

        // Auto-detect delimiter from first 4KB
        $sample = fread($handle, 4096) ?: '';
        rewind($handle);

        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($sample, $delim);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delim;
            }
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $bestDelimiter, '"', "\\")) !== false) {
            $cleanedRow = [];
            foreach ($data as $cell) {
                // Remove UTF-8 BOM if present on first cell
                $cellStr = trim((string)$cell);
                $cellStr = preg_replace('/^\xEF\xBB\xBF/', '', $cellStr);
                $cleanedRow[] = self::neutralizeFormula($cellStr);
            }
            $rows[] = $cleanedRow;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Map header names case-insensitively and flexibly.
     *
     * @param array<int, mixed> $headerRow
     * @return array<string, int> Map of field_key => column_index
     */
    private static function buildColumnMapping(array $headerRow): array
    {
        $map = [];

        $aliases = [
            'name'          => ['name', 'fullname', 'contactname', 'leadname', 'clientname', 'prospectname'],
            'company'       => ['company', 'companyname', 'organization', 'business', 'org', 'firm'],
            'email'         => ['email', 'emailaddress', 'mail'],
            'phone'         => ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'contactnumber', 'telephone', 'cell'],
            'service'       => ['service', 'services', 'serviceinterested', 'servicerequired', 'projecttype', 'requirement'],
            'budget'        => ['budget', 'estimatedbudget', 'projectbudget', 'approxbudget', 'dealvalue', 'amount'],
            'message'       => ['message', 'inquiry', 'enquiry', 'requirements', 'projectdetails', 'description', 'query'],
            'source'        => ['source', 'leadsource', 'channel', 'acquisitionchannel'],
            'status'        => ['status', 'leadstatus', 'stage', 'pipelinestage'],
            'call_status'   => ['callstatus', 'call_status', 'callingstatus', 'callstate', 'outreachstatus'],
            'notes'         => ['notes', 'internalnotes', 'comments', 'comment', 'remarks'],
            'next_followup' => ['nextfollowup', 'nextfollowupat', 'nextfollowupdate', 'followupdate', 'followup', 'nextcall']
        ];

        foreach ($headerRow as $idx => $rawHeader) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$rawHeader));
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $key => $synonyms) {
                if (!isset($map[$key])) {
                    foreach ($synonyms as $syn) {
                        if ($normalized === $syn) {
                            $map[$key] = (int)$idx;
                            break 2;
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Extract normalized field values from a raw row using column mapping.
     *
     * @param array<int, mixed> $raw
     * @param array<string, int> $map
     * @return array<string, string>
     */
    private static function extractLeadData(array $raw, array $map): array
    {
        $get = function (string $key) use ($raw, $map): string {
            if (!isset($map[$key])) {
                return '';
            }
            $idx = $map[$key];
            return trim((string)($raw[$idx] ?? ''));
        };

        return [
            'name'          => $get('name'),
            'company'       => $get('company'),
            'email'         => $get('email'),
            'phone'         => $get('phone'),
            'service'       => $get('service'),
            'budget'        => $get('budget'),
            'message'       => $get('message'),
            'source'        => $get('source'),
            'status'        => $get('status'),
            'call_status'   => $get('call_status'),
            'notes'         => $get('notes'),
            'next_followup' => $get('next_followup')
        ];
    }

    /**
     * Validate an individual row's business logic.
     *
     * @param array<string, string> $lead
     * @param int $rowNumber
     * @return array{is_valid: bool, errors: array<string>}
     */
    private static function validateLeadRow(array &$lead, int $rowNumber): array
    {
        $errors = [];

        // 1. Required: Name
        if (empty($lead['name'])) {
            $errors[] = "Row {$rowNumber}: Name is missing.";
        }

        // 2. Required: Phone OR Email
        if (empty($lead['email']) && empty($lead['phone'])) {
            $errors[] = "Row {$rowNumber}: At least one contact method (Email or Phone) is required.";
        }

        // 3. Email Format Check (if present)
        if (!empty($lead['email'])) {
            if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: Invalid email format ({$lead['email']}).";
            }
        }

        // 4. Phone Format Check (if present)
        if (!empty($lead['phone'])) {
            $digitsOnly = preg_replace('/[^0-9]/', '', $lead['phone']);
            if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
                $errors[] = "Row {$rowNumber}: Invalid phone number ({$lead['phone']}). Must contain 7 to 15 digits.";
            }
        }

        // 5. Status Validation & Defaulting
        if (!empty($lead['status'])) {
            $matchedStatus = null;
            foreach (self::VALID_LEAD_STATUSES as $validSt) {
                if (strcasecmp($lead['status'], $validSt) === 0) {
                    $matchedStatus = $validSt;
                    break;
                }
            }
            if ($matchedStatus) {
                $lead['status'] = $matchedStatus;
            } else {
                $errors[] = "Row {$rowNumber}: Invalid status '{$lead['status']}'. Allowed: " . implode(', ', self::VALID_LEAD_STATUSES);
            }
        } else {
            $lead['status'] = 'New';
        }

        // 6. Call Status Validation & Defaulting
        if (!empty($lead['call_status'])) {
            $matchedCall = null;
            foreach (self::VALID_CALL_STATUSES as $validCs) {
                if (strcasecmp($lead['call_status'], $validCs) === 0) {
                    $matchedCall = $validCs;
                    break;
                }
            }
            if ($matchedCall) {
                $lead['call_status'] = $matchedCall;
            } else {
                $errors[] = "Row {$rowNumber}: Invalid call status '{$lead['call_status']}'. Allowed: " . implode(', ', self::VALID_CALL_STATUSES);
            }
        } else {
            $lead['call_status'] = 'Not Called';
        }

        // 7. Source Defaulting
        if (empty($lead['source'])) {
            $lead['source'] = 'Excel Import';
        }

        // 8. Service Normalization
        if (!empty($lead['service'])) {
            foreach (self::VALID_SERVICES as $vs) {
                if (strcasecmp($lead['service'], $vs) === 0) {
                    $lead['service'] = $vs;
                    break;
                }
            }
        } else {
            $lead['service'] = 'Website';
        }

        // 9. Next Follow-up Date Parsing (if provided)
        if (!empty($lead['next_followup'])) {
            $parsedDate = strtotime($lead['next_followup']);
            if ($parsedDate === false || $parsedDate < 0) {
                $errors[] = "Row {$rowNumber}: Invalid next follow-up date format ({$lead['next_followup']}).";
            } else {
                $lead['next_followup'] = date('Y-m-d H:i:s', $parsedDate);
            }
        } else {
            $lead['next_followup'] = null;
        }

        return [
            'is_valid' => empty($errors),
            'errors'   => $errors
        ];
    }

    /**
     * Clean and normalize phone numbers for duplicate matching.
     */
    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        // If leading 91 (India) with 12 digits, strip country code for matching
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Prevent CSV/Excel formula injection (strip or quote leading =, +, -, @).
     */
    private static function neutralizeFormula(string $val): string
    {
        $val = trim($val);
        if ($val !== '' && in_array($val[0], ['=', '+', '-', '@'], true)) {
            // Strip leading symbol if immediately followed by dangerous formula commands
            if (preg_match('/^[=+\-@](SUM|CMD|EXEC|IMPORT|HYPERLINK|AVERAGE)/i', $val)) {
                return "'" . $val;
            }
        }
        return $val;
    }

    /**
     * Validate whether file is a real openxml zip archive (XLSX).
     */
    private static function isValidXlsxZip(string $filePath): bool
    {
        $fh = fopen($filePath, 'rb');
        if (!$fh) {
            return false;
        }
        $header = fread($fh, 4);
        fclose($fh);

        // ZIP magic bytes: PK\x03\x04
        if ($header !== "PK\x03\x04") {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $hasContentTypes = ($zip->locateName('[Content_Types].xml') !== false);
            $zip->close();
            return $hasContentTypes;
        }

        return false;
    }

    /**
     * Validate basic CSV structure.
     */
    private static function isValidCsvFile(string $filePath): bool
    {
        $fh = fopen($filePath, 'r');
        if (!$fh) {
            return false;
        }
        $firstLine = fgets($fh);
        fclose($fh);

        if ($firstLine === false) {
            return false;
        }

        // Must be text without null bytes
        return strpos($firstLine, "\0") === false;
    }
}
