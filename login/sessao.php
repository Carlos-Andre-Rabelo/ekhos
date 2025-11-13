<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? 'client') === 'admin';
}

function is_client(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? 'client') === 'client';
}