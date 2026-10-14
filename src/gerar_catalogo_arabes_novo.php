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

        // Corrige o acento de "Página"
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . ' | Atualizado em ' . date('d/m/Y'), 0, 1, 'C');

        // Adiciona a observação abaixo
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, utf8_decode('*A disponibilidade dos itens e o preço devem ser consultados no dia da compra.'), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0); // volta ao normal
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
        $larguraImagem = 35;
        $alturaImagem = 35;
        $xImagem = 18;
        $xTexto = 55;
        $larguraTexto = 120;
        $alturaMinima = 65; // aumentei para caber os novos preços
        
        $precoUnitario   = $produto['valor_venda'];
        $preco10  = $produto['preco_2'];
        $preco20 = $produto['preco_3'];


        // Calcula altura do texto
        $this->SetFont('Arial', 'B', 12);
        $alturaTitulo = 8;
        $this->SetFont('Arial', '', 12);
        $alturaPreco = 7;
        $this->SetFont('Arial', '', 8);
        $descricao_truncada = $this->truncarDescricao(mb_convert_encoding($produto['descricao'], 'ISO-8859-1', 'UTF-8'), 3, 510, $this);
        $alturaDescricao = $this->GetMultiCellHeight($larguraTexto, 5, $descricao_truncada);

        $alturaTextoTotal = $alturaTitulo + $alturaPreco + 12 /* novos preços */ + $alturaDescricao;
        $alturaBloco = max($alturaMinima, $alturaTextoTotal);

        // Quebra de página
        if ($this->GetY() + $alturaBloco > 270) {
            $this->AddPage();
        }

        // Fundo
        $bgColor = ($index % 2 == 0) ? [255, 255, 255] : [230, 230, 230];
        $this->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
        $this->Rect(10, $this->GetY(), 190, $alturaBloco, 'F');

        // Guia colorida
        $this->SetFillColor($corGuia[0], $corGuia[1], $corGuia[2]);
        $this->Rect(10, $this->GetY(), 5, $alturaBloco + 5, 'F');

        // Imagem (comprimida para reduzir tamanho do PDF)
        if (!empty($produto['imagem'])) {
            $caminhoOriginal = 'uploads/' . $produto['imagem'];
            if (file_exists($caminhoOriginal)) {
                // Comprime para 70x70px (2x o tamanho de exibição para boa qualidade) com 60% de qualidade JPEG
                $caminhoComprimido = comprimirImagem($caminhoOriginal, 140, 140, 80);
                $imagemY = $this->GetY() + ($alturaBloco - $alturaImagem) / 2;
                $this->Image($caminhoComprimido, $xImagem, $imagemY, $larguraImagem, $alturaImagem);
            }
        }

        // Texto
        $yTexto = $this->GetY() + 5;
        $this->SetXY($xTexto, $yTexto);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, $alturaTitulo, mb_convert_encoding(mb_strtoupper($produto['nome']), 'ISO-8859-1', 'UTF-8'), 0, 1);

        // Preço normal
        $this->SetFont('Arial', '', 12);
        $this->SetX($xTexto);
        $this->Cell(0, $alturaPreco, 'R$ ' . number_format($produto['valor_venda'], 2, ',', '.'), 0, 1);

        // Preços especiais
        $this->SetFont('Arial', '', 9);
        $this->SetX($xTexto);
        $this->Cell(0, 5, '10 unid.: R$ ' . number_format($preco10, 2, ',', '.'), 0, 1);
        $this->SetX($xTexto);
        $this->Cell(0, 5, '20 unid.: R$ ' . number_format($preco20, 2, ',', '.'), 0, 1);

        $this->Ln(2);

        // Descrição
        $this->SetX($xTexto);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell($larguraTexto, 5, $descricao_truncada, 0, 1);

        // Move cursor
        $this->SetY($this->GetY() - 1 + ($alturaBloco - $alturaTextoTotal));
    }


    // Adicione este método na sua classe PDF:
    function GetMultiCellHeight($w, $h, $txt)
    {
        // Cria um clone do PDF para calcular a altura sem imprimir
        $cp = clone $this;
        $cp->SetXY(0, 0);
        $cp->MultiCell($w, $h, $txt);
        $altura = $cp->GetY();
        unset($cp);
        return $altura;
    }
}

// Criação do PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$produtosAgrupados = agruparPorSexoEMarca($produtosCompletos);

$produtoIndexGlobal = 0;
foreach (['feminino', 'masculino', 'unissex'] as $categoria) {
    if (!empty($produtosAgrupados[$categoria])) {
        $titulo = ucfirst($categoria) . 's';
        $corCategoria = $categoria === 'feminino' ? [255, 182, 193] : ($categoria === 'masculino' ? [0, 0, 0] : [135, 206, 235]);

        $pdf->AddCategoriaTitulo($titulo, $corCategoria);

        foreach ($produtosAgrupados[$categoria] as $marca => $produtosDaMarca) {
            foreach ($produtosDaMarca as $produto) {
                $pdf->AddProduct($produto, $produtoIndexGlobal, $corCategoria);
                $produtoIndexGlobal++;
            }
        }
    }
}

$pdf->Output();
