document.addEventListener('DOMContentLoaded', function() {
    // Initialize date range picker
    flatpickr("#date-range", {
        mode: "range",
        dateFormat: "Y-m-d",
        maxDate: "today"
    });

    // Add export functionality
    document.getElementById('export-csv').addEventListener('click', function() {
        window.location.href = 'export_invoices.php' + window.location.search;
    });
});