<?php
// Recibe el formulario de la landing, lo guarda y lo envía a Brevo si está configurado.
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$c = viatto_config();
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $c['origenes'], true)) { header("Access-Control-Allow-Origin: $origin"); header('Vary: Origin'); }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { header('Access-Control-Allow-Methods: POST'); header('Access-Control-Allow-Headers: Content-Type'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'Método no permitido'])); }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$falla = function ($msg, $code = 422) { http_response_code($code); exit(json_encode(['ok' => false, 'error' => $msg])); };

// Campo trampa: los robots lo llenan, las personas no lo ven
if (!empty($in['empresa'])) exit(json_encode(['ok' => true]));

$nombre = trim(mb_substr($in['nombre'] ?? '', 0, 120));
$correo = strtolower(trim(mb_substr($in['correo'] ?? '', 0, 190)));
$tel = trim(mb_substr($in['telefono'] ?? '', 0, 40));
$perfil = in_array($in['perfil'] ?? '', ['Viajero', 'Promotor', 'Operador'], true) ? $in['perfil'] : 'Viajero';
$datos = !empty($in['acepta_datos']);
$mkt = !empty($in['acepta_marketing']);
if (mb_strlen($nombre) < 2) $falla('Escribe tu nombre.');
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $falla('Revisa el correo.');
if (strlen(preg_replace('/\D/', '', $tel)) < 7) $falla('Escribe tu número de teléfono.');
if (!$datos) $falla('Debes autorizar el tratamiento de tus datos para registrarte.');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = trim(explode(',', $ip)[0]);
$db = viatto_db();

// Máximo 5 registros por IP cada 10 minutos
$q = $db->prepare("SELECT COUNT(*) FROM registros WHERE ip = ? AND creado > NOW() - INTERVAL 10 MINUTE");
$q->execute([$ip]);
if ($q->fetchColumn() >= 5) $falla('Demasiados intentos. Prueba de nuevo en unos minutos.', 429);

$utm = fn($k) => isset($in[$k]) ? mb_substr(preg_replace('/[^\w\-.]/u', '', $in[$k]), 0, 80) : null;
try {
  $db->prepare("INSERT INTO registros (nombre, correo, telefono, perfil, acepta_datos, acepta_marketing, origen, utm_source, utm_medium, utm_campaign, ip)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$nombre, $correo, $tel, $perfil, 1, $mkt ? 1 : 0, mb_substr($in['origen'] ?? '', 0, 190), $utm('utm_source'), $utm('utm_medium'), $utm('utm_campaign'), $ip]);
  $id = $db->lastInsertId();
} catch (PDOException $e) {
  if ($e->getCode() == 23000) exit(json_encode(['ok' => true, 'repetido' => true])); // el correo ya estaba registrado
  $falla('No pudimos guardar tu registro. Intenta de nuevo.', 500);
}

// Enviar a Brevo solo si la persona aceptó recibir correos
if ($mkt && !empty($c['brevo_api_key']) && !empty($c['brevo_list_id'])) {
  $partes = preg_split('/\s+/', $nombre, 2);
  $ch = curl_init('https://api.brevo.com/v3/contacts');
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['api-key: ' . $c['brevo_api_key'], 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
      'email' => $correo, 'updateEnabled' => true, 'listIds' => [(int)$c['brevo_list_id']],
      'attributes' => ['FIRSTNAME' => $partes[0], 'LASTNAME' => $partes[1] ?? '', 'SMS' => preg_replace('/[^\d+]/', '', $tel), 'PERFIL' => $perfil],
    ]),
  ]);
  curl_exec($ch);
  $st = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($st >= 200 && $st < 300) $db->prepare("UPDATE registros SET brevo_ok = 1 WHERE id = ?")->execute([$id]);
}
echo json_encode(['ok' => true]);
