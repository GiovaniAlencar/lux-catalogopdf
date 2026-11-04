<?php
/**
 * Catálogo da Bella Parfum — layout próprio, diferente do da LUX:
 *   papel marfim, letras serifadas (como a logo), topo claro com fio duplo dourado,
 *   2 colunas × 5 linhas de cards horizontais (foto à esquerda), preços em estilo
 *   "cardápio" com pontilhado e link discreto "Pedir pelo WhatsApp".
 * Usa os dados da identidade "bella" definidos em CatalogoPdf::usarMarca().
 */
class CatalogoBellaPdf extends CatalogoPdf
{
    const MARFIM = [249, 246, 239];
    const GRAFITE = [28, 26, 24];
    const PRATA_ESCURA = [96, 96, 102];
    const OURO = [172, 136, 70];
    const OURO_CLARO = [214, 196, 160];
    const FUNDO_FOTO_B = [241, 236, 226];

    // grade: 2 por linha, 5 linhas por página
    const COLS = 2;
    const LINHAS = 5;
    const POR_PAGINA = self::COLS * self::LINHAS;
    const TOPO = 33;
    const BASE = 274;

    public function Header()
    {
        if ($this->capa) {
            return;
        }
        $this->cor(self::MARFIM);
        $this->Rect(0, 0, 210, 297, 'F');

        $logo = $this->id['logo'] ?? null;
        if ($logo && is_file($logo)) {
            $this->Image($logo, 12, 5, 19, 19, '', $this->linkPrincipal());
        }
        // título centralizado, serifado
        $this->SetXY(40, 7);
        $this->SetFont('Times', 'I', 17);
        $this->cor(self::GRAFITE, 'T');
        $this->Cell(130, 8, self::t($this->titulo), 0, 2, 'C');
        $this->SetFont('Times', '', 8.5);
        $this->cor(self::OURO, 'T');
        $this->Cell(130, 5, self::t($this->espacar(mb_strtoupper($this->secao))), 0, 0, 'C');
        // contatos à direita
        $this->SetXY(150, 8.5);
        $this->SetFont('Times', 'B', 9);
        $this->cor(self::GRAFITE, 'T');
        $this->Cell(48, 5, self::t((string) ($this->id['whatsapp_txt'] ?? '')), 0, 2, 'R', false, $this->linkWhats());
        $this->SetFont('Times', 'I', 8.5);
        $this->cor(self::PRATA_ESCURA, 'T');
        $this->Cell(48, 5, self::t(!empty($this->id['instagram']) ? '@' . $this->id['instagram'] : ''), 0, 0, 'R', false, $this->linkPrincipal());
        // fio duplo dourado com losango no meio
        $this->fioOrnamento(27.5);
    }

    public function Footer()
    {
        if ($this->capa || $this->PageNo() === $this->paginaCapa) {
            return;
        }
        $this->cor(self::OURO_CLARO, 'D');
        $this->SetLineWidth(0.2);
        $this->Line(60, 281, 150, 281);
        $this->SetY(282.5);
        $this->SetFont('Times', 'I', 8);
        $this->cor(self::PRATA_ESCURA, 'T');
        $this->Cell(0, 4, self::t('Bella Parfum  ·  Perfumaria Árabe Atacado  ·  página ' . ($this->PageNo() - 1)), 0, 1, 'C');
        $this->SetFont('Times', '', 6.8);
        $this->Cell(0, 4, self::t('Atualizado em ' . date('d/m/Y') . '. Disponibilidade e preços devem ser confirmados no dia do pedido.'), 0, 0, 'C');
    }

    /** Capa: logo inteira, nome do catálogo em serifa e contatos. */
    public function Capa(string $subtitulo, int $qtd): void
    {
        $this->capa = true;
        $this->AddPage();
        $this->paginaCapa = $this->PageNo();
        $this->cor([17, 15, 13]);
        $this->Rect(0, 0, 210, 297, 'F');
        $logo = $this->id['capa'] ?? null;
        if ($logo && is_file($logo)) {
            $this->Image($logo, 30, 16, 150, 150, '', $this->linkPrincipal());
        }
        $this->SetY(178);
        $this->SetFont('Times', '', 10);
        $this->cor(self::OURO_CLARO, 'T');
        $this->Cell(0, 6, self::t($this->espacar('CATÁLOGO')), 0, 1, 'C');
        $this->SetFont('Times', 'I', 34);
        $this->cor([226, 226, 230], 'T');
        $this->Cell(0, 16, self::t($this->titulo), 0, 1, 'C');
        $this->SetFont('Times', '', 12);
        $this->cor([190, 190, 196], 'T');
        $this->Cell(0, 7, self::t($subtitulo), 0, 1, 'C');
        $this->Ln(3);
        $this->fioOrnamento($this->GetY() + 2, 70, 140, [172, 136, 70]);
        $this->Ln(9);
        $this->SetFont('Times', 'I', 10);
        $this->cor([150, 150, 156], 'T');
        $this->Cell(0, 6, self::t('Atualizado em ' . date('d/m/Y \à\s H:i')), 0, 1, 'C');

        $this->SetY(246);
        $this->SetFont('Times', 'B', 13);
        $this->cor([226, 226, 230], 'T');
        $this->Cell(0, 7, self::t('Pedidos pelo WhatsApp: ' . ($this->id['whatsapp_txt'] ?? '')), 0, 1, 'C', false, $this->linkWhats());
        $this->SetFont('Times', 'I', 11);
        $this->cor([190, 190, 196], 'T');
        $this->Cell(0, 6, self::t('@' . ($this->id['instagram'] ?? '')), 0, 1, 'C', false, $this->linkPrincipal());
        $this->capa = false;
    }

    /** Card horizontal: foto à esquerda, nome/marca e preços pontilhados à direita. */
    public function Card(array $p, int $pos, string $precos, ?string $foto): void
    {
        $gx = 6;
        $gy = 3.5;
        $w = (186 - $gx * (self::COLS - 1)) / self::COLS;
        $h = (self::BASE - self::TOPO - $gy * (self::LINHAS - 1)) / self::LINHAS;
        $x = 12 + ($pos % self::COLS) * ($w + $gx);
        $y = self::TOPO + intdiv($pos, self::COLS) * ($h + $gy);
        $link = (string) ($p['link'] ?? '');

        // fundo branco, sem cantos arredondados, filete dourado embaixo
        $this->cor([255, 255, 255]);
        $this->Rect($x, $y, $w, $h, 'F');
        $this->cor(self::OURO_CLARO);
        $this->Rect($x, $y + $h - 0.6, $w, 0.6, 'F');

        // foto
        $fs = $h - 6;
        $fx = $x + 3;
        $fy = $y + 3;
        $this->cor(self::FUNDO_FOTO_B);
        $this->Rect($fx, $fy, $fs, $fs, 'F');
        if ($foto && is_file($foto)) {
            [$iw, $ih] = @getimagesize($foto) ?: [1, 1];
            $esc = min(($fs - 3) / $iw, ($fs - 3) / $ih);
            $dw = $iw * $esc;
            $dh = $ih * $esc;
            $this->Image($foto, $fx + ($fs - $dw) / 2, $fy + ($fs - $dh) / 2, $dw, $dh, 'JPG', $link);
        }

        // textos
        $tx = $fx + $fs + 4;
        $tw = $x + $w - 3 - $tx;
        $this->SetXY($tx, $y + 3.5);
        if (!empty($p['sexo'])) {
            $this->SetFont('Times', '', 6.5);
            $this->cor(self::OURO, 'T');
            $this->Cell($tw, 3.2, self::t($this->espacar(mb_strtoupper($p['sexo']))), 0, 2);
            $this->Ln(0.6);
        }
        $this->SetFont('Times', 'B', 9.6);
        $this->cor(self::GRAFITE, 'T');
        foreach ($this->quebrar(self::t(trim($this->titulo2($p['nome']))), $tw, 2) as $l) {
            $this->SetX($tx);
            $this->Cell($tw, 4.2, $l, 0, 2, 'L', false, $link);
        }
        $this->SetX($tx);
        $this->SetFont('Times', 'I', 8);
        $this->cor(self::PRATA_ESCURA, 'T');
        $this->Cell($tw, 3.8, self::t($this->cortar(self::t($p['marca'] ?: ''), $tw)), 0, 2);

        // preços estilo cardápio, de baixo para cima
        $yLink = $y + $h - 7.6;
        $linhas = [];
        if ($precos === 'bella10') {
            $v = self::precoBella($p['preco_3'] ?? 0, 1.12);
            $linhas[] = ['Preço', $v];
        } elseif ($precos === 'bella') {
            $linhas[] = ['5 unidades', self::precoBella($p['preco_3'] ?? 0, 1.22)];
            $linhas[] = ['10 unidades', self::precoBella($p['preco_3'] ?? 0, 1.18)];
        }
        $yp = $yLink - 1.5 - count($linhas) * 4.6;
        foreach ($linhas as [$rot, $v]) {
            $this->pontilhado($tx, $yp, $tw, $rot, $v > 0 ? self::brl($v) : 'Consulte');
            $yp += 4.6;
        }

        // link discreto para o WhatsApp
        if ($link !== '') {
            $this->SetXY($tx, $yLink);
            $this->SetFont('Times', 'I', 8);
            $this->cor(self::OURO, 'T');
            $txt = self::t('Pedir pelo WhatsApp  ›');
            $lw = $this->GetStringWidth($txt);
            $this->Cell($lw, 4.4, $txt, 0, 0, 'L', false, $link);
            $this->cor(self::OURO, 'D');
            $this->SetLineWidth(0.15);
            $this->Line($tx, $yLink + 4, $tx + $lw, $yLink + 4);
        }
    }

    /** "5 unidades ........ R$ 171,00" */
    private function pontilhado(float $x, float $y, float $w, string $rot, string $valor): void
    {
        $this->SetFont('Times', '', 8.4);
        $this->cor(self::PRATA_ESCURA, 'T');
        $r = self::t($rot);
        $rw = $this->GetStringWidth($r);
        $this->SetXY($x, $y);
        $this->Cell($rw, 4.4, $r, 0, 0);
        $this->SetFont('Times', 'B', 10.2);
        $this->cor(self::GRAFITE, 'T');
        $v = self::t($valor);
        $vw = $this->GetStringWidth($v);
        $this->SetXY($x + $w - $vw, $y);
        $this->Cell($vw, 4.4, $v, 0, 0, 'R');
        // pontinhos entre o rótulo e o preço
        $this->cor(self::OURO_CLARO);
        for ($px = $x + $rw + 1.5; $px < $x + $w - $vw - 1.5; $px += 1.3) {
            $this->Rect($px, $y + 3.1, 0.35, 0.35, 'F');
        }
    }

    /** Fio duplo dourado com um losango no centro. */
    private function fioOrnamento(float $y, float $x1 = 12, float $x2 = 198, ?array $cor = null): void
    {
        $c = $cor ?? self::OURO;
        $this->cor($c, 'D');
        $this->cor($c);
        $meio = ($x1 + $x2) / 2;
        $this->SetLineWidth(0.35);
        $this->Line($x1, $y, $meio - 4, $y);
        $this->Line($meio + 4, $y, $x2, $y);
        $this->SetLineWidth(0.12);
        $this->Line($x1, $y + 0.9, $meio - 4, $y + 0.9);
        $this->Line($meio + 4, $y + 0.9, $x2, $y + 0.9);
        // losango
        $s = 1.6;
        $k = $this->k;
        $hp = $this->h;
        $yc = $y + 0.45;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l f',
            $meio * $k, ($hp - ($yc - $s)) * $k,
            ($meio + $s) * $k, ($hp - $yc) * $k,
            $meio * $k, ($hp - ($yc + $s)) * $k,
            ($meio - $s) * $k, ($hp - $yc) * $k));
    }

    /** Letras espaçadas para os rótulos em caixa alta. */
    private function espacar(string $s): string
    {
        return trim(implode(' ', preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY)));
    }

    /** Nome do produto em "Título" (fica mais elegante em serifa do que tudo maiúsculo). */
    private function titulo2(string $nome): string
    {
        $t = mb_convert_case(mb_strtolower(trim($nome)), MB_CASE_TITLE);
        // mantém volumes e siglas: 100ml → 100ML, Edp → EDP
        return preg_replace_callback('/\b(\d+\s?ml|edp|edt|edc|usa|uae)\b/iu', fn ($m) => mb_strtoupper($m[0]), $t);
    }
}
