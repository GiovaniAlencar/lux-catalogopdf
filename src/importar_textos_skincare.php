<?php
/**
 * Preenche UMA VEZ a descrição dos produtos de Skin Care no catálogo com os textos do catálogo
 * feito à mão (lib/skincare_textos.php): "Principais funções", "Indicado para" e "Tamanho".
 * Primeiro mostra o que vai fazer; só grava ao clicar em "Gravar". Por padrão não mexe em
 * produto que já tem descrição.
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/CatalogoPdf.php';
require_once __DIR__ . '/lib/CatalogoSkincarePdf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_skincare'])) {
    $_SESSION['csrf_skincare'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['csrf_skincare'];

function normalizar(string $s): string
{
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    return ' ' . preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($s)) . ' ';
}

$textos = require __DIR__ . '/lib/skincare_textos.php';
$erro = null;
$linhas = [];
$gravados = null;
try {
    $erp = luxDb('erp');
    $cat = luxDb('catalogo');
    $cats = CatalogoSkincarePdf::categoriasErp($erp);
    if (!$cats) {
        throw new RuntimeException('Não achei a categoria de Skin Care no ERP (procurei "skin" no nome).');
    }
    $st = $erp->prepare('SELECT id FROM produtos WHERE categoria_id IN (' . implode(',', array_fill(0, count($cats), '?')) . ')');
    $st->execute($cats);
    $idsErp = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $produtos = [];
    if ($idsErp) {
        $st = $cat->prepare('SELECT id, id_erp, nome, descricao FROM products WHERE id_erp IN (' . implode(',', array_fill(0, count($idsErp), '?')) . ')');
        $st->execute($idsErp);
        $produtos = $st->fetchAll();
    }

    // casa cada texto com os produtos pelo nome
    $usados = [];
    foreach ($textos as $i => [$tem, $nao, $descricao]) {
        $achados = array_values(array_filter($produtos, function ($p) use ($tem, $nao) {
            $n = normalizar((string) $p['nome']);
            foreach ($tem as $w) {
                if (strpos($n, ' ' . $w) === false) {
                    return false;
                }
            }
            foreach ($nao as $w) {
                if (strpos($n, ' ' . $w) !== false) {
                    return false;
                }
            }
            return true;
        }));
        $linhas[] = ['i' => $i, 'chave' => implode(' + ', $tem), 'achados' => $achados, 'descricao' => $descricao];
        foreach ($achados as $a) {
            $usados[(int) $a['id']][] = $i;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página.');
        }
        $substituir = !empty($_POST['substituir']);
        $up = $cat->prepare('UPDATE products SET descricao = ? WHERE id = ?');
        $gravados = 0;
        foreach ($linhas as $l) {
            if (count($l['achados']) !== 1) {
                continue; // nenhum ou mais de um: não arrisca
            }
            $p = $l['achados'][0];
            if (count($usados[(int) $p['id']]) > 1) {
                continue; // o mesmo produto bateu com dois textos
            }
            if (trim((string) $p['descricao']) !== '' && !$substituir) {
                continue;
            }
            $up->execute([$l['descricao'], (int) $p['id']]);
            $gravados++;
        }
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Textos do Skin Care - Lux Catálogo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="h4 mb-1">Textos do catálogo de Skin Care</h1>
    <p class="text-muted">Preenche a descrição dos produtos com "Principais funções", "Indicado para" e "Tamanho" do catálogo feito à mão. Depois é só editar pela tela do produto.</p>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= $h($erro) ?></div>
    <?php endif; ?>
    <?php if ($gravados !== null): ?>
        <div class="alert alert-success"><?= (int) $gravados ?> produto(s) atualizado(s). <a href="gerar_catalogo_skincare.php" target="_blank">Gerar o PDF</a></div>
    <?php endif; ?>
    <?php if (!$erro): ?>
    <table class="table table-sm bg-white align-middle">
        <thead><tr><th>Texto (procura no nome)</th><th>Produto no catálogo</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($linhas as $l):
            $n = count($l['achados']);
            $p = $l['achados'][0] ?? null;
            $repetido = $p && count($usados[(int) $p['id']]) > 1;
            if ($n === 0) {
                [$cls, $sit] = ['table-warning', 'Não encontrado: confira o nome no catálogo'];
            } elseif ($n > 1 || $repetido) {
                [$cls, $sit] = ['table-warning', 'Bateu com mais de um: não mexo (preencha à mão)'];
            } elseif (trim((string) $p['descricao']) !== '') {
                [$cls, $sit] = ['', 'Já tem descrição: só troca se marcar "substituir"'];
            } else {
                [$cls, $sit] = ['table-success', 'Vai preencher'];
            }
        ?>
            <tr class="<?= $cls ?>">
                <td><code><?= $h($l['chave']) ?></code></td>
                <td><?= $n ? implode('<br>', array_map(fn ($a) => '#' . (int) $a['id'] . ' ' . $h($a['nome']), $l['achados'])) : '—' ?></td>
                <td><?= $h($sit) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" class="d-flex gap-3 align-items-center">
        <input type="hidden" name="token" value="<?= $h($token) ?>">
        <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="substituir" value="1"> substituir descrições que já existem</label>
        <button class="btn btn-dark">Gravar</button>
        <a href="index.php" class="btn btn-link">Voltar</a>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
