<?php
require_once __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$options = new QROptions([
    'version'      => 5,
    'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
    'outputBase64' => true,
    'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::L,
    'addQuietzone' => false,
]);

$qrCode = new QRCode($options);
echo $qrCode->render('test');
