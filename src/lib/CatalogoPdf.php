<?php
/**
 * Catálogo em PDF, 12 produtos por página, com três identidades:
 *   lux    — padrão do site (preto + dourado), botão "Ver detalhes" abre o produto em luxatacado.com.br;
 *   bella  — Bella Parfum (preto + prata), contatos próprios, botão "Pedir no WhatsApp" (sem site, sem LUX);
 *   neutro — sem logo, sem nome, sem site e sem contatos (para revendedor repassar ao cliente dele).
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

    /** identidade em uso (lux | bella | neutro) e os dados dela */
    public string $marca = 'lux';
    public array $id = [];
    public string $textoBotao = 'VER DETALHES  >';
    // cores do topo e da capa
    public array $corTopo = self::PRETO;
    public array $corTopoLinha = self::DOURADO;
    public array $corTopoTitulo = self::DOURADO_CLARO;
    public array $corDestaque = self::DOURADO;

    /** Troca a identidade visual. $cfg vem do config.local.php (WhatsApp/Instagram/site da LUX). */
    public function usarMarca(string $marca, array $cfg): void
    {
        $this->marca = $marca;
        if ($marca === 'bella') {
            $prata = [208, 208, 212];
            $this->id = [
                'nome' => 'Bella Parfum',
                'rodape' => 'Bella Parfum · Perfumaria Árabe Atacado',
                'logo' => __DIR__ . '/../assets/pdf/bella_marca.jpg',
                'capa' => __DIR__ . '/../assets/pdf/bella_capa.jpg',
                'whatsapp' => '5583998674968',
                'whatsapp_txt' => '(83) 99867-4968',
                'instagram' => 'bellaparfunatacado',
                'site' => null,
                'site_txt' => null,
            ];
            $this->textoBotao = 'PEDIR NO WHATSAPP';
            $this->corTopo = [17, 15, 13];
            $this->corTopoLinha = [176, 140, 74];
            $this->corTopoTitulo = $prata;
            $this->corDestaque = [176, 140, 74];
            $this->corBotao = [24, 22, 20];
            $this->corBotaoTexto = $prata;
            $this->corSelo = [24, 22, 20];
            $this->corSeloTexto = $prata;
            $this->corFaixaTexto = [120, 92, 40];
        } elseif ($marca === 'neutro') {
            $this->id = ['nome' => null, 'rodape' => null, 'logo' => null, 'capa' => null, 'whatsapp' => null, 'whatsapp_txt' => null, 'instagram' => null, 'site' => null, 'site_txt' => null];
            $this->corTopo = [38, 38, 40];
            $this->corTopoLinha = [168, 154, 134];
            $this->corTopoTitulo = [240, 236, 228];
            $this->corDestaque = [168, 154, 134];
            $this->corSelo = [38, 38, 40];
            $this->corSeloTexto = [240, 236, 228];
            $this->corFaixaTexto = [96, 88, 76];
        } else {
            $this->id = [
                'nome' => 'Lux Perfumes',
                'rodape' => 'Lux Perfumes',
                'logo' => __DIR__ . '/../assets/pdf/logo.jpg',
                'capa' => __DIR__ . '/../assets/pdf/logo.jpg',
                'whatsapp' => $cfg['whatsapp'] ?? null,
                'whatsapp_txt' => '(83) 99945-9629',
                'instagram' => $cfg['instagram'] ?? null,
                'site' => $cfg['site_url'] ?? null,
                'site_txt' => 'luxatacado.com.br',
            ];
        }
    }

    protected function linkWhats(): string
    {
        return !empty($this->id['whatsapp']) ? 'https://wa.me/' . $this->id['whatsapp'] : '';
    }

    protected function linkPrincipal(): string
    {
        if (!empty($this->id['site'])) {
            return (string) $this->id['site'];
        }
        return !empty($this->id['instagram']) ? 'https://instagram.com/' . $this->id['instagram'] : '';
    }

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
        $this->cor($this->corTopo);
        $this->Rect(0, 0, 210, 24, 'F');
        $this->cor($this->corTopoLinha);
        $this->Rect(0, 24, 210, 0.8, 'F');
        $logo = $this->id['logo'] ?? null;
        $xTitulo = 10;
        if ($logo && is_file($logo)) {
            $this->Image($logo, 10, 2, 20, 20, '', $this->linkPrincipal());
            $xTitulo = 34;
        }
        $this->SetXY($xTitulo, 6);
        $this->SetFont('Helvetica', 'B', 13);
        $this->cor($this->corTopoTitulo, 'T');
        $this->Cell(120, 7, self::t(mb_strtoupper($this->titulo)), 0, 2);
        $this->SetFont('Helvetica', '', 8.5);
        $this->cor([200, 200, 200], 'T');
        $this->Cell(120, 5, self::t($this->secao), 0, 0);
        // canto direito: contatos clicáveis (o neutro não tem)
        if (!empty($this->id['whatsapp_txt'])) {
            $this->SetXY(140, 8);
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->cor($this->corTopoTitulo, 'T');
            $this->Cell(60, 5, self::t('Pedidos: ' . $this->id['whatsapp_txt']), 0, 2, 'R', false, $this->linkWhats());
            $this->SetFont('Helvetica', '', 8);
            $this->cor([200, 200, 200], 'T');
            $sub = $this->id['site_txt'] ?: ($this->id['instagram'] ? '@' . $this->id['instagram'] : '');
            $this->Cell(60, 5, self::t($sub), 0, 0, 'R', false, $this->linkPrincipal());
        }
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
        $this->Cell(95, 4, self::t((string) ($this->id['rodape'] ?? '')), 0, 0, 'L');
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
        $this->cor($this->corTopo);
        $this->Rect(0, 0, 210, 297, 'F');
        $logo = $this->id['capa'] ?? null;
        $yTexto = 140;
        if ($this->marca === 'bella' && $logo && is_file($logo)) {
            // logo da Bella em destaque, ocupando o topo da capa
            $this->Image($logo, 30, 18, 150, 150, '', $this->linkPrincipal());
            $yTexto = 176;
        } elseif ($logo && is_file($logo)) {
            $this->Image($logo, 65, 45, 80, 80, '', $this->linkPrincipal());
        } else {
            $yTexto = 105; // sem logo: título mais no meio
        }
        $this->SetY($yTexto);
        $this->SetFont('Helvetica', '', 10);
        $this->cor($this->corTopoTitulo, 'T');
        $this->Cell(0, 6, self::t('C A T Á L O G O'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 30);
        $this->cor($this->marca === 'bella' ? $this->corTopoTitulo : $this->corDestaque, 'T');
        $this->Cell(0, 16, self::t(mb_strtoupper($this->titulo)), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->cor([210, 210, 210], 'T');
        $this->Cell(0, 7, self::t($subtitulo), 0, 1, 'C');
        $this->Ln(4);
        $this->cor($this->corDestaque);
        $this->Rect(95, $this->GetY(), 20, 0.8, 'F');
        $this->Ln(8);
        $this->SetFont('Helvetica', '', 9.5);
        $this->cor([170, 170, 170], 'T');
        $this->Cell(0, 6, self::t('Atualizado em ' . date('d/m/Y \à\s H:i')), 0, 1, 'C');

        // contatos clicáveis (o neutro não tem)
        if (!empty($this->id['whatsapp_txt'])) {
            $this->SetY($this->marca === 'bella' ? 246 : 238);
            $this->SetFont('Helvetica', 'B', 11);
            $this->cor($this->marca === 'bella' ? $this->corTopoTitulo : self::DOURADO_CLARO, 'T');
            $this->Cell(0, 7, self::t('Pedidos pelo WhatsApp: ' . $this->id['whatsapp_txt']), 0, 1, 'C', false, $this->linkWhats());
            $this->SetFont('Helvetica', '', 10);
            $this->cor([210, 210, 210], 'T');
            $linha = array_filter([$this->id['site_txt'], $this->id['instagram'] ? '@' . $this->id['instagram'] : null]);
            $this->Cell(0, 6, self::t(implode('  ·  ', $linha)), 0, 1, 'C', false, $this->linkPrincipal());
            $this->Ln(10);
            $this->SetFont('Helvetica', '', 7.5);
            $this->cor([120, 120, 120], 'T');
            $this->Cell(0, 5, self::t((string) $this->id['rodape']), 0, 1, 'C');
        }
        $this->capa = false;
    }

    // grade: 4 por linha, 3 linhas por página
    const COLS = 4;
    const LINHAS = 3;
    const POR_PAGINA = self::COLS * self::LINHAS;

    /** Preço da Bella: sobre o preço de 20 un. da LUX, arredondado em reais (regra do catálogo antigo dela). */
    public static function precoBella($preco3, float $fator): float
    {
        return (float) $preco3 > 0 ? round((float) $preco3 * $fator) : 0.0;
    }

    const VERMELHO = [205, 38, 45];

    /**
     * Página de texto (ex.: brindes da Black): título e blocos com destaque + descrição.
     * @param array<int, array{0:string,1:string}> $blocos [destaque, texto]
     */
    public function PaginaBlocos(string $secao, string $titulo, array $blocos): void
    {
        $this->secao = $secao;
        $this->AddPage();
        $this->SetXY(10, self::TOPO + 4);
        $this->SetFont('Helvetica', 'B', 18);
        $this->cor(self::PRETO, 'T');
        $this->Cell(190, 10, self::t($titulo), 0, 1, 'L');
        $this->Ln(4);
        foreach ($blocos as [$destaque, $texto]) {
            $y = $this->GetY();
            $this->cor(self::CREME);
            $this->cor($this->corCardBorda, 'D');
            $this->SetLineWidth(0.3);
            $this->RoundedRect(10, $y, 190, 26, 2.5, 'DF');
            $this->cor($this->corDestaque);
            $this->RoundedRect(10, $y, 3.2, 26, 1.2, 'F');
            $this->SetXY(18, $y + 4);
            $this->SetFont('Helvetica', 'B', 14);
            $this->cor(self::PRETO, 'T');
            $this->Cell(178, 8, self::t($destaque), 0, 2);
            $this->SetFont('Helvetica', '', 11);
            $this->cor(self::CINZA, 'T');
            $this->Cell(178, 7, self::t($texto), 0, 2);
            $this->SetY($y + 32);
        }
    }

    /** Card do produto na posição 0..11 da página. $precos: tabela10 | tabela6 | unico | bella | bella10 | promo | nenhum */
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
        // sem preço e sem botão (catálogo neutro): a foto ocupa o espaço que sobraria
        $fh = ($precos === 'nenhum' && empty($p['link'])) ? $h - 18 : 42;
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
        $temLink = !empty($p['link']);
        if (!$temLink) {
            $byb += $bh; // sem botão: os preços descem
        }
        if ($precos === 'promo') {
            // Black/promoção: selo de % na foto, "de R$ x" riscado e "por R$ y" em destaque
            $de = (float) $p['valor_venda'];
            $por = (float) ($p['promo'] ?? 0);
            if ($de > 0 && $por > 0 && $por < $de) {
                $pct = '-' . (int) round((1 - $por / $de) * 100) . '%';
                $this->SetFont('Helvetica', 'B', 7);
                $tw = $this->GetStringWidth($pct) + 4;
                $this->cor(self::VERMELHO);
                $this->RoundedRect($fx + $fw - $tw - 1.5, $fy + 1.5, $tw, 4.6, 1.2, 'F');
                $this->SetXY($fx + $fw - $tw - 1.5, $fy + 1.5);
                $this->cor([255, 255, 255], 'T');
                $this->Cell($tw, 4.6, $pct, 0, 0, 'C');

                $topo = $byb - 13.2;
                $this->SetXY($cx, $topo);
                $this->SetFont('Helvetica', '', 6.2);
                $this->cor(self::CINZA, 'T');
                $rot = self::t('de ');
                $this->Cell($this->GetStringWidth($rot), 4, $rot, 0, 0);
                $val = self::t(self::brl($de));
                $vw = $this->GetStringWidth($val);
                $xv = $this->GetX();
                $this->Cell($vw, 4, $val, 0, 0);
                $this->cor(self::CINZA, 'D');
                $this->SetLineWidth(0.25);
                $this->Line($xv, $topo + 2.1, $xv + $vw, $topo + 2.1);
                $this->Cell(4, 4, self::t(' por'), 0, 2);
                $this->SetXY($cx, $topo + 3.6);
                $this->SetFont('Helvetica', 'B', 11.5);
                $this->cor(self::VERMELHO, 'T');
                $this->Cell($cw, 6, self::t(self::brl($por)), 0, 2);
                $this->SetX($cx);
                $this->SetFont('Helvetica', '', 5);
                $this->cor(self::CINZA, 'T');
                $this->Cell($cw, 2.8, self::t('em qualquer quantidade'), 0, 0);
            } else {
                $this->SetXY($cx, $byb - 7.4);
                $this->SetFont('Helvetica', 'B', 10.5);
                $this->cor(self::PRETO, 'T');
                $this->Cell($cw, 6, self::t(self::brl($de)), 0, 2);
            }
        } elseif ($precos === 'bella' || $precos === 'bella10') {
            $p5 = self::precoBella($p['preco_3'] ?? 0, 1.22);
            $p10 = self::precoBella($p['preco_3'] ?? 0, $precos === 'bella' ? 1.18 : 1.12);
            if ($precos === 'bella10') {
                $this->SetXY($cx, $byb - 7.4);
                $this->SetFont('Helvetica', 'B', 10.5);
                $this->cor(self::PRETO, 'T');
                $this->Cell($cw, 6, self::t($p10 > 0 ? self::brl($p10) : 'Consulte'), 0, 2);
            } else {
                $bw = ($cw - 1.4) / 2;
                $by = $byb - 9.4;
                foreach ([['5 un.', $p5], ['10 un.', $p10]] as $i => [$r, $v]) {
                    $bx = $cx + $i * ($bw + 1.4);
                    $this->cor(self::CREME);
                    $this->cor($this->corFaixaBorda, 'D');
                    $this->RoundedRect($bx, $by, $bw, 8.2, 1.1, 'DF');
                    $this->SetXY($bx, $by + 0.6);
                    $this->SetFont('Helvetica', '', 5.4);
                    $this->cor(self::CINZA, 'T');
                    $this->Cell($bw, 2.8, self::t($r), 0, 2, 'C');
                    $this->SetFont('Helvetica', 'B', 7.6);
                    $this->cor(self::PRETO, 'T');
                    $this->Cell($bw, 4, self::t($v > 0 ? self::brl($v) : 'Consulte'), 0, 0, 'C');
                }
            }
        } elseif ($precos !== 'nenhum') {
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

        // botão: "Ver detalhes" (site da LUX) ou "Pedir no WhatsApp" (Bella); o neutro não tem
        if (!$temLink) {
            return;
        }
        $this->cor($this->corBotao);
        $this->RoundedRect($cx, $byb, $cw, $bh, 1.5, 'F');
        $this->SetXY($cx, $byb);
        $this->SetFont('Helvetica', 'B', 6.4);
        $this->cor($this->corBotaoTexto, 'T');
        $this->Cell($cw, $bh, self::t($this->textoBotao), 0, 0, 'C', false, $p['link']);
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
