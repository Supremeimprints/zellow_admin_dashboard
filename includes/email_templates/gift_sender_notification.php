<?php
function getGiftSenderTemplate($details) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2>Your Gift Order #{$details['order_id']}</h2>
        <p>Thank you for your gift order! Here are the details:</p>
        
        <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <h3>Order Details</h3>
            <p><strong>Order ID:</strong> #{$details['order_id']}</p>
            <p><strong>Tracking Number:</strong> {$details['tracking_number']}</p>
            <p><strong>Status:</strong> {$details['status']}</p>
            <p><strong>Recipient:</strong> {$details['recipient_name']}</p>
            " . ($details['estimated_delivery'] ? "<p><strong>Estimated Delivery:</strong> {$details['estimated_delivery']}</p>" : "") . "
        </div>

        <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <h3>Gift Details</h3>
            <p><strong>Gift Message:</strong> {$details['gift_message']}</p>
            " . ($details['gift_wrap'] ? "<p><strong>Gift Wrap:</strong> {$details['gift_wrap']}</p>" : "") . "
            " . ($details['special_requests'] ? "<p><strong>Special Requests:</strong> {$details['special_requests']}</p>" : "") . "
        </div>

        <p>Track your order anytime at: <a href='{$details['tracking_url']}'>{$details['tracking_url']}</a></p>
    </div>";
}
