<?php
// Get current year
$currentYear = date('Y');
$version = '1.2.2'; // Store version number for easy updates
?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global CSRF token
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// AJAX helper function
function sendRequest(url, data, callback) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(callback)
    .catch(error => console.error('Error:', error));
}
</script>
</body>
<footer class="footer" style="padding: 20px 0; text-align: center; color: #666; font-size: 14px; margin-top: auto;">
    <div class="container">
        <p style="margin: 0;">
            © <?php echo $currentYear; ?> Zellow Enterprises. All Rights Reserved.<br>
            Version <?php echo $version; ?>
        </p>
    </div>
</footer>
</html>
