<?php
/**
 * @var int $orderId
 * @var array $giftData
 */
?>
<html>
<body>
    <h2>You Have a Gift Coming!</h2>
    <p>Someone special has sent you a gift from Zellow Store.</p>
    
    <?php if (!empty($giftData['gift_message'])): ?>
    <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <p><strong>Their Message:</strong><br>
        <?= htmlspecialchars($giftData['gift_message']) ?></p>
    </div>
    <?php endif; ?>
    
    <p>Your gift is being prepared with care and will be delivered soon.</p>
    
    <?php if (!empty($giftData['tracking_code'])): ?>
    <p>You can track your gift using this code: <strong><?= htmlspecialchars($giftData['tracking_code']) ?></strong></p>
    <?php endif; ?>
</body>
</html>
