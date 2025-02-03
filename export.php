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
        case 'topCustomersTable':
        $query = "SELECT 
                    u.userame AS 'Name',
                    COUNT(o.order_id) AS 'Orders',
                    SUM(o.total_amount) AS 'Total Spent'
                  FROM orders o
                  JOIN users u ON o.user_id = u.id
                  WHERE u.role = 'customer'
                  GROUP BY u.id
                  ORDER BY 'Total Spent' DESC
                  LIMIT 5";
        break;
        case 'topDriversTable':
        $query = "SELECT 
                    d.name AS 'Name',
                    COUNT(o.order_id) AS 'Orders',
                    SUM(o.total_amount) AS 'Total Earnings'
                  FROM orders o
                  JOIN drivers d ON o.driver_id = d.driver_id
                  GROUP BY d.driver_id
                  ORDER BY 'Total Earnings' DESC
                  LIMIT 5";
        break;
        case 'OrdersPerCustomerTable':
        $query = "WITH OrderStats AS (
            SELECT 
                o.id,
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oi.quantity) as total_items,
                ROUND(AVG(oi.quantity), 1) as avg_items_per_order,
                SUM(oi.subtotal) as total_spent
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE 1=1";
        break;
    case 'ordersPerCustomer':
        ob_clean();
        $params = [];
        $query = "WITH CustomerOrders AS (
            SELECT 
                o.id,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(oi.quantity) as total_items,
                COUNT(oi.id) as items_per_order,
                SUM(oi.subtotal) as total_spent
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            GROUP BY o.id
        )
        SELECT 
            CASE 
                WHEN items_per_order <= 2 THEN '1-2 items'
                WHEN items_per_order <= 5 THEN '3-5 items'
                WHEN items_per_order <= 10 THEN '6-10 items'
                WHEN items_per_order <= 20 THEN '11-20 items'
                ELSE '20+ items'
            END as order_group,
            COUNT(*) as customer_count,
            ROUND(AVG(total_items), 1) as avg_items,
            ROUND(AVG(items_per_order), 1) as avg_items_per_order,
            ROUND(AVG(total_spent), 2) as avg_spent
        FROM CustomerOrders
        GROUP BY 
            CASE 
                WHEN items_per_order <= 2 THEN '1-2 items'
                WHEN items_per_order <= 5 THEN '3-5 items'
                WHEN items_per_order <= 10 THEN '6-10 items'
                WHEN items_per_order <= 20 THEN '11-20 items'
                ELSE '20+ items'
            END
        ORDER BY 
            MIN(items_per_order) ASC";
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
    
    // Prevent any output before PDF generation
    ob_clean();
    
    // Create new TCPDF instance
    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Zellow Admin');
    $pdf->SetAuthor('Zellow System');
    $pdf->SetTitle(ucfirst($table) . ' Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Add title
    $pdf->Cell(0, 10, ucfirst($table) . ' Report', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Set font for table header
    $pdf->SetFont('helvetica', 'B', 11);
    
    // Calculate column widths based on table type
    $columnWidths = [];
    $headers = array_keys($data[0]);
    $pageWidth = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
    $defaultWidth = $pageWidth / count($headers);
    
    foreach ($headers as $header) {
        $columnWidths[] = $defaultWidth;
    }
    
    // Add table headers
    foreach ($headers as $index => $header) {
        $pdf->Cell($columnWidths[$index], 7, $header, 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Set font for table data
    $pdf->SetFont('helvetica', '', 10);
    
    // Add table data
    foreach ($data as $row) {
        foreach ($row as $index => $cell) {
            // Format numbers if needed
            if (is_numeric($cell)) {
                if (strpos($headers[$index], 'Amount') !== false || 
                    strpos($headers[$index], 'Revenue') !== false || 
                    strpos($headers[$index], 'Spent') !== false) {
                    $cell = number_format($cell, 2);
                }
            }
            $pdf->Cell($columnWidths[$index], 6, $cell, 1, 0, 'L');
        }
        $pdf->Ln();
    }
    
    // Special handling for ordersPerCustomer - add pie chart
    if ($table === 'ordersPerCustomer') {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Orders Distribution Chart', 0, 1, 'C');
        
        // Create pie chart using TCPDF's graphic functions
        $pdf->SetFont('helvetica', '', 10);
        $centerX = 105;
        $centerY = 150;
        $radius = 50;
        
        $total = array_sum(array_column($data, 'customer_count'));
        
        if ($total > 0) {  // Check for division by zero
            $startAngle = 0;
            $colors = [
                [79, 70, 229],  // Indigo
                [16, 185, 129], // Green
                [245, 158, 11], // Yellow
                [239, 68, 68],  // Red
                [139, 92, 246]  // Purple
            ];
            
            foreach ($data as $index => $row) {
                $percentage = ($row['customer_count'] / $total) * 100;
                $angle = ($percentage * 360) / 100;
                
                $pdf->SetFillColor($colors[$index][0], $colors[$index][1], $colors[$index][2]);
                $pdf->PieSector($centerX, $centerY, $radius, $startAngle, $startAngle + $angle, 'FD');
                
                // Add legend
                $legendY = 220 + ($index * 10);
                $pdf->Rect(70, $legendY, 5, 5, 'F', [], $colors[$index]);
                $pdf->SetXY(80, $legendY);
                $pdf->Cell(0, 5, $row['order_group'] . ' (' . number_format($percentage, 1) . '%)', 0, 1);
                
                $startAngle += $angle;
            }
        } else {
            $pdf->Cell(0, 10, 'No data available for chart', 0, 1, 'C');
        }
    }

    // Ensure headers haven't been sent
    if (headers_sent()) {
        die("Error: Headers already sent. PDF download failed.");
    }
    
    $pdf->Output('export.pdf', 'D');
    exit();
} else {
    echo "Invalid format specified.";
}
?>
