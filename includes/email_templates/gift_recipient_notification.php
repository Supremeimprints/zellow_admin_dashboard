<?php
function getGiftRecipientTemplate($details) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2>You Have a Gift Coming!</h2>
        <p>{$details['sender_name']} has sent you a special gift!</p>
        
        <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <h3>Gift Message</h3>
            <p style='font-style: italic;'>\"{$details['gift_message']}\"</p>
        </div>

        <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <h3>Delivery Information</h3>
            <p><strong>Tracking Number:</strong> {$details['tracking_number']}</p>
            " . ($details['estimated_delivery'] ? "<p><strong>Estimated Delivery:</strong> {$details['estimated_delivery']}</p>" : "") . "
        </div>

        <p>Track your gift's journey at: <a href='{$details['tracking_url']}'>{$details['tracking_url']}</a></p>
        
        <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <p style='font-size: 12px; color: #666;'>This is a gift notification from Zellow Enterprises.</p>
        </div>
    </div>";
}
