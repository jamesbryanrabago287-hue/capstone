<?php
// Transparent baseline, not a forecasting formula specified or validated by the paper.
function medicine_usage_projection($used90,$recordAgeDays,$leadDays=14) {
 $days=max(1,min(90,(int)$recordAgeDays));$usage=max(0,(int)$used90);$lead=max(1,(int)$leadDays);
 if($usage===0)return ['daily'=>null,'next30'=>null,'suggested_point'=>null,'days'=>$days,'quality'=>'No recorded issues in the observation period'];
 $daily=$usage/$days;
 return ['daily'=>$daily,'next30'=>(int)ceil($daily*30),'suggested_point'=>(int)ceil($daily*$lead),'days'=>$days,'quality'=>$days<30?'Limited history: fewer than 30 days':'Historical baseline; not a validated forecast'];
}
