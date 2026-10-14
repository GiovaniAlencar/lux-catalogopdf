<?php
require_once __DIR__ . '/auth_check.php';
header('Content-Type: text/html; charset=utf-8');

require 'vendor/autoload.php';

// Configuração do banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=luxcatalogo;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}

// Verifica se há produtos selecionados
if (empty($_POST['produtos'])) {
    die('Nenhum produto selecionado.');
}

$produtos_selecionados = $_POST['produtos'];

// Recupera os produtos selecionados
$produtos = [];
foreach ($produtos_selecionados as $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $produtos[] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Função para agrupar produtos por sexo e marca em ordem alfabética
function agruparPorSexoEMarca($produtos)
{
    $agrupado = ['feminino' => [], 'masculino' => [], 'unissex' => []];
    
    foreach ($produtos as $produto) {
        $sexo = strtolower($produto['sexo']);
        $marca = trim($produto['marca'] ?? '');

        if (!isset($agrupado[$sexo])) {
            continue;
        }

        $agrupado[$sexo][$marca][] = $produto;
    }

    // Ordena as marcas alfabeticamente e garante que sem marca fique por último
    foreach ($agrupado as $sexo => $produtosPorMarca) {
        foreach ($produtosPorMarca as $marca => $produtosDaMarca) {
            usort($produtosDaMarca, function ($a, $b) {
                return strcasecmp(
                    trim($a['descricao'] ?? ''),
                    trim($b['descricao'] ?? '')
                );
            });

            $agrupado[$sexo][$marca] = $produtosDaMarca;
        }

        uksort($agrupado[$sexo], function ($a, $b) {
            if ($a === '') return 1; // Sem marca vai para o final
            if ($b === '') return -1;
            return strcasecmp($a, $b);
        });
    }

    return $agrupado;
}

// Classe PDF customizada
class PDF extends FPDF
{
    function Header()
    {
        if ($this->PageNo() == 1) {
            $this->Image('uploads/logomarca.png', 10, 6, 30);

            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('Catálogo de Perfumes'), 0, 1, 'C');
            $this->Ln(5);

            $this->SetFont('Arial', '', 10);

            $this->Image('uploads/whatsapp.png', 60, 20, 5);
            $this->SetXY(65, 20);
            $this->Cell(0, 5, '(83) 99945-9629', 0, 1);

            $this->Image('uploads/instagram.png', 120, 20, 5);
            $this->SetXY(125, 20);
            $this->Cell(0, 5, '@luxperfumesdistribuidora', 0, 1);

            $this->Ln(20);
        }
    }

    function Footer()
    {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);

        $this->Ln(10);
        $this->Cell(0, 10, 'Página ' . $this->PageNo() . ' | Atualizado em ' . date('d/m/Y'), 0, 0, 'C');
    }

    function AddCategoriaTitulo($titulo, $cor)
    {
        $alturaNecessaria = 100;

        if ($this->GetY() + $alturaNecessaria > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $this->Ln(5);
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor($cor[0], $cor[1], $cor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, mb_convert_encoding($titulo, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    function truncarDescricao($texto, $maxLinhas, $larguraTexto, $pdf)
    {
        $pdf->SetFont('Arial', '', 8);

        $linhas = explode("\n", wordwrap($texto, $larguraTexto / 3, "\n"));

        if (count($linhas) > $maxLinhas) {
            $textoTruncado = '';
            for ($i = 0; $i < $maxLinhas; $i++) {
                $textoTruncado .= $linhas[$i] . "\n";
            }
            $textoTruncado .= '...';
            return $textoTruncado;
        }

        return $texto;
    }

    function AddProduct($produto, $index, $corGuia)
    {
        $altura_produto = 50;
        if ($this->GetY() + $altura_produto > 270) {
            $this->AddPage();
        }

        $bgColor = ($index % 2 == 0) ? [255, 255, 255] : [230, 230, 230];
        $this->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
        $this->Rect(10, $this->GetY(), 190, 50, 'F');

        $this->SetFillColor($corGuia[0], $corGuia[1], $corGuia[2]);
        $this->Rect(10, $this->GetY(), 5, 50, 'F');

        if (!empty($produto['imagem'])) {
            $this->Image('uploads/' . $produto['imagem'], 18, $this->GetY() + 5, 35);
        }

        $this->SetXY(55, $this->GetY());
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, mb_convert_encoding($produto['nome'], 'ISO-8859-1', 'UTF-8'), 0, 1);

        $this->SetFont('Arial', '', 12);
        $this->SetX(55);
        $textoPreco = ($produto['preco'] !== null && $produto['preco'] !== '')
            ? 'R$ ' . number_format((float) $produto['preco'], 2, ',', '.')
            : 'Sem preco';
        $this->Cell(0, 7, $textoPreco, 0, 1);

        $this->SetX(55);
        $this->SetFont('Arial', '', 8);

        $descricao_truncada = $this->truncarDescricao(mb_convert_encoding($produto['descricao'], 'ISO-8859-1', 'UTF-8'), 3, 510, $this);
        $this->MultiCell(120, 5, $descricao_truncada, 0, 1);

        $this->Ln(5);
    }
}

// Criação do PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$produtos_agrupados = agruparPorSexoEMarca($produtos);

foreach (['feminino', 'masculino', 'unissex'] as $categoria) {
    if (!empty($produtos_agrupados[$categoria])) {
        $titulo = ucfirst($categoria) . 's';
        $corCategoria = $categoria === 'feminino' ? [255, 182, 193] :
            ($categoria === 'masculino' ? [0, 0, 0] : [135, 206, 235]);

        $pdf->AddCategoriaTitulo($titulo, $corCategoria);

        foreach ($produtos_agrupados[$categoria] as $marca => $produtosDaMarca) {
            // Adiciona título da marca
            // $pdf->SetFont('Arial', 'B', 12);
            // $pdf->SetFillColor(220, 220, 220);
            // $pdf->Cell(0, 7, $marca ?: 'Sem marca', 0, 1, 'L', true);
            // $pdf->Ln(2);

            // Adiciona produtos da marca
            foreach ($produtosDaMarca as $index => $produto) {
                $pdf->AddProduct($produto, $index, $corCategoria);
            }
        }
    }
}

$pdf->Output();
?>
