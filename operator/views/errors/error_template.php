<?php
$error_code = $error_code ?? '404';
$error_title = $error_title ?? 'Page Not Found';
$error_message = $error_message ?? 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= $error_code ?> - Alamex Portal</title>
    <link rel="stylesheet" href="/shared/assets/css/style.css">
    <style>
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .error-code {
            font-size: 72px;
            color: #e74c3c;
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 24px;
            color: #2c3e50;
            margin: 20px 0;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        .back-button {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .back-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code"><?= $error_code ?></h1>
        <h2 class="error-title"><?= $error_title ?></h2>
        <p class="error-message"><?= $error_message ?></p>
        <a href="/" class="back-button">Return to Home</a>
    </div>
</body>
</html> 