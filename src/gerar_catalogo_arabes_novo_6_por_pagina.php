<?php
require_once __DIR__ . '/auth_check.php';
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL & ~E_DEPRECATED);
require 'vendor/autoload.php';

// Configuração dos bancos de dados
try {
    $pdoCatalogo = new PDO('mysql:host=catalogo_db;dbname=catalogo;charset=utf8', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoERP = new PDO('mysql:host=erp_db;port=3306;dbname=erp', 'root', 'B4ncOLUXErp!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}

// Busca produtos no ERP com estoque > 0 e categoria_id = 3
$queryERP = "
    SELECT e.produto_id,
        p.valor_venda,
        p.preco_2,
        p.preco_3,
        e.quantidade
    FROM estoques e
    JOIN produtos p ON p.id = e.produto_id
    WHERE e.quantidade > 0 AND p.categoria_id = 3
";

$stmtERP = $pdoERP->query($queryERP);
$produtosERP = $stmtERP->fetchAll(PDO::FETCH_ASSOC);

if (empty($produtosERP)) {
    die('Nenhum produto disponível para o catálogo.');
}

// Busca informações complementares no catálogo
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

// Associa os produtos do ERP com os do catálogo
$produtosCompletos = [];
$catalogoMap = [];

foreach ($produtosCatalogo as $p) {
    $catalogoMap[$p['id_erp']] = $p;
}

foreach ($produtosERP as $produtoERP) {
    $id = $produtoERP['produto_id'];

    if (isset($catalogoMap[$id])) {
        $produtosCompletos[] = array_merge($catalogoMap[$id], $produtoERP);
    }
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
            if ($a === '') return 1;
            if ($b === '') return -1;
            return strcasecmp($a, $b);
        });
    }

    return $agrupado;
}

// Função para comprimir e redimensionar imagem
function comprimirImagem($caminhoOriginal, $largura = 70, $altura = 70, $qualidade = 60)
{
    // Cria pasta de cache se não existir
    $pastaCache = 'uploads/cache/';
    if (!is_dir($pastaCache)) {
        mkdir($pastaCache, 0755, true);
    }

    // Nome do arquivo em cache
    $nomeArquivo = pathinfo($caminhoOriginal, PATHINFO_FILENAME);
    $caminhoCache = $pastaCache . $nomeArquivo . '_' . $largura . 'x' . $altura . '_q' . $qualidade . '.jpg';

    // Se já existe em cache e é mais recente que o original, usa o cache
    if (file_exists($caminhoCache) && filemtime($caminhoCache) >= filemtime($caminhoOriginal)) {
        return $caminhoCache;
    }

    // Obtém informações da imagem original
    $info = getimagesize($caminhoOriginal);
    if (!$info) {
        return $caminhoOriginal; // Retorna original se não conseguir ler
    }

    $tipo = $info[2];

    // Carrega a imagem baseado no tipo (@ suprime warnings de perfil ICC incorreto)
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagemOriginal = @imagecreatefromjpeg($caminhoOriginal);
            break;
        case IMAGETYPE_PNG:
            $imagemOriginal = @imagecreatefrompng($caminhoOriginal);
            break;
        case IMAGETYPE_GIF:
            $imagemOriginal = @imagecreatefromgif($caminhoOriginal);
            break;
        case IMAGETYPE_WEBP:
            $imagemOriginal = @imagecreatefromwebp($caminhoOriginal);
            break;
        default:
            return $caminhoOriginal; // Tipo não suportado
    }

    if (!$imagemOriginal) {
        return $caminhoOriginal;
    }

    // Cria nova imagem redimensionada
    $imagemNova = imagecreatetruecolor($largura, $altura);

    // Preserva transparência para PNG (converte para fundo branco)
    $branco = imagecolorallocate($imagemNova, 255, 255, 255);
    imagefill($imagemNova, 0, 0, $branco);

    // Redimensiona com qualidade
    imagecopyresampled(
        $imagemNova,
        $imagemOriginal,
        0,
        0,
        0,
        0,
        $largura,
        $altura,
        imagesx($imagemOriginal),
        imagesy($imagemOriginal)
    );

    // Salva como JPEG comprimido
    imagejpeg($imagemNova, $caminhoCache, $qualidade);

    // Libera memória
    imagedestroy($imagemOriginal);
    imagedestroy($imagemNova);

    return $caminhoCache;
}

// Classe PDF customizada

// Classe PDF customizada - layout premium em grade 2x3 (6 produtos por página)
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFillColor(12, 12, 12);
        $this->Rect(0, 0, 210, 34, 'F');

        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(0.6);
        $this->Line(0, 2, 22, 0);
        $this->Line(188, 0, 210, 2);
        $this->Line(0, 33, 210, 33);

        if (file_exists('uploads/logomarca.png')) {
            $this->Image('uploads/logomarca.png', 9, 4, 25, 25);
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', '', 9);
        $this->SetXY(42, 6);
        $this->Cell(70, 5, utf8_decode('CATÁLOGO DE'), 0, 1, 'L');

        $this->SetTextColor(212, 175, 55);
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(42, 10);
        $this->Cell(78, 8, utf8_decode('PERFUMES'), 0, 1, 'L');

        $this->SetTextColor(230, 230, 230);
        $this->SetFont('Arial', '', 7);
        $this->SetXY(42, 20);
        $this->Cell(80, 5, utf8_decode('ÁRABES  •  IMPORTADOS  •  PREMIUM'), 0, 1, 'L');

        $this->SetDrawColor(212, 175, 55);
        $this->Line(126, 6, 126, 28);

        if (file_exists('uploads/whatsapp.png')) {
            $this->Image('uploads/whatsapp.png', 132, 7, 5, 5);
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', '', 8);
        $this->SetXY(139, 7);
        $this->Cell(62, 5, '(83) 99945-9629', 0, 1, 'L');

        if (file_exists('uploads/instagram.png')) {
            $this->Image('uploads/instagram.png', 132, 16, 5, 5);
        }
        $this->SetXY(139, 16);
        $this->Cell(67, 5, '@luxperfumesdistribuidora', 0, 1, 'L');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(38);
    }

    function Footer()
    {
        $this->SetY(-18);

        $this->SetFillColor(12, 12, 12);
        $this->Rect(0, $this->GetY(), 210, 18, 'F');

        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(0.4);
        $this->Line(0, $this->GetY(), 210, $this->GetY());

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', '', 6.5);
        $this->SetXY(8, $this->GetY() + 4);
        $this->Cell(58, 4, utf8_decode('ENVIAMOS PARA TODO O BRASIL'), 0, 0, 'L');

        $this->SetTextColor(212, 175, 55);
        $this->Cell(2, 4, '|', 0, 0, 'C');

        $this->SetTextColor(255, 255, 255);
        $this->Cell(64, 4, utf8_decode('FAÇA SEU PEDIDO PELO WHATSAPP'), 0, 0, 'C');

        $this->SetTextColor(212, 175, 55);
        $this->Cell(2, 4, '|', 0, 0, 'C');

        $this->SetTextColor(255, 255, 255);
        $this->Cell(64, 4, utf8_decode('LUX PERFUMES • DISTRIBUIDORA'), 0, 0, 'R');

        $this->SetTextColor(170, 170, 170);
        $this->SetFont('Arial', '', 5.5);
        $this->SetXY(8, $this->GetY() + 10);
        $this->Cell(
            194,
            3,
            utf8_decode('*Disponibilidade e preços devem ser consultados no dia da compra.  Página ') .
            $this->PageNo() . ' • ' . date('d/m/Y'),
            0,
            0,
            'C'
        );
    }

    function AddCategoriaTitulo($titulo, $cor)
    {
        $this->SetY(39);

        $this->SetFillColor($cor[0], $cor[1], $cor[2]);
        $this->Rect(10, $this->GetY(), 190, 12, 'F');

        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(0.35);
        $this->Line(10, $this->GetY() + 12, 200, $this->GetY() + 12);

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(10, $this->GetY() + 1);
        $this->Cell(
            190,
            9,
            mb_convert_encoding($titulo, 'ISO-8859-1', 'UTF-8'),
            0,
            0,
            'C'
        );

        $this->SetTextColor(0, 0, 0);
        $this->SetY(55);
    }

    function truncarDescricao($texto, $maxLinhas, $larguraTexto)
    {
        $this->SetFont('Arial', '', 7);
        $palavras = preg_split('/\s+/', trim($texto));
        $linhas = [];
        $linha = '';
        $acabou = true;

        foreach ($palavras as $palavra) {
            $teste = trim($linha . ' ' . $palavra);

            if ($this->GetStringWidth($teste) <= $larguraTexto) {
                $linha = $teste;
            } else {
                if ($linha !== '') {
                    $linhas[] = $linha;
                }
                $linha = $palavra;

                if (count($linhas) >= $maxLinhas) {
                    $acabou = false;
                    break;
                }
            }
        }

        if (count($linhas) < $maxLinhas && $linha !== '') {
            $linhas[] = $linha;
        }

        $textoFinal = implode("\n", array_slice($linhas, 0, $maxLinhas));

        if (!$acabou && $textoFinal !== '') {
            $textoFinal = rtrim($textoFinal, '.') . '...';
        }

        return $textoFinal;
    }

    function AddProductGrid($produto, $index)
    {
        $margemX = 10;
        $gapX = 4;
        $larguraCard = 91;
        $alturaCard = 56;
        $gapY = 4;

        $coluna = $index % 2;
        $linha = floor(($index % 6) / 2);

        $x = $margemX + ($coluna * ($larguraCard + $gapX));
        $y = 57 + ($linha * ($alturaCard + $gapY));

        // Card claro e limpo
        $this->SetFillColor(255, 253, 250);
        $this->SetDrawColor(232, 188, 196);
        $this->SetLineWidth(0.3);
        $this->Rect($x, $y, $larguraCard, $alturaCard, 'DF');

        // Área da foto
        $this->SetFillColor(252, 242, 244);
        $this->Rect($x + 3, $y + 4, 34, 39, 'F');

        if (!empty($produto['imagem'])) {
            $caminhoOriginal = 'uploads/' . $produto['imagem'];

            if (file_exists($caminhoOriginal)) {
                $caminhoComprimido = comprimirImagem($caminhoOriginal, 220, 220, 82);
                $this->Image($caminhoComprimido, $x + 4, $y + 5, 32, 37);
            }
        }

        $xTexto = $x + 40;
        $larguraTexto = 47;

        // Nome
        $this->SetTextColor(18, 31, 46);
        $this->SetFont('Arial', 'B', 9);
        $nome = mb_convert_encoding(
            mb_strtoupper(trim($produto['nome'])),
            'ISO-8859-1',
            'UTF-8'
        );
        $this->SetXY($xTexto, $y + 4);
        $this->MultiCell($larguraTexto, 4.2, $nome, 0, 'L');

        // Preço normal
        $this->SetTextColor(18, 31, 46);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($xTexto, $y + 15);
        $this->Cell(
            $larguraTexto,
            6,
            'R$ ' . number_format($produto['valor_venda'], 2, ',', '.'),
            0,
            1,
            'L'
        );

        // Preços de atacado
        $this->SetFillColor(252, 229, 234);
        $this->Rect($xTexto, $y + 22, $larguraTexto, 13, 'F');

        $this->SetTextColor(70, 70, 70);
        $this->SetFont('Arial', '', 6.5);
        $this->SetXY($xTexto + 2, $y + 24);
        $this->Cell(21, 3, '10 UN.', 0, 0, 'L');
        $this->Cell(21, 3, '20 UN.', 0, 1, 'L');

        $this->SetTextColor(205, 60, 91);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY($xTexto + 2, $y + 28);
        $this->Cell(
            21,
            4,
            'R$ ' . number_format($produto['preco_2'], 2, ',', '.'),
            0,
            0,
            'L'
        );
        $this->Cell(
            21,
            4,
            'R$ ' . number_format($produto['preco_3'], 2, ',', '.'),
            0,
            1,
            'L'
        );

        // Descrição curta
        $descricao = mb_convert_encoding(
            $produto['descricao'] ?? '',
            'ISO-8859-1',
            'UTF-8'
        );
        $descricao = $this->truncarDescricao($descricao, 2, $larguraTexto - 2);

        $this->SetTextColor(65, 65, 65);
        $this->SetFont('Arial', '', 6.6);
        $this->SetXY($xTexto, $y + 37);
        $this->MultiCell($larguraTexto, 3.7, $descricao, 0, 'L');

        // Identificação inferior
        $this->SetTextColor(205, 60, 91);
        $this->SetFont('Arial', 'B', 5.8);
        $this->SetXY($x + 4, $y + 48);
        $this->Cell(28, 3, utf8_decode('FEMININO'), 0, 0, 'L');

        $this->SetTextColor(145, 145, 145);
        $this->SetFont('Arial', '', 5.5);
        $marca = trim($produto['marca'] ?? '');
        $this->Cell(
            55,
            3,
            mb_convert_encoding($marca, 'ISO-8859-1', 'UTF-8'),
            0,
            0,
            'R'
        );
    }
}

// Criação do PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$produtosAgrupados = agruparPorSexoEMarca($produtosCompletos);

$coresCategoria = [
    'feminino' => [224, 102, 129], // rosa sofisticado
    'masculino' => [28, 28, 28],   // preto
    'unissex'   => [116, 91, 53]   // dourado escuro
];

foreach (['feminino', 'masculino', 'unissex'] as $categoria) {
    if (empty($produtosAgrupados[$categoria])) {
        continue;
    }

    // Mantém a ordem por marca já feita pela função original.
    $produtosCategoria = [];
    foreach ($produtosAgrupados[$categoria] as $marca => $produtosDaMarca) {
        foreach ($produtosDaMarca as $produto) {
            $produtosCategoria[] = $produto;
        }
    }

    $total = count($produtosCategoria);
    $titulo = ucfirst($categoria) . ($categoria === 'unissex' ? '' : 's');
    $corCategoria = $coresCategoria[$categoria];

    for ($i = 0; $i < $total; $i++) {
        // 6 produtos por página: 2 colunas x 3 linhas.
        if ($i === 0 || $i % 6 === 0) {
            if ($i > 0 || $categoria !== 'feminino') {
                $pdf->AddPage();
            }

            $pdf->AddCategoriaTitulo($titulo, $corCategoria);
        }

        $pdf->AddProductGrid($produtosCategoria[$i], $i % 6);
    }
}

$pdf->Output();
?>
