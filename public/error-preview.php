<?php
declare(strict_types=1);

$title = '监控端状态异常';
$message = '监控端状态异常，请检查';
$helpText = '请确认监控端恢复在线后，再重新发起支付。';
$buttonText = '返回上页';

require dirname(__DIR__) . '/view/merchant/error.php';
