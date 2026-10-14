<?php
// Copie para config.local.php (NÃO vai para o git) e preencha.
// Arquivo .php: mesmo que alguém acesse pela web, não mostra nada.
return [
    'erp' => ['host' => 'erp_db', 'db' => 'erp', 'user' => 'root', 'pass' => ''],
    'catalogo' => ['host' => 'catalogo_db', 'db' => 'catalogo', 'user' => 'root', 'pass' => ''],
    // endereço do site (botão "Ver detalhes" do PDF abre a página do produto)
    'site_url' => 'https://luxatacado.com.br',
    // onde buscar a foto quando ela não está na pasta uploads/ (útil no computador local)
    'imagens_url' => 'https://cdn.luxatacado.com.br/uploads/',
    'whatsapp' => '5583999459629',
    'instagram' => 'luxperfumesdistribuidora',
];
