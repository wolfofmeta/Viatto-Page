<?php
// Descarga todos los registros en CSV (para Excel o para importar en otra herramienta)
session_start();
if (empty($_SESSION['viatto_panel'])) { http_response_code(403); exit('Inicia sesión en el panel.'); }
require __DIR__ . '/../api/db.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="viatto-registros-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // para que Excel lea bien las tildes
fputcsv($out, ['Fecha', 'Nombre', 'Correo', 'Teléfono', 'Perfil', 'Acepta correos', 'En Brevo', 'Fuente', 'Medio', 'Campaña']);
foreach (viatto_db()->query("SELECT * FROM registros ORDER BY creado DESC") as $r) {
  fputcsv($out, [$r['creado'], $r['nombre'], $r['correo'], $r['telefono'], $r['perfil'], $r['acepta_marketing'] ? 'Sí' : 'No', $r['brevo_ok'] ? 'Sí' : 'No', $r['utm_source'], $r['utm_medium'], $r['utm_campaign']]);
}
