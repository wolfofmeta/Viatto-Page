<?php
// Panel de registros de la landing de Viatto
session_start();
require __DIR__ . '/../api/db.php';
$c = viatto_config();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (isset($_GET['salir'])) { session_destroy(); header('Location: ./'); exit; }
$error = '';
if (empty($_SESSION['viatto_panel'])) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    usleep(400000); // frena intentos repetidos
    if (!empty($c['panel_pass_hash']) && password_verify($_POST['clave'] ?? '', $c['panel_pass_hash'])) {
      session_regenerate_id(true); $_SESSION['viatto_panel'] = 1; header('Location: ./'); exit;
    }
    $error = 'Contraseña incorrecta.';
  }
  ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Panel Viatto</title>
  <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#062A1C;font:15px/1.5 system-ui,sans-serif;color:#fff}form{background:#0A2419;padding:32px;border-radius:20px;width:min(360px,90vw);display:grid;gap:14px}h1{margin:0;font-size:22px}input{padding:12px;border-radius:10px;border:1px solid #2a5a43;background:#fff;color:#062A1C;font:inherit}button{padding:12px;border:0;border-radius:999px;background:#0A8F4E;color:#fff;font-weight:600;cursor:pointer}p{margin:0;color:#ffb3b3}</style></head>
  <body><form method="post"><h1>Panel de registros</h1><label for="clave">Contraseña</label><input id="clave" name="clave" type="password" autocomplete="current-password" required autofocus><?php if ($error) echo "<p>$error</p>"; ?><button>Entrar</button></form></body></html><?php
  exit;
}

$db = viatto_db();
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Reenviar a Brevo los que aceptaron correos y aún no están
$aviso = '';
if (isset($_POST['sincronizar']) && !empty($c['brevo_api_key'])) {
  $n = 0;
  foreach ($db->query("SELECT * FROM registros WHERE acepta_marketing = 1 AND brevo_ok = 0 LIMIT 300") as $r) {
    $partes = preg_split('/\s+/', $r['nombre'], 2);
    $ch = curl_init('https://api.brevo.com/v3/contacts');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
      CURLOPT_HTTPHEADER => ['api-key: ' . $c['brevo_api_key'], 'Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode(['email' => $r['correo'], 'updateEnabled' => true, 'listIds' => [(int)$c['brevo_list_id']],
        'attributes' => ['FIRSTNAME' => $partes[0], 'LASTNAME' => $partes[1] ?? '', 'PERFIL' => $r['perfil']]])]);
    curl_exec($ch); $st = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($st >= 200 && $st < 300) { $db->prepare("UPDATE registros SET brevo_ok = 1 WHERE id = ?")->execute([$r['id']]); $n++; }
  }
  $aviso = "Se enviaron $n contactos a Brevo.";
}

$total = (int)$db->query("SELECT COUNT(*) FROM registros")->fetchColumn();
$hoy = (int)$db->query("SELECT COUNT(*) FROM registros WHERE DATE(creado) = CURDATE()")->fetchColumn();
$semana = (int)$db->query("SELECT COUNT(*) FROM registros WHERE creado > NOW() - INTERVAL 7 DAY")->fetchColumn();
$mkt = (int)$db->query("SELECT COUNT(*) FROM registros WHERE acepta_marketing = 1")->fetchColumn();
$pend = (int)$db->query("SELECT COUNT(*) FROM registros WHERE acepta_marketing = 1 AND brevo_ok = 0")->fetchColumn();
$perfiles = $db->query("SELECT perfil, COUNT(*) n FROM registros GROUP BY perfil")->fetchAll(PDO::FETCH_KEY_PAIR);
$dias = $db->query("SELECT DATE(creado) d, COUNT(*) n FROM registros WHERE creado > CURDATE() - INTERVAL 29 DAY GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$fuentes = $db->query("SELECT COALESCE(NULLIF(utm_source,''),'directo') f, COUNT(*) n FROM registros GROUP BY f ORDER BY n DESC LIMIT 6")->fetchAll(PDO::FETCH_KEY_PAIR);

$buscar = trim($_GET['q'] ?? '');
$filtro = in_array($_GET['perfil'] ?? '', ['Viajero', 'Promotor', 'Operador'], true) ? $_GET['perfil'] : '';
$sql = "SELECT * FROM registros WHERE 1"; $args = [];
if ($buscar !== '') { $sql .= " AND (nombre LIKE ? OR correo LIKE ? OR telefono LIKE ?)"; $args = array_fill(0, 3, "%$buscar%"); }
if ($filtro) { $sql .= " AND perfil = ?"; $args[] = $filtro; }
$st = $db->prepare($sql . " ORDER BY creado DESC LIMIT 300"); $st->execute($args); $filas = $st->fetchAll();

$max = max(array_merge([1], array_values($dias)));
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Registros · Viatto</title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..100,600..900&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#F2F6F3;--card:#fff;--ink:#062A1C;--muted:#557264;--line:#D6E2DB;--accent:#0A8F4E;--soft:#E6EFE9}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:400 14px/1.5 Poppins,system-ui,sans-serif}
.wrap{max-width:1200px;margin:0 auto;padding:24px 18px 60px}
header{display:flex;flex-wrap:wrap;gap:12px;align-items:end;justify-content:space-between}
h1{margin:0;font:900 34px/1 Archivo,sans-serif;font-stretch:68%;text-transform:uppercase}
.sub{color:var(--muted);font-size:13px}
.acts{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:1.5px solid var(--line);background:var(--card);color:var(--ink);padding:9px 16px;border-radius:999px;font:600 13px Poppins,sans-serif;text-decoration:none;cursor:pointer}
.btn.pri{background:var(--accent);border-color:var(--accent);color:#fff}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:20px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px}
.kpi b{display:block;font:800 34px/1 Archivo,sans-serif;font-stretch:80%;font-variant-numeric:tabular-nums}
.kpi span{color:var(--muted);font-size:12.5px}
.grid2{display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:12px}@media(max-width:820px){.grid2{grid-template-columns:1fr}}
.box{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;min-width:0}
.box h2{margin:0 0 12px;font:600 14px Poppins,sans-serif}
.bars{display:flex;align-items:end;gap:4px;height:140px;border-bottom:1px solid var(--line)}
.bars i{flex:1;background:var(--accent);border-radius:4px 4px 0 0;min-height:2px;position:relative}
.bars i:hover::after{content:attr(data-t);position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;font:11px Poppins;padding:3px 6px;border-radius:6px;white-space:nowrap}
.axis{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;margin-top:6px}
.lst{display:grid;gap:8px}.lst div{display:flex;justify-content:space-between;gap:8px}.lst div b{font-variant-numeric:tabular-nums}
.meter{height:6px;background:var(--soft);border-radius:6px;overflow:hidden;margin-top:3px}.meter i{display:block;height:100%;background:var(--accent)}
form.f{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 10px}form.f input,form.f select{padding:9px 12px;border-radius:10px;border:1px solid var(--line);font:inherit;background:var(--card);color:var(--ink)}
.tbl{overflow-x:auto;background:var(--card);border:1px solid var(--line);border-radius:16px}
table{border-collapse:collapse;width:100%;min-width:760px}th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--line);white-space:nowrap}th{font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:var(--soft)}
.ok{color:var(--accent);font-weight:600}.no{color:var(--muted)}
.aviso{margin-top:14px;padding:10px 14px;border-radius:12px;background:#e3f6ea;color:var(--ink)}
</style></head><body><div class="wrap">
<header><div><h1>Registros de la landing</h1><div class="sub">viattopay.com · lista de espera y 5K millas</div></div>
<div class="acts"><a class="btn pri" href="exportar.php">Descargar CSV</a><?php if (!empty($c['brevo_api_key']) && $pend): ?><form method="post" style="margin:0"><button class="btn" name="sincronizar" value="1">Enviar <?= $pend ?> pendientes a Brevo</button></form><?php endif; ?><a class="btn" href="?salir=1">Salir</a></div></header>
<?php if ($aviso) echo '<div class="aviso">' . $e($aviso) . '</div>'; ?>
<div class="kpis">
  <div class="kpi"><b><?= $total ?></b><span>Registros en total</span></div>
  <div class="kpi"><b><?= $hoy ?></b><span>Hoy</span></div>
  <div class="kpi"><b><?= $semana ?></b><span>Últimos 7 días</span></div>
  <div class="kpi"><b><?= $mkt ?></b><span>Aceptan correos<?= $total ? ' · ' . round($mkt / $total * 100) . ' %' : '' ?></span></div>
</div>
<div class="grid2">
  <div class="box"><h2>Registros por día · últimos 30 días</h2><div class="bars"><?php for ($i = 29; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $n = $dias[$d] ?? 0; echo '<i style="height:' . round($n / $max * 100) . '%" data-t="' . date('d/m', strtotime($d)) . ' · ' . $n . '"></i>'; } ?></div>
  <div class="axis"><span><?= date('d/m', strtotime('-29 day')) ?></span><span>hoy</span></div></div>
  <div class="box"><h2>Por perfil</h2><div class="lst"><?php foreach (['Viajero', 'Promotor', 'Operador'] as $p) { $n = (int)($perfiles[$p] ?? 0); echo '<div><span>' . $p . '</span><b>' . $n . '</b></div><div class="meter"><i style="width:' . ($total ? round($n / $total * 100) : 0) . '%"></i></div>'; } ?></div>
  <h2 style="margin-top:18px">Por fuente</h2><div class="lst"><?php foreach ($fuentes as $f => $n) echo '<div><span>' . $e($f) . '</span><b>' . $n . '</b></div>'; if (!$fuentes) echo '<div class="no">Aún sin datos</div>'; ?></div></div>
</div>
<form class="f" method="get"><input name="q" value="<?= $e($buscar) ?>" placeholder="Buscar nombre, correo o teléfono" aria-label="Buscar"><select name="perfil" aria-label="Perfil"><option value="">Todos los perfiles</option><?php foreach (['Viajero', 'Promotor', 'Operador'] as $p) echo "<option" . ($filtro === $p ? ' selected' : '') . ">$p</option>"; ?></select><button class="btn">Filtrar</button></form>
<div class="tbl"><table><thead><tr><th>Fecha</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Perfil</th><th>Correos</th><th>Brevo</th><th>Fuente</th></tr></thead><tbody>
<?php foreach ($filas as $r): ?><tr><td><?= $e(date('d/m/Y H:i', strtotime($r['creado']))) ?></td><td><?= $e($r['nombre']) ?></td><td><?= $e($r['correo']) ?></td><td><?= $e($r['telefono']) ?></td><td><span class="pill"><?= $e($r['perfil']) ?></span></td><td class="<?= $r['acepta_marketing'] ? 'ok' : 'no' ?>"><?= $r['acepta_marketing'] ? 'Sí' : 'No' ?></td><td class="<?= $r['brevo_ok'] ? 'ok' : 'no' ?>"><?= $r['brevo_ok'] ? 'Sí' : '—' ?></td><td><?= $e($r['utm_source'] ?: 'directo') ?></td></tr><?php endforeach; ?>
<?php if (!$filas) echo '<tr><td colspan="8" class="no">Todavía no hay registros' . ($buscar || $filtro ? ' con ese filtro' : '') . '. Llegan aquí apenas alguien llena el formulario de viattopay.com.</td></tr>'; ?>
</tbody></table></div>
<p class="sub">Se muestran los 300 más recientes. Para ver todos, descarga el CSV.</p>
</div></body></html>
