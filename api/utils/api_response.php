<?php
function send_json_response($success, $message, $data = null, $status_code = 200) {
    header('Content-Type: application/json');
    http_response_code($status_code);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit();
}

function send_success($message, $data = null) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
