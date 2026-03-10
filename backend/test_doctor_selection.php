<?php
require __DIR__ . '/vendor/autoload.php';
use App\Services\AIService;

$svc = new AIService();
$r1 = $svc->processLocally('show doctors', []);
print_r($r1);
$ctx = ['extracted_data' => $r1['extracted_data']];
$r2 = $svc->processLocally('2', $ctx);
print_r($r2);
