<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();

$variantId = intval($_GET['variant_id'] ?? 0);
if (!$variantId) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT s.id, s.chassis_no, s.motor_no, s.battery_serial, s.charger_serial, s.sale_price, s.purchase_price FROM bike_stock s WHERE s.variant_id=? AND s.status='in_stock' ORDER BY s.id");
$stmt->execute([$variantId]);
$bikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($bikes);
