<?php
/**
 * Catálogo em PDF no padrão visual do site (preto + dourado), 4 produtos por página.
 * Cada card tem o botão "Ver detalhes" que abre a página do produto no site.
 */

class CatalogoPdf extends FPDF
{
    // paleta do site
    const PRETO = [11, 11, 12];
    const DOURADO = [201, 162, 77];
    const DOURADO_CLARO = [231, 200, 120];
    const DOURADO_ESCURO = [156, 122, 46];
    const CREME = [250, 248, 243];
    const FUNDO_FOTO = [247, 245, 240];
    const LINHA = [236, 231, 220];
    const CINZA = [115, 115, 115];

    public string $titulo = '';
    public string $secao = '';
    public array $cfg = [];
    public bool $capa = false;
    public int $paginaCapa = 0;

    // cores do card (o catálogo colorido da Ciclo troca estas; os outros ficam no padrão preto + dourado)
    public array $corCardBorda = self::LINHA;
    public array $corBotao = [214, 176, 92];
    public array $corBotaoTexto = self::PRETO;
    public array $corSelo = self::PRETO;
    public array $corSeloTexto = self::DOURADO_CLARO;
    public array $corFaixaTexto = self::DOURADO_ESCURO;
    public array $corFaixaBorda = self::LINHA;

    // área útil das páginas de produtos
    const TOPO = 30;
    const BASE = 278;

    public static function t(?string $s): string
    {
        $s = (string) $s;
        $r = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
        return $r === false ? utf8_decode($s) : $r;
    }

    public static function brl($v): string
    {
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }

    protected function cor(array $c, string $tipo = 'F'): void
    {
        if ($tipo === 'F') {
            $this->SetFillColor($c[0], $c[1], $c[2]);
        } elseif ($tipo === 'D') {
            $this->SetDrawColor($c[0], $c[1], $c[2]);
        } else {
            $this->SetTextColor($c[0], $c[1], $c[2]);
        }
    }

    /** Retângulo com cantos arredondados (script clássico do FPDF). */
    public function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        $op = $style == 'F' ? 'f' : ($style == 'FD' || $style == 'DF' ? 'B' : 'S');
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k
        ));
    }

    public function Header()
    {
        if ($this->capa) {
            return;
        }
        $this->cor(self::PRETO);
        $this->Rect(0, 0, 210, 24, 'F');
        $this->cor(self::DOURADO);
        $this->Rect(0, 24, 210, 0.8, 'F');
        $logo = __DIR__ . '/../assets/pdf/logo.jpg';
        if (is_file($logo)) {
            $this->Image($logo, 10, 2, 20, 20, '', $this->cfg['site_url'] ?? '');
        }
        $this->SetXY(34, 6);
        $this->SetFont('Helvetica', 'B', 13);
        $this->cor(self::DOURADO_CLARO, 'T');
        $this->Cell(120, 7, self::t(mb_strtoupper($this->titulo)), 0, 2);
        $this->SetFont('Helvetica', '', 8.5);
        $this->cor([200, 200, 200], 'T');
        $this->Cell(120, 5, self::t($this->secao), 0, 0);
        // canto direito: WhatsApp clicável
        $this->SetXY(140, 8);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->cor(self::DOURADO_CLARO, 'T');
        $wa = 'https://wa.me/' . ($this->cfg['whatsapp'] ?? '');
        $this->Cell(60, 5, self::t('Pedidos: (83) 99945-9629'), 0, 2, 'R', false, $wa);
        $this->SetFont('Helvetica', '', 8);
        $this->cor([200, 200, 200], 'T');
        $this->Cell(60, 5, self::t('luxatacado.com.br'), 0, 0, 'R', false, $this->cfg['site_url'] ?? '');
    }

    public function Footer()
    {
        if ($this->capa || $this->PageNo() === $this->paginaCapa) {
            return;
        }
        $this->cor(self::LINHA, 'D');
        $this->SetLineWidth(0.3);
        $this->Line(10, 281, 200, 281);
        $this->SetY(282.5);
        $this->SetFont('Helvetica', '', 7.5);
        $this->cor(self::CINZA, 'T');
        $this->Cell(95, 4, self::t('Lux Perfumes'), 0, 0, 'L');
        $this->Cell(95, 4, self::t('Página ' . ($this->PageNo() - 1) . ' · Atualizado em ' . date('d/m/Y')), 0, 1, 'R');
        $this->SetFont('Helvetica', 'I', 6.8);
        $this->Cell(190, 4, self::t('*A disponibilidade dos itens e o preço devem ser consultados no dia da compra.'), 0, 0, 'C');
    }

    /** Capa: fundo preto, logo e nome do catálogo. */
    public function Capa(string $subtitulo, int $qtd): void
    {
        $this->capa = true;
        $this->AddPage();
        $this->paginaCapa = $this->PageNo();
        $this->cor(self::PRETO);
        $this->Rect(0, 0, 210, 297, 'F');
        $logo = __DIR__ . '/../assets/pdf/logo.jpg';
        if (is_file($logo)) {
            $this->Image($logo, 65, 45, 80, 80, '', $this->cfg['site_url'] ?? '');
        }
        $this->SetY(140);
        $this->SetFont('Helvetica', '', 10);
        $this->cor(self::DOURADO_CLARO, 'T');
        $this->Cell(0, 6, self::t('C A T Á L O G O'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 30);
        $this->cor(self::DOURADO, 'T');
        $this->Cell(0, 16, self::t(mb_strtoupper($this->titulo)), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->cor([210, 210, 210], 'T');
        $this->Cell(0, 7, self::t($subtitulo), 0, 1, 'C');
        $this->Ln(4);
        $this->cor(self::DOURADO);
        $this->Rect(95, $this->GetY(), 20, 0.8, 'F');
        $this->Ln(8);
        $this->SetFont('Helvetica', '', 9.5);
        $this->cor([170, 170, 170], 'T');
        $this->Cell(0, 6, self::t('Atualizado em ' . date('d/m/Y \à\s H:i')), 0, 1, 'C');

        // contatos clicáveis
        $this->SetY(238);
        $this->SetFont('Helvetica', 'B', 11);
        $this->cor(self::DOURADO_CLARO, 'T');
        $this->Cell(0, 7, self::t('Pedidos pelo WhatsApp: (83) 99945-9629'), 0, 1, 'C', false, 'https://wa.me/' . ($this->cfg['whatsapp'] ?? ''));
        $this->SetFont('Helvetica', '', 10);
        $this->cor([210, 210, 210], 'T');
        $this->Cell(0, 6, self::t('luxatacado.com.br  ·  @' . ($this->cfg['instagram'] ?? '')), 0, 1, 'C', false, $this->cfg['site_url'] ?? '');
        $this->Ln(10);
        $this->SetFont('Helvetica', '', 7.5);
        $this->cor([120, 120, 120], 'T');
        $this->Cell(0, 5, self::t('Lux Perfumes'), 0, 1, 'C');
        $this->capa = false;
    }

    // grade: 4 por linha, 3 linhas por página
    const COLS = 4;
    const LINHAS = 3;
    const POR_PAGINA = self::COLS * self::LINHAS;

    /** Card do produto na posição 0..11 da página. $precos: tabela10 | tabela6 | unico | nenhum */
    public function Card(array $p, int $pos, string $precos, ?string $foto): void
    {
        $gx = 4;
        $gy = 5;
        $w = (190 - $gx * (self::COLS - 1)) / self::COLS;
        $h = (self::BASE - self::TOPO - $gy * (self::LINHAS - 1)) / self::LINHAS;
        $x = 10 + ($pos % self::COLS) * ($w + $gx);
        $y = self::TOPO + intdiv($pos, self::COLS) * ($h + $gy);

        $this->cor([255, 255, 255]);
        $this->cor($this->corCardBorda, 'D');
        $this->SetLineWidth(0.25);
        $this->RoundedRect($x, $y, $w, $h, 2.2, 'DF');

        // foto
        $fx = $x + 2;
        $fy = $y + 2;
        $fw = $w - 4;
        $fh = 42;
        $this->cor(self::FUNDO_FOTO);
        $this->RoundedRect($fx, $fy, $fw, $fh, 1.6, 'F');
        if ($foto && is_file($foto)) {
            [$iw, $ih] = @getimagesize($foto) ?: [1, 1];
            $esc = min(($fw - 4) / $iw, ($fh - 4) / $ih);
            $dw = $iw * $esc;
            $dh = $ih * $esc;
            $this->Image($foto, $fx + ($fw - $dw) / 2, $fy + ($fh - $dh) / 2, $dw, $dh, 'JPG', $p['link']);
        }
        if (!empty($p['sexo'])) {
            $txt = self::t(mb_strtoupper($p['sexo']));
            $this->SetFont('Helvetica', 'B', 4.8);
            $tw = $this->GetStringWidth($txt) + 3.4;
            $this->cor($this->corSelo);
            $this->RoundedRect($fx + 1.5, $fy + 1.5, $tw, 3.6, 1.8, 'F');
            $this->cor($this->corSeloTexto, 'T');
            $this->SetXY($fx + 1.5, $fy + 1.5);
            $this->Cell($tw, 3.6, $txt, 0, 0, 'C');
        }

        // nome (2 linhas) e marca
        $cx = $x + 2.5;
        $cw = $w - 5;
        $this->SetXY($cx, $fy + $fh + 2);
        $this->SetFont('Helvetica', 'B', 7.2);
        $this->cor(self::PRETO, 'T');
        foreach ($this->quebrar(self::t(mb_strtoupper(trim($p['nome']))), $cw, 2) as $l) {
            $this->SetX($cx);
            $this->Cell($cw, 3.4, $l, 0, 2, 'L', false, $p['link']);
        }
        $this->SetX($cx);
        $this->SetFont('Helvetica', '', 6);
        $this->cor(self::CINZA, 'T');
        $this->Cell($cw, 3.4, self::t($this->cortar(self::t($p['marca'] ?: ''), $cw)), 0, 2);

        // preços (mesmas regras do catálogo antigo)
        $bh = 6.2;
        $byb = $y + $h - $bh - 2.2;
        if ($precos !== 'nenhum') {
            $faixas = [];
            if ($precos === 'tabela10') {
                $faixas = [['10 un.', $p['preco_2']], ['20 un.', $p['preco_3']]];
            } elseif ($precos === 'tabela6') {
                $faixas = [['6 un.', $p['preco_2']], ['12 un.', $p['preco_3']]];
            }
            $faixas = array_values(array_filter($faixas, fn ($f) => (float) $f[1] > 0));
            $topoPreco = $byb - ($faixas ? 7.6 : 0) - 6.2;
            $this->SetXY($cx, $topoPreco);
            if ($precos === 'tabela6') {
                $this->SetFont('Helvetica', '', 5.6);
                $this->cor(self::CINZA, 'T');
                $rot = self::t('Unitário ');
                $this->Cell($this->GetStringWidth($rot) + 0.5, 6, $rot, 0, 0);
            }
            $this->SetFont('Helvetica', 'B', 10.5);
            $this->cor(self::PRETO, 'T');
            $this->Cell($cw, 6, self::t(self::brl($p['valor_venda'])), 0, 2);
            if ($faixas) {
                $bw = ($cw - 1.4) / 2;
                $by = $byb - 7.2;
                foreach ($faixas as $i => [$r, $v]) {
                    $bx = $cx + $i * ($bw + 1.4);
                    $this->cor(self::CREME);
                    $this->cor($this->corFaixaBorda, 'D');
                    $this->RoundedRect($bx, $by, $bw, 6.2, 1.1, 'DF');
                    $this->SetXY($bx, $by + 0.4);
                    $this->SetFont('Helvetica', '', 4.8);
                    $this->cor(self::CINZA, 'T');
                    $this->Cell($bw, 2.4, self::t($r), 0, 2, 'C');
                    $this->SetFont('Helvetica', 'B', 6.4);
                    $this->cor($this->corFaixaTexto, 'T');
                    $this->Cell($bw, 3, self::t(self::brl($v)), 0, 0, 'C');
                }
            }
        }

        // botão "Ver detalhes" (abre a página do produto no site)
        $this->cor($this->corBotao);
        $this->RoundedRect($cx, $byb, $cw, $bh, 1.5, 'F');
        $this->SetXY($cx, $byb);
        $this->SetFont('Helvetica', 'B', 6.4);
        $this->cor($this->corBotaoTexto, 'T');
        $this->Cell($cw, $bh, self::t('VER DETALHES  >'), 0, 0, 'C', false, $p['link']);
        $this->Link($cx, $byb, $cw, $bh, $p['link']);
    }

    protected function cortar(string $txt, float $w): string
    {
        if ($this->GetStringWidth($txt) <= $w) {
            return $txt;
        }
        while ($txt !== '' && $this->GetStringWidth($txt . '...') > $w) {
            $txt = substr($txt, 0, -1);
        }
        return $txt . '...';
    }

    /** Quebra o texto em até $max linhas que cabem na largura (com "..." se sobrar). */
    protected function quebrar(string $txt, float $w, int $max): array
    {
        $palavras = preg_split('/\s+/', $txt);
        $linhas = [];
        $atual = '';
        foreach ($palavras as $pal) {
            $teste = $atual === '' ? $pal : $atual . ' ' . $pal;
            if ($this->GetStringWidth($teste) <= $w) {
                $atual = $teste;
            } else {
                if ($atual !== '') {
                    $linhas[] = $atual;
                }
                $atual = $pal;
            }
        }
        if ($atual !== '') {
            $linhas[] = $atual;
        }
        if (count($linhas) > $max) {
            $linhas = array_slice($linhas, 0, $max);
            $ult = $linhas[$max - 1];
            while ($ult !== '' && $this->GetStringWidth($ult . '...') > $w) {
                $ult = substr($ult, 0, -1);
            }
            $linhas[$max - 1] = rtrim($ult) . '...';
        }
        return $linhas;
    }
}
