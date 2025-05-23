<?php

$USER_ROUTES = [
    "/GCO/frontend/index.php",
    "/GCO/frontend/home.php",
    "/GCO/frontend/User_Profile.php",
    "/GCO/frontend/cart.php",
    "/GCO/frontend/"  // Non-PHP route
];

// Separate .php routes and exact paths
$phpRoutes = array_filter($USER_ROUTES, fn($r) => str_ends_with($r, '.php'));
$exactRoutes = array_filter($USER_ROUTES, fn($r) => !str_ends_with($r, '.php'));

// Extract just the PHP filenames
$routePatternParts = array_map(function ($route) {
    return preg_quote(basename($route), '/');
}, $phpRoutes);
$routePattern = implode('|', $routePatternParts);

// Start building .htaccess content
$htaccess = "# RewriteEngine On\n\n";

// Allow exact non-PHP routes
foreach ($exactRoutes as $route) {
    $htaccess .= "RewriteCond %{REQUEST_URI} ^" . preg_quote($route, '/') . "$ [NC]\n";
    $htaccess .= "RewriteRule ^ - [L]\n\n";
}

// Allow user-defined .php routes
if ($routePattern) {
    $htaccess .= "RewriteCond %{REQUEST_URI} ^/GCO/frontend/($routePattern)\\.php$ [NC]\n";
    $htaccess .= "RewriteRule ^ - [L]\n\n";
}

// Route everything else to index.php
$htaccess .= "RewriteRule ^ index.php [QSA,L]\n";

// Write the file
file_put_contents(__DIR__ . '/.htaccess', $htaccess);

// echo "<pre>{$_SERVER['REQUEST_URI']}\n.htaccess written:\n\n$htaccess</pre>";

header("Location: /GCO/frontend/home.php");
exit();

