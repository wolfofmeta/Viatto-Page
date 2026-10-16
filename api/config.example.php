<?php
// Copia este archivo como config.php (en la misma carpeta) y llena tus datos.
// config.php NUNCA se sube a GitHub: tiene las claves.
return [
  // Base de datos MySQL de Hostinger (hPanel → Bases de datos → MySQL)
  'db_host' => 'localhost',
  'db_name' => 'u000000000_viatto',
  'db_user' => 'u000000000_viatto',
  'db_pass' => 'CAMBIA_ESTO',

  // Contraseña del panel. Genera el hash abriendo /panel/hash.php?c=TU_CONTRASEÑA una vez y bórralo después.
  'panel_pass_hash' => '',

  // Brevo (mail marketing). Déjalo vacío si todavía no lo usas: los registros igual se guardan.
  'brevo_api_key' => '',
  'brevo_list_id' => 0,   // número de la lista de Brevo donde caen los registros

  // Dominios que pueden enviar el formulario
  'origenes' => ['https://viattopay.com', 'https://www.viattopay.com'],
];
