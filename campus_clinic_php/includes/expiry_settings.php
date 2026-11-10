<?php
function ensure_expiry_settings(PDO $pdo): void {
 static $ready=[];$key=spl_object_id($pdo);if(isset($ready[$key]))return;
 $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_settings (setting_key VARCHAR(80) PRIMARY KEY,setting_value VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$ready[$key]=true;
}
function expiry_warning_days(): int {
 global $pdo;
 ensure_expiry_settings($pdo);$key=spl_object_id($pdo);
 if(isset($GLOBALS['_expiry_days'][$key]))return $GLOBALS['_expiry_days'][$key];
 $stmt=$pdo->prepare('SELECT setting_value FROM clinic_settings WHERE setting_key=?');$stmt->execute(['expiry_warning_days']);$value=$stmt->fetchColumn();
 $days=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>365]]);
 return $GLOBALS['_expiry_days'][$key]=$days===false?30:$days;
}
function save_expiry_warning_days(PDO $pdo,$value,bool $admin): int {
 if(!$admin)throw new InvalidArgumentException('Only an administrator can change expiry warning settings.');
 if(!is_scalar($value)||is_bool($value)||!preg_match('/^[0-9]+$/',(string)$value))throw new InvalidArgumentException('Enter a whole number of days from 1 to 365.');
 $days=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>365]]);
 if($days===false)throw new InvalidArgumentException('Enter a whole number of days from 1 to 365.');
 ensure_expiry_settings($pdo);
 $pdo->prepare('INSERT INTO clinic_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['expiry_warning_days',(string)$days]);
 $GLOBALS['_expiry_days'][spl_object_id($pdo)]=$days;return $days;
}
