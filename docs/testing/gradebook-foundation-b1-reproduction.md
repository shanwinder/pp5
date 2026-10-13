# Task 6V-B1 — Disposable DB reproduction

วันที่ 14 October 2026 · Asia/Bangkok

ใช้สำหรับ B1 review/reproduction เท่านั้น ไม่ใช่ production deployment, activation หรือการเริ่ม B2–B5. อ่านคู่กับ [B1 report](../superpowers/notes/2026-10-13-m6-6-task-6v-b1-foundation.md) และ [SQL review boundary](../../database/review/6v-b1/README.md).

## Safety boundary / prerequisites

- ใช้ MySQL **8.0.40** ที่ initialize ใหม่จาก binary ของ MAMP, PHP **8.3.14** พร้อม `pdo_mysql`, และ PHPUnit **11.5.56** ที่มีอยู่ใน `htdocs/vendor`. ไม่คัดลอก datadir, dump, student/school rows หรือ application config ของ MAMP.
- `GradebookFoundationDatabase::connect()` เป็น connection/ownership guard ไม่ใช่ provisioner. ต้องสร้าง server/schema/owner ก่อนรัน tests. Guard เดิมต้องคง host `127.0.0.1`, port **13396**, schema `pp5_b1_schema`, datadir prefix `/private/tmp/pp5-6v-b1-`, actual datadir ตรง owner file, principal **`pp5_b1@localhost`** และ token ใน `__b1_owner` ตรงกัน.
- ถ้า port 13396 ถูกใช้, binary/path ไม่ตรง, initialize/startup ไม่สำเร็จ หรือ identity ไม่ตรง: **STOP**. ห้ามเปลี่ยน guard, เปลี่ยนเป็น port 8889, ยืม shared `pp5_test`, fallback ไป MAMP config หรือฆ่า process ที่ไม่ใช่ของรอบนี้. ให้ปิดเฉพาะ private server ของรอบก่อนหลังพิสูจน์ ownership หรือจัด environment ที่ตรงข้อกำหนด.
- รัน code blocks ตามลำดับใน **Bash shell เดียวกัน**. `set -euo pipefail` ทำให้ error หยุดขั้นตอน. Network/process sandbox อาจต้องอนุญาตการเปิด private loopback server; การปฏิเสธไม่ใช่ PASS และไม่ใช่เหตุให้ลด isolation.
- Shell/owner/evidence อยู่ใน fresh private temporary directory; ไม่ stage/commit credentials, owner token, datadir หรือ logs. `--no-defaults` ป้องกันการอ่าน MySQL configuration ของ MAMP/เครื่องโดยไม่ตั้งใจ. Private initialized root ใช้สำหรับ provisioning ผ่าน socket ของรอบนี้เท่านั้น.

## 1. Fresh private server

```bash
set -euo pipefail
umask 077
export B1_REPO=/Applications/MAMP/htdocs/pp5
export B1_PHP=/Applications/MAMP/bin/php/php8.3.14/bin/php
export B1_MYSQL=/Applications/MAMP/Library/bin/mysql80/bin
test -x "$B1_PHP"
test -x "$B1_MYSQL/mysqld"
test -f "$B1_REPO/htdocs/vendor/bin/phpunit"
"$B1_PHP" -r 'if (!extension_loaded("pdo_mysql")) exit(1);'
"$B1_MYSQL/mysqld" --no-defaults --version
if /usr/sbin/lsof -nP -iTCP:13396 -sTCP:LISTEN; then
  echo 'STOP: port 13396 already has a listener' >&2
  exit 1
fi
export B1_TMP
B1_TMP=$(mktemp -d /private/tmp/pp5-6v-b1-closure-XXXXXX)
mkdir "$B1_TMP/mysql-isolated"
"$B1_MYSQL/mysqld" --no-defaults --initialize-insecure \
  --basedir="$(dirname "$B1_MYSQL")" \
  --datadir="$B1_TMP/mysql-isolated" \
  --log-error="$B1_TMP/initialize.log"
"$B1_MYSQL/mysqld" --no-defaults \
  --basedir="$(dirname "$B1_MYSQL")" \
  --datadir="$B1_TMP/mysql-isolated" \
  --bind-address=127.0.0.1 --port=13396 --mysqlx=0 \
  --socket="$B1_TMP/mysql.sock" --pid-file="$B1_TMP/mysql.pid" \
  --log-error="$B1_TMP/mysql.log" --daemonize
for attempt in {1..30}; do
  if "$B1_MYSQL/mysqladmin" --no-defaults \
    --socket="$B1_TMP/mysql.sock" --user=root ping >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
"$B1_MYSQL/mysqladmin" --no-defaults \
  --socket="$B1_TMP/mysql.sock" --user=root ping
printf 'Private evidence directory: %s\n' "$B1_TMP"
```

หาก initialize ล้มเหลว ห้าม reuse partial datadir. เก็บ failure log แล้วเริ่มใหม่ด้วย fresh directory หลังจัดข้อจำกัด environment; ห้ามใช้ `--initialize` บน MAMP datadir.

## 2. Provision schema, ownership และ S1–S10

สร้าง script ชั่วคราวด้านล่าง. ก่อน DDL แรก script ตรวจ actual server identity ผ่าน socket, exact engine version/strict mode และ schema ทั้งสองต้องยังไม่มี. สร้างเฉพาะ `pp5_b1_schema` และ `pp5_b1_recovery`, ไม่สร้าง/แตะ `pp5_test`. Account `pp5_b1@localhost` มี ALL เฉพาะสอง schemas นี้ ไม่มี global/SUPER/GRANT OPTION. Token/credential สุ่มใหม่ทุกครั้ง.

```bash
cat > "$B1_TMP/provision.php" <<'PHP'
<?php
declare(strict_types=1);
$repo = getenv('B1_REPO');
$dir = realpath(getenv('B1_TMP'));
if (!$dir || !str_starts_with($dir, '/private/tmp/pp5-6v-b1-')
    || is_link($dir.'/mysql-isolated')) throw new RuntimeException('Unsafe private directory');
$datadir = $dir.'/mysql-isolated/';
$socket = $dir.'/mysql.sock';
$p = new PDO('mysql:unix_socket='.$socket.';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$identity = $p->query('SELECT @@datadir datadir, @@port port, @@socket socket,
    VERSION() version, @@sql_mode sql_mode, DATABASE() db, CURRENT_USER() principal')->fetch();
if ($identity['datadir'] !== $datadir || (int)$identity['port'] !== 13396
    || $identity['socket'] !== $socket || $identity['version'] !== '8.0.40'
    || $identity['db'] !== null || $identity['principal'] !== 'root@localhost'
    || !str_contains($identity['sql_mode'], 'STRICT_TRANS_TABLES')) {
    throw new RuntimeException('Private server identity mismatch; no DDL allowed');
}
$baseline = glob($repo.'/database/migrations/*.sql'); sort($baseline);
$steps = glob($repo.'/database/review/6v-b1/*.sql'); sort($steps);
$seeds = glob($repo.'/database/seeds/*.sql'); sort($seeds);
if (count($baseline) !== 6 || count($steps) !== 10 || count($seeds) !== 4) {
    throw new RuntimeException('Review inventory changed; review before reproduction');
}
$q = $p->query("SELECT COUNT(*) FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME IN ('pp5_b1_schema','pp5_b1_recovery')");
if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Never reuse a schema');
if ((int)$p->query("SELECT COUNT(*) FROM mysql.user WHERE User='pp5_b1'")->fetchColumn() !== 0) {
    throw new RuntimeException('Never reuse a principal');
}
$token = bin2hex(random_bytes(16));
$secret = bin2hex(random_bytes(24));
foreach (['pp5_b1_schema', 'pp5_b1_recovery'] as $db) {
    $p->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $p->exec('USE `'.$db.'`');
    if ($p->query('SELECT DATABASE()')->fetchColumn() !== $db) throw new RuntimeException('Schema mismatch');
    $p->exec('CREATE TABLE __b1_owner (token CHAR(32) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $p->prepare('INSERT INTO __b1_owner VALUES (?)')->execute([$token]);
}
$p->exec('USE pp5_b1_schema');
$p->exec('CREATE TABLE schema_migrations (migration VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
foreach (array_merge($baseline, $steps) as $file) {
    $p->exec(file_get_contents($file));
    $p->prepare('INSERT INTO schema_migrations(migration) VALUES (?)')->execute([basename($file)]);
}
$p->exec('CREATE TABLE seed_migrations (seed VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
foreach ($seeds as $file) {
    $p->exec(file_get_contents($file));
    $p->prepare('INSERT INTO seed_migrations(seed) VALUES (?)')->execute([basename($file)]);
}
$p->exec("CREATE USER 'pp5_b1'@'localhost' IDENTIFIED BY ".$p->quote($secret));
foreach (['pp5_b1_schema', 'pp5_b1_recovery'] as $db) {
    $p->exec('GRANT ALL PRIVILEGES ON `'.$db."`.* TO 'pp5_b1'@'localhost'");
}
$owner = ['token'=>$token, 'datadir'=>$datadir, 'config'=>[
    'host'=>'127.0.0.1', 'port'=>13396, 'database'=>'pp5_b1_schema',
    'username'=>'pp5_b1', 'password'=>$secret, 'charset'=>'utf8mb4',
]];
$path = $dir.'/b1-test-owner.json';
if (file_put_contents($path, json_encode($owner, JSON_THROW_ON_ERROR)) === false
    || !chmod($path, 0600)) throw new RuntimeException('Owner file write failed');
file_put_contents($dir.'/server-identity.json', json_encode($identity, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
putenv('PP5_B1_TEST_OWNER='.$path);
require $repo.'/htdocs/vendor/autoload.php';
require $repo.'/tests/Support/GradebookFoundationDatabase.php';
$guarded = GradebookFoundationDatabase::connect();
$grants = $guarded->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
file_put_contents($dir.'/principal-grants.json', json_encode($grants, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
if ((int)$guarded->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() !== 16
    || (int)$guarded->query('SELECT COUNT(*) FROM gradebook_school_cutovers')->fetchColumn() !== 0) {
    throw new RuntimeException('Incomplete schema or unexpected cutover fixture');
}
echo "PASS: fresh schema, S1–S10, seeds, restricted principal and unchanged ownership guard\n";
PHP
"$B1_PHP" -l "$B1_TMP/provision.php"
"$B1_PHP" "$B1_TMP/provision.php"
export PP5_B1_TEST_OWNER="$B1_TMP/b1-test-owner.json"
test -s "$PP5_B1_TEST_OWNER"
```

MySQL DDL implicit commit: provision ล้มเหลวบางขั้น **ห้าม blind replay / IF NOT EXISTS / แก้ ledger ให้ดูผ่าน**. เก็บ logs, ปิด private server ที่ยืนยันตัวตนแล้ว และเริ่ม fresh datadir. Recovery rehearsal ขั้นที่ 4 เป็นการทดสอบ disposable เท่านั้น. SQL ถูกอ่านตรงจาก review directory; ไม่มีการย้าย/copy SQL เข้า automatic migration directory ของ repository และไม่เรียก live `tools/migrate.php`.

## 3. Run เฉพาะ B1 และตรวจ critical SKIP

Missing owner ทำให้ Feature tests SKIP โดย design; PHPUnit exit 0 แบบทั่วไปจึงไม่พอ. คำสั่งนี้ใช้ exact three-class filter, `--fail-on-skipped`, `--fail-on-empty-test-suite`, `--fail-on-incomplete`, JUnit evidence และไม่สร้าง PHPUnit cache ใน repository.

```bash
cd "$B1_REPO"
"$B1_PHP" htdocs/vendor/bin/phpunit -c phpunit.xml \
  --filter '^(GradebookAnnualFoundationTest|GradebookFoundationSchemaTest|GradebookAnnualCourseFoundationTest)::' \
  --fail-on-empty-test-suite --fail-on-skipped --fail-on-incomplete \
  --fail-on-warning --fail-on-risky --display-skipped --display-incomplete \
  --do-not-cache-result --colors=never \
  --log-junit "$B1_TMP/b1-junit.xml" 2>&1 | tee "$B1_TMP/b1-tests.log"
python3 - "$B1_TMP/b1-junit.xml" <<'PY'
import sys, xml.etree.ElementTree as ET
root = ET.parse(sys.argv[1]).getroot()
cases = root.findall('.//testcase')
expected = {'GradebookAnnualFoundationTest': 32,
            'GradebookFoundationSchemaTest': 35,
            'GradebookAnnualCourseFoundationTest': 25}
actual = {name: sum(c.get('classname') == name for c in cases) for name in expected}
bad = [c for c in cases if any(c.find(tag) is not None for tag in ('skipped','failure','error'))]
if len(cases) != 92 or actual != expected or bad:
    raise SystemExit(f'FAIL: cases={len(cases)}, classes={actual}, skipped/failing={len(bad)}')
print('PASS: 92 B1 cases, exact class counts, 0 SKIP/failure/error')
PY
```

ทั้ง 25 service cases และ 35 schema cases เป็น critical DB coverage ต้องรันจริง รวม two-process creation concurrency, audit rollback, retry after response loss และ actual FK/CHECK negatives. ทั้ง 32 unit cases รวม precision/revision validation. หาก code/data-provider เปลี่ยนจน counts เปลี่ยน ต้องตรวจ inventory/review evidence ใหม่ ห้ามลด expected counts เพื่อหลบ SKIP. เก็บ CLI exit status/log และ JUnit ทั้งคู่; JUnit checker ไม่แทน PHPUnit exit code.

## 4. Recovery rehearsal (B1 existing test)

`pp5_b1_recovery` ต้องมีเพียง `__b1_owner` จาก provisioning; script สร้าง baseline/seed/synthetic legacy fixtures และจำลอง DDL interruption เอง. Guard ตรวจ reference schema และ recovery datadir/port/schema/token ก่อน writes. รันครั้งเดียวต่อ fresh recovery schema; ไม่ rerun บน schema ที่เติมแล้ว.

```bash
"$B1_PHP" tests/Database/gradebook-foundation-recovery.php > "$B1_TMP/recovery.json"
python3 - "$B1_TMP/recovery.json" <<'PY'
import json, sys
r = json.load(open(sys.argv[1]))
if (r['status'] != 'PASS' or r['database'] != 'pp5_b1_recovery'
    or len(r['checks']) != 46 or r['legacy_before_hashes'] != r['legacy_after_hashes']):
    raise SystemExit('FAIL: recovery evidence incomplete')
print('PASS: recovery 46 checks and unchanged synthetic legacy data hashes')
PY
```

## 5. Verify identity before shutdown / retain evidence

ใช้ owner guard ต่อ TCP และ admin socket identity ซ้ำก่อน `SHUTDOWN`; ไม่มี `killall`, ไม่หยุด MAMP, ไม่ DROP schema บน server อื่น. Datadir คงไว้ให้ review; หากต้องการทดสอบใหม่ให้ initialize fresh directory. Directory/token เป็น safety boundary สำหรับ local trusted developer environment ไม่ใช่การป้องกันผู้ที่มีสิทธิ์แก้ datadir/owner file โดยเจตนา.

```bash
cat > "$B1_TMP/shutdown.php" <<'PHP'
<?php
declare(strict_types=1);
$repo = getenv('B1_REPO');
require $repo.'/htdocs/vendor/autoload.php';
require $repo.'/tests/Support/GradebookFoundationDatabase.php';
$guarded = GradebookFoundationDatabase::connect();
$owner = json_decode(file_get_contents(getenv('PP5_B1_TEST_OWNER')), true, 512, JSON_THROW_ON_ERROR);
$dir = realpath(getenv('B1_TMP'));
if (!$dir || $owner['datadir'] !== $dir.'/mysql-isolated/') throw new RuntimeException('Directory mismatch');
$p = new PDO('mysql:unix_socket='.$dir.'/mysql.sock;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($p->query('SELECT @@datadir')->fetchColumn() !== $owner['datadir']
    || (int)$p->query('SELECT @@port')->fetchColumn() !== 13396
    || $p->query('SELECT @@socket')->fetchColumn() !== $dir.'/mysql.sock'
    || $p->query('SELECT CURRENT_USER()')->fetchColumn() !== 'root@localhost') {
    throw new RuntimeException('Shutdown ownership mismatch');
}
foreach (['pp5_b1_schema','pp5_b1_recovery'] as $db) {
    if ((int)$guarded->query('SELECT COUNT(*) FROM `'.$db.'`.schema_migrations')->fetchColumn() !== 16
        || (int)$guarded->query("SELECT COUNT(*) FROM `".$db."`.gradebook_school_cutovers WHERE phase='ACTIVE'")->fetchColumn() !== 0) {
        throw new RuntimeException('Final ledger/activation state mismatch');
    }
}
file_put_contents($dir.'/shutdown-verified.json', json_encode(['datadir'=>$owner['datadir'],
    'port'=>13396, 'schemas'=>['pp5_b1_schema','pp5_b1_recovery'], 'ledger_each'=>16,
    'active_cutovers_each'=>0], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$p->exec('SHUTDOWN');
echo "PASS: owned private server shutdown requested\n";
PHP
"$B1_PHP" "$B1_TMP/shutdown.php"
for attempt in {1..30}; do
  if test ! -e "$B1_TMP/mysql.pid"; then break; fi
  sleep 1
done
test ! -e "$B1_TMP/mysql.pid"
unset PP5_B1_TEST_OWNER
```

เมื่อ test ล้มเหลวก่อน recovery สำเร็จ final ledger check จะบล็อก shutdown block นี้: ให้ตรวจ actual private datadir/port/socket/principal ตามเดิมก่อนสั่ง SHUTDOWN ผ่าน private socket โดยไม่ใช้ ledger completeness เป็นเหตุให้แตะ server อื่น. เก็บ failure evidence และอย่านับเป็น PASS.

## Evidence classification

ขั้นตอนนี้พิสูจน์ disposable provisioning + ownership + B1-focused PHPUnit + existing recovery rehearsal. ไม่ใช่ full PHPUnit rerun, migration-runner revalidation, trusted browser Golden A–M, authenticated MAMP proof หรือ authorization-revocation concurrency proof. ใช้ผล B1 เดิมเฉพาะ gate ที่ PO รับแล้ว; Authorization Lock Inversion ยังเป็น **Mandatory B3 Activation Blocker**. ไม่มี production migration / activation approval.
