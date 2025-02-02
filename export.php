<?php
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
                    o.order_id AS 'Order ID',
                    u.username AS 'Customer',
                    o.total_amount AS 'Amount',
                    o.order_date AS 'Date'
                  FROM orders o
                  JOIN users u ON o.user_id = u.id
                  ORDER BY o.order_date DESC
                  LIMIT 100";
        break;
    case 'topCustomersTable':
        $query = "SELECT 
                    u.username AS 'Customer',
                    SUM(o.total_amount) AS 'Total Spent'
                  FROM orders o
                  JOIN users u ON o.user_id = u.id
                  GROUP BY u.username
                  ORDER BY 'Total Spent' DESC
                  LIMIT 100";
        break;
    default:
        echo "Invalid table specified.";
        exit();
}

$stmt = $db->query($query);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $mpdf = new \Mpdf\Mpdf();
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
