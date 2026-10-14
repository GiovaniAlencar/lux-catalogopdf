<?php
/** Configuração e conexões (sem senha no código: ficam em config.local.php). */

function luxConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $arquivo = dirname(__DIR__) . '/config.local.php';
        if (!is_file($arquivo)) {
            http_response_code(500);
            exit('Configuração ausente: crie src/config.local.php a partir de config.local.example.php');
        }
        $cfg = require $arquivo;
    }
    return $cfg;
}

function luxDb(string $nome): PDO
{
    static $pool = [];
    if (!isset($pool[$nome])) {
        $c = luxConfig()[$nome];
        $pool[$nome] = new PDO(
            "mysql:host={$c['host']};dbname={$c['db']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pool[$nome];
}
