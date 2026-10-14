<?php
/**
 * Função para converter imagens WebP para JPG
 * @param string $arquivo_origem Caminho do arquivo original
 * @param string $pasta_destino Pasta onde salvar o arquivo convertido
 * @return string|false Nome do arquivo convertido ou false se falhar
 */
function converterWebpParaJpg($arquivo_origem, $pasta_destino = 'uploads/') {
    // Verifica se o arquivo existe
    if (!file_exists($arquivo_origem)) {
        return false;
    }
    
    // Obtém informações da imagem
    $info_imagem = getimagesize($arquivo_origem);
    if (!$info_imagem) {
        return false;
    }
    
    // Verifica se é uma imagem WebP
    $tipo_mime = $info_imagem['mime'];
    $extensao_original = pathinfo($arquivo_origem, PATHINFO_EXTENSION);
    
    // Se não for WebP, retorna o arquivo original
    if ($tipo_mime !== 'image/webp' && strtolower($extensao_original) !== 'webp') {
        return basename($arquivo_origem);
    }
    
    // Cria a imagem a partir do WebP
    $imagem_webp = imagecreatefromwebp($arquivo_origem);
    if (!$imagem_webp) {
        return false;
    }
    
    // Gera um nome único para o arquivo JPG
    $nome_base = pathinfo($arquivo_origem, PATHINFO_FILENAME);
    $nome_jpg = $nome_base . '_converted_' . time() . '.jpg';
    $caminho_jpg = $pasta_destino . $nome_jpg;
    
    // Converte e salva como JPG
    $qualidade = 90; // Qualidade da imagem (0-100)
    $resultado = imagejpeg($imagem_webp, $caminho_jpg, $qualidade);
    
    // Libera a memória
    imagedestroy($imagem_webp);
    
    if ($resultado) {
        return $nome_jpg;
    }
    
    return false;
}

/**
 * Função para processar upload de imagem com conversão automática
 * @param array $arquivo Array $_FILES['imagem']
 * @param string $pasta_destino Pasta onde salvar
 * @return string|false Nome do arquivo processado ou false se falhar
 */
function processarUploadImagem($arquivo, $pasta_destino = 'uploads/') {
    // Verifica se houve erro no upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Verifica se é uma imagem válida
    $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $tipo_mime = mime_content_type($arquivo['tmp_name']);
    
    if (!in_array($tipo_mime, $tipos_permitidos)) {
        return false;
    }
    
    // Se for WebP, converte para JPG
    if ($tipo_mime === 'image/webp') {
        $arquivo_convertido = converterWebpParaJpg($arquivo['tmp_name'], $pasta_destino);
        if ($arquivo_convertido) {
            return $arquivo_convertido;
        }
    } else {
        // Para outros formatos, apenas move o arquivo
        $nome_arquivo = time() . '_' . basename($arquivo['name']);
        $caminho_destino = $pasta_destino . $nome_arquivo;
        
        if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
            return $nome_arquivo;
        }
    }
    
    return false;
}
?>
