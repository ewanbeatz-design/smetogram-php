<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

if (current_user()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
