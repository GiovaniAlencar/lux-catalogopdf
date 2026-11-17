<?php
/**
 * Catálogo de Skin Care: 2 produtos por página, separados por linha (Collagen, PDRN Pink...),
 * cada linha com a sua cor, e para cada produto: nome, tamanho, preço, "Principais funções"
 * e "Indicado para" (lidos da descrição do produto no catálogo). Sem estoque → "ESGOTADO".
 */
class CatalogoSkincarePdf extends CatalogoPdf
{
    /**
     * Linhas na ordem em que aparecem no PDF: [nome, padrão no nome do produto, cor de cima, cor de baixo, texto claro?].
     * Produto que não bate com nenhuma (e sem "Linha:" na descrição) vai para "Outros".
     */
    const LINHAS = [
        ['Collagen', '/^(?!.*PDRN).*COLLAGEN/', [231, 150, 132], [196, 108, 90], false],
        ['PDRN Pink', '/PDRN/', [234, 88, 150], [186, 48, 110], true],
        ['Zero Pore', '/ZERO\s*P/', [59, 130, 246], [29, 78, 216], true],
        ['Red Succinic', '/SUCCINIC/', [220, 38, 38], [153, 27, 27], true],
        ['Kojic', '/KOJIC/', [234, 179, 8], [202, 138, 4], false],
        ['Deep Vita C', '/VITA\s*C\b|VITAMIN\s*C/', [250, 204, 21], [234, 179, 8], false],
        ['TXA Niacinamide', '/\bTXA\b/', [190, 24, 93], [157, 23, 77], true],
        ['Gluta Glow', '/GLUTA/', [180, 83, 9], [120, 53, 15], true],
        ['Firming & Lift', '/\bEGF\b|\bNAD\b|LIFT|HYALUR|EXOSOME|FIRMING/', [153, 27, 27], [120, 24, 24], true],
    ];
    const OUTROS = ['Outros', '', [55, 55, 60], [25, 25, 28], true];

    /** cores da página atual (fundo em degradê) e do texto */
    public ?array $fundo = null;
    public bool $claro = true;
    public string $etiqueta = '';
    public string $data = '';
    /** o rodapé de uma página é desenhado quando a próxima começa: guarda o que vale para cada página */
    private array $porPagina = [];

    // ---------- leitura dos dados ----------

    /** Categorias do ERP com "skin" no nome (ex.: "Skincare", "Skin Care"). */
    public static function categoriasErp(PDO $erp): array
    {
        foreach (['categorias', 'categoria_produtos', 'categories'] as $tabela) {
            try {
                $ids = $erp->query("SELECT id FROM `$tabela` WHERE nome LIKE '%skin%'")->fetchAll(PDO::FETCH_COLUMN);
                if ($ids) {
                    return array_map('intval', $ids);
                }
            } catch (Throwable $e) {
                // tabela com outro nome: tenta a próxima
            }
        }
        return [];
    }

    /** Linha do produto: "Linha: X" na descrição vence; senão pelo nome. */
    public static function linhaDe(string $nome, ?string $linhaDescricao): array
    {
        if ($linhaDescricao) {
            foreach (self::LINHAS as $l) {
                if (mb_strtolower($l[0]) === mb_strtolower(trim($linhaDescricao))) {
                    return $l;
                }
            }
            $o = self::OUTROS;
            $o[0] = trim($linhaDescricao);
            return $o;
        }
        $n = mb_strtoupper($nome);
        foreach (self::LINHAS as $l) {
            if (preg_match($l[1], $n)) {
                return $l;
            }
        }
        return self::OUTROS;
    }

    /**
     * Lê a descrição no formato:
     *   Principais funções:
     *   - item
     *   Indicado para:
     *   - item
     *   Tamanho: 100 ml        (opcional; senão sai do nome)
     *   Linha: Collagen        (opcional; senão sai do nome)
     * O que não estiver nesse formato vira texto corrido.
     */
    public static function lerDescricao(?string $desc): array
    {
        $r = ['funcoes' => [], 'indicado' => [], 'linha' => null, 'tamanho' => null, 'texto' => ''];
        $modo = null;
        $soltas = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $desc) as $linha) {
            $l = trim($linha);
            if ($l === '') {
                continue;
            }
            $sem = preg_replace('/^[-•*·–]\s*/u', '', $l);
            if (preg_match('/^(principais\s+)?fun[cç](?:[oõ]es|ao|ão)\s*:?\s*(.*)$/iu', $sem, $m)) {
                $modo = 'funcoes';
                if (trim($m[2]) !== '') {
                    $r['funcoes'] = array_merge($r['funcoes'], self::itens($m[2]));
                }
                continue;
            }
            if (preg_match('/^indicad[oa]s?\s+para\s*:?\s*(.*)$/iu', $sem, $m)) {
                $modo = 'indicado';
                if (trim($m[1]) !== '') {
                    $r['indicado'] = array_merge($r['indicado'], self::itens($m[1]));
                }
                continue;
            }
            if (preg_match('/^linha\s*:\s*(.+)$/iu', $sem, $m)) {
                $r['linha'] = trim($m[1]);
                continue;
            }
            if (preg_match('/^(tamanho|conte[uú]do|volume)\s*:\s*(.+)$/iu', $sem, $m)) {
                $r['tamanho'] = trim($m[2]);
                continue;
            }
            if ($modo) {
                $r[$modo][] = $sem;
            } else {
                $soltas[] = $l;
            }
        }
        $r['texto'] = implode(' ', $soltas);
        return $r;
    }

    private static function itens(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*;\s*/', $s))));
    }

    /** Tamanho tirado do nome ("... 100ML" → "100 ml"). */
    public static function tamanhoDoNome(string $nome): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(ML|G|GR|PCS|PADS|UN|UNID)\b\.?\s*$/i', trim($nome), $m)) {
            $u = strtolower($m[2]);
            $u = ['gr' => 'g', 'unid' => 'un'][$u] ?? $u;
            return $m[1] . ' ' . $u;
        }
        return null;
    }

    /** Nome para o título: sem a marca na frente e sem o tamanho no fim. */
    public static function nomeLimpo(string $nome, string $marca): string
    {
        $n = trim($nome);
        if ($marca !== '' && stripos($n, $marca) === 0) {
            $n = trim(substr($n, strlen($marca)), " -–·");
        }
        $n = preg_replace('/\s*[-–]?\s*\d+(?:[.,]\d+)?\s*(ML|G|GR|PCS|PADS|UN|UNID)\b\.?\s*$/i', '', $n);
        return mb_strtoupper(trim($n));
    }

    // ---------- desenho ----------

    public function Header()
    {
        $this->porPagina[$this->PageNo()] = [$this->claro, $this->etiqueta];
        if ($this->fundo) {
            $this->degrade($this->fundo[0], $this->fundo[1]);
        }
    }

    public function Footer()
    {
        [$claro, $etiqueta] = $this->porPagina[$this->PageNo()] ?? [$this->claro, $this->etiqueta];
        $txt = $claro ? [235, 235, 235] : [40, 40, 40];
        if ($etiqueta !== '') {
            $this->SetXY(10, 280.5);
            $this->SetFont('Helvetica', 'BI', 8);
            $this->cor($txt, 'T');
            $this->Cell(190, 4, self::t(mb_strtoupper($etiqueta)), 0, 0, 'R');
        }
        $this->cor($claro ? [200, 200, 200] : [90, 90, 90], 'D');
        $this->SetLineWidth(0.15);
        $this->Line(10, 285.5, 200, 285.5);
        $this->SetFont('Helvetica', '', 6.5);
        $this->cor($txt, 'T');
        $this->SetXY(10, 287);
        $this->Cell(120, 4, self::t('LUX PERFUMES DISTRIBUIDORA · ' . $this->data), 0, 0, 'L');
        $this->Cell(70, 4, (string) $this->PageNo(), 0, 0, 'R');
    }

    /** Fundo em degradê de cima para baixo (faixas finas). */
    private function degrade(array $a, array $b): void
    {
        $n = 90;
        $h = 297 / $n;
        for ($i = 0; $i < $n; $i++) {
            $f = $i / ($n - 1);
            $this->SetFillColor(
                (int) round($a[0] + ($b[0] - $a[0]) * $f),
                (int) round($a[1] + ($b[1] - $a[1]) * $f),
                (int) round($a[2] + ($b[2] - $a[2]) * $f)
            );
            $this->Rect(0, $i * $h, 210, $h + 0.2, 'F');
        }
    }

    public function CapaSkin(string $titulo, string $subtitulo, string $rodape): void
    {
        $this->fundo = [[13, 13, 14], [10, 10, 11]];
        $this->claro = true;
        $this->etiqueta = '';
        $this->AddPage();
        $logo = $this->id['capa'] ?? null;
        if ($logo && is_file($logo)) {
            $this->Image($logo, 70, 88, 70, 70, '', $this->linkPrincipal());
        }
        $this->cor(self::DOURADO, 'F');
        $this->Rect(75, 168, 60, 0.5, 'F');
        $this->SetXY(10, 174);
        $this->SetFont('Times', 'B', 36);
        $this->cor(self::DOURADO_CLARO, 'T');
        $this->Cell(190, 16, self::t(mb_strtoupper($titulo)), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->cor([235, 235, 235], 'T');
        $this->Cell(190, 9, self::t(self::espacado($subtitulo)), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9.5);
        $this->cor([170, 170, 170], 'T');
        $this->Cell(190, 6, self::t($rodape), 0, 1, 'C');
    }

    public function PaginaLinha(array $linha): void
    {
        $this->fundo = [$linha[2], $linha[3]];
        $this->claro = $linha[4];
        $this->etiqueta = '';
        $this->AddPage();
        $cor = $this->claro ? [255, 255, 255] : [20, 20, 20];
        $this->SetXY(10, 136);
        $this->SetFont('Helvetica', '', 9);
        $this->cor($this->claro ? [245, 245, 245] : [50, 50, 50], 'T');
        $this->Cell(190, 6, 'L I N H A', 0, 1, 'C');
        $tam = 40;
        $this->SetFont('Times', 'BI', $tam);
        while ($tam > 18 && $this->GetStringWidth(self::t(mb_strtoupper($linha[0]))) > 180) {
            $tam -= 2;
            $this->SetFont('Times', 'BI', $tam);
        }
        $this->cor($cor, 'T');
        $this->SetX(10);
        $this->Cell(190, 20, self::t(mb_strtoupper($linha[0])), 0, 1, 'C');
    }

    public function PaginaFinal(): void
    {
        $this->fundo = [[13, 13, 14], [10, 10, 11]];
        $this->claro = true;
        $this->etiqueta = '';
        $this->AddPage();
        $logo = $this->id['capa'] ?? null;
        if ($logo && is_file($logo)) {
            $this->Image($logo, 70, 88, 70, 70, '', $this->linkPrincipal());
        }
        $this->cor(self::DOURADO, 'F');
        $this->Rect(75, 168, 60, 0.5, 'F');
        $this->SetXY(10, 174);
        $this->SetFont('Times', 'B', 28);
        $this->cor(self::DOURADO_CLARO, 'T');
        $this->Cell(190, 14, 'Obrigado', 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(190, 8, self::t('Preços sujeitos a alteração · Consulte disponibilidade'), 0, 1, 'C');
        if (!empty($this->id['whatsapp_txt'])) {
            $this->Ln(6);
            $this->SetFont('Helvetica', 'B', 11);
            $this->Cell(190, 7, self::t('Pedidos pelo WhatsApp: ' . $this->id['whatsapp_txt']), 0, 1, 'C', false, $this->linkWhats());
            $this->SetFont('Helvetica', '', 10);
            $this->cor([210, 210, 210], 'T');
            $this->Cell(190, 6, self::t((string) ($this->id['site_txt'] ?? '')), 0, 1, 'C', false, $this->linkPrincipal());
        }
    }

    /**
     * Um produto. $slot 0 = em cima (foto à esquerda), 1 = embaixo (foto à direita).
     * $sozinho = único da página: card alto e texto no meio, como no catálogo feito à mão.
     */
    public function ProdutoSkin(array $p, int $slot, bool $sozinho, ?string $foto): void
    {
        $claro = $this->claro;
        $corTexto = $claro ? [255, 255, 255] : [17, 17, 17];
        $corSuave = $claro ? [238, 238, 238] : [55, 55, 55];

        if ($sozinho) {
            [$cx, $cy, $cw, $ch] = [12, 12, 88, 262];
            [$tx, $tw] = [108, 92];
        } elseif ($slot === 0) {
            [$cx, $cy, $cw, $ch] = [12, 12, 88, 128];
            [$tx, $tw] = [108, 92];
        } else {
            [$cx, $cy, $cw, $ch] = [110, 147, 88, 128];
            [$tx, $tw] = [12, 92];
        }

        // card branco com a foto
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($cx, $cy, $cw, $ch, 4, 'F');
        if ($foto && is_file($foto) && ($info = @getimagesize($foto))) {
            $max = $cw - 10;
            $esc = min($max / $info[0], ($sozinho ? 120 : $ch - 12) / $info[1]);
            $iw = $info[0] * $esc;
            $ih = $info[1] * $esc;
            $this->Image($foto, $cx + ($cw - $iw) / 2, $cy + ($ch - $ih) / 2, $iw, $ih, '', $p['link'] ?? '');
        } else {
            $this->SetXY($cx, $cy + $ch / 2 - 3);
            $this->SetFont('Helvetica', '', 8);
            $this->cor([170, 170, 170], 'T');
            $this->Cell($cw, 6, self::t('foto em breve'), 0, 0, 'C');
        }
        $esgotado = (float) ($p['qtd'] ?? 0) <= 0;
        if ($esgotado) {
            $this->SetFillColor(17, 17, 17);
            $this->Rect($cx, $cy + $ch / 2 - 7, $cw, 14, 'F');
            $this->SetXY($cx, $cy + $ch / 2 - 7);
            $this->SetFont('Helvetica', 'B', 13);
            $this->cor([255, 255, 255], 'T');
            $this->Cell($cw, 14, 'E S G O T A D O', 0, 0, 'C');
        }

        // ---- monta o texto em linhas para saber a altura e centralizar ----
        $d = $p['skin'];
        $this->SetFont('Times', 'BI', 15.5);
        $tituloLinhas = $this->quebrar(self::t($p['titulo']), $tw, 3);
        $colW = ($tw - 5) / 2;
        $this->SetFont('Times', '', 8.4);
        $col1 = [];
        foreach ($d['funcoes'] as $it) {
            $col1[] = $this->quebrar(self::t($it), $colW - 3.5, 3);
        }
        $col2 = [];
        foreach ($d['indicado'] as $it) {
            $col2[] = $this->quebrar(self::t($it), $colW - 3.5, 3);
        }
        $texto = [];
        if (!$col1 && !$col2 && $d['texto'] !== '') {
            $texto = $this->quebrar(self::t($d['texto']), $tw, $sozinho ? 22 : 9);
        }
        $alt = function (array $col): float {
            $h = 0;
            foreach ($col as $ls) {
                $h += count($ls) * 3.9 + 1.1;
            }
            return $h;
        };
        $hTitulo = count($tituloLinhas) * 6.6;
        $hInfo = ($col1 || $col2) ? 7 + max($alt($col1), $alt($col2)) : ($texto ? 7 + count($texto) * 4 : 0);
        $hTotal = $hTitulo + 6 + 13 + $hInfo;
        $y = $cy + max(4, ($ch - $hTotal) / 2);

        // nome
        $this->SetFont('Times', 'BI', 15.5);
        $this->cor($corTexto, 'T');
        foreach ($tituloLinhas as $l) {
            $this->SetXY($tx, $y);
            $this->Cell($tw, 6.6, $l, 0, 0, 'L', false, $p['link'] ?? '');
            $y += 6.6;
        }
        // tamanho
        $this->SetXY($tx, $y + 0.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->cor($corSuave, 'T');
        $this->Cell($tw, 4.5, self::t((string) ($p['tamanho'] ?? '')), 0, 0, 'L');
        $y += 7;
        // preço ou esgotado
        if ($esgotado) {
            $this->SetFont('Helvetica', 'B', 11);
            $w = $this->GetStringWidth('ESGOTADO') + 8;
            $this->SetFillColor(17, 17, 17);
            $this->RoundedRect($tx, $y + 1.5, $w, 8, 1.5, 'F');
            $this->SetXY($tx, $y + 1.5);
            $this->cor([255, 255, 255], 'T');
            $this->Cell($w, 8, 'ESGOTADO', 0, 0, 'C');
        } else {
            $this->SetXY($tx, $y);
            $this->SetFont('Helvetica', 'B', 18);
            $this->cor($corTexto, 'T');
            $this->Cell($tw, 11, self::t(self::brl($p['valor_venda'])), 0, 0, 'L');
        }
        $y += 13;

        // funções / indicado para (ou texto corrido)
        if ($col1 || $col2) {
            $cols = [[$tx, 'PRINCIPAIS FUNÇÕES', $col1], [$tx + $colW + 5, 'INDICADO PARA', $col2]];
            foreach ($cols as [$x, $tit, $col]) {
                if (!$col) {
                    continue;
                }
                $yy = $y;
                $this->SetXY($x, $yy);
                $this->SetFont('Helvetica', 'B', 7.6);
                $this->cor($corTexto, 'T');
                $this->Cell($colW, 5, self::t($tit), 0, 0, 'L');
                $yy += 7;
                $this->SetFont('Times', '', 8.4);
                $this->cor($corSuave, 'T');
                foreach ($col as $ls) {
                    $this->SetXY($x, $yy);
                    $this->Cell(3.5, 3.9, chr(149), 0, 0, 'L');
                    foreach ($ls as $l) {
                        $this->SetXY($x + 3.5, $yy);
                        $this->Cell($colW - 3.5, 3.9, $l, 0, 0, 'L');
                        $yy += 3.9;
                    }
                    $yy += 1.1;
                }
            }
        } elseif ($texto) {
            $this->SetXY($tx, $y);
            $this->SetFont('Helvetica', 'B', 7.6);
            $this->cor($corTexto, 'T');
            $this->Cell($tw, 5, self::t('DESCRIÇÃO'), 0, 0, 'L');
            $yy = $y + 7;
            $this->SetFont('Times', '', 8.8);
            $this->cor($corSuave, 'T');
            foreach ($texto as $l) {
                $this->SetXY($tx, $yy);
                $this->Cell($tw, 4, $l, 0, 0, 'L');
                $yy += 4;
            }
        }
    }

    private static function espacado(string $s): string
    {
        return implode(' ', preg_split('//u', mb_strtoupper($s), -1, PREG_SPLIT_NO_EMPTY));
    }
}
