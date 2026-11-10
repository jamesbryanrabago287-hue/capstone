<?php
function clinic_find_students(PDO $pdo,string $query) {
 $query=mb_substr(trim($query),0,80);if($query==='')return [];
 $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';
 $stmt=$pdo->prepare("SELECT id,student_no,full_name,college,course FROM students WHERE student_no LIKE ? ESCAPE '!' OR full_name LIKE ? ESCAPE '!' ORDER BY CASE WHEN student_no=? THEN 0 ELSE 1 END,full_name,student_no LIMIT 21");$stmt->execute([$like,$like,$query]);return $stmt->fetchAll();
}
