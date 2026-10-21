<?php
require_once __DIR__ . '/funciones.php';
$titulo = $titulo ?? 'Mala Fama Games';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?> | Mala Fama Games</title>
<meta name="theme-color" content="#0b0d26">
<meta name="csrf-token" content="<?= tokenCsrf() ?>">
<meta name="api-base" content="api/">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="icon" href="img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rajdhani:wght@600;700&family=Tilt+Neon&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css?v=15">
<?php if (!empty($conHero)): ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/hero.css?v=2">
<script>
// Entrada del hero: se arma antes del primer pintado y se retira sola a los 4 s como respaldo
document.documentElement.classList.add('intro');
setTimeout(function(){ document.documentElement.classList.remove('intro'); }, 4000);
</script>
<?php endif; ?>
</head>
<body>
<?php if (empty($conHero)) require __DIR__ . '/nav.php'; ?>

<main>
