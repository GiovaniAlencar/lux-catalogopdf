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
        p.preco_3,
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
            $this->Image('uploads/mae.png', 10, 6, 30);

            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('Catálogo de Perfumes'), 0, 1, 'C');
            $this->Ln(5);

            $this->SetFont('Arial', '', 10);

            $this->Image('uploads/instagram.png', 60, 20, 5);
            $this->SetXY(65, 20);
            $this->Cell(0, 5, '@bellaparfunatacado', 0, 1);

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

    function prepararTexto($texto)
    {
        $texto = trim($texto ?? '');
        if ($texto === '') {
            return '';
        }

        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    function truncarDescricao($texto, $maxLinhas, $larguraTexto, $tamanhoFonte = 6)
    {
        if ($texto === '') {
            return '';
        }

        $this->SetFont('Arial', '', $tamanhoFonte);
        $charsPorLinha = max(12, (int) ($larguraTexto / 1.5));
        $linhas = explode("\n", wordwrap($texto, $charsPorLinha, "\n", true));

        if (count($linhas) > $maxLinhas) {
            $textoTruncado = '';
            for ($i = 0; $i < $maxLinhas; $i++) {
                $textoTruncado .= $linhas[$i] . "\n";
            }
            return rtrim($textoTruncado) . '...';
        }

        return $texto;
    }

    function medirAlturaTexto($largura, $alturaLinha, $texto, $estilo = '', $tamanho = 6)
    {
        if ($texto === '') {
            return 0;
        }

        $this->SetFont('Arial', $estilo, $tamanho);
        return $this->GetMultiCellHeight($largura, $alturaLinha, $texto);
    }

    function formatarPreco($valor)
    {
        return number_format(round((float) $valor), 2, ',', '.');
    }

    function calcularPrecosEspeciais($preco2)
    {
        $preco2 = (float) ($preco2 ?? 0);

        return [
            'preco5' => $preco2 * 1.22,
            'preco10' => $preco2 * 1.18,
        ];
    }

    function calcularAlturaProduto($produto, $larguraTexto, $larguraImagem)
    {
        $nome = $this->prepararTexto(mb_strtoupper($produto['nome'] ?? ''));
        $descricao = $this->truncarDescricao(
            $this->prepararTexto($produto['descricao'] ?? ''),
            4,
            $larguraTexto,
            6
        );

        $alturaNome = $this->medirAlturaTexto($larguraTexto, 4.5, $nome, 'B', 9);
        $alturaPrecosEspeciais = 6;
        $alturaDescricao = $this->medirAlturaTexto($larguraTexto, 3.5, $descricao, '', 6);

        $alturaConteudo = 4 + $alturaNome + $alturaPrecosEspeciais;
        if ($descricao !== '') {
            $alturaConteudo += 2 + $alturaDescricao;
        }
        $alturaConteudo += 4;

        return max($larguraImagem + 6, $alturaConteudo);
    }

    function renderProduct($produto, $xColuna, $yInicio, $larguraColuna, $alturaBloco)
    {
        $larguraImagem = 28;
        $alturaImagem = 28;
        $xImagem = $xColuna;
        $xTexto = $xColuna + $larguraImagem + 4;
        $larguraTexto = $larguraColuna - $larguraImagem - 4;
        $precos = $this->calcularPrecosEspeciais($produto['preco_3'] ?? 0);

        $nome = $this->prepararTexto(mb_strtoupper($produto['nome'] ?? ''));
        $descricao = $this->truncarDescricao(
            $this->prepararTexto($produto['descricao'] ?? ''),
            4,
            $larguraTexto,
            6
        );

        if (!empty($produto['imagem'])) {
            $caminhoOriginal = 'uploads/' . $produto['imagem'];
            if (file_exists($caminhoOriginal)) {
                $caminhoComprimido = comprimirImagem($caminhoOriginal, 112, 112, 80);
                $imagemY = $yInicio + ($alturaBloco - $alturaImagem) / 2;
                $this->Image($caminhoComprimido, $xImagem, $imagemY, $larguraImagem, $alturaImagem);
            }
        }

        $y = $yInicio + 3;

        $this->SetXY($xTexto, $y);
        $this->SetFont('Arial', 'B', 9);
        $this->MultiCell($larguraTexto, 4.5, $nome, 0, 'L');
        $y = $this->GetY() + 1;

        $this->SetFont('Arial', 'B', 8);
        $this->SetXY($xTexto, $y);
        $metadeTexto = $larguraTexto / 2;
        $this->Cell($metadeTexto, 6, '5 un.: R$ ' . $this->formatarPreco($precos['preco5']), 0, 0);
        $this->Cell($metadeTexto, 6, '10 un.: R$ ' . $this->formatarPreco($precos['preco10']), 0, 1);
        $y += 6;

        if ($descricao !== '') {
            $this->SetXY($xTexto, $y + 1);
            $this->SetFont('Arial', '', 6);
            $this->MultiCell($larguraTexto, 3.5, $descricao, 0, 'L');
        }
    }

    function AddProductRow($produtoEsquerda, $produtoDireita)
    {
        $margem = 5;
        $gap = 4;
        $larguraColuna = (210 - ($margem * 2) - $gap) / 2;
        $xEsquerda = $margem;
        $xDireita = $margem + $larguraColuna + $gap;
        $larguraImagem = 28;
        $larguraTexto = $larguraColuna - $larguraImagem - 4;

        $alturaEsquerda = $produtoEsquerda ? $this->calcularAlturaProduto($produtoEsquerda, $larguraTexto, $larguraImagem) : 0;
        $alturaDireita = $produtoDireita ? $this->calcularAlturaProduto($produtoDireita, $larguraTexto, $larguraImagem) : 0;
        $alturaBloco = max($alturaEsquerda, $alturaDireita);

        if ($this->GetY() + $alturaBloco > 265) {
            $this->AddPage();
        }

        $yInicio = $this->GetY();

        if ($produtoEsquerda) {
            $this->renderProduct($produtoEsquerda, $xEsquerda, $yInicio, $larguraColuna, $alturaBloco);
        }

        if ($produtoDireita) {
            $this->renderProduct($produtoDireita, $xDireita, $yInicio, $larguraColuna, $alturaBloco);
        }

        $this->SetDrawColor(220, 220, 220);
        $this->Line($margem, $yInicio + $alturaBloco, 210 - $margem, $yInicio + $alturaBloco);
        $this->SetDrawColor(0, 0, 0);

        $this->SetY($yInicio + $alturaBloco + 2);
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

foreach (['feminino', 'masculino', 'unissex'] as $categoria) {
    if (!empty($produtosAgrupados[$categoria])) {
        $titulo = ucfirst($categoria) . 's';
        $corCategoria = $categoria === 'feminino' ? [255, 182, 193] : ($categoria === 'masculino' ? [0, 0, 0] : [135, 206, 235]);

        $pdf->AddCategoriaTitulo($titulo, $corCategoria);

        $buffer = [];
        foreach ($produtosAgrupados[$categoria] as $marca => $produtosDaMarca) {
            foreach ($produtosDaMarca as $produto) {
                $buffer[] = $produto;
                if (count($buffer) === 2) {
                    $pdf->AddProductRow($buffer[0], $buffer[1]);
                    $buffer = [];
                }
            }
        }

        if (!empty($buffer)) {
            $pdf->AddProductRow($buffer[0], null);
        }
    }
}

$pdf->Output();
