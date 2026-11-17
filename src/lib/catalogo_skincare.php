<?php
/**
 * Catálogo de Skin Care (incluído por gerar_catalogo_pdf.php?tipo=skincare).
 * - Produtos: categoria de Skin Care do ERP que estão cadastrados no catálogo (com ou sem estoque).
 * - Preço (valor de venda) e estoque: ERP. Nome, marca, foto e descrição: catálogo.
 * - "Principais funções", "Indicado para", "Tamanho" e "Linha": lidos da descrição
 *   (formato em CatalogoSkincarePdf::lerDescricao). Sem estoque: aparece "ESGOTADO".
 * Usa: $cfg, $marca e as funções de foto de gerar_catalogo_pdf.php.
 */
require_once __DIR__ . '/CatalogoSkincarePdf.php';

try {
    $erp = luxDb('erp');
    $cat = luxDb('catalogo');
    $cats = CatalogoSkincarePdf::categoriasErp($erp);
    if (!$cats) {
        exit('Não achei a categoria de Skin Care no ERP (procurei uma categoria com "skin" no nome).');
    }
    $ocultar = '';
    $tem = $erp->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'ocultar_site'")->fetchColumn();
    if ((int) $tem) {
        $ocultar = ' AND p.ocultar_site = 0';
    }
    // LEFT JOIN: produto sem estoque também entra (sai como ESGOTADO)
    $st = $erp->prepare('
        SELECT p.id, p.nome AS nome_erp, p.valor_venda, COALESCE(SUM(e.quantidade), 0) AS qtd
        FROM produtos p
        LEFT JOIN estoques e ON e.produto_id = p.id
        WHERE p.categoria_id IN (' . implode(',', array_fill(0, count($cats), '?')) . ")$ocultar
        GROUP BY p.id, p.nome, p.valor_venda
    ");
    $st->execute($cats);
    $erpRows = [];
    foreach ($st->fetchAll() as $r) {
        $erpRows[(int) $r['id']] = $r;
    }
    $produtos = [];
    if ($erpRows) {
        $ph = implode(',', array_fill(0, count($erpRows), '?'));
        // só o que está cadastrado no catálogo (é lá que ficam foto e descrição)
        $st = $cat->prepare("SELECT id_erp, nome, descricao, marca, imagem FROM products WHERE id_erp IN ($ph)");
        $st->execute(array_keys($erpRows));
        foreach ($st->fetchAll() as $c) {
            $id = (int) $c['id_erp'];
            $marcaProd = trim((string) $c['marca']) ?: 'Skin Care';
            $skin = CatalogoSkincarePdf::lerDescricao($c['descricao']);
            $linha = CatalogoSkincarePdf::linhaDe((string) $c['nome'], $skin['linha']);
            $produtos[] = $c + $erpRows[$id] + [
                'marca_nome' => $marcaProd,
                'skin' => $skin,
                'linha' => $linha,
                'titulo' => CatalogoSkincarePdf::nomeLimpo((string) $c['nome'], $marcaProd),
                'tamanho' => $skin['tamanho'] ?: CatalogoSkincarePdf::tamanhoDoNome((string) $c['nome']),
                'link' => linkProduto($marca, $id, (string) $c['nome'], $cfg),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[catalogo-pdf skincare] ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao buscar os produtos. Tente novamente.');
}
if (!$produtos) {
    exit('Nenhum produto de Skin Care cadastrado no catálogo.');
}

// marca → linha (na ordem de CatalogoSkincarePdf::LINHAS, "Outros" no fim) → nome
$ordemLinha = array_flip(array_column(CatalogoSkincarePdf::LINHAS, 0));
usort($produtos, function ($a, $b) use ($ordemLinha) {
    return strcasecmp($a['marca_nome'], $b['marca_nome'])
        ?: (($ordemLinha[$a['linha'][0]] ?? 999) <=> ($ordemLinha[$b['linha'][0]] ?? 999))
        ?: strcmp($a['linha'][0], $b['linha'][0])
        ?: strcasecmp($a['titulo'], $b['titulo']);
});
$grupos = [];
foreach ($produtos as $p) {
    $grupos[$p['marca_nome'] . '|' . $p['linha'][0]][] = $p;
}

// fotos maiores que nos outros catálogos (o card aqui é grande) e com fundo branco
$fotos = prepararFotos(array_column($produtos, 'imagem'), $cfg, 900, [255, 255, 255]);

$pdf = new CatalogoSkincarePdf('P', 'mm', 'A4');
$pdf->SetCompression(true);
$pdf->cfg = $cfg;
$pdf->usarMarca($marca, $cfg);
$pdf->titulo = 'Skin Care';
$pdf->data = date('d/m/Y');
$pdf->SetTitle(CatalogoPdf::t('Catálogo de Skin Care - LUX Perfumes'));
$pdf->SetAuthor('LUX Perfumes');
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(10, 10, 10);

$marcas = array_values(array_unique(array_column($produtos, 'marca_nome')));
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$pdf->CapaSkin(count($marcas) === 1 ? $marcas[0] : 'Skin Care', 'Catálogo de Skin Care', '- ' . $meses[(int) date('n')] . ' -');

foreach ($grupos as $lista) {
    $linha = $lista[0]['linha'];
    $pdf->PaginaLinha($linha);
    $pdf->etiqueta = (count($marcas) > 1 || $lista[0]['marca_nome'] !== 'Skin Care' ? $lista[0]['marca_nome'] . ' · ' : '') . $linha[0];
    foreach (array_chunk($lista, 2) as $par) {
        $pdf->AddPage();
        foreach ($par as $slot => $p) {
            $pdf->ProdutoSkin($p, $slot, count($par) === 1, $fotos[fotoNomeLimpo($p['imagem'])] ?? null);
        }
    }
}
$pdf->PaginaFinal();

if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output('I', 'catalogo-skincare-' . date('d-m-Y') . '.pdf');
