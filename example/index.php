<?php

error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

session_start();

$captcha = new \AJUR\Template\KCaptcha();
$captcha->display();

if ($_REQUEST[session_name()]) {
    $_SESSION['captcha_keystring'] = $captcha->getKeyString();
}
