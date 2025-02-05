<?php
function handlePageNotFound() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Page Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5 text-center">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h1 class="display-1 text-muted">404</h1>
                    <h2 class="mb-4">Page Not Found</h2>
                    <p class="lead mb-4">The page you're looking for is under construction or doesn't exist.</p>
                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
