<?php
$regionRates = getRegionRates($db, $region['id']);
$ratesMap = array_column($regionRates, null, 'shipping_method_id');
?>

<form method="POST" class="shipping-rates-form">
    <input type="hidden" name="region_id" value="<?= $region['id'] ?>">
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Shipping Method</th>
                    <th>Base Rate (Ksh)</th>
                    <th>Per Additional Item (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shippingMethods as $method): 
                    $rate = $ratesMap[$method['id']] ?? ['base_rate' => 0, 'per_item_fee' => 0];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($method['display_name']) ?></td>
                        <td>
                            <input type="number" 
                                   name="rates[<?= $method['id'] ?>][base]" 
                                   class="form-control form-control-sm"
                                   value="<?= $rate['base_rate'] ?>"
                                   step="0.01" min="0" required>
                        </td>
                        <td>
                            <input type="number" 
                                   name="rates[<?= $method['id'] ?>][per_item]" 
                                   class="form-control form-control-sm"
                                   value="<?= $rate['per_item_fee'] ?>"
                                   step="0.01" min="0" required>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="text-end mt-2">
        <button type="submit" name="update_region_rates" class="btn btn-primary btn-sm">
            Update Rates
        </button>
    </div>
</form>
