<?php
require_once __DIR__ . '/company.php';
$pt = $pt ?? '';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    .header .meta-line { margin:2px 0; }
    .header .meta-line i { color:#095D3B; margin-right:5px; width:14px; text-align:center; }
</style>
<div class="header">
    <img src="../pic/alhafiz.jpeg" alt="Logo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:10px;border:2px solid #095D3B;">
    <h1><?php echo COMPANY_NAME; ?></h1>
    <p style="color:#333;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:8px;"><?php echo e($pt); ?></p>
    <div class="meta-line" style="margin-top:10px;"><i class="fas fa-map-marker-alt"></i> <?php echo COMPANY_ADDRESS; ?></div>
    <div class="meta-line"><?php echo COMPANY_TAGLINE; ?> | <?php echo COMPANY_LINE3; ?></div>
    <div class="meta-line"><i class="fab fa-whatsapp"></i> <?php echo COMPANY_PHONES; ?></div>
    <div class="meta-line"><i class="fas fa-envelope"></i> <?php echo COMPANY_EMAIL; ?></div>
</div>
