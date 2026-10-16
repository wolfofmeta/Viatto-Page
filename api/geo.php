<?php
// Ubicación aproximada por IP y teléfono con indicativo de país
function viatto_ip() {
  foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
  }
  return '';
}
// Devuelve ['pais' => 'CO', 'pais_nombre' => 'Colombia', 'ciudad' => 'Cartagena'] o null
function viatto_geo($ip) {
  if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
  $ch = curl_init('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code,city&lang=es');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3]);
  $r = json_decode((string)curl_exec($ch), true);
  curl_close($ch);
  if (empty($r['success']) || empty($r['country_code'])) {
    // respaldo: si el sitio pasa por Cloudflare llega el país en una cabecera
    $cc = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
    return preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX' ? ['pais' => $cc, 'pais_nombre' => $cc, 'ciudad' => null] : null;
  }
  return ['pais' => strtoupper($r['country_code']), 'pais_nombre' => mb_substr($r['country'] ?? '', 0, 60), 'ciudad' => mb_substr($r['city'] ?? '', 0, 80) ?: null];
}
function viatto_indicativo($cc) {
  static $t = ['CO'=>'57','US'=>'1','CA'=>'1','MX'=>'52','VE'=>'58','EC'=>'593','PE'=>'51','PA'=>'507','CR'=>'506','AR'=>'54','CL'=>'56','BR'=>'55','BO'=>'591','PY'=>'595','UY'=>'598','DO'=>'1','PR'=>'1','GT'=>'502','SV'=>'503','HN'=>'504','NI'=>'505','CU'=>'53','ES'=>'34','PT'=>'351','FR'=>'33','IT'=>'39','DE'=>'49','GB'=>'44','NL'=>'31','CH'=>'41','BE'=>'32','AW'=>'297','CW'=>'599','IL'=>'972','CN'=>'86','JP'=>'81','AU'=>'61'];
  return $t[$cc] ?? null;
}
// Busca si el número empieza por un indicativo conocido (el más largo primero)
function viatto_prefijo($d) {
  static $codes = null;
  if ($codes === null) { $codes = array_unique(array_filter(array_map('viatto_indicativo', ['CO','US','MX','VE','EC','PE','PA','CR','AR','CL','BR','BO','PY','UY','GT','SV','HN','NI','CU','ES','PT','FR','IT','DE','GB','NL','CH','BE','AW','CW','IL','CN','JP','AU']))); usort($codes, fn($a, $b) => strlen($b) - strlen($a)); }
  foreach ($codes as $c) if ($c !== '1' && strpos($d, $c) === 0) return $c;
  return null;
}
// Si el número no trae indicativo (+ o 00), le pone el del país detectado. Devuelve +57 312 873 5219
function viatto_telefono($tel, $cc) {
  $t = trim($tel);
  $d = preg_replace('/\D/', '', $t);
  if ($d === '') return $t;
  if (strpos($t, '+') === 0) $full = $d;
  elseif (strpos($d, '00') === 0) $full = substr($d, 2);
  elseif (strlen($d) > 10 && ($pref = viatto_prefijo($d))) $full = $d; // ya trae el indicativo de algún país, sin "+"
  else {
    $ind = viatto_indicativo($cc ?: 'CO');
    if (!$ind) return $t;
    if (strpos($d, $ind) === 0 && strlen($d) > strlen($ind) + 7) $full = $d; // ya venía con el indicativo sin "+"
    else $full = $ind . ltrim($d, '0');
  }
  if (strpos($full, '58') === 0 && strlen($full) === 12) return '+58 ' . substr($full, 2, 3) . ' ' . substr($full, 5, 3) . ' ' . substr($full, 8);
  if (strpos($full, '57') === 0 && strlen($full) === 12) return '+57 ' . substr($full, 2, 3) . ' ' . substr($full, 5, 3) . ' ' . substr($full, 8);
  if (strpos($full, '1') === 0 && strlen($full) === 11) return '+1 ' . substr($full, 1, 3) . ' ' . substr($full, 4, 3) . ' ' . substr($full, 7);
  return '+' . $full;
}
function viatto_bandera($cc) {
  if (!preg_match('/^[A-Z]{2}$/', (string)$cc)) return '';
  return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
}
