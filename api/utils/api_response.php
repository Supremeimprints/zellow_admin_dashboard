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

function send_error($message, $status_code = 400) {
    send_json_response(false, $message, null, $status_code);
}

function send_success($message, $data = null) {
    send_json_response(true, $message, $data);
}
