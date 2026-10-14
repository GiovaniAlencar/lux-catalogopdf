<?php
// Catálogo dos produtos marcados em "Listar produtos" (POST produtos[]). Usa o gerador único.
$_GET['tipo'] = 'selecionados';
require __DIR__ . '/gerar_catalogo_pdf.php';
