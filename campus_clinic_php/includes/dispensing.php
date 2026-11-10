<?php
require_once __DIR__.'/inventory.php';
function ensure_dispensing_table(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_dispenses (\n id INT AUTO_INCREMENT PRIMARY KEY, consultation_id INT NOT NULL, transaction_id INT NOT NULL UNIQUE,\n medicine_name VARCHAR(150) NOT NULL, batch_number VARCHAR(100) NOT NULL, unit VARCHAR(50) NOT NULL,\n expiration_date DATE NOT NULL, issued_by_name VARCHAR(120) NOT NULL,\n KEY idx_dispense_visit (consultation_id),\n CONSTRAINT fk_dispense_visit FOREIGN KEY (consultation_id) REFERENCES clinic_consultations(id) ON DELETE RESTRICT,\n CONSTRAINT fk_dispense_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function clinic_issue_medicine(PDO $pdo,$assessmentId,array $input,array $staff,$visitId=null) {
    $medicineId=inventory_integer($input['medicine_id'] ?? '',1,PHP_INT_MAX,'Medicine');
    $qty=inventory_integer($input['quantity'] ?? '',1,100000,'Quantity');
    $token=$input['request_token'] ?? '';if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Refresh before recording medicine issuance.');
    $pdo->beginTransaction();
    try {
        // Lock the visit first: completion and issuance cannot race each other.
        $stmt=$pdo->prepare('SELECT * FROM clinic_consultations WHERE '.($visitId!==null?'id':'assessment_id').'=? FOR UPDATE');$stmt->execute([$visitId ?? $assessmentId]);$visit=$stmt->fetch();
        if(!$visit)throw new InvalidArgumentException('Verify the student and start the consultation first.');
        $stmt=$pdo->prepare('SELECT t.*,d.consultation_id FROM transactions t LEFT JOIN clinic_dispenses d ON d.transaction_id=t.id WHERE t.request_token=?');$stmt->execute([$token]);$old=$stmt->fetch();
        if($old){
            if((int)$old['consultation_id']!==(int)$visit['id'] || (int)$old['medicine_id']!==$medicineId || (int)$old['quantity']!==$qty || (int)$old['user_id']!==(int)$staff['id'])throw new InvalidArgumentException('This form has already been used. Refresh before recording another issue.');
            $pdo->commit();return ['duplicate'=>true,'transaction_id'=>(int)$old['id'],'balance'=>(int)$old['balance_after']];
        }
        if($visit['status']!=='In progress')throw new InvalidArgumentException('Completed consultations are read-only.');
        $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? FOR UPDATE');$stmt->execute([$medicineId]);$m=$stmt->fetch();if(!$m)throw new InvalidArgumentException('Medicine batch not found.');
        $movement=inventory_movement($pdo,['medicine_id'=>$medicineId,'type'=>'Stock Out','quantity'=>$qty,'reference_no'=>'VISIT-'.(int)$visit['id'],'notes'=>$input['notes'] ?? '', 'request_token'=>$token],$staff);
        $stmt=$pdo->prepare('SELECT id FROM transactions WHERE request_token=?');$stmt->execute([$token]);$transactionId=(int)$stmt->fetchColumn();
        $pdo->prepare('INSERT INTO clinic_dispenses(consultation_id,transaction_id,medicine_name,batch_number,unit,expiration_date,issued_by_name) VALUES(?,?,?,?,?,?,?)')->execute([$visit['id'],$transactionId,$m['name'],$m['batch_number'] ?? '',$m['unit'],$m['expiration_date'],$staff['full_name']]);
        $pdo->commit();return ['duplicate'=>false,'transaction_id'=>$transactionId,'balance'=>$movement['balance']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
