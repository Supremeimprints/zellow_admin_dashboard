<?php
function getServiceNotificationTemplate($details) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2>New Service Request Assignment</h2>
        <p>You have been assigned a new {$details['service_type']} request.</p>
        <p><strong>Order ID:</strong> #{$details['order_id']}</p>
        <p><strong>Required Service:</strong> {$details['service_type']}</p>
        <p><strong>Details:</strong> {$details['message']}</p>
        <p><strong>Status:</strong> Pending</p>
        <p>Please log in to your dashboard to accept this assignment.</p>
    </div>";
}
