<?php
/**
 * Router script for PHP built-in server
 * Handles proper 404 responses for non-existent files
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . '/public' . $requestUri;

// If the file exists, let the server handle it normally
if (is_file($filePath)) {
    return false; // Let PHP serve the file
}

// If it's a directory, check for index.html
if (is_dir($filePath)) {
    $indexFile = $filePath . '/index.html';
    if (is_file($indexFile)) {
        return false; // Let PHP serve the directory
    }
}

// File doesn't exist - return 404
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Static Forge</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 50px 20px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 20px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        h2 {
            font-size: 1.5rem;
            margin: 0 0 30px 0;
            opacity: 0.9;
        }
        p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.8;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .url {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page <span class="url">' . htmlspecialchars($requestUri) . '</span> could not be found.</p>
        <p>It may have been moved, deleted, or you may have entered the wrong URL.</p>
        <a href="/">← Back to Home</a>
    </div>
</body>
</html>';

exit;