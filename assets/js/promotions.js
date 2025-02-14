// Campaign management functions
function editCampaign(campaign) {
    // Fill edit modal with campaign data
    document.getElementById('edit_campaign_id').value = campaign.campaign_id;
    document.getElementById('edit_status').value = campaign.status;
    document.getElementById('edit_budget').value = campaign.budget;
    document.getElementById('edit_end_date').value = campaign.end_date;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('editCampaignModal')).show();
}

// Coupon management functions
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert('Coupon code copied: ' + code);
    });
}

// Form validations
document.addEventListener('DOMContentLoaded', function() {
    // Campaign form validation
    const campaignForm = document.getElementById('newCampaignForm');
    if (campaignForm) {
        campaignForm.addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
            }
        });
    }

    // Initialize feather icons
    feather.replace();
});
