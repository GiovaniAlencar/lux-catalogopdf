<?php
require_once __DIR__ . '/auth_check.php';
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL & ~E_DEPRECATED);
require 'vendor/autoload.php';

// =====================
// CONEXÕES
// =====================
try {
    $pdoCatalogo = new PDO('mysql:host=catalogo_db;dbname=catalogo;charset=utf8', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdoERP = new PDO('mysql:host=erp_db;port=3306;dbname=erp', 'root', 'B4ncOLUXErp!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('Erro na conexão: ' . $e->getMessage());
}

// =====================
// ERP PRODUTOS
// =====================
$queryERP = "
    SELECT e.produto_id, p.valor_venda, p.valor_compra, e.quantidade
    FROM estoques e
    JOIN produtos p ON p.id = e.produto_id
    WHERE e.quantidade > 0 AND p.categoria_id = 8
";

$stmtERP = $pdoERP->query($queryERP);
$produtosERP = $stmtERP->fetchAll(PDO::FETCH_ASSOC);

if (empty($produtosERP)) {
    die('Nenhum produto disponível para catálogo.');
}

// =====================
// REGRA DE PREÇO
// =====================
function calcularPrecos($produto)
{
    $custo = $produto['valor_compra'];
    $id = (int)$produto['produto_id'];

    if (in_array($id, [22,20,17,148,571,351,79,122,730], true)) {
        $preco2 = ceil(($custo * 1.30) + 2);
        $preco3 = ceil(($custo * 1.25) + 2);
    } else {
        $preco2 = ceil(($custo * 1.25) + 2);
        $preco3 = ceil(($custo * 1.20) + 2);

        $venda = $produto['valor_venda'];

        if ($venda < $preco2) $preco2 = $venda;
        if ($venda < $preco3) $preco3 = $venda;
    }

    return [$preco2, $preco3];
}

// =====================
// UPDATE BANCO (CORRIGIDO)
// =====================
$updateStmt = $pdoERP->prepare("
    UPDATE produtos
    SET preco_2 = :p2,
        preco_3 = :p3
    WHERE id = :id
");

// aplica cálculo + salva
foreach ($produtosERP as &$p) {

    [$p2, $p3] = calcularPrecos($p);

    $p['preco_2'] = $p2;
    $p['preco_3'] = $p3;

    $updateStmt->execute([
        ':p2' => $p2,
        ':p3' => $p3,
        ':id' => $p['produto_id']
    ]);
}
unset($p);

// =====================
// CATÁLOGO
// =====================
$idsERP = array_column($produtosERP, 'produto_id');
$placeholders = implode(',', array_fill(0, count($idsERP), '?'));

$stmtCatalogo = $pdoCatalogo->prepare("
    SELECT p.id_erp, p.nome, p.descricao, p.sexo, p.marca, p.imagem
    FROM products p
    WHERE p.id_erp IN ($placeholders)
");

$stmtCatalogo->execute($idsERP);
$produtosCatalogo = $stmtCatalogo->fetchAll(PDO::FETCH_ASSOC);

// MAP
$catalogoMap = [];
foreach ($produtosCatalogo as $c) {
    $catalogoMap[$c['id_erp']] = $c;
}

// MERGE
$produtosCompletos = [];

foreach ($produtosERP as $erp) {
    $id = $erp['produto_id'];

    if (isset($catalogoMap[$id])) {
        $produtosCompletos[] = array_merge($catalogoMap[$id], $erp);
    }
}

// =====================
// AGRUPAMENTO
// =====================
function agrupar($produtos)
{
    $agrupado = ['feminino' => [], 'masculino' => [], 'unissex' => []];

    foreach ($produtos as $p) {
        $sexo = strtolower($p['sexo']);
        if (!isset($agrupado[$sexo])) continue;

        $marca = trim($p['marca'] ?? '');
        $agrupado[$sexo][$marca][] = $p;
    }

    return $agrupado;
}

// =====================
// PDF
// =====================
class PDF extends FPDF
{
    function Header()
    {
        if ($this->PageNo() == 1) {
            $this->Image('uploads/logomarca.png', 10, 6, 30);
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('Catálogo de Perfumes'), 0, 1, 'C');
            $this->Ln(15);
        }
    }

    function AddProduct($produto)
    {
        $xImg = 18;
        $xTxt = 55;

        $this->SetFillColor(255,255,255);
        $this->Rect(10, $this->GetY(), 190, 55, 'F');

        if (!empty($produto['imagem'])) {
            $img = 'uploads/' . $produto['imagem'];
            if (file_exists($img)) {
                $this->Image($img, $xImg, $this->GetY()+8, 35, 35);
            }
        }

        $this->SetXY($xTxt, $this->GetY()+5);
        $this->SetFont('Arial','B',12);
        $this->Cell(0,6,utf8_decode($produto['nome']),0,1);

        $this->SetX($xTxt);
        $this->SetFont('Arial','',11);
        $this->Cell(0,6,'R$ '.number_format($produto['valor_venda'],2,',','.'),0,1);

        $this->SetX($xTxt);
        $this->SetFont('Arial','',9);
        $this->Cell(0,5,'Preço 2: R$ '.number_format($produto['preco_2'],2,',','.'),0,1);

        $this->SetX($xTxt);
        $this->Cell(0,5,'Preço 3: R$ '.number_format($produto['preco_3'],2,',','.'),0,1);

        $this->Ln(5);
    }
}

// =====================
// GERAR PDF
// =====================
$pdf = new PDF();
$pdf->AddPage();

$agrupado = agrupar($produtosCompletos);

foreach ($agrupado as $sexo => $marcas) {
    foreach ($marcas as $marca => $produtos) {
        foreach ($produtos as $p) {
            $pdf->AddProduct($p);
        }
    }
}

$pdf->Output();