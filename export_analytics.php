<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/financial_functions.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get date parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$exportType = $_GET['export'] ?? 'csv';

$database = new Database();
$db = $database->getConnection();

// Get transaction data
$transactions = getTransactionHistory($db, $startDate, $endDate, 1000); // Increased limit for export

if (empty($transactions) || isset($transactions['error'])) {
    die('No data available for export');
}

// Prepare data for export
$exportData = [];
$headers = ['Date', 'Type', 'Reference', 'Amount', 'Status'];

// Add headers as first row
$exportData[] = $headers;

// Add transaction data
foreach ($transactions as $transaction) {
    $exportData[] = [
        date('Y-m-d H:i:s', strtotime($transaction['transaction_date'])),
        $transaction['type'] ?? 'Unknown',
        $transaction['reference'] ?? '',
        $transaction['amount'] ?? '0',
        $transaction['status'] ?? 'Unknown'
    ];
}

// Export based on type
switch ($exportType) {
    case 'excel':
        exportExcel($exportData, "transactions_{$startDate}_to_{$endDate}");
        break;
    
    case 'pdf':
        exportPDF($exportData, "transactions_{$startDate}_to_{$endDate}");
        break;
    
    case 'csv':
    default:
        exportCSV($exportData, "transactions_{$startDate}_to_{$endDate}");
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
    $row = 1;
    foreach ($data as $rowData) {
        $col = 'A';
        foreach ($rowData as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Style the header row
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
        'font' => [
            'bold' => true
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E6E6E6'
            ]
        ]
    ]);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

function exportPDF($data, $filename) {
    require_once 'vendor/autoload.php'; // Make sure TCPDF is installed
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Zellow Admin');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Transaction Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Create the table
    $html = '<table border="1" cellpadding="4">';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output the PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
