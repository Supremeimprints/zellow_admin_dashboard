<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
        }
        .error-content {
            text-align: center;
            padding: 2rem;
        }
        .error-number {
            font-size: 8rem;
            font-weight: 700;
            color: #dc3545;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .error-text {
            font-size: 1.5rem;
            color: #495057;
            margin-bottom: 2rem;
        }
        .home-button {
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .home-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .error-image {
            width: 100%;
            max-width: 300px;
            max-height: 250px;
            height: auto;
            object-fit: contain;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        @media (max-width: 768px) {
            .error-image {
                max-width: 200px;
                max-height: 180px;
            }
        }

        @media (max-width: 480px) {
            .error-image {
                max-width: 150px;
                max-height: 140px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <img src="assets/images/404bot.png" alt="404 Error" class="error-image">
                    
            <div class="error-number">404</div>
            <div class="error-text mb-4">Oops! Page Not Found</div>
            <p class="text-muted mb-4">
                The page you are looking for might have been removed, had its name changed, 
                or is temporarily unavailable.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="javascript:history.back()" class="btn btn-outline-secondary home-button">
                    <i class="fas fa-arrow-left me-2"></i>Go Back
                </a>
                <a href="index.php" class="btn btn-primary home-button">
                    <i class="fas fa-home me-2"></i>Home Page
                </a>
            </div>
        </div>
    </div>
</body>
</html>

                </a>
            </div>
        </div>
    </div>
</body>
</html>
