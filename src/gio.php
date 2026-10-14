<?php

$pdo = new PDO('mysql:host=erp_db;port=3306;dbname=erp', 'root', 'B4ncOLUXErp!');

$produtos = [
"ISABELLE LA BELLE SPLASH ASAD BOURBON 300ML",
"ISABELLE LA BELLE SPLASH FAKHAR FEM 300ML",
"ISABELLE LA BELLE SPLASH FAKHAR GOLD 300ML",
"ISABELLE LA BELLE SPLASH FAKHAR PRETO 300ML",
"ISABELLE LA BELLE SPLASH ASAD 300ML",
"ISABELLE LA BELLE SPLASH CLUB INTENSE 300ML",
"ISABELLE LA BELLE SPLASH CLUB ICONIC 300ML",
"ISABELLE LA BELLE SPLASH CLUB WOMAN 300ML",
"ISABELLE LA BELLE SPLASH CLUB MALEKA 300ML",
"ISABELLE LA BELLE SPLASH SABAH 300ML",
"ISABELLE LA BELLE SPLASH SABAH SUGGAR 300ML",
"ISABELLE LA BELLE SPLASH YARA 300ML",
"ISABELLE LA BELLE SPLASH ANGEL 300ML",
"ISABELLE LA BELLE SPLASH COMO MOISELLE 300ML",
"ISABELLE LA BELLE SPLASH LIQUID BRUN 300ML",
"ISABELLE LA BELLE SPLASH YARA MOI 300ML",
"ISABELLE LA BELLE SPLASH LA BOMBA 300ML",
"ISABELLE LA BELLE SPLASH VICTORIA 300ML",
"ISABELLE LA BELLE SPLASH ROYAL AMBER 300ML",
"ISABALLE LA BELLE SPLASH HAWAS BLACK 300ML",
"ISABALLE LA BELLE SPLASH SALVO 300ML"
];

$naoEncontrados = [];

foreach ($produtos as $nome) {

    // verifica se existe
    $check = $pdo->prepare("SELECT id FROM produtos WHERE nome = ?");
    $check->execute([$nome]);

    if ($check->rowCount() > 0) {

        $update = $pdo->prepare("
            UPDATE produtos 
            SET valor_compra = 48.40, valor_venda = 65.00
            WHERE nome = ?
        ");
        $update->execute([$nome]);

        echo "✅ Atualizado: $nome <br>";

    } else {
        $naoEncontrados[] = $nome;
        echo "❌ Não encontrado: $nome <br>";
    }
}

echo "<hr><strong>Resumo:</strong><br>";
echo "Não encontrados: <br>";

foreach ($naoEncontrados as $n) {
    echo "- $n <br>";
}