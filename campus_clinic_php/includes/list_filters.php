<?php
function clinic_date($value) {
 if(!is_string($value))return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $d && $d->format('Y-m-d')===$value?$d:null;
}
function inventory_period(array $query) {
 $period=is_string($query['period'] ?? null)?$query['period']:'30';if(!in_array($period,['today','7','30','90','all','custom'],true))$period='30';
 $today=new DateTimeImmutable('today');$from=null;$to=null;$error='';
 if($period==='custom'){$from=clinic_date($query['from'] ?? null);$to=clinic_date($query['to'] ?? null);if(!$from || !$to || $from>$to){$error='Choose valid dates with the start on or before the end.';$from=$today->modify('-29 days');$to=$today;$period='30';}}
 elseif($period!=='all'){$to=$today;$days=$period==='today'?1:(int)$period;$from=$today->modify('-'.($days-1).' days');}
 return ['period'=>$period,'from'=>$from?$from->format('Y-m-d'):'','to'=>$to?$to->format('Y-m-d'):'','until'=>$to?$to->modify('+1 day')->format('Y-m-d'):'','error'=>$error];
}
function clinic_pager($total,$raw,$size=20){$pages=max(1,(int)ceil($total/$size));$page=min($pages,max(1,(int)(is_scalar($raw)?$raw:1)));return ['page'=>$page,'pages'=>$pages,'offset'=>($page-1)*$size];}
