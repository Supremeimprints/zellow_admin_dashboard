<?php
/**
 * @var int $orderId
 * @var array $giftData
 */
?>
<html>
<body>
    <h2>Gift Order Confirmation</h2>
    <p>Your gift order has been confirmed and will be prepared with care.</p>
    
    <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <p><strong>Recipient:</strong> <?= htmlspecialchars($giftData['recipient_email']) ?></p>
        <p><strong>Your Message:</strong><br>
        <?= htmlspecialchars($giftData['gift_message']) ?></p>
    </div>
    
    <p><strong>Order Details:</strong></p>
    <ul>
        <li>Order ID: #<?= $orderId ?></li>
        <li>Tracking Code: <?= htmlspecialchars($giftData['tracking_code']) ?></li>
        <li>Gift Wrapping: <?= $giftData['is_gift_wrapped'] ? 'Yes' : 'No' ?></li>
    </ul>
    
    <p><a href="https://your-domain.com/orders/<?= $orderId ?>" 
          style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
       View Order Details
    </a></p>
</body>
</html>
