<?php

require_once('vendor/autoload.php');

$text = <<<TXT
ایــن متن برای تست می‌باشد:
بالا
فَعّال
الف abc ب
۲۰ و 20 - ۳۰ و 30
TXT;

Ghost098\PersianLog2Vis\PersianLog2Vis::correct($text);

// Create the image
$im = imagecreatetruecolor(400, 200);

// Create some colors
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);

// Replace path by your own font path
$font = './DejaVuSans.ttf';

// Add the text
@imagettftext($im, 20, 0, 10, 30, $white, $font, $text);

// Set the content-type
header("Content-type: image/png");

// Using imagepng() results in clearer text compared with imagejpeg()
imagepng($im);
imagedestroy($im);
