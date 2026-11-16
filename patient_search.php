<?php
require_once __DIR__.'/includes/auth.php';header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
if(!isset($_SESSION['user'])){http_response_code(401);echo json_encode(['error'=>'Sign in to search patients.']);exit;}require_login();
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['error'=>'Use GET.']);exit;}
$q=is_string($_GET['q'] ?? null)?mb_substr(trim($_GET['q']),0,80):'';
try{$where='';$params=[];if($q!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$where=" WHERE full_name LIKE ? ESCAPE '!' OR student_no LIKE ? ESCAPE '!'";$params=[$like,$like];}$stmt=$pdo->prepare('SELECT id,full_name,student_no,patient_type FROM students'.$where.' ORDER BY full_name,id LIMIT 20');$stmt->execute($params);echo json_encode(['patients'=>$stmt->fetchAll()],JSON_INVALID_UTF8_SUBSTITUTE);}
catch(Throwable $e){error_log('Patient search failed: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'Could not search patients. Refresh and try again.']);}
