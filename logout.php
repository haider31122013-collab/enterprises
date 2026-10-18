<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

unset($_SESSION['user_id']);
set_flash('success', 'You have been logged out.');
redirect('index.php');
