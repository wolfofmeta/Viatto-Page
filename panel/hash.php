<?php
// Uso único: abre /panel/hash.php?c=TU_CONTRASEÑA, copia el resultado en config.php y BORRA este archivo.
header('Content-Type: text/plain; charset=utf-8');
echo isset($_GET['c']) && strlen($_GET['c']) >= 10 ? password_hash($_GET['c'], PASSWORD_DEFAULT) : 'Pasa ?c= con una contraseña de al menos 10 caracteres.';
