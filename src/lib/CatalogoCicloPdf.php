<?php
/**
 * Catálogo colorido da Ciclo (linha nacional): cada seção tem a sua cor de fundo,
 * bolhas decorativas e o Kids com confete. Mesma grade e cards do catálogo padrão.
 */
require_once __DIR__ . '/CatalogoPdf.php';

class CatalogoCicloPdf extends CatalogoPdf
{
    // paleta alegre (seções)
    const TEMAS = [
        'kids'       => ['fundo' => [255, 246, 214], 'forte' => [255, 99, 132], 'bolhas' => [[255, 221, 120], [255, 190, 205], [160, 222, 255], [190, 240, 200]]],
        'colonia'    => ['fundo' => [240, 234, 255], 'forte' => [124, 77, 255], 'bolhas' => [[214, 200, 255], [255, 214, 236], [200, 230, 255]]],
        'splash'     => ['fundo' => [255, 232, 242], 'forte' => [233, 30, 120], 'bolhas' => [[255, 200, 222], [255, 226, 180], [220, 205, 255]]],
        'hidratante' => ['fundo' => [224, 247, 238], 'forte' => [0, 158, 124], 'bolhas' => [[190, 236, 216], [205, 232, 255], [255, 240, 190]]],
        'outros'     => ['fundo' => [228, 242, 253], 'forte' => [25, 118, 210], 'bolhas' => [[200, 228, 252], [255, 228, 200], [210, 240, 220]]],
    ];
    const ARCO_IRIS = [[255, 99, 132], [255, 159, 64], [255, 205, 86], [75, 192, 140], [54, 162, 235], [153, 102, 255]];
    const TEXTO = [45, 34, 74];

    public string $secaoChave = 'outros';

    public function tema(): array
    {
        return self::TEMAS[$this->secaoChave] ?? self::TEMAS['outros'];
    }

    /** Aplica as cores da seção nos cards (chamar antes do AddPage da seção). */
    public function usarSecao(string $chave, string $nome): void
    {
        $this->secaoChave = isset(self::TEMAS[$chave]) ? $chave : 'outros';
        $this->secao = $nome;
        $t = $this->tema();
        $forte = $t['forte'];
        $this->corCardBorda = $this->misturar($forte, [255, 255, 255], 0.55);
        $this->corBotao = $forte;
        $this->corBotaoTexto = [255, 255, 255];
        $this->corSelo = $forte;
        $this->corSeloTexto = [255, 255, 255];
        $this->corFaixaTexto = $this->misturar($forte, [0, 0, 0], 0.25);
        $this->corFaixaBorda = $this->misturar($forte, [255, 255, 255], 0.7);
    }

    private function misturar(array $a, array $b, float $p): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $p),
            (int) round($a[1] + ($b[1] - $a[1]) * $p),
            (int) round($a[2] + ($b[2] - $a[2]) * $p),
        ];
    }

    /** Círculo preenchido (FPDF não tem nativo). */
    public function Circulo(float $x, float $y, float $r): void
    {
        $k = $this->k;
        $h = $this->h;
        $m = 4 / 3 * (sqrt(2) - 1) * $r;
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($h - $y) * $k));
        $pts = [
            [$x + $r, $y - $m, $x + $m, $y - $r, $x, $y - $r],
            [$x - $m, $y - $r, $x - $r, $y - $m, $x - $r, $y],
            [$x - $r, $y + $m, $x - $m, $y + $r, $x, $y + $r],
            [$x + $m, $y + $r, $x + $r, $y + $m, $x + $r, $y],
        ];
        foreach ($pts as $p) {
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $p[0] * $k, ($h - $p[1]) * $k, $p[2] * $k, ($h - $p[3]) * $k, $p[4] * $k, ($h - $p[5]) * $k));
        }
        $this->_out('f');
    }

    private function faixaArcoIris(float $y, float $alt): void
    {
        $n = count(self::ARCO_IRIS);
        $w = 210 / $n;
        foreach (self::ARCO_IRIS as $i => $c) {
            $this->cor($c);
            $this->Rect($i * $w, $y, $w + 0.1, $alt, 'F');
        }
    }

    /** Confete determinístico (sempre igual em cada página da seção). */
    private function confete(int $semente, int $qtd, float $y0, float $y1): void
    {
        mt_srand($semente);
        for ($i = 0; $i < $qtd; $i++) {
            $c = self::ARCO_IRIS[mt_rand(0, count(self::ARCO_IRIS) - 1)];
            $this->cor($this->misturar($c, [255, 255, 255], 0.35));
            $x = mt_rand(20, 2080) / 10;
            $y = mt_rand((int) ($y0 * 10), (int) ($y1 * 10)) / 10;
            if (mt_rand(0, 1)) {
                $this->Circulo($x, $y, mt_rand(6, 14) / 10);
            } else {
                $this->Rect($x, $y, mt_rand(12, 24) / 10, mt_rand(6, 10) / 10, 'F');
            }
        }
        mt_srand();
    }

    public function Header()
    {
        if ($this->capa) {
            return;
        }
        $t = $this->tema();

        // fundo da página na cor da seção + bolhas (ficam atrás dos cards)
        $this->cor($t['fundo']);
        $this->Rect(0, 0, 210, 297, 'F');
        $b = $t['bolhas'];
        $this->cor($b[0]);
        $this->Circulo(-8, 120, 34);
        $this->cor($b[1 % count($b)]);
        $this->Circulo(218, 200, 40);
        $this->cor($b[2 % count($b)]);
        $this->Circulo(40, 300, 26);
        $this->cor($b[0]);
        $this->Circulo(185, 60, 16);
        if ($this->secaoChave === 'kids') {
            $this->confete(7, 70, 30, 278);
        }

        // topo colorido
        $this->cor($t['forte']);
        $this->Rect(0, 0, 210, 23, 'F');
        $this->faixaArcoIris(23, 1.6);
        $logo = __DIR__ . '/../assets/pdf/logo.jpg';
        if (is_file($logo)) {
            $this->Image($logo, 10, 2.5, 18, 18, '', $this->cfg['site_url'] ?? '');
        }
        $this->SetXY(32, 5);
        $this->SetFont('Helvetica', 'B', 14);
        $this->cor([255, 255, 255], 'T');
        $this->Cell(110, 7, self::t(mb_strtoupper($this->titulo)), 0, 2);
        $this->SetFont('Helvetica', 'B', 9);
        $this->cor($this->misturar($t['forte'], [255, 255, 255], 0.75), 'T');
        $this->Cell(110, 5, self::t($this->secao), 0, 0);
        $this->SetXY(140, 7);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->cor([255, 255, 255], 'T');
        $this->Cell(60, 5, self::t('Pedidos: (83) 99945-9629'), 0, 2, 'R', false, 'https://wa.me/' . ($this->cfg['whatsapp'] ?? ''));
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(60, 5, self::t('luxatacado.com.br'), 0, 0, 'R', false, $this->cfg['site_url'] ?? '');
    }

    public function Footer()
    {
        if ($this->capa || $this->PageNo() === $this->paginaCapa) {
            return;
        }
        $this->SetY(282.5);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->cor(self::TEXTO, 'T');
        $this->Cell(95, 4, self::t('Lux Perfumes · Ciclo Cosméticos'), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 7.5);
        $this->Cell(95, 4, self::t('Página ' . ($this->PageNo() - 1) . ' · Atualizado em ' . date('d/m/Y')), 0, 1, 'R');
        $this->SetFont('Helvetica', 'I', 6.8);
        $this->Cell(190, 4, self::t('*A disponibilidade dos itens e o preço devem ser consultados no dia da compra.'), 0, 0, 'C');
        $this->faixaArcoIris(295.4, 1.6);
    }

    /** Capa colorida. $secoes = nomes das seções presentes (ex.: Kids, Body Splash...). */
    public function CapaCiclo(string $subtitulo, int $qtd, array $secoes): void
    {
        $this->capa = true;
        $this->AddPage();
        $this->paginaCapa = $this->PageNo();

        $this->cor([255, 250, 240]);
        $this->Rect(0, 0, 210, 297, 'F');
        // bolhas grandes nas bordas
        $bolhas = [
            [[255, 99, 132], -20, -10, 70], [[255, 205, 86], 205, 15, 55], [[54, 162, 235], 225, 120, 45],
            [[153, 102, 255], -15, 200, 55], [[75, 192, 140], 190, 300, 70], [[255, 159, 64], 40, 312, 40],
        ];
        foreach ($bolhas as [$c, $x, $y, $r]) {
            $this->cor($this->misturar($c, [255, 255, 255], 0.15));
            $this->Circulo($x, $y, $r);
        }
        foreach ([[[255, 221, 120], 30, 70, 6], [[160, 222, 255], 178, 92, 5], [[255, 190, 205], 168, 212, 7], [[190, 240, 200], 52, 168, 4.5]] as [$c, $x, $y, $r]) {
            $this->cor($c);
            $this->Circulo($x, $y, $r);
        }
        $this->confete(11, 45, 60, 230);

        // logo num cartão branco
        $this->cor([255, 255, 255]);
        $this->RoundedRect(75, 48, 60, 60, 6, 'F');
        $logo = __DIR__ . '/../assets/pdf/logo.jpg';
        if (is_file($logo)) {
            $this->Image($logo, 80, 53, 50, 50, '', $this->cfg['site_url'] ?? '');
        }

        $this->SetY(122);
        $this->SetFont('Helvetica', 'B', 10);
        $this->cor([233, 30, 120], 'T');
        $this->Cell(0, 6, self::t('C A T Á L O G O   N A C I O N A L'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 40);
        $this->cor(self::TEXTO, 'T');
        $this->Cell(0, 18, self::t('CICLO'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 15);
        $this->cor([124, 77, 255], 'T');
        $this->Cell(0, 8, self::t('Cosméticos'), 0, 1, 'C');
        $this->Ln(3);
        // faixinha arco-íris no meio
        $n = count(self::ARCO_IRIS);
        foreach (self::ARCO_IRIS as $i => $c) {
            $this->cor($c);
            $this->Rect(75 + $i * (60 / $n), $this->GetY(), 60 / $n, 1.8, 'F');
        }
        $this->Ln(8);

        // "chips" das seções
        if ($secoes) {
            $this->SetFont('Helvetica', 'B', 9.5);
            $larg = [];
            foreach ($secoes as $s) {
                $larg[] = $this->GetStringWidth(self::t($s)) + 10;
            }
            $total = array_sum($larg) + 4 * (count($larg) - 1);
            $x = (210 - $total) / 2;
            $y = $this->GetY();
            foreach (array_values($secoes) as $i => $s) {
                $c = self::ARCO_IRIS[$i % $n];
                $this->cor($c);
                $this->RoundedRect($x, $y, $larg[$i], 8, 4, 'F');
                $this->SetXY($x, $y);
                $this->cor([255, 255, 255], 'T');
                $this->Cell($larg[$i], 8, self::t($s), 0, 0, 'C');
                $x += $larg[$i] + 4;
            }
            $this->SetY($y + 14);
        }

        $this->SetFont('Helvetica', '', 11);
        $this->cor(self::TEXTO, 'T');
        $this->Cell(0, 7, self::t($subtitulo . ' · ' . $qtd . ' produtos'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->cor([110, 100, 130], 'T');
        $this->Cell(0, 6, self::t('Atualizado em ' . date('d/m/Y \à\s H:i')), 0, 1, 'C');

        // contatos
        $this->SetY(240);
        $this->cor([37, 211, 102]);
        $this->RoundedRect(50, 240, 110, 11, 5.5, 'F');
        $this->SetXY(50, 240);
        $this->SetFont('Helvetica', 'B', 11);
        $this->cor([255, 255, 255], 'T');
        $this->Cell(110, 11, self::t('Pedidos pelo WhatsApp: (83) 99945-9629'), 0, 1, 'C', false, 'https://wa.me/' . ($this->cfg['whatsapp'] ?? ''));
        $this->Ln(3);
        $this->SetFont('Helvetica', 'B', 10);
        $this->cor(self::TEXTO, 'T');
        $this->Cell(0, 6, self::t('luxatacado.com.br  ·  @' . ($this->cfg['instagram'] ?? '')), 0, 1, 'C', false, $this->cfg['site_url'] ?? '');
        $this->faixaArcoIris(293, 4);
        $this->capa = false;
    }
}
