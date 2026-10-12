<?php
// Minimal PSR-4 autoloader for bundled libraries (chillerlan/php-qrcode, MIT)
spl_autoload_register(function ($class) {
    $map = [
        'chillerlan\\QRCode\\'   => __DIR__ . '/chillerlan/php-qrcode/',
        'chillerlan\\Settings\\' => __DIR__ . '/chillerlan/php-settings-container/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
});
