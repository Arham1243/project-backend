<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

trait ExportsTabularPdf
{
    /**
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $headerMap
     */
    protected function streamPdfDownload(
        string $title,
        array $data,
        array $columns,
        array $headerMap,
        string $filename
    ): Response {
        $pdf = Pdf::loadView('pdfs.tabular-export', [
            'title' => $title,
            'data' => $data,
            'columns' => $columns,
            'headerMap' => $headerMap,
            'generatedAt' => now()->format('M j, Y g:i A T'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }
}
