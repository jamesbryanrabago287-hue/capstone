<?php
require_once __DIR__.'/includes/auth.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!isset($_SESSION['user'])){http_response_code(401);echo json_encode(['error'=>'Sign in to view notifications.']);exit;}
require_login();
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['error'=>'Use GET.']);exit;}
require_once __DIR__.'/includes/assessment_notifications.php';
try{echo json_encode(assessment_notification_snapshot($pdo),JSON_THROW_ON_ERROR);}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Notifications are temporarily unavailable.']);}
