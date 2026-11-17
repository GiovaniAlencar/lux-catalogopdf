<?php
/**
 * Gerador único dos catálogos em PDF (substitui os gerar_catalogo_*.php copiados).
 *   gerar_catalogo_pdf.php?tipo=arabes | frances | miniaturas | hidratantes | vs | sprays
 *                               | sem_preco | sem_preco_miniaturas | ciclo | paris | black | skincare | arabes_mae | arabes_mae_10
 *   (POST produtos[] = ids da tabela products do catálogo → tipo "selecionados")
 * Preços iguais ao catálogo antigo; visual do site; 4 produtos por linha (12 por página);
 * botão "Ver detalhes" abre a página do produto em luxatacado.com.br/produto/{id}.
 * Identidade: lux (padrão) · bella (Bella Parfum: logo, WhatsApp e Instagram dela, sem site e sem LUX)
 *             · neutro (sem preço, para o revendedor repassar: sem logo, nome, site ou contato).
 */
require_once __DIR__ . '/auth_check.php';
session_write_close(); // não trava o painel enquanto o PDF é gerado
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/CatalogoPdf.php';
require_once __DIR__ . '/lib/CatalogoCicloPdf.php';
require_once __DIR__ . '/lib/CatalogoBellaPdf.php';

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
set_time_limit(300);
ini_set('memory_limit', '512M');

// tipo => [título, categorias do ERP, preços, identidade]
const TIPOS = [
    'arabes'               => ['Perfumes Árabes', [3], 'tabela10', 'lux'],
    'frances'              => ['Perfumes Importados', [8], 'tabela10', 'lux'],
    'miniaturas'           => ['Miniaturas', [6], 'tabela6', 'lux'],
    'hidratantes'          => ['Hidratantes', [4], 'unico', 'lux'],
    'vs'                   => ['Body Splash', [7, 9], 'unico', 'lux'],
    'sprays'               => ['Body Sprays', [5], 'unico', 'lux'],
    // para o revendedor mandar ao cliente dele: sem preço e sem identidade da LUX
    'sem_preco'            => ['Perfumes Árabes', [3], 'nenhum', 'neutro'],
    'sem_preco_miniaturas' => ['Miniaturas', [6], 'nenhum', 'neutro'],
    'selecionados'         => ['Seleção de produtos', [], 'tabela10', 'lux'],
    // linha nacional: pega pela marca Ciclo (ERP ou catálogo) ou pela categoria "Ciclo"/"Nacionais" do ERP
    'ciclo'                => ['Ciclo Cosméticos', [], 'tabela10', 'lux'],
    // linha nacional Paris Elysees: pela marca (ERP ou catálogo) ou "Paris Elysees" no nome
    'paris'                => ['Paris Elysees', [], 'tabela10', 'lux'],
    // Black Friday: todos os produtos com promoção ativa ou agendada no ERP (Vendas › Promoções),
    // separados pela campanha (nome da campanha na importação em lote), com "de/por"
    'black'                => ['Black Friday LUX', [], 'promo', 'lux'],
    // Skin Care: layout próprio (2 por página, por linha, funções/indicado para, esgotados) — lib/catalogo_skincare.php
    'skincare'             => ['Skin Care', [], 'unico', 'lux'],
    // Bella Parfum: preços dela sobre o preço de 20 un. (5 un. +22% e 10 un. +18%; o "_10" só um preço, +12%)
    'arabes_mae'           => ['Perfumes Árabes', [3], 'bella', 'bella'],
    'arabes_mae_10'        => ['Perfumes Árabes', [3], 'bella10', 'bella'],
];

// Fotos: tamanho pensado para o card (~45 mm): 360 px / JPEG 70 deixa cada foto com ~10-15 KB.
// (ficam aqui em cima porque o Skin Care usa as funções de foto antes do resto do arquivo rodar)
const FOTO_MAX_PX = 360;
const FOTO_QUALIDADE = 70;
const FOTO_FALHA_TTL = 86400; // foto que não baixou: não tenta de novo por 24 h

/** Link do produto no PDF conforme a identidade. */
function linkProduto(string $marca, int $id, string $nome, array $cfg): ?string
{
    if ($marca === 'neutro') {
        return null; // não leva a nenhum site
    }
    if ($marca === 'bella') {
        return 'https://wa.me/5583998674968?text=' . rawurlencode('Olá! Tenho interesse no perfume ' . trim($nome) . '.');
    }
    return rtrim($cfg['site_url'], '/') . '/produto/' . $id;
}

// Ordem das campanhas no PDF da Black (as que não estão aqui vêm depois, em ordem alfabética).
const BLACK_ORDEM = ['LIQUIDA GERAL', 'BLACK MINI', 'BLACK PARIS'];

// Página de brindes do PDF da Black: [destaque, texto]. Mude aqui quando a regra mudar.
// Vazio = sem página de brindes (as campanhas de brinde são divulgadas por fora).
// Para voltar: ['Destaque', 'texto'], uma linha por brinde.
const BLACK_BRINDES = [];

/**
 * Promoções da Black no ERP: ativas, ainda não vencidas (inclui as agendadas para começar),
 * com unidades no limite. A mais recente vence quando o produto tem mais de uma.
 * @return array<int, array{preco:float, campanha:string, ate:string}>
 */
function promocoesBlack(PDO $erp): array
{
    $sql = "SELECT pr.produto_id, pr.tipo, pr.valor, pr.data_fim, COALESCE(NULLIF(TRIM(pr.observacao), ''), 'OFERTAS') AS campanha, p.valor_venda
            FROM promocoes pr
            JOIN produtos p ON p.id = pr.produto_id
            WHERE pr.ativo = 1 AND pr.data_fim >= CURDATE()
              AND (pr.quantidade_limite IS NULL OR pr.quantidade_utilizada < pr.quantidade_limite)
            ORDER BY pr.id DESC";
    $out = [];
    foreach ($erp->query($sql)->fetchAll() as $r) {
        $pid = (int) $r['produto_id'];
        if (isset($out[$pid])) {
            continue;
        }
        $base = (float) $r['valor_venda'];
        $preco = $r['tipo'] === 'preco_fixo' ? (float) $r['valor'] : round($base - $base * (float) $r['valor'] / 100, 2);
        if ($preco > 0 && $preco < $base) {
            $out[$pid] = ['preco' => round($preco, 2), 'campanha' => mb_strtoupper((string) $r['campanha']), 'ate' => (string) $r['data_fim']];
        }
    }
    return $out;
}

/** ids do ERP dos produtos da Paris Elysees (marca no ERP, marca no catálogo ou nome). */
function idsParis(PDO $erp, PDO $cat): array
{
    $ids = [];
    foreach ($cat->query("SELECT id_erp FROM products WHERE (marca LIKE '%paris%elys%' OR nome LIKE '%paris%elys%') AND id_erp IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $i) {
        $ids[] = (int) $i;
    }
    $sql = "SELECT p.id FROM produtos p
            LEFT JOIN marcas m ON m.id = p.marca_id
            WHERE m.nome LIKE '%paris%elys%' OR p.nome LIKE '%paris%elys%'";
    foreach ($erp->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $i) {
        $ids[] = (int) $i;
    }
    return array_values(array_unique(array_filter($ids)));
}

/**
 * ids do ERP dos produtos da Ciclo (marca no ERP, marca no catálogo ou categoria Ciclo/Nacionais).
 * A categoria "Nacionais" também tem a Paris Elysees (11/11/2026): essa fica de fora daqui.
 */
function idsCiclo(PDO $erp, PDO $cat): array
{
    $ids = [];
    foreach ($cat->query("SELECT id_erp FROM products WHERE marca LIKE '%ciclo%' AND id_erp IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $i) {
        $ids[] = (int) $i;
    }
    $sql = "SELECT p.id FROM produtos p
            LEFT JOIN marcas m ON m.id = p.marca_id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE m.nome LIKE '%ciclo%' OR c.nome LIKE '%ciclo%' OR c.nome LIKE 'nacion%'";
    foreach ($erp->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $i) {
        $ids[] = (int) $i;
    }
    return array_values(array_diff(array_unique(array_filter($ids)), idsParis($erp, $cat)));
}

/** Linha do produto da Ciclo pelo nome: kids, colonia, splash, hidratante ou outros. */
function linhaCiclo(array $p): string
{
    $n = ' ' . strtoupper(strtr((string) $p['nome'] . ' ' . (string) $p['sexo'], [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
    ])) . ' ';
    if (preg_match('/KIDS?\b|INFANTIL|BABY|BEBE|CRIANCA/', $n)) {
        return 'kids';
    }
    if (preg_match('/SPLASH|BODY MIST|\bMIST\b/', $n)) {
        return 'splash';
    }
    if (preg_match('/HIDRAT|LOCAO|CREME|LOTION|\bOLEO\b|MANTEIGA/', $n)) {
        return 'hidratante';
    }
    if (preg_match('/COLONIA|\bDEO\b|PERFUME|PARFUM|\bEDP\b|\bEDT\b/', $n)) {
        return 'colonia';
    }
    return 'outros';
}

$tipo = $_GET['tipo'] ?? 'arabes';
if (!isset(TIPOS[$tipo])) {
    http_response_code(404);
    exit('Catálogo inexistente.');
}
[$titulo, $categorias, $precos, $marca] = TIPOS[$tipo];
$cfg = luxConfig();
if ($tipo === 'skincare') {
    require __DIR__ . '/lib/catalogo_skincare.php';
    exit;
}

// ---- produtos com estoque (ERP) + nome/foto/marca (catálogo) ----
try {
    $erp = luxDb('erp');
    $cat = luxDb('catalogo');
    // seleção manual: ids do catálogo → ids do ERP
    $idsSelecionados = [];
    if ($tipo === 'ciclo') {
        $idsSelecionados = idsCiclo($erp, $cat);
        if (!$idsSelecionados) {
            exit('Nenhum produto da Ciclo encontrado (confira a marca ou a categoria no ERP).');
        }
    } elseif ($tipo === 'black') {
        $promosBlack = promocoesBlack($erp);
        $idsSelecionados = array_keys($promosBlack);
        if (!$idsSelecionados) {
            exit('Nenhuma promoção ativa no ERP (Vendas › Promoções). Lance as promoções da Black e gere de novo.');
        }
    } elseif ($tipo === 'paris') {
        $idsSelecionados = idsParis($erp, $cat);
        if (!$idsSelecionados) {
            exit('Nenhum produto da Paris Elysees encontrado (confira a marca no ERP ou no catálogo).');
        }
    } elseif ($tipo === 'selecionados') {
        $sel = array_slice(array_values(array_filter(array_map('intval', (array) ($_POST['produtos'] ?? [])))), 0, 500);
        if (!$sel) {
            exit('Nenhum produto selecionado.');
        }
        $st = $cat->prepare('SELECT id_erp FROM products WHERE id IN (' . implode(',', array_fill(0, count($sel), '?')) . ')');
        $st->execute($sel);
        $idsSelecionados = array_values(array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))));
        if (!$idsSelecionados) {
            exit('Os produtos selecionados não estão vinculados ao ERP.');
        }
    }
    $porIds = in_array($tipo, ['selecionados', 'ciclo', 'paris', 'black'], true);
    $filtro = $porIds
        ? 'p.id IN (' . implode(',', array_fill(0, count($idsSelecionados), '?')) . ')'
        : 'p.categoria_id IN (' . implode(',', array_fill(0, count($categorias), '?')) . ')';
    $ocultar = '';
    $tem = $erp->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'ocultar_site'")->fetchColumn();
    if ((int) $tem) {
        $ocultar = ' AND p.ocultar_site = 0';
    }
    $st = $erp->prepare("
        SELECT p.id, p.nome AS nome_erp, p.valor_venda, p.preco_2, p.preco_3, SUM(e.quantidade) AS qtd
        FROM estoques e
        JOIN produtos p ON p.id = e.produto_id
        WHERE $filtro $ocultar
        GROUP BY p.id, p.nome, p.valor_venda, p.preco_2, p.preco_3
        HAVING qtd > 0
    ");
    $st->execute($porIds ? $idsSelecionados : $categorias);
    $erpRows = [];
    foreach ($st->fetchAll() as $r) {
        $erpRows[(int) $r['id']] = $r;
    }
    $produtos = [];
    if ($erpRows) {
        $ph = implode(',', array_fill(0, count($erpRows), '?'));
        $st = $cat->prepare("SELECT id_erp, nome, sexo, marca, imagem FROM products WHERE id_erp IN ($ph)");
        $st->execute(array_keys($erpRows));
        foreach ($st->fetchAll() as $c) {
            $id = (int) $c['id_erp'];
            $produtos[] = $c + $erpRows[$id] + [
                'link' => linkProduto($marca, $id, (string) $c['nome'], $cfg),
            ];
        }
        if ($tipo === 'black') {
            // produto em promoção que ainda não está no catálogo: entra com o nome do ERP, sem foto
            $noCatalogo = array_flip(array_map(fn ($x) => (int) $x['id_erp'], $produtos));
            foreach ($erpRows as $id => $r) {
                if (!isset($noCatalogo[$id])) {
                    $produtos[] = ['id_erp' => $id, 'nome' => $r['nome_erp'], 'sexo' => '', 'marca' => '', 'imagem' => ''] + $r + [
                        'link' => linkProduto($marca, $id, (string) $r['nome_erp'], $cfg),
                    ];
                }
            }
            foreach ($produtos as &$pp) {
                $pp['promo'] = $promosBlack[(int) $pp['id_erp']]['preco'];
                $pp['campanha'] = $promosBlack[(int) $pp['id_erp']]['campanha'];
            }
            unset($pp);
        }
    }
} catch (Throwable $e) {
    error_log('[catalogo-pdf] ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao buscar os produtos. Tente novamente.');
}

if (!$produtos) {
    exit('Nenhum produto disponível para este catálogo.');
}

// ---- agrupa por gênero (Femininos, Masculinos, Unissex) e ordena por marca/nome ----
// Black: agrupa pela campanha (ordem de BLACK_ORDEM, depois alfabética) e ordena por marca/nome
if ($tipo === 'black') {
    $grupos = [];
    foreach (BLACK_ORDEM as $nomeCamp) {
        $grupos[$nomeCamp] = [];
    }
    foreach ($produtos as $p) {
        $grupos[$p['campanha']][] = $p;
    }
    $fixos = array_intersect_key($grupos, array_flip(BLACK_ORDEM));
    $outros = array_diff_key($grupos, $fixos);
    ksort($outros);
    $grupos = $fixos + $outros;
    foreach ($grupos as &$lista) {
        usort($lista, fn ($a, $b) => strcasecmp(trim((string) $a['marca']), trim((string) $b['marca'])) ?: strcasecmp($a['nome'], $b['nome']));
    }
    unset($lista);
    $nomesGrupo = array_combine(array_keys($grupos), array_map(fn ($k) => ucwords(mb_strtolower($k)), array_keys($grupos)));
} elseif ($tipo === 'ciclo') {
    $grupos = ['kids' => [], 'colonia' => [], 'splash' => [], 'hidratante' => [], 'outros' => []];
    foreach ($produtos as $p) {
        $grupos[linhaCiclo($p)][] = $p;
    }
    foreach ($grupos as &$lista) {
        usort($lista, fn ($a, $b) => strcasecmp($a['nome'], $b['nome']));
    }
    unset($lista);
    $nomesGrupo = ['kids' => 'Ciclo Kids', 'colonia' => 'Colônias e Perfumes', 'splash' => 'Body Splash', 'hidratante' => 'Hidratantes', 'outros' => 'Outros produtos'];
} else {
    $grupos = ['feminino' => [], 'masculino' => [], 'unissex' => [], 'outros' => []];
    foreach ($produtos as $p) {
        $g = mb_strtolower(trim((string) $p['sexo']));
        $grupos[isset($grupos[$g]) ? $g : 'outros'][] = $p;
    }
    foreach ($grupos as &$lista) {
        usort($lista, fn ($a, $b) => strcasecmp(trim((string) $a['marca']), trim((string) $b['marca'])) ?: strcasecmp($a['nome'], $b['nome']));
    }
    unset($lista);
    $nomesGrupo = ['feminino' => 'Femininos', 'masculino' => 'Masculinos', 'unissex' => 'Unissex', 'outros' => 'Outros'];
}

// ---- fotos: arquivo local (uploads/) ou baixada do CDN; reduzida e guardada em cache (constantes FOTO_* lá em cima) ----

function fotoCacheDir(): string
{
    $dir = __DIR__ . '/uploads/cache/pdf-v2';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function fotoNomeLimpo(?string $img): ?string
{
    $img = ltrim((string) $img, '/');
    return ($img === '' || strpos($img, '..') !== false) ? null : $img;
}

function fotoDestino(string $img, int $px = FOTO_MAX_PX): string
{
    // tamanho diferente do padrão (ex.: Skin Care) fica num arquivo próprio no cache
    return fotoCacheDir() . '/' . md5($img) . ($px === FOTO_MAX_PX ? '' : '-' . $px) . '.jpg';
}

/** Reduz e grava a foto no cache. Retorna o caminho ou null. */
function fotoGravar(string $bytes, string $destino, int $px = FOTO_MAX_PX, array $fundo = [247, 245, 240]): ?string
{
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return null;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $esc = min($px / $w, $px / $h, 1);
    $nw = max(1, (int) round($w * $esc));
    $nh = max(1, (int) round($h * $esc));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, $fundo[0], $fundo[1], $fundo[2]));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($dst, false);
    $ok = imagejpeg($dst, $destino, FOTO_QUALIDADE);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? $destino : null;
}

/**
 * Prepara todas as fotos de uma vez: as que já estão em cache são usadas direto,
 * as locais são convertidas e as que faltam são baixadas em paralelo.
 * Retorna [nomeDaImagem => caminhoDoJpeg].
 */
function prepararFotos(array $imagens, array $cfg, int $px = FOTO_MAX_PX, array $fundo = [247, 245, 240]): array
{
    $prontas = [];
    $baixar = [];
    foreach (array_unique(array_filter(array_map('fotoNomeLimpo', $imagens))) as $img) {
        $destino = fotoDestino($img, $px);
        $original = __DIR__ . '/uploads/' . $img;
        $temOriginal = is_file($original);
        if (is_file($destino) && (!$temOriginal || filemtime($destino) >= filemtime($original))) {
            $prontas[$img] = $destino;
            continue;
        }
        if ($temOriginal) {
            $bytes = @file_get_contents($original);
            if ($bytes && ($f = fotoGravar($bytes, $destino, $px, $fundo))) {
                $prontas[$img] = $f;
            }
            continue;
        }
        $falha = $destino . '.falha';
        if (is_file($falha) && time() - filemtime($falha) < FOTO_FALHA_TTL) {
            continue; // já tentou há pouco e não existe
        }
        if (!empty($cfg['imagens_url'])) {
            $baixar[$img] = rtrim($cfg['imagens_url'], '/') . '/' . str_replace('%2F', '/', rawurlencode($img));
        }
    }

    if ($baixar && function_exists('curl_multi_init')) {
        foreach (array_chunk($baixar, 16, true) as $lote) {
            $mh = curl_multi_init();
            $hs = [];
            foreach ($lote as $img => $url) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                ]);
                curl_multi_add_handle($mh, $ch);
                $hs[$img] = $ch;
            }
            do {
                $st = curl_multi_exec($mh, $ativos);
                if ($ativos) {
                    curl_multi_select($mh, 1.0);
                }
            } while ($ativos && $st === CURLM_OK);
            foreach ($hs as $img => $ch) {
                $bytes = curl_multi_getcontent($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $f = ($code === 200 && $bytes) ? fotoGravar($bytes, fotoDestino($img, $px), $px, $fundo) : null;
                if ($f) {
                    $prontas[$img] = $f;
                } else {
                    @touch(fotoDestino($img, $px) . '.falha');
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
    } elseif ($baixar) {
        $ctx = stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]);
        foreach ($baixar as $img => $url) {
            $bytes = @file_get_contents($url, false, $ctx);
            $f = $bytes ? fotoGravar($bytes, fotoDestino($img, $px), $px, $fundo) : null;
            if ($f) {
                $prontas[$img] = $f;
            } else {
                @touch(fotoDestino($img, $px) . '.falha');
            }
        }
    }
    return $prontas;
}

$fotos = prepararFotos(array_column($produtos, 'imagem'), $cfg);

// ---- monta o PDF ----
$ciclo = $tipo === 'ciclo';
$pdf = $ciclo ? new CatalogoCicloPdf('P', 'mm', 'A4') : ($marca === 'bella' ? new CatalogoBellaPdf('P', 'mm', 'A4') : new CatalogoPdf('P', 'mm', 'A4'));
$pdf->SetCompression(true);
$pdf->cfg = $cfg;
$pdf->usarMarca($marca, $cfg);
$pdf->titulo = $titulo;
$nomeMarca = $pdf->id['nome'] ?? null;
$pdf->SetTitle(CatalogoPdf::t('Catálogo ' . $titulo . ($nomeMarca ? ' - ' . $nomeMarca : '')));
if ($nomeMarca) {
    $pdf->SetAuthor(CatalogoPdf::t($nomeMarca));
}
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(10, 10, 10);

$subtitulo = $marca === 'neutro' ? 'Consulte disponibilidade e valores' : ($precos === 'nenhum' ? 'Catálogo sem preços' : 'Preços para revenda');
if ($ciclo) {
    $presentes = [];
    foreach ($grupos as $g => $lista) {
        if ($lista) {
            $presentes[] = str_replace('Ciclo ', '', $nomesGrupo[$g]);
        }
    }
    $pdf->CapaCiclo($subtitulo, count($produtos), $presentes);
} else {
    $pdf->Capa($tipo === 'black' ? 'Só nos dias 13 e 14 de novembro · pedido mínimo de 3 unidades (pode misturar)' : $subtitulo, count($produtos));
}
if ($tipo === 'black' && BLACK_BRINDES) {
    $pdf->PaginaBlocos('Brindes', 'Brindes da Black Friday', BLACK_BRINDES);
}

foreach ($grupos as $g => $lista) {
    if (!$lista) {
        continue;
    }
    if ($ciclo) {
        $pdf->usarSecao($g, $nomesGrupo[$g]);
    } else {
        $pdf->secao = $nomesGrupo[$g];
    }
    foreach ($lista as $i => $p) {
        $pos = $i % $pdf::POR_PAGINA;
        if ($pos === 0) {
            $pdf->AddPage();
        }
        $pdf->Card($p, $pos, $precos, $fotos[fotoNomeLimpo($p['imagem'])] ?? null);
    }
}

if (ob_get_length()) {
    ob_end_clean();
}
$arquivo = match ($marca) {
    'bella' => 'catalogo-bella-parfum-' . date('d-m-Y') . '.pdf',
    'neutro' => 'catalogo-' . ($tipo === 'sem_preco_miniaturas' ? 'miniaturas' : 'perfumes-arabes') . '-' . date('d-m-Y') . '.pdf',
    default => 'catalogo-' . $tipo . '-' . date('d-m-Y') . '.pdf',
};
$pdf->Output('I', $arquivo);
