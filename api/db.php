<?php
// Conexión y tabla de registros (se crea sola la primera vez)
function viatto_config() {
  static $c = null;
  if ($c === null) {
    $f = __DIR__ . '/config.php';
    if (!is_file($f)) { http_response_code(500); exit(json_encode(['ok' => false, 'error' => 'Falta api/config.php'])); }
    $c = require $f;
  }
  return $c;
}
function viatto_db() {
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = viatto_config();
  $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(190) NOT NULL UNIQUE,
    telefono VARCHAR(40) NOT NULL,
    perfil ENUM('Viajero','Promotor','Operador') NOT NULL DEFAULT 'Viajero',
    acepta_datos TINYINT(1) NOT NULL DEFAULT 0,
    acepta_marketing TINYINT(1) NOT NULL DEFAULT 0,
    origen VARCHAR(190) NULL,
    utm_source VARCHAR(80) NULL, utm_medium VARCHAR(80) NULL, utm_campaign VARCHAR(80) NULL,
    ip VARCHAR(45) NULL,
    brevo_ok TINYINT(1) NOT NULL DEFAULT 0,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (creado), INDEX (perfil), INDEX (ip)
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  return $pdo;
}
