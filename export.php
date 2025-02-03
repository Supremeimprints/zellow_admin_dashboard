<?php

use Mpdf\Mpdf;
session_start();
require_once 'config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

function getTransactionsForExport($pdo) {
    try {
        $query = "
            SELECT * FROM (
                SELECT 
                    reference_id,
                    CASE 
                        WHEN transaction_type = 'Customer Payment' AND payment_status = 'completed' 
                        THEN total_amount 
                        ELSE 0 
                    END as money_in,
                    CASE 
                        WHEN transaction_type = 'OUT' 
                        THEN total_amount 
                        ELSE 0 
                    END as money_out,
                    payment_status,
                    transaction_date
                FROM transactions
                WHERE (transaction_type = 'Customer Payment' AND payment_status = 'completed')
                   OR transaction_type = 'OUT'
            ) AS combined_transactions 
            ORDER BY transaction_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Export error: " . $e->getMessage());
        return [];
    }
}

// Handle export request
$table = $_GET['table'] ?? '';
$format = $_GET['format'] ?? '';

if ($table === 'recentTransactionsTable') {
    $data = getTransactionsForExport($db);
    
    $filename = 'transactions_export_' . date('Y-m-d');

    // Set headers based on format
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($output, ['Reference ID', 'Money In (KES)', 'Money Out (KES)', 'Status', 'Date']);
            
            // Add data
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['reference_id'],
                    number_format($row['money_in'], 2),
                    number_format($row['money_out'], 2),
                    $row['payment_status'],
                    date('Y-m-d', strtotime($row['transaction_date']))
                ]);
            }
            
            fclose($output);
            break;

        case 'excel':
            require 'vendor/autoload.php'; // Make sure PHPSpreadsheet is installed
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Add headers
            $sheet->setCellValue('A1', 'Reference ID');
            $sheet->setCellValue('B1', 'Money In (KES)');
            $sheet->setCellValue('C1', 'Money Out (KES)');
            $sheet->setCellValue('D1', 'Status');
            $sheet->setCellValue('E1', 'Date');
            
            // Add data
            $row = 2;
            foreach ($data as $item) {
                $sheet->setCellValue('A' . $row, $item['reference_id']);
                $sheet->setCellValue('B' . $row, number_format($item['money_in'], 2));
                $sheet->setCellValue('C' . $row, number_format($item['money_out'], 2));
                $sheet->setCellValue('D' . $row, $item['payment_status']);
                $sheet->setCellValue('E' . $row, date('Y-m-d', strtotime($item['transaction_date'])));
                $row++;
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            break;

        case 'pdf':
            require_once 'vendor/autoload.php'; // Make sure TCPDF is installed
            
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Your System');
            $pdf->SetTitle('Transactions Report');
            $pdf->AddPage();
            
            // Add title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'Transactions Report', 0, 1, 'C');
            $pdf->Ln(10);
            
            // Add table headers
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(50, 7, 'Reference ID', 1);
            $pdf->Cell(35, 7, 'Money In', 1);
            $pdf->Cell(35, 7, 'Money Out', 1);
            $pdf->Cell(35, 7, 'Status', 1);
            $pdf->Cell(35, 7, 'Date', 1);
            $pdf->Ln();
            
            // Add data
            $pdf->SetFont('helvetica', '', 12);
            foreach ($data as $row) {
                $pdf->Cell(50, 6, $row['reference_id'], 1);
                $pdf->Cell(35, 6, number_format($row['money_in'], 2), 1);
                $pdf->Cell(35, 6, number_format($row['money_out'], 2), 1);
                $pdf->Cell(35, 6, $row['payment_status'], 1);
                $pdf->Cell(35, 6, date('Y-m-d', strtotime($row['transaction_date'])), 1);
                $pdf->Ln();
            }
            
            $pdf->Output($filename . '.pdf', 'D');
            break;
    }
    exit();
}

// Handle other tables...
$format = $_GET['format'] ?? 'csv';
$table = $_GET['table'] ?? 'lowStockTable';

switch ($table) {
    case 'lowStockTable':
        $query = "SELECT 
                    p.product_name AS 'Product',
                    i.stock_quantity AS 'Current Stock',
                    i.min_stock_level AS 'Minimum Required'
                  FROM inventory i
                  JOIN products p ON i.product_id = p.product_id
                  WHERE i.stock_quantity < i.min_stock_level";
        break;
    case 'recentTransactionsTable':
        $query = "SELECT 
                    t.reference_id AS 'Reference',
                    CASE 
                        WHEN t.transaction_type = 'IN' THEN t.total_amount
                        ELSE 0 
                    END AS 'Money In',
                    CASE 
                        WHEN t.transaction_type = 'OUT' THEN ABS(t.total_amount)
                        ELSE 0 
                    END AS 'Money Out',
                    t.payment_status AS 'Status',
                    t.transaction_date AS 'Date'
                  FROM transactions t
                  ORDER BY t.transaction_date DESC
                  LIMIT 100";
        break;
    case 'topProductsTable':
        $query = "SELECT 
                    p.product_name AS 'Product',
                    SUM(oi.quantity) AS 'Sales',
                    SUM(oi.quantity * oi.unit_price) AS 'Revenue'
                  FROM order_items oi
                  JOIN orders o ON oi.order_id = o.order_id
                  JOIN products p ON oi.product_id = p.product_id
                  GROUP BY p.product_name
                  ORDER BY 'Revenue' DESC
                  LIMIT 5";
        break;
    default:
        echo "Invalid table specified.";
        exit();
}

$stmt = $db->query($query);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    echo "No data available for export.";
    exit();
}

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($data[0]));
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
} elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="export.xls"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($data[0]), "\t");
    foreach ($data as $row) {
        fputcsv($output, $row, "\t");
    }
    fclose($output);
} elseif ($format === 'pdf') {
    require_once 'vendor/autoload.php';
    $mpdf = new Mpdf();
    $html = '<table border="1"><thead><tr>';
    foreach (array_keys($data[0]) as $header) {
        $html .= "<th>{$header}</th>";
    }
    $html .= '</tr></thead><tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= "<td>{$cell}</td>";
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $mpdf->WriteHTML($html);
    $mpdf->Output('export.pdf', 'D');
} else {
    echo "Invalid format specified.";
}
?>
