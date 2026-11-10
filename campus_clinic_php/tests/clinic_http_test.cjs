const {execFileSync}=require('child_process');
const path=require('path');
const root=path.resolve(__dirname,'..');
const base=process.env.CLINIC_TEST_URL||'http://localhost/3b_rabago/campus_clinic_php/';
const marker='TEST-HANDOFF-'+Date.now();
const php=(code,args=[])=>execFileSync('C:/xampp/php/php.exe',['-r',code,...args],{encoding:'utf8',cwd:root}).trim();
function check(ok,label){if(!ok)throw new Error(label);console.log('PASS: '+label);}
let staffSession=null,publicSession=null;
(async()=>{try{
 const r=await fetch(base+'pre_assessment.php');const html=await r.text(),cookie=r.headers.getSetCookie().map(x=>x.split(';')[0]).join('; ');publicSession=cookie.match(/PHPSESSID=([^;]+)/)?.[1];const csrf=html.match(/data-csrf="([^"]+)"/)[1];
 const studentPost=async values=>{const r=await fetch(base+'chatbot.php',{method:'POST',headers:{Cookie:cookie},body:new URLSearchParams({...values,csrf})});return {status:r.status,data:await r.json()};};
 const a=await studentPost({action:'assess',answers:JSON.stringify({symptom:'other',warning:'no',duration:'today',severity:'mild',trend:'improving',hydration:'normal',age:'adult',risk:'no',details:'Synthetic unlisted symptom'})});check(a.status===200&&a.data.result.next_action.includes('Visit the campus clinic'),'student next action');
 const values={action:'save',assessment_token:a.data.assessment_token,consent:'yes',student_name:marker,student_no:marker};const receipt=await studentPost(values),retry=await studentPost(values),id=receipt.data.reference;
 check(receipt.status===200&&receipt.data.reference_label.startsWith('CC-')&&receipt.data.handoff.includes('student ID'),'reference receipt and visit instructions');check(id===retry.data.reference,'submission retry is idempotent');
 const f=JSON.parse(php('require "includes/auth.php";$pdo->prepare("INSERT INTO students(student_no,full_name,college,course) VALUES(?,?,?,?)")->execute([$argv[1],$argv[1],"Test college","Test course"]);$student=$pdo->lastInsertId();$u=$pdo->query("SELECT id,username,full_name,role FROM users WHERE status=\'Active\' LIMIT 1")->fetch();if(!$u)exit(1);$_SESSION["user"]=$u;echo json_encode(["student"=>$student,"session"=>session_id(),"csrf"=>csrf_token()]);',[marker]));staffSession=f.session;const staffCookie='PHPSESSID='+staffSession;
 const get=async p=>{const r=await fetch(base+p,{headers:{Cookie:staffCookie},redirect:'manual'});return {status:r.status,html:await r.text()};};
 const post=async(p,v)=>{const r=await fetch(base+p,{method:'POST',headers:{Cookie:staffCookie},redirect:'manual',body:new URLSearchParams({csrf:f.csrf,...v})});return {status:r.status,html:await r.text()};};
 let inbox=await get('assessments.php?status=New&q='+receipt.data.reference_label);check(inbox.status===200&&inbox.html.includes(marker),'new assessment searchable');
 const page='consultation.php?assessment_id='+id;
 check((await post(page,{action:'start',student_id:f.student,csrf:'invalid'})).status===400,'invalid CSRF rejected');
 check((await post(page,{action:'start',student_id:f.student})).html.includes('Confirm that you verified'),'identity verification required');
 check((await post(page,{action:'start',student_id:f.student,verified:'yes'})).status===302,'verified visit starts');
 let visit=await get(page);check(visit.html.includes('In progress consultation')&&visit.html.includes('name="version" value="1"'),'in-progress record opens');
 const notes={findings:'Synthetic staff findings <script>test</script>',next_steps:'Synthetic next steps',outcome:'Follow-up advised',version:'1'};
 check((await post(page,{action:'save',...notes})).status===302,'draft saves');
 visit=await get(page);check(visit.html.includes('&lt;script&gt;test&lt;/script&gt;')&&visit.html.includes('name="version" value="2"'),'notes escaped and revision updated');
 const stale=await post(page,{action:'save',...notes});check(stale.html.includes('updated elsewhere')&&stale.html.includes('name="version" value="1"'),'stale draft cannot overwrite newer visit');
 check((await post(page,{action:'complete',version:'2',findings:'',next_steps:'',outcome:''})).html.includes('before completing'),'incomplete visit cannot complete');
 check((await post(page,{action:'complete',...notes,version:'2'})).status===302,'visit completes');
 visit=await get(page);check(visit.html.includes('Completed consultation')&&visit.html.includes('This completed visit is read-only.')&&!visit.html.includes('textarea name="findings"'),'completed record read-only');
 inbox=await get('assessments.php?status=Completed&student_id='+f.student);check(inbox.html.includes(marker)&&inbox.html.includes('View completed consultation'),'completed visit in verified history');
 check((await post(page,{action:'save',...notes,version:'3'})).html.includes('completed and read-only'),'stale POST cannot edit completed visit');
 const anon=await fetch(base+page,{redirect:'manual'});check(anon.status===302&&anon.headers.get('location')==='login.php','public access blocked');
 console.log('Student-to-consultation HTTP workflow passed.');
 }finally{
 php('require "includes/auth.php";$s=$pdo->prepare("SELECT id FROM chatbot_sessions WHERE student_no=? AND student_name=?");$s->execute([$argv[1],$argv[1]]);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id){$pdo->prepare("DELETE FROM clinic_consultations WHERE assessment_id=? AND student_no_snapshot=?")->execute([$id,$argv[1]]);$pdo->prepare("DELETE FROM chatbot_reviews WHERE session_id=?")->execute([$id]);$pdo->prepare("DELETE FROM chatbot_sessions WHERE id=? AND student_no=? AND student_name=?")->execute([$id,$argv[1],$argv[1]]);}$pdo->prepare("DELETE FROM students WHERE student_no=? AND full_name=?")->execute([$argv[1],$argv[1]]);',[marker]);
 for(const id of [staffSession,publicSession])if(id)php('session_id($argv[1]);session_start();session_destroy();',[id]);
 console.log('Synthetic records removed. Audit events for the test remain.');
 }
})().catch(e=>{console.error('FAIL:',e.message);process.exitCode=1});
