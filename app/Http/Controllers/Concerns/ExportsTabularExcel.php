<?php

namespace App\Http\Controllers\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsTabularExcel
{
    /**
     * @return array<int, string>
     */
    protected function defaultExcelTextColumnKeys(): array
    {
        return ['invoice_number', 'reference_number', 'order', 'mailgun_message_id', 'email_mailgun_message_id'];
    }

    protected function streamXlsxDownload(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<int, string>|null  $columns
     * @param  array<string, string>  $headerMap
     * @param  array<int, string>  $extraTextColumnKeys
     */
    protected function spreadsheetFromTabularRows(
        array $data,
        ?array $columns,
        array $headerMap = [],
        array $extraTextColumnKeys = []
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if (empty($data)) {
            return $spreadsheet;
        }

        $headers = ! empty($columns) ? $columns : array_keys((array) $data[0]);
        $textKeys = array_fill_keys(
            array_unique(array_merge($this->defaultExcelTextColumnKeys(), $extraTextColumnKeys)),
            true
        );

        $col = 1;
        foreach ($headers as $header) {
            $headerCellRef = Coordinate::stringFromColumnIndex($col).'1';
            $label = $headerMap[$header] ?? ucwords(str_replace(['_id', '_'], ['', ' '], str_replace('.', ' ', $header)));
            $sheet->setCellValue($headerCellRef, $label);
            $sheet->getStyle($headerCellRef)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $col++;
        }

        $rowNum = 2;
        foreach ($data as $row) {
            $col = 1;
            foreach ($headers as $key) {
                $cellRef = Coordinate::stringFromColumnIndex($col).$rowNum;
                $value = $row[$key] ?? '';

                if (isset($textKeys[$key])) {
                    $sheet->setCellValueExplicit($cellRef, (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cellRef, $value);

                    if (is_numeric($value) && $value !== '') {
                        $sheet->getStyle($cellRef)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                if (function_exists('pdf_export_cell_is_right_aligned') && pdf_export_cell_is_right_aligned($key)) {
                    $sheet->getStyle($cellRef)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $col++;
            }
            $rowNum++;
        }

        return $spreadsheet;
    }
}
