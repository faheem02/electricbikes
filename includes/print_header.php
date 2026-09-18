<?php
require_once __DIR__ . '/company.php';
$pt = $pt ?? '';
$logoRel = (isset($base_path) && $base_path === '') ? 'assets/ms_jaguar_logo.svg' : '../assets/ms_jaguar_logo.svg';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    .letterhead-container {
        width: 100%;
        margin-bottom: 12px;
        font-family: 'Poppins', sans-serif;
    }
    .letterhead-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        width: 100%;
    }
    .letterhead-left {
        text-align: left;
    }
    .letterhead-title {
        font-size: 27px;
        font-weight: 800;
        color: #E31E24;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin: 0;
        line-height: 1.05;
        font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    }
    .letterhead-owner {
        font-size: 15px;
        font-weight: 700;
        color: #102E68;
        margin-top: 4px;
        margin-bottom: 1px;
        line-height: 1.2;
    }
    .letterhead-phone {
        font-size: 14px;
        font-weight: 700;
        color: #008CCC;
        line-height: 1.25;
    }
    .letterhead-right {
        text-align: right;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
    .letterhead-logo {
        margin-bottom: 2px;
    }
    .letterhead-logo img {
        height: 56px;
        width: auto;
        object-fit: contain;
        display: block;
    }
    .letterhead-address {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 13.5px;
        font-weight: 700;
        color: #102E68;
        margin-top: 2px;
    }
    .letterhead-address .pin-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #00AEEF;
        color: #fff;
        font-size: 9px;
    }
    .letterhead-bar {
        height: 4px;
        background: #00AEEF;
        width: 100%;
        margin: 6px 0 8px 0;
        border-radius: 2px;
    }
    .letterhead-report-title {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        color: #102E68;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 4px 0 6px 0;
    }
</style>

<div class="letterhead-container">
    <div class="letterhead-top">
        <div class="letterhead-left">
            <h1 class="letterhead-title"><?php echo COMPANY_NAME; ?></h1>
            <div class="letterhead-owner"><?php echo COMPANY_OWNER; ?></div>
            <div class="letterhead-phone"><?php echo COMPANY_PHONE_1; ?></div>
            <div class="letterhead-phone"><?php echo COMPANY_PHONE_2; ?></div>
            <?php if (!empty($showTaxInfo)): ?>
            <div class="letterhead-tax" style="font-size:11.5px; color:#102E68; font-weight:700; margin-top:3px; line-height:1.35;">
                <div>STRN NO: <span style="color:#008CCC;"><?php echo COMPANY_STRN; ?></span></div>
                <div>NTN NO: <span style="color:#008CCC;"><?php echo COMPANY_NTN; ?></span></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="letterhead-right">
            <div class="letterhead-logo">
                <img src="<?php echo $logoRel; ?>" alt="MS JAGUAR AUTHORIZED DEALER">
            </div>
            <div class="letterhead-address">
                <span class="pin-circle"><i class="fas fa-map-marker-alt"></i></span>
                <span><?php echo COMPANY_ADDRESS; ?></span>
            </div>
        </div>
    </div>
    <div class="letterhead-bar"></div>
    <?php if (!empty($pt)): ?>
    <div class="letterhead-report-title"><?php echo e($pt); ?></div>
    <?php endif; ?>
</div>
