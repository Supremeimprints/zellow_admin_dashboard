<?php
/**
 * @var int $orderId
 * @var array $giftData
 */
?>
<html>
<body>
    <h2>You've Received a Gift! 🎁</h2>
    <p>Someone special has sent you a gift from Zellow Enterprises.</p>
    
    <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <p><strong>Gift Message:</strong><br>
        <?= htmlspecialchars($giftData['gift_message']) ?></p>
    </div>
    
    <p>Track your gift:</p>
    <p><a href="https://your-domain.com/track?code=<?= htmlspecialchars($giftData['tracking_code']) ?>" 
          style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
       Track Your Gift
    </a></p>
    
    <p style="color: #6c757d; font-size: 0.9em;">
        Order Reference: #<?= $orderId ?><br>
        Tracking Code: <?= htmlspecialchars($giftData['tracking_code']) ?>
    </p>
</body>
</html>
