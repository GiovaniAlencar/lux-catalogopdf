<?php
require_once __DIR__ . '/auth_check.php';
header('Content-Type: text/html; charset=utf-8');

require 'vendor/autoload.php';

try {
    $pdoCatalogo = new PDO('mysql:host=localhost;dbname=luxcatalogo;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoERP = new PDO('mysql:host=localhost;dbname=luxerp;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}

$queryERP = "
    SELECT e.produto_id, p.valor_venda, e.quantidade
    FROM estoques e
    JOIN produtos p ON p.id = e.produto_id
    WHERE e.quantidade > 0 AND p.categoria_id = 3
";
$stmtERP = $pdoERP->query($queryERP);
$produtosERP = $stmtERP->fetchAll(PDO::FETCH_ASSOC);

if (empty($produtosERP)) {
    die('Nenhum produto disponível para o catálogo.');
}

$idsERP = array_column($produtosERP, 'produto_id');
$placeholders = implode(',', array_fill(0, count($idsERP), '?'));

$queryCatalogo = "
    SELECT p.id_erp, p.nome, p.descricao, p.sexo, p.marca, p.imagem
    FROM products p
    WHERE p.id_erp IN ($placeholders)
";
$stmtCatalogo = $pdoCatalogo->prepare($queryCatalogo);
$stmtCatalogo->execute($idsERP);
$produtosCatalogo = $stmtCatalogo->fetchAll(PDO::FETCH_ASSOC);

$catalogoPorIdErp = [];
foreach ($produtosCatalogo as $produtoCatalogo) {
    $catalogoPorIdErp[(string) $produtoCatalogo['id_erp']] = $produtoCatalogo;
}

$produtosCompletos = [];
foreach ($produtosERP as $produtoERP) {
    $idErp = (string) $produtoERP['produto_id'];

    if (!isset($catalogoPorIdErp[$idErp])) {
        continue;
    }

    $produtosCompletos[] = array_merge($catalogoPorIdErp[$idErp], $produtoERP);
}

function agruparPorMarca(array $produtos): array
{
    $agrupado = [];

    foreach ($produtos as $produto) {
        $marca = trim((string) ($produto['marca'] ?? ''));
        $marca = $marca !== '' ? $marca : 'Sem marca';
        $agrupado[$marca][] = $produto;
    }

    foreach ($agrupado as $marca => $produtosDaMarca) {
        usort($produtosDaMarca, static function ($a, $b) {
            return strcasecmp(
                trim((string) ($a['descricao'] ?? '')),
                trim((string) ($b['descricao'] ?? ''))
            );
        });

        $agrupado[$marca] = $produtosDaMarca;
    }

    uksort($agrupado, static function ($a, $b) {
        if ($a === 'Sem marca') {
            return 1;
        }
        if ($b === 'Sem marca') {
            return -1;
        }
        return strcasecmp($a, $b);
    });

    return $agrupado;
}

function textoPdf(string $texto): string
{
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class PDFIndiceMarcas extends FPDF
{
    public bool $mostrarCabecalho = false;

    public function Header()
    {
        if (!$this->mostrarCabecalho) {
            return;
        }

        if (file_exists('uploads/logomarca.png')) {
            $this->Image('uploads/logomarca.png', 10, 8, 22);
        }

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, textoPdf('Catálogo Árabes com Índice'), 0, 1, 'C');
        $this->Ln(4);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, textoPdf('Página ') . $this->PageNo(), 0, 0, 'C');
    }

    public function addIndice(array $produtosPorMarca, array $links): void
    {
        $this->AddPage();
        $this->SetFillColor(31, 60, 136);
        $this->Rect(0, 0, 210, 42, 'F');

        if (file_exists('uploads/logomarca.png')) {
            $this->Image('uploads/logomarca.png', 12, 8, 24);
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetXY(40, 10);
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 8, textoPdf('Catálogo Árabes com Índice'), 0, 1);
        $this->SetX(40);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, textoPdf('Clique em uma marca para ir direto à seção.'), 0, 1);

        $this->SetTextColor(0, 0, 0);
        $this->Ln(14);

        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, textoPdf('Atualizado em ' . date('d/m/Y') . ' | Marcas: ' . count($produtosPorMarca) . ' | Produtos: ' . array_sum(array_map('count', $produtosPorMarca))), 0, 1);
        $this->Ln(4);

        $colunas = 4;
        $margemX = 10;
        $espaco = 4;
        $larguraBotao = (190 - (($colunas - 1) * $espaco)) / $colunas;
        $alturaBotao = 12;
        $colunaAtual = 0;

        foreach ($produtosPorMarca as $marca => $produtosDaMarca) {
            if ($this->GetY() + $alturaBotao > 280) {
                $this->AddPage();
                $this->Ln(10);
                $colunaAtual = 0;
            }

            $x = $margemX + ($colunaAtual * ($larguraBotao + $espaco));
            $y = $this->GetY();

            $this->SetXY($x, $y);
            $this->SetFillColor(237, 242, 247);
            $this->SetDrawColor(31, 60, 136);
            $this->SetTextColor(31, 60, 136);
            $this->SetFont('Arial', 'B', 9);

            $titulo = $marca . ' (' . count($produtosDaMarca) . ')';
            if (mb_strlen($titulo, 'UTF-8') > 24) {
                $titulo = mb_substr($titulo, 0, 21, 'UTF-8') . '...';
            }

            $this->Cell($larguraBotao, $alturaBotao, textoPdf($titulo), 1, 0, 'C', true, $links[$marca]);

            $colunaAtual++;

            if ($colunaAtual >= $colunas) {
                $colunaAtual = 0;
                $this->Ln($alturaBotao + 4);
            }
        }

        if ($colunaAtual !== 0) {
            $this->Ln($alturaBotao + 4);
        }
    }

    public function addMarcaTitulo(string $marca, int $link): void
    {
        if ($this->GetY() > 240) {
            $this->AddPage();
        }

        $this->SetLink($link, $this->GetY(), $this->PageNo());
        $this->Ln(3);
        $this->SetFillColor(31, 60, 136);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, textoPdf($marca), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    public function addProduto(array $produto, int $index): void
    {
        $alturaMinima = 48;
        $xTexto = 55;
        $larguraTexto = 135;

        $this->SetFont('Arial', 'B', 12);
        $alturaTitulo = 7;
        $this->SetFont('Arial', '', 11);
        $alturaPreco = 6;
        $this->SetFont('Arial', '', 8);
        $descricao = textoPdf((string) ($produto['descricao'] ?? ''));
        $descricaoTruncada = $this->truncarDescricao($descricao, 3, 92);
        $alturaDescricao = $this->getMultiCellHeight($larguraTexto, 4.5, $descricaoTruncada);
        $alturaBloco = max($alturaMinima, $alturaTitulo + $alturaPreco + 8 + $alturaDescricao);

        if ($this->GetY() + $alturaBloco > 275) {
            $this->AddPage();
        }

        $yInicial = $this->GetY();
        $corFundo = ($index % 2 === 0) ? [255, 255, 255] : [242, 245, 249];
        $this->SetFillColor($corFundo[0], $corFundo[1], $corFundo[2]);
        $this->Rect(10, $yInicial, 190, $alturaBloco, 'F');
        $this->SetFillColor(31, 60, 136);
        $this->Rect(10, $yInicial, 5, $alturaBloco, 'F');

        if (!empty($produto['imagem'])) {
            $caminhoImagem = 'uploads/' . $produto['imagem'];
            if (file_exists($caminhoImagem)) {
                $this->Image($caminhoImagem, 18, $yInicial + 6, 28, 28);
            }
        }

        $this->SetXY($xTexto, $yInicial + 5);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, $alturaTitulo, textoPdf(mb_strtoupper((string) $produto['nome'], 'UTF-8')), 0, 1);

        $this->SetX($xTexto);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, $alturaPreco, 'R$ ' . number_format((float) $produto['valor_venda'], 2, ',', '.'), 0, 1);

        $this->SetX($xTexto);
        $this->SetFont('Arial', '', 8);
        $sexo = ucfirst((string) ($produto['sexo'] ?? ''));
        $estoque = (int) ($produto['quantidade'] ?? 0);
        $this->Cell(0, 5, textoPdf('Sexo: ' . $sexo . ' | Estoque: ' . $estoque), 0, 1);

        $this->SetX($xTexto);
        $this->MultiCell($larguraTexto, 4.5, $descricaoTruncada, 0, 'L');
        $this->SetY($yInicial + $alturaBloco + 3);
    }

    private function truncarDescricao(string $texto, int $maxLinhas, int $larguraAproximada): string
    {
        $larguraWrap = max(1, (int) round($larguraAproximada / 3));
        $linhas = explode("\n", wordwrap($texto, $larguraWrap, "\n"));

        if (count($linhas) <= $maxLinhas) {
            return $texto;
        }

        return implode("\n", array_slice($linhas, 0, $maxLinhas)) . '...';
    }

    private function getMultiCellHeight(float $w, float $h, string $txt): float
    {
        $clone = clone $this;
        $clone->SetXY(0, 0);
        $clone->MultiCell($w, $h, $txt);
        $altura = $clone->GetY();
        unset($clone);
        return $altura;
    }
}

$produtosPorMarca = agruparPorMarca($produtosCompletos);
$linksPorMarca = [];

foreach ($produtosPorMarca as $marca => $_) {
    $linksPorMarca[$marca] = 0;
}

$pdf = new PDFIndiceMarcas();
$pdf->SetAutoPageBreak(true, 18);

foreach ($produtosPorMarca as $marca => $_) {
    $linksPorMarca[$marca] = $pdf->AddLink();
}

$pdf->addIndice($produtosPorMarca, $linksPorMarca);
$pdf->mostrarCabecalho = true;
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$indexGlobal = 0;
foreach ($produtosPorMarca as $marca => $produtosDaMarca) {
    $pdf->addMarcaTitulo($marca, $linksPorMarca[$marca]);

    foreach ($produtosDaMarca as $produto) {
        $pdf->addProduto($produto, $indexGlobal);
        $indexGlobal++;
    }
}

$pdf->Output('I', 'catalogo_arabes_com_indice.pdf');
