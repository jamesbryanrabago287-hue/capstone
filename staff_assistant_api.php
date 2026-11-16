<?php
require_once __DIR__.'/includes/auth.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function staff_assistant_error(int $status,string $message): void {http_response_code($status);echo json_encode(['error'=>$message]);exit;}
if(!isset($_SESSION['user']))staff_assistant_error(401,'Sign in to use the staff assistant.');
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');staff_assistant_error(405,'Use POST to ask a question.');}
$raw=file_get_contents('php://input');if(strlen($raw)>4096)staff_assistant_error(413,'Your question is too long.');
$data=json_decode($raw,true);if(!is_array($data)||!is_string($data['csrf'] ?? null)||!hash_equals(csrf_token(),$data['csrf']))staff_assistant_error(403,'Refresh the page and try again.');
require_once __DIR__.'/includes/staff_chat.php';
if(($data['action'] ?? '')==='reset'){unset($_SESSION['_staff_chat']);echo json_encode(staff_chat_base('New chat started. What would you like to check?'));exit;}
if(!is_string($data['question'] ?? null))staff_assistant_error(400,'Enter a question.');
if(($data['ai_consent'] ?? '')==='yes'&&($data['ai_provider'] ?? '')!==clinic_ai_settings()['provider'])staff_assistant_error(400,'The AI provider changed. Refresh to review the current privacy notice.');
require_once __DIR__.'/includes/staff_assistant.php';
try{echo json_encode(staff_chat_answer($pdo,$data['question'],($data['ai_consent'] ?? '')==='yes'),JSON_INVALID_UTF8_SUBSTITUTE);}
catch(InvalidArgumentException $e){staff_assistant_error(400,$e->getMessage());}
catch(Throwable $e){error_log('Staff assistant failed: '.$e->getMessage());staff_assistant_error(500,'Could not read the inventory data. Please try again.');}
