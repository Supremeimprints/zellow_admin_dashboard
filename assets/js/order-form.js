document.addEventListener('change', function(event) {
    // Special request toggle handler
    if (event.target.classList.contains('special-request-toggle')) {
        const options = event.target.closest('.special-requests-section')
                               .querySelector('.special-request-options');
        const serviceSelect = options.querySelector('.service-type');
        const serviceDetails = options.querySelector('.service-details');
        
        options.style.display = event.target.checked ? 'block' : 'none';
        
        if (!event.target.checked) {
            serviceSelect.value = '';
            serviceDetails.value = '';
        } else {
            serviceSelect.required = true;
            serviceDetails.required = true;
        }
        
        calculateTotal();
    }
    
    // Service type change handler
    if (event.target.classList.contains('service-type')) {
        calculateTotal();
    }
});

// Update calculateTotal function to include special services
function calculateTotal() {
    let subtotal = 0;
    let specialServicesTotal = 0;
    let giftWrapTotal = 0;
    
    document.querySelectorAll('.product-item').forEach(item => {
        const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(item.querySelector('.unit-price-input').value) || 0;
        
        // Base product cost
        subtotal += (quantity * price);
        
        // Special services cost
        const specialRequestToggle = item.querySelector('.special-request-toggle');
        if (specialRequestToggle?.checked) {
            const serviceType = item.querySelector('.service-type').value;
            const serviceCost = serviceType === 'engraving' ? 500 : 
                              serviceType === 'printing' ? 300 : 0;
            specialServicesTotal += (serviceCost * quantity);
        }
        
        // Gift wrap cost (existing calculation)
        // ...
    });
    
    // Update display
    updateTotalsDisplay(subtotal, specialServicesTotal, giftWrapTotal);
}
