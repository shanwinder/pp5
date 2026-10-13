<?php
declare(strict_types=1);

/** Explicit opt-in; never falls back to application/MAMP credentials or a shared pp5_test. */
final class GradebookFoundationDatabase
{
    public static function connect(string $pdoClass = PDO::class): PDO
    {
        $path = getenv('PP5_B1_TEST_OWNER');
        if (!$path || !is_file($path)) { throw new RuntimeException('PP5_B1_TEST_OWNER required'); }
        $owner = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $c = $owner['config'];
        if ($c['host'] !== '127.0.0.1' || $c['database'] !== 'pp5_b1_schema' || $c['port'] !== 13396
            || !str_starts_with($owner['datadir'], '/private/tmp/pp5-6v-b1-')) { throw new RuntimeException('Unsafe B1 test config'); }
        $pdo = new $pdoClass(App\Support\Database::dsn($c), $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $actual = $pdo->query('SELECT DATABASE() db, @@port port, @@datadir datadir, CURRENT_USER() principal')->fetch();
        if ($actual['db'] !== $c['database'] || (int) $actual['port'] !== 13396 || $actual['datadir'] !== $owner['datadir']
            || $actual['principal'] !== 'pp5_b1@localhost' || $pdo->query('SELECT token FROM __b1_owner')->fetchColumn() !== $owner['token']) {
            throw new RuntimeException('B1 ownership mismatch; no writes allowed');
        }
        return $pdo;
    }

    public static function insert(PDO $p, string $table, array $values): int
    {
        $q = $p->prepare('INSERT INTO '.$table.' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', array_fill(0,count($values),'?')).')');
        $q->execute(array_values($values));
        return (int) $p->lastInsertId();
    }

    public static function uuid(): string
    {
        $v = bin2hex(random_bytes(16));
        return substr($v,0,8).'-'.substr($v,8,4).'-'.substr($v,12,4).'-'.substr($v,16,4).'-'.substr($v,20);
    }

    /** Synthetic fixtures only; deliberately different Subject IDs in the two terms. */
    public static function fixture(PDO $p): array
    {
        $suffix = bin2hex(random_bytes(8)); $i = static fn ($t,$v) => self::insert($p,$t,$v);
        $actor = $i('users',['username'=>'b1-'.$suffix,'display_name'=>'B1 synthetic','password_hash'=>'not-a-login']);
        $school = $i('schools',['school_code'=>'b1-'.$suffix,'name_th'=>'B1 synthetic']);
        $i('school_memberships',['school_id'=>$school,'user_id'=>$actor]);
        $role = $p->query("SELECT id FROM roles WHERE code='SCHOOL_ADMIN'")->fetchColumn();
        $i('user_role_assignments',['school_id'=>$school,'user_id'=>$actor,'role_id'=>$role]);
        $year = $i('academic_years',['school_id'=>$school,'year_be'=>2569,'status'=>'ACTIVE']);
        $grade = $p->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $room = $i('classrooms',['school_id'=>$school,'academic_year_id'=>$year,'grade_level_id'=>$grade,'code'=>'B1','name_th'=>'B1']);
        $epoch = self::uuid();
        $i('gradebook_school_cutovers',['school_id'=>$school,'epoch_key'=>$epoch,'created_by'=>$actor,'updated_by'=>$actor]);
        $offerings = [];
        foreach ([1,2] as $term) {
            $subject = $i('subjects',['school_id'=>$school,'code'=>'B1-'.$term,'name_th'=>'B1']);
            $o = ['school_id'=>$school,'academic_year_id'=>$year,'classroom_id'=>$room,'subject_id'=>$subject,'term_no'=>$term];
            $id = $i('subject_offerings',$o); $offerings[] = ['id'=>$id]+$o;
            $i('gradebook_offering_governance',['subject_offering_id'=>$id,'school_id'=>$school,'academic_year_id'=>$year,'cutover_epoch_key'=>$epoch,
                'origin'=>'PRE_ACTIVATION','state'=>'LEGACY','classified_via'=>'INVENTORY','classification_reason'=>'Synthetic test fixture',
                'classified_by'=>$actor,'classification_hash'=>str_repeat('a',64)]);
        }
        return compact('actor','school','year','room','epoch','offerings');
    }
}
