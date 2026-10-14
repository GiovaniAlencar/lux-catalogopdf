<?php
/**
 * Aplica preços promocionais (preco_2 e preco_3) a partir de JSON ou Excel.
 * Salva backup dos preços antigos para restaurar depois.
 *
 * Uso (no servidor, pasta do catálogo):
 *   php atualizar_precos_promocao.php
 *   php atualizar_precos_promocao.php precos_promocao.json
 */

declare(strict_types=1);

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

    die("Não foi possível criar pasta de backup gravável.\n");
}

function parsePrecoBR(?string $valor): ?float
{
    if ($valor === null || trim($valor) === '') {
        return null;
    }

    $limpo = preg_replace('/[^\d,\.]/', '', trim($valor));
    if ($limpo === '') {
        return null;
    }

    if (str_contains($limpo, ',')) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    }

    return is_numeric($limpo) ? round((float) $limpo, 2) : null;
}

function lerPrecosDoJson(string $jsonPath): array
{
    $conteudo = file_get_contents($jsonPath);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível ler o arquivo JSON.');
    }

    // Remove BOM UTF-8 (comum em arquivos gerados no Windows)
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
    $conteudo = trim($conteudo);

    $dados = json_decode($conteudo, true);
    if (!is_array($dados)) {
        $erro = json_last_error_msg();
        throw new RuntimeException("JSON inválido ({$erro}).");
    }

    $lista = $dados['produtos'] ?? $dados;
    if (!is_array($lista)) {
        throw new RuntimeException('JSON sem lista de produtos.');
    }

    $precos = [];
    foreach ($lista as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = $item['id'] ?? $item['produto_id'] ?? null;
        $por = $item['por'] ?? $item['preco'] ?? null;

        if ($id === null || $por === null) {
            continue;
        }

        $id = (int) $id;
        $valor = is_numeric($por) ? round((float) $por, 2) : parsePrecoBR((string) $por);

        if ($id > 0 && $valor !== null) {
            $precos[$id] = $valor;
        }
    }

    if ($precos === []) {
        throw new RuntimeException('Nenhum produto válido encontrado no JSON.');
    }

    return $precos;
}

function colLetterToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;

    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return $index;
}

function lerPrecosDoExcel(string $excelPath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'Extensão ZipArchive não disponível. Use precos_promocao.json em vez de .xlsx.'
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($excelPath) !== true) {
        throw new RuntimeException('Não foi possível abrir o arquivo Excel.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        foreach ($shared->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } else {
                $texto = '';
                foreach ($si->r as $run) {
                    $texto .= (string) $run->t;
                }
                $sharedStrings[] = $texto;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Planilha sheet1 não encontrada no Excel.');
    }

    $sheet = simplexml_load_string($sheetXml);
    $grid = [];

    foreach ($sheet->sheetData->row as $row) {
        $rowNum = (int) $row['r'];

        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches)) {
                continue;
            }

            $col = colLetterToIndex($matches[1]);
            $type = (string) ($cell['t'] ?? '');
            $value = '';

            if ($type === 's') {
                $idx = (int) $cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif (isset($cell->v)) {
                $value = (string) $cell->v;
            } elseif (isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            }

            $grid[$rowNum][$col] = $value;
        }
    }

    $colPor = null;
    for ($col = 1; $col <= 10; $col++) {
        $header = strtolower(trim($grid[2][$col] ?? ''));
        if ($header === 'por') {
            $colPor = $col;
            break;
        }
    }

    if ($colPor === null) {
        throw new RuntimeException('Coluna "por" não encontrada na linha 2 do Excel.');
    }

    $precos = [];
    $maxRow = max(array_keys($grid));

    for ($row = 3; $row <= $maxRow; $row++) {
        $idRaw = trim($grid[$row][1] ?? '');
        if ($idRaw === '' || !ctype_digit($idRaw)) {
            continue;
        }

        $por = parsePrecoBR($grid[$row][$colPor] ?? null);
        if ($por === null) {
            continue;
        }

        $precos[(int) $idRaw] = $por;
    }

    if ($precos === []) {
        throw new RuntimeException('Nenhum produto válido encontrado no Excel.');
    }

    return $precos;
}

function lerPrecos(string $arquivo): array
{
    $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

    return match ($ext) {
        'json' => lerPrecosDoJson($arquivo),
        'xlsx' => lerPrecosDoExcel($arquivo),
        default => throw new RuntimeException('Formato não suportado. Use .json ou .xlsx'),
    };
}

function resolverArquivoEntrada(?string $informado): string
{
    if ($informado !== null && is_file($informado)) {
        return $informado;
    }

    $candidatos = [
        __DIR__ . '/precos_promocao.json',
        __DIR__ . '/Pasta1.json',
        __DIR__ . '/Pasta1.xlsx',
    ];

    foreach ($candidatos as $arquivo) {
        if (is_file($arquivo)) {
            return $arquivo;
        }
    }

    die("Arquivo não encontrado. Envie precos_promocao.json para /var/www/html/\n");
}

$arquivoEntrada = resolverArquivoEntrada($argv[1] ?? null);
$backupDir = resolverPastaBackup();

try {
    $pdoERP = new PDO('mysql:host=erp_db;port=3306;dbname=erp', 'root', 'B4ncOLUXErp!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('Erro na conexão com o ERP: ' . $e->getMessage() . "\n");
}

$precosNovos = lerPrecos($arquivoEntrada);
$ids = array_keys($precosNovos);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmtAtual = $pdoERP->prepare("
    SELECT id, preco_2, preco_3
    FROM produtos
    WHERE id IN ({$placeholders})
");
$stmtAtual->execute($ids);
$atuais = $stmtAtual->fetchAll(PDO::FETCH_ASSOC);

$mapAtual = [];
foreach ($atuais as $row) {
    $mapAtual[(int) $row['id']] = $row;
}

$backup = [
    'criado_em' => date('Y-m-d H:i:s'),
    'arquivo_origem' => basename($arquivoEntrada),
    'motivo' => 'promocao_pasta1',
    'produtos' => [],
];

$stmtUpdate = $pdoERP->prepare('UPDATE produtos SET preco_2 = ?, preco_3 = ? WHERE id = ?');

$atualizados = 0;
$naoEncontrados = [];

$pdoERP->beginTransaction();

try {
    foreach ($precosNovos as $id => $novoPreco) {
        if (!isset($mapAtual[$id])) {
            $naoEncontrados[] = $id;
            continue;
        }

        $backup['produtos'][] = [
            'id' => $id,
            'preco_2' => (float) $mapAtual[$id]['preco_2'],
            'preco_3' => (float) $mapAtual[$id]['preco_3'],
            'preco_promocional' => $novoPreco,
        ];

        $stmtUpdate->execute([$novoPreco, $novoPreco, $id]);
        $atualizados++;
    }

    $backupFile = $backupDir . '/precos_backup_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents(
        $backupFile,
        json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    $pdoERP->commit();
} catch (Throwable $e) {
    $pdoERP->rollBack();
    die('Erro ao atualizar preços: ' . $e->getMessage() . "\n");
}

echo "Arquivo lido: {$arquivoEntrada}\n";
echo "Preços promocionais aplicados com sucesso.\n";
echo "Produtos atualizados: {$atualizados}\n";
echo "Backup salvo em: {$backupFile}\n";

if ($naoEncontrados !== []) {
    echo 'IDs não encontrados no ERP: ' . implode(', ', $naoEncontrados) . "\n";
}

echo "\nPara voltar aos preços antigos amanhã:\n";
echo "  php restaurar_precos_promocao.php \"{$backupFile}\"\n";
