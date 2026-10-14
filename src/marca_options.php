<?php

function obterMarcasBase(): array
{
    return [
        'afnan',
        'alhambra',
        'Alhambra Collection',
        'ANFAR',
        'Arabic Collection',
        'ard',
        'armaf',
        'Arqus',
        'asdaaf',
        'Azzaro',
        'Bidaya',
        'BOGART',
        'Brand Collection',
        'BURBERRY',
        'Calvin Klein',
        'Dream Collection',
        'ferrari',
        'french',
        'GABRIELA',
        'haramain',
        'isabelle',
        'Joop!',
        'lattafa',
        'Nadia',
        'Nautica',
        'orientica',
        'Paco Rabanne',
        'Ralph Lauren',
        'rasasi',
        'rayhaan',
        'VS',
        'wataniah',
    ];
}

function obterCaminhoMarcasCustomizadas(): string
{
    return __DIR__ . '/marcas_customizadas.json';
}

function obterCaminhoMarcasExcluidas(): string
{
    return __DIR__ . '/marcas_excluidas.json';
}

function lerListaJson(string $caminho): array
{
    if (!file_exists($caminho)) {
        return [];
    }

    $conteudo = file_get_contents($caminho);

    if ($conteudo === false || trim($conteudo) === '') {
        return [];
    }

    $itens = json_decode($conteudo, true);

    if (!is_array($itens)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $itens), static function ($item) {
        return $item !== '';
    }));
}

function salvarListaJson(string $caminho, array $itens): bool
{
    return file_put_contents(
        $caminho,
        json_encode(ordenarMarcas($itens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function lerMarcasCustomizadas(): array
{
    return lerListaJson(obterCaminhoMarcasCustomizadas());
}

function lerMarcasExcluidas(): array
{
    return lerListaJson(obterCaminhoMarcasExcluidas());
}

function ordenarMarcas(array $marcas): array
{
    $marcasUnicas = [];

    foreach ($marcas as $marca) {
        $marca = trim((string) $marca);

        if ($marca === '') {
            continue;
        }

        $chave = mb_strtolower($marca, 'UTF-8');

        if (!isset($marcasUnicas[$chave])) {
            $marcasUnicas[$chave] = $marca;
        }
    }

    $marcasOrdenadas = array_values($marcasUnicas);

    usort($marcasOrdenadas, static function ($a, $b) {
        return strcasecmp($a, $b);
    });

    return $marcasOrdenadas;
}

function obterMarcasPadrao(): array
{
    $marcas = ordenarMarcas(array_merge(
        obterMarcasBase(),
        lerMarcasCustomizadas()
    ));

    $marcasExcluidas = lerMarcasExcluidas();

    return array_values(array_filter($marcas, static function ($marca) use ($marcasExcluidas) {
        foreach ($marcasExcluidas as $marcaExcluida) {
            if (mb_strtolower($marca, 'UTF-8') === mb_strtolower($marcaExcluida, 'UTF-8')) {
                return false;
            }
        }

        return true;
    }));
}

function normalizarMarcaNaLista(?string $marca, array $marcas): string
{
    $marca = trim((string) $marca);

    if ($marca === '') {
        return '';
    }

    foreach ($marcas as $marcaLista) {
        if (mb_strtolower($marca, 'UTF-8') === mb_strtolower($marcaLista, 'UTF-8')) {
            return $marcaLista;
        }
    }

    return '';
}

function normalizarMarca(?string $marca): string
{
    return normalizarMarcaNaLista($marca, obterMarcasPadrao());
}

function salvarMarcaCustomizada(string $marca): string
{
    $marca = trim($marca);

    if ($marca === '') {
        return '';
    }

    $marcaExistente = normalizarMarca($marca);

    if ($marcaExistente !== '') {
        return $marcaExistente;
    }

    $marcaBase = normalizarMarcaNaLista($marca, obterMarcasBase());
    $marcasExcluidas = lerMarcasExcluidas();

    if (normalizarMarcaNaLista($marca, $marcasExcluidas) !== '') {
        $marcasExcluidas = array_values(array_filter($marcasExcluidas, static function ($marcaExcluida) use ($marca) {
            return mb_strtolower($marcaExcluida, 'UTF-8') !== mb_strtolower($marca, 'UTF-8');
        }));

        if (!salvarListaJson(obterCaminhoMarcasExcluidas(), $marcasExcluidas)) {
            return '';
        }
    }

    if ($marcaBase !== '') {
        return $marcaBase;
    }

    $marcasAtualizadas = array_merge(lerMarcasCustomizadas(), [$marca]);

    if (!salvarListaJson(obterCaminhoMarcasCustomizadas(), $marcasAtualizadas)) {
        return '';
    }

    return normalizarMarca($marca);
}

function excluirMarca(string $marca): bool
{
    $marcaNormalizada = normalizarMarca($marca);

    if ($marcaNormalizada === '') {
        return false;
    }

    $marcaCustomizada = normalizarMarcaNaLista($marcaNormalizada, lerMarcasCustomizadas());

    if ($marcaCustomizada !== '') {
        $marcasCustomizadas = array_values(array_filter(lerMarcasCustomizadas(), static function ($item) use ($marcaCustomizada) {
            return mb_strtolower($item, 'UTF-8') !== mb_strtolower($marcaCustomizada, 'UTF-8');
        }));

        return salvarListaJson(obterCaminhoMarcasCustomizadas(), $marcasCustomizadas);
    }

    $marcaBase = normalizarMarcaNaLista($marcaNormalizada, obterMarcasBase());

    if ($marcaBase === '') {
        return false;
    }

    return salvarListaJson(
        obterCaminhoMarcasExcluidas(),
        array_merge(lerMarcasExcluidas(), [$marcaBase])
    );
}
