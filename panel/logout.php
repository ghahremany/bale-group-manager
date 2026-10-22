<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();
$admin = Auth::current();
if ($admin) {
    Logger::audit((int)$admin['id'], (string)$admin['username'], 'logout', 'panel');
}
Auth::logout();
redirect('panel/login.php');
