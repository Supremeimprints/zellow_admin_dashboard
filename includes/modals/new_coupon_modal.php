<div class="modal fade" id="newCouponModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Coupon Code (Optional)</label>
                        <input type="text" name="code" class="form-control" placeholder="Leave blank for auto-generated code">
                        <small class="text-muted">If left blank, a unique code will be generated</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Type</label>
                        <select name="discount_type" class="form-select" required>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Value</label>
                        <input type="number" name="discount_value" class="form-control" step="0.01" required>
                        <small class="text-muted">For percentage, enter value between 1-100</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Minimum Order Amount</label>
                        <input type="number" name="min_order_amount" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expiration Date</label>
                                <input type="date" name="expiration_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total Usage Limit</label>
                                <input type="number" name="usage_limit_total" class="form-control" value="0">
                                <small class="text-muted">0 for unlimited</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usage Limit Per User</label>
                                <input type="number" name="usage_limit_per_user" class="form-control" value="0">
                                <small class="text-muted">0 for unlimited</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_coupon" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>
