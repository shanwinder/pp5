<?php
declare(strict_types=1);
// Explicit disposable-only CLI rehearsal; no production runner replacement.
if (PHP_SAPI !== 'cli') { exit(2); }
require dirname(__DIR__,2).'/htdocs/vendor/autoload.php';
require dirname(__DIR__).'/Support/GradebookFoundationDatabase.php';
$reference=GradebookFoundationDatabase::connect();
$owner=json_decode(file_get_contents(getenv('PP5_B1_TEST_OWNER')),true,512,JSON_THROW_ON_ERROR);
$c=$owner['config'];$c['database']='pp5_b1_recovery';
$p=App\Support\Database::connect($c);
if($p->query('SELECT @@datadir')->fetchColumn()!==$owner['datadir'] || (int)$p->query('SELECT @@port')->fetchColumn()!==13396
    || $p->query('SELECT DATABASE()')->fetchColumn()!=='pp5_b1_recovery'
    || $p->query('SELECT token FROM __b1_owner')->fetchColumn()!==$owner['token'])throw new RuntimeException('Recovery ownership mismatch');
$checks=[];
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
$root=dirname(__DIR__,2);
$p->exec('CREATE TABLE schema_migrations (migration VARCHAR(190) NOT NULL PRIMARY KEY,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
foreach(glob($root.'/database/migrations/*.sql') as $f){$p->exec(file_get_contents($f));$p->prepare('INSERT INTO schema_migrations(migration) VALUES(?)')->execute([basename($f)]);}
foreach(glob($root.'/database/seeds/*.sql') as $f)$p->exec(file_get_contents($f));
$i=fn($t,$v)=>GradebookFoundationDatabase::insert($p,$t,$v);
$school=$i('schools',['school_code'=>'b1-legacy','name_th'=>'Synthetic legacy']);
$user=$i('users',['username'=>'b1-legacy','display_name'=>'Synthetic','password_hash'=>'not-a-login']);
$year=$i('academic_years',['school_id'=>$school,'year_be'=>2569,'status'=>'ACTIVE']);
$grade=$p->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
$room=$i('classrooms',['school_id'=>$school,'academic_year_id'=>$year,'grade_level_id'=>$grade,'code'=>'B1','name_th'=>'Synthetic']);
$subject=$i('subjects',['school_id'=>$school,'code'=>'B1','name_th'=>'Synthetic']);
$offering=$i('subject_offerings',['school_id'=>$school,'academic_year_id'=>$year,'classroom_id'=>$room,'subject_id'=>$subject,'term_no'=>1]);
$student=$i('students',['school_id'=>$school,'student_code'=>'SYNTHETIC','prefix_th'=>'ด.ช.','first_name_th'=>'ทดสอบ','last_name_th'=>'เท่านั้น']);
$enrollment=$i('student_enrollments',['school_id'=>$school,'academic_year_id'=>$year,'student_id'=>$student,'grade_level_id'=>$grade]);
foreach([null,'0.00','1.50'] as $k=>$score){$component=$i('gradebook_components',['school_id'=>$school,'academic_year_id'=>$year,'subject_offering_id'=>$offering,'code'=>'B1-'.$k,'name_th'=>'Synthetic','max_score'=>'23.75','sort_order'=>$k]);
$i('gradebook_scores',['school_id'=>$school,'academic_year_id'=>$year,'subject_offering_id'=>$offering,'enrollment_id'=>$enrollment,'component_id'=>$component,'score'=>$score,'updated_by'=>$user]);}
(new App\Repositories\AuditLogRepository($p))->record($school,$user,'B1_SYNTHETIC_LEGACY','subject_offerings',$offering,null,['synthetic'=>true],'Synthetic history preservation',null);
$oldTables=$p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$oldTables=array_values(array_diff($oldTables,['schema_migrations','__b1_owner']));
$snapshot=static function()use($p,$oldTables):array{$result=[];foreach($oldTables as $t){$rows=$p->query('SELECT * FROM `'.$t.'`')->fetchAll();$json=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($json);$result[$t]=hash('sha256',implode("\n",$json));}return $result;};
$before=$snapshot();
$shape=static function(PDO $db,string $table,bool $beforeS10=false):string{
 $sql=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
 $sql=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$sql);
 if($beforeS10)$sql=preg_replace('/^  CONSTRAINT `fk_governance_(first|latest)_receipt`[^\n]+\n/m','',$sql);
 return $sql;
};
$targets=['subject_offerings','gradebook_school_cutovers','gradebook_offering_governance','gradebook_annual_courses','gradebook_annual_course_offerings','gradebook_score_sections','gradebook_component_sections','gradebook_structure_drafts','gradebook_structure_publish_receipts','gradebook_offering_governance'];
$files=glob($root.'/database/review/6v-b1/*.sql');sort($files);$assert(count($files)===10,'Exact S1–S10 inventory');
// A ledger row alone is not evidence: inject inconsistent S2 claim, observe missing table, remove only test claim.
$p->prepare('INSERT INTO schema_migrations(migration) VALUES(?)')->execute([basename($files[1])]);
$assert((int)$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='gradebook_school_cutovers'")->fetchColumn()===0,'Detect ledger-complete/schema-absent inconsistency');
$p->prepare('DELETE FROM schema_migrations WHERE migration=?')->execute([basename($files[1])]);
// Candidate-index mismatch must not be reconciled as complete.
$p->exec('ALTER TABLE subject_offerings ADD UNIQUE KEY uq_offering_annual_scope(id,school_id)');
$assert($shape($p,'subject_offerings')!==$shape($reference,'subject_offerings'),'Detect wrong candidate-index tuple');
$p->exec('ALTER TABLE subject_offerings DROP INDEX uq_offering_annual_scope');
foreach($files as $n=>$f){
 $name=basename($f);$sql=file_get_contents($f);$p->exec($sql); // simulate process loss AFTER DDL but BEFORE ledger insert
 $q=$p->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration=?');$q->execute([$name]);$assert((int)$q->fetchColumn()===0,'S'.($n+1).' DDL committed without ledger');
 try{$p->exec($sql);throw new RuntimeException('Blind replay unexpectedly succeeded');}catch(PDOException $e){$assert(in_array($e->errorInfo[1],[1050,1061,1826],true),'S'.($n+1).' blind replay refused');}
 if($n===9){
  $p->exec('ALTER TABLE gradebook_offering_governance DROP FOREIGN KEY fk_governance_latest_receipt');
  $assert($shape($p,$targets[$n])!==$shape($reference,$targets[$n]),'Incomplete S10 is not complete');
  $p->exec('ALTER TABLE gradebook_offering_governance ADD CONSTRAINT fk_governance_latest_receipt FOREIGN KEY(latest_publish_receipt_id,school_id,academic_year_id,subject_offering_id) REFERENCES gradebook_structure_publish_receipts(id,school_id,academic_year_id,subject_offering_id) ON DELETE RESTRICT ON UPDATE RESTRICT');
 }
 $assert($shape($p,$targets[$n])===$shape($reference,$targets[$n],$n===2),'S'.($n+1).' exact SHOW CREATE attestation before recovery');
 // Explicit rehearsal recovery of that verified step only. Never use IF NOT EXISTS or pretend DDL rollback.
 $p->prepare('INSERT INTO schema_migrations(migration) VALUES(?)')->execute([$name]);
}
$assert($before===$snapshot(),'All preexisting data including NULL/zero/scores/maxima/audit history unchanged');
$assert((int)$p->query('SELECT COUNT(*) FROM gradebook_offering_governance')->fetchColumn()===0,'No automatic Legacy classification');
$assert((int)$p->query('SELECT COUNT(*) FROM gradebook_annual_courses')->fetchColumn()===0,'No automatic annual pairing');
foreach(array_unique($targets) as $t)$assert($shape($p,$t)===$shape($reference,$t),'Final schema matches '.$t);
echo json_encode(['status'=>'PASS','database'=>'pp5_b1_recovery','checks'=>$checks,'legacy_before_hashes'=>$before,'legacy_after_hashes'=>$snapshot()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
