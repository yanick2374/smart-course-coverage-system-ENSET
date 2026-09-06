<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$title = basename($_SERVER['PHP_SELF'], '.php');
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(ucwords(str_replace('_',' ',$title))) ?></title>
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><div style="padding:40px;font-family:Arial">
<a href="dashboard.php">← Back to Dashboard</a><h1><?= htmlspecialchars(ucwords(str_replace('_',' ',$title))) ?></h1>
<p>This page is a placeholder. The dashboard is fully connected to the supplied database schema; this module can be implemented next.</p>
</div></body></html>