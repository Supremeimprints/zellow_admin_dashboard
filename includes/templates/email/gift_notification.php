<?php
function getGifteeEmailTemplate($orderId, $giftData) {
    return <<<HTML
    <html>
    <body>
        <h2>You've Received a Gift! 🎁</h2>
        <p>Someone special has sent you a gift from Zellow Enterprises.</p>
        
        <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p><strong>Gift Message:</strong><br>
            {$giftData['gift_message']}</p>
        </div>
        
        <p>Track your gift's journey:</p>
        <p><a href="https://your-domain.com/track?code={$giftData['tracking_code']}" 
              style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
           Track Your Gift
        </a></p>
        
        <p style="color: #6c757d; font-size: 0.9em;">
            Order Reference: #{$orderId}<br>
            Tracking Code: {$giftData['tracking_code']}
        </p>
    </body>
    </html>
    HTML;
}

function getGifterEmailTemplate($orderId, $giftData) {
    return <<<HTML
    <html>
    <body>
        <h2>Gift Order Confirmation</h2>
        <p>Your gift order has been confirmed and will be prepared with care.</p>
        
        <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p><strong>Recipient:</strong> {$giftData['recipient_email']}</p>
            <p><strong>Your Message:</strong><br>
            {$giftData['gift_message']}</p>
        </div>
        
        <p><strong>Order Details:</strong></p>
        <ul>
            <li>Order ID: #{$orderId}</li>
            <li>Tracking Code: {$giftData['tracking_code']}</li>
            <li>Gift Wrapping: {$giftData['is_gift_wrapped'] ? 'Yes' : 'No'}</li>
        </ul>
        
        <p><a href="https://your-domain.com/orders/{$orderId}" 
              style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
           View Order Details
        </a></p>
    </body>
    </html>
    HTML;
}
?>
