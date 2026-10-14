<?php

try {
    $pdo = new PDO("mysql:host=erp_db;port=3306;dbname=erp", "root", "B4ncOLUXErp!");
    echo "Conectado!";
} catch (PDOException $e) {
    echo $e->getMessage();
}
