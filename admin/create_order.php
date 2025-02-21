<?php
require_once '../config/database.php';
require_once '../admin/controllers/GiftCustomizationController.php';

$database = new Database();
$db = $database->getConnection();
$giftController = new GiftCustomizationController($db);

// Get occasions and customizations for the form
$occasions = $giftController->getOccasions();
$customizations = $giftController->getAvailableCustomizations();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Order - Zellow Admin</title>
    <!-- ...existing head content... -->
</head>
<body>
    <!-- ...existing form content... -->
    
    <!-- Add Gift Options Section -->
    <div class="card mb-4" id="giftSection">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Gift Options</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="isGift" name="is_gift">
                <label class="form-check-label" for="isGift">This is a gift</label>
            </div>
        </div>
        
        <div class="card-body" id="giftOptions" style="display: none;">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Recipient's Email</label>
                    <input type="email" class="form-control" name="recipient_email" 
                           placeholder="Where should we send the gift notification?">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Occasion</label>
                    <select class="form-select" name="occasion_id">
                        <option value="">Select an occasion</option>
                        <?php foreach ($occasions as $occasion): ?>
                            <option value="<?= $occasion['id'] ?>"><?= htmlspecialchars($occasion['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Gift Message</label>
                    <textarea class="form-control" name="gift_message" rows="3" 
                              placeholder="Add a personal message to your gift"></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="notifyRecipient" 
                               name="notify_recipient" checked>
                        <label class="form-check-label" for="notifyRecipient">
                            Notify recipient by email when gift is shipped
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="giftWrap" 
                               name="is_gift_wrapped">
                        <label class="form-check-label" for="giftWrap">
                            Add gift wrapping (+Ksh 5.00)
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Customization Section -->
    <div id="customizationOptions" class="card mb-4" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Customization Options</h5>
        </div>
        <div class="card-body">
            <!-- Dynamically populated based on selected product -->
        </div>
    </div>

    <script>
        document.getElementById('isGift').addEventListener('change', function() {
            const giftOptions = document.getElementById('giftOptions');
            giftOptions.style.display = this.checked ? 'block' : 'none';
            
            // Toggle required attributes on gift fields
            const giftFields = giftOptions.querySelectorAll('input, select, textarea');
            giftFields.forEach(field => {
                field.required = this.checked;
            });
        });

        // Function to load customization options when a product is selected
        function loadCustomizationOptions(productId) {
            fetch(`/api/customizations?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('customizationOptions');
                    if (data.customizations && data.customizations.length > 0) {
                        container.style.display = 'block';
                        // Render customization form fields
                        renderCustomizationFields(data.customizations);
                    } else {
                        container.style.display = 'none';
                    }
                });
        }

        // Add to your existing form submission handler
        document.querySelector('form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const isGift = formData.get('is_gift') === 'on';
            
            if (isGift) {
                // Add gift data to the order
                const giftData = {
                    recipient_email: formData.get('recipient_email'),
                    occasion_id: formData.get('occasion_id'),
                    gift_message: formData.get('gift_message'),
                    notify_recipient: formData.get('notify_recipient') === 'on',
                    is_gift_wrapped: formData.get('is_gift_wrapped') === 'on'
                };
                
                // Add to your existing order data
                orderData.is_gift = true;
                orderData.gift_details = giftData;
            }
            
            try {
                const response = await fetch('/api/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getToken()}`
                    },
                    body: JSON.stringify(orderData)
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    showNotification('Order created successfully');
                    // Redirect to order details page
                    window.location.href = `view_order.php?id=${result.order_id}`;
                }
            } catch (error) {
                showError('Failed to create order');
            }
        });
    </script>
</body>
</html>
