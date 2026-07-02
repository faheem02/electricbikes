<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id < 1) { http_response_code(400); exit; }

$s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
$s->execute([$id]);
$row = $s->fetch(PDO::FETCH_ASSOC);

if (!$row) { http_response_code(404); exit; }

header('Content-Type: application/json');
echo json_encode($row);
