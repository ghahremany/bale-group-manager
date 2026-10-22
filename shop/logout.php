<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
Auth::boot();
ShopAuth::logout();
redirect('shop/login.php');
