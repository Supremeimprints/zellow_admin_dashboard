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
                        <label class="form-label">Coupon Code</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="code" pattern="[A-Z0-9-]+" 
                                   title="Uppercase letters, numbers and hyphens only" required>
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="this.previousElementSibling.value = '<?= generateCouponCode() ?>'">
                                Generate
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Percentage</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="discount" 
                                   min="1" max="100" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" name="expiration_date" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" name="create_coupon" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Coupon
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
