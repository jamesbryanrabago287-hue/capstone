<?php
require_once __DIR__.'/includes/auth.php';header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function registration_error(int $status,string $message): void {http_response_code($status);echo json_encode(['error'=>$message]);exit;}
if(!isset($_SESSION['user']))registration_error(401,'Sign in to register patients.');require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');registration_error(405,'Use POST to register a patient.');}
$raw=file_get_contents('php://input');if(strlen($raw)>8192)registration_error(413,'Patient details are too long.');$data=json_decode($raw,true);
if(!is_array($data)||!is_string($data['csrf'] ?? null)||!hash_equals(csrf_token(),$data['csrf']))registration_error(403,'Refresh the page and try again.');
if(!is_array($data['patient'] ?? null)||!is_string($data['request_token'] ?? null))registration_error(400,'Enter the patient details.');
require_once __DIR__.'/includes/patient_registration.php';
if(!isset($_SESSION['_patient_registration_requests'])||!is_array($_SESSION['_patient_registration_requests']))$_SESSION['_patient_registration_requests']=[];
try{$result=register_patient_now($pdo,$data['patient'],$data['request_token'],$_SESSION['_patient_registration_requests']);if(!$result['duplicate'])record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'create_patient','Registered patient ID '.$result['patient']['id']);$result['next_token']=bin2hex(random_bytes(32));echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE);}
catch(InvalidArgumentException $e){registration_error(400,$e->getMessage());}
catch(Throwable $e){error_log('Patient registration failed: '.$e->getMessage());registration_error(500,'Could not register the patient. Please try again.');}
