<?php

return [
    'manual_journals' => [
        // Dalam kilobytes (20 MB default)
        'max_upload_kb' => (int) env('MANUAL_JOURNAL_IMPORT_MAX_UPLOAD_KB', 20 * 1024),
        // Total baris data CSV maksimum per upload
        'max_rows' => (int) env('MANUAL_JOURNAL_IMPORT_MAX_ROWS', 50000),
    ],
];
