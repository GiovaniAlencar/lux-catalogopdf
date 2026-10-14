<?php
/**
 * Restaura preco_2 e preco_3 a partir de um backup gerado por atualizar_precos_promocao.php
 *
 * Uso:
 *   php restaurar_precos_promocao.php
 *   php restaurar_precos_promocao.php backups/precos_backup_2026-07-28_08-00-00.json
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/lib/config.php';

header('Content-Type: text/plain; charset=utf-8');

function resolverPastaBackup(): string
{
    $candidatos = [
        __DIR__ . '/uploads/backups',
        __DIR__ . '/backups',
        sys_get_temp_dir() . '/lux-perfumes-backups',
    ];

    foreach ($candidatos as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }

        if (!is_dir($dir) && @mkdir($dir, 0755, true) && is_writable($dir)) {
            return $dir;
        }
    }

    die("Não foi possível encontrar pasta de backup gravável.\n");
}

$backupDir = resolverPastaBackup();
$backupPath = $argv[1] ?? null;

if ($backupPath === null) {
    $files = glob($backupDir . '/precos_backup_*.json') ?: [];
    if ($files === []) {
        die("Nenhum backup encontrado em {$backupDir}\n");
    }

    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    $backupPath = $files[0];
    echo "Usando backup mais recente: {$backupPath}\n\n";
}

if (!is_file($backupPath)) {
    die("Arquivo de backup não encontrado: {$backupPath}\n");
}

$backup = json_decode(file_get_contents($backupPath), true);
if (!is_array($backup) || empty($backup['produtos'])) {
    die("Backup inválido ou vazio: {$backupPath}\n");
}

try {
    $pdoERP = luxDb('erp');
} catch (PDOException $e) {
    die('Erro na conexão com o ERP: ' . $e->getMessage() . "\n");
}

$stmt = $pdoERP->prepare('UPDATE produtos SET preco_2 = ?, preco_3 = ? WHERE id = ?');
$restaurados = 0;

$pdoERP->beginTransaction();

try {
    foreach ($backup['produtos'] as $produto) {
        $stmt->execute([
            (float) $produto['preco_2'],
            (float) $produto['preco_3'],
            (int) $produto['id'],
        ]);
        $restaurados++;
    }

    $pdoERP->commit();
} catch (Throwable $e) {
    $pdoERP->rollBack();
    die('Erro ao restaurar preços: ' . $e->getMessage() . "\n");
}

echo "Preços restaurados com sucesso.\n";
echo "Produtos restaurados: {$restaurados}\n";
echo 'Backup utilizado: ' . basename($backupPath) . "\n";
echo 'Criado em: ' . ($backup['criado_em'] ?? 'desconhecido') . "\n";
