<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/financial_functions.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$exportType = $_GET['export'] ?? 'csv';

$database = new Database();
$db = $database->getConnection();

// Get transaction data
$transactions = getTransactionHistory($db, $startDate, $endDate, 1000);

if (empty($transactions)) {
    die('No data available for export');
}

// Format data for export
$exportData = [];

// Define headers
$headers = ['Date', 'Time', 'Type', 'Reference', 'Description', 'Amount', 'Status'];
$exportData[] = $headers;

// Format transaction data
foreach ($transactions as $transaction) {
    $date = new DateTime($transaction['transaction_date']);
    
    $exportData[] = [
        $date->format('M d, Y'),          // Date
        $date->format('h:i A'),           // Time
        $transaction['transaction_type'],  // Type
        $transaction['reference_id'],      // Reference
        $transaction['description'] ?? '-', // Description
        ($transaction['amount'] >= 0 ? '+' : '-') . 
            'Ksh ' . number_format(abs($transaction['amount']), 2), // Amount
        $transaction['payment_status']     // Status
    ];
}

// Generate filename
$filename = 'transactions_' . date('Y-m-d_His');

// Export based on type
switch ($exportType) {
    case 'excel':
        exportExcel($exportData, $filename);
        break;
    
    case 'pdf':
        exportPDF($exportData, $filename);
        break;
    
    case 'csv':
    default:
        exportCSV($exportData, $filename);
        break;
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $fp = fopen('php://output', 'wb');
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

function exportExcel($data, $filename) {
    require_once 'vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add data
    $sheet->fromArray($data, NULL, 'A1');
    
    // Style the header row
    $highestColumn = $sheet->getHighestColumn();
    $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6E6E6']
        ]
    ]);
    
    // Auto-size columns
    foreach (range('A', $highestColumn) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set number format for amount column (Column F)
    $sheet->getStyle('F2:F' . $sheet->getHighestRow())
          ->getNumberFormat()
          ->setFormatCode('_("Ksh"* #,##0.00_);_("Ksh"* -#,##0.00_);_("Ksh"* "-"??_);_(@_)');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

function exportPDF($data, $filename) {
    require_once 'vendor/autoload.php';
    
    $pdf = new \Mpdf\Mpdf([
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    
    // Add title
    $pdf->WriteHTML('<h2>Transaction History</h2>');
    
    // Create table HTML
    $html = '<table border="1" cellpadding="4" style="width: 100%; border-collapse: collapse; font-size: 12px;">';
    
    // Add header row
    $html .= '<tr style="background-color: #f3f3f3;">';
    foreach ($data[0] as $header) {
        $html .= '<th style="text-align: left;">' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr>';
    
    // Add data rows
    array_shift($data); // Remove header row
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    $pdf->WriteHTML($html);
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
