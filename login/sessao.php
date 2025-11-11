<?php
declare(strict_types=1);

// Inicia a sessão em todas as páginas que incluírem este arquivo.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário está logado checando a existência do user_id na sessão.
 *
 * @return bool True se o usuário estiver logado, false caso contrário.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Verifica se o usuário logado é um administrador.
 *
 * @return bool True se o usuário for admin, false caso contrário.
 */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? 'client') === 'admin';
}

/**
 * Verifica se o usuário logado é um cliente.
 *
 * @return bool True se o usuário for cliente, false caso contrário.
 */
function is_client(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? 'client') === 'client';
}