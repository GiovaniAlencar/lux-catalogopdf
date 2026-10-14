<?php
/**
 * Verificação de autenticação para páginas do dashboard.
 * Inclua no início de cada página que exija login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario_email'])) {
    header('Location: login.php?redirecionar=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
