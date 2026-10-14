<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Retorna tipos de produtos fixos
echo json_encode(["Perfumaria Árabe", "Miniaturas", "Hidratantes", "Sprays"]);
