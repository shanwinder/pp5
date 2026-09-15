<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\{SchoolRepository, AcademicYearRepository, StudentRepository, GradeLevelRepository,
    ClassroomRepository, StudentEnrollmentRepository, StudentClassroomPlacementRepository,
    StudentImportBatchRepository, StudentImportRowRepository, AuditLogRepository};
use App\Validation\StudentProfileRules;
use DomainException;
use PDO;
use Throwable;

final class StudentImportService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private StudentRepository $students,
        private GradeLevelRepository $grades,
        private ClassroomRepository $classrooms,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private StudentImportBatchRepository $batches,
        private StudentImportRowRepository $rows,
        private AuditLogRepository $audit
    ) {}

    public function preview(int $schoolId, int $actorUserId, int $academicYearId, string $sourceName, string $sourceSha256, array $rows, ?string $ipAddress = null): int
    {
        $this->expirePreviews($schoolId);
        return $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $sourceName, $sourceSha256, $rows): int {
            $this->lockSchool($schoolId);
            $year = $this->openYear($schoolId, $academicYearId);
            $name = basename($sourceName);
            if ($name === '' || !mb_check_encoding($name, 'UTF-8') || preg_match('/\p{Cc}/u', $name)
                || mb_strlen($name, 'UTF-8') > 190 || !preg_match('/\A[a-f0-9]{64}\z/', $sourceSha256)) {
                throw new DomainException('ข้อมูลไฟล์นำเข้าไม่ถูกต้อง');
            }
            $this->uniqueHash($schoolId, $academicYearId, $sourceSha256);
            $decisions = $this->decisions($schoolId, $year, $rows);
            $id = $this->batches->create($schoolId, $academicYearId, $actorUserId, $name, $sourceSha256);
            foreach ($decisions as $row) { $this->rows->insert($id, $schoolId, $academicYearId, $row); }
            $this->batches->updatePreviewCounts($schoolId, $id, $this->counts($decisions));
            return $id;
        });
    }

    public function apply(int $schoolId, int $actorUserId, int $batchId, ?string $ipAddress = null): void
    {
        $reference = $this->reference($schoolId, $batchId);
        $this->transaction(function () use ($schoolId, $actorUserId, $batchId, $ipAddress, $reference): void {
            $this->lockSchool($schoolId);
            $year = $this->openYear($schoolId, $reference['academic_year_id']);
            $batch = $this->previewBatch($schoolId, $batchId);
            if ($batch['academic_year_id'] !== $year['id']) { throw new DomainException('ไม่พบรายการนำเข้า'); }
            $this->uniqueHash($schoolId, $year['id'], $batch['source_sha256']);
            if ($batch['error_count'] > 0) { throw new DomainException('กรุณาแก้ไขข้อผิดพลาดแล้วนำเข้าไฟล์ใหม่'); }
            $rows = $this->rows->listForBatch($schoolId, $batchId);
            if (count($rows) !== $batch['row_count']) { throw new DomainException('ข้อมูลตัวอย่างไม่ครบ กรุณานำเข้าใหม่'); }
            // The school lock serializes all supported student/academic writes. Lock matched
            // students and enrollments in ID order before resolving placements/classrooms.
            $studentIds = [];
            foreach ($rows as $row) {
                $codeMatch = $this->students->findByCodeForSchool($schoolId, $row['student_code']);
                $nationalMatch = $row['national_id'] === null ? null : $this->students->findByNationalIdForSchool($schoolId, $row['national_id']);
                foreach ([$codeMatch, $nationalMatch] as $match) { if ($match !== null) { $studentIds[$match['id']] = $match['id']; } }
            }
            sort($studentIds, SORT_NUMERIC);
            $enrollmentIds = [];
            foreach ($studentIds as $id) {
                $this->students->lockForSchool($schoolId, $id);
                $enrollment = $this->enrollments->findForStudentYear($schoolId, $year['id'], $id);
                if ($enrollment !== null) { $enrollmentIds[] = $enrollment['id']; }
            }
            sort($enrollmentIds, SORT_NUMERIC);
            foreach ($enrollmentIds as $id) { $this->enrollments->lockForSchool($schoolId, $id); }
            foreach ($enrollmentIds as $id) { $this->placements->lockActiveForEnrollment($schoolId, $id); }
            $decisions = $this->decisions($schoolId, $year, $rows, true);
            $counts = $this->counts($decisions);
            if ($counts['error_count'] > 0 || $counts['create_enrollment_count'] === 0) {
                throw new DomainException('ข้อมูลเปลี่ยนแปลง มีข้อขัดแย้ง หรือไม่มีรายการใหม่ กรุณาตรวจสอบไฟล์อีกครั้ง');
            }
            $summary = ['row_count' => count($decisions), 'created_students' => 0, 'created_enrollments' => 0,
                'created_placements' => 0, 'noop_rows' => 0, 'source_sha256' => $batch['source_sha256']];
            // No business writes occur until every staged row has been revalidated.
            foreach ($decisions as $row) {
                $studentId = $row['matched_student_id'];
                if ($row['student_action'] === 'CREATE') {
                    $studentId = $this->students->create($schoolId, $row);
                    $this->audit->record($schoolId, $actorUserId, 'STUDENT_CREATED', 'students', $studentId, null,
                        ['status' => 'ACTIVE', 'has_national_id' => $row['national_id'] !== null], null, $ipAddress);
                    ++$summary['created_students'];
                }
                if ($row['enrollment_action'] === 'NOOP') { ++$summary['noop_rows']; continue; }
                $id = $this->enrollments->create($schoolId, $year['id'], $studentId, $row['grade_level_id'], $row['entry_date']);
                $this->audit->record($schoolId, $actorUserId, 'STUDENT_ENROLLMENT_CREATED', 'student_enrollments', $id, null,
                    ['academic_year_id' => $year['id'], 'student_id' => $studentId, 'grade_level_id' => $row['grade_level_id'],
                        'entry_date' => $row['entry_date'], 'status' => 'ACTIVE'], null, $ipAddress);
                ++$summary['created_enrollments'];
                if ($row['classroom_id'] !== null) {
                    $this->placements->create($schoolId, $year['id'], $row['grade_level_id'], $id, $row['classroom_id']);
                    $this->audit->record($schoolId, $actorUserId, 'STUDENT_CLASSROOM_PLACEMENT_CHANGED', 'student_enrollments', $id,
                        ['classroom_id' => null], ['classroom_id' => $row['classroom_id']], null, $ipAddress);
                    ++$summary['created_placements'];
                }
            }
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_IMPORT_APPLIED', 'student_import_batches', $batchId, null, $summary, null, $ipAddress);
            $this->batches->markApplied($schoolId, $batchId);
            $this->rows->deleteForBatch($schoolId, $batchId);
        });
    }

    public function cancel(int $schoolId, int $actorUserId, int $batchId): void
    {
        $this->transaction(function () use ($schoolId, $batchId): void {
            $this->lockSchool($schoolId);
            $this->previewBatch($schoolId, $batchId);
            $this->rows->deleteForBatch($schoolId, $batchId);
            $this->batches->markCancelled($schoolId, $batchId);
        });
    }

    public function expirePreviews(int $schoolId): void
    {
        $this->transaction(function () use ($schoolId): void {
            $this->lockSchool($schoolId);
            foreach ($this->batches->expiredPreviewIdsForSchool($schoolId) as $id) {
                $this->rows->deleteForBatch($schoolId, $id);
                $this->batches->markExpired($schoolId, $id);
            }
        });
    }

    private function decisions(int $schoolId, array $year, array $rows, bool $lock = false): array
    {
        if ($rows === [] || count($rows) > 1000) { throw new DomainException('ไฟล์ต้องมีข้อมูล 1–1,000 แถว'); }
        $numbers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['row_no']) || !is_int($row['row_no']) || $row['row_no'] < 2 || isset($numbers[$row['row_no']])) {
                throw new DomainException('หมายเลขแถวในไฟล์ไม่ถูกต้อง');
            }
            $numbers[$row['row_no']] = true;
        }
        usort($rows, static fn (array $a, array $b): int => $a['row_no'] <=> $b['row_no']);
        $result = []; $codes = []; $nationals = [];
        foreach ($rows as $raw) {
            $row = ['row_no' => $raw['row_no'], 'student_code' => '', 'national_id' => null, 'prefix_th' => '', 'first_name_th' => '',
                'last_name_th' => '', 'gender_code' => null, 'birth_date' => null, 'grade_level_code' => '', 'classroom_code' => null,
                'entry_date' => null, 'matched_student_id' => null, 'student_action' => 'NONE', 'enrollment_action' => 'NONE', 'error_code' => null, 'error_message' => null];
            try {
                $profile = [];
                foreach (['student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th', 'gender_code', 'birth_date'] as $field) {
                    $value = $raw[$field] ?? null;
                    if (!is_string($value) && !(in_array($field, ['national_id', 'gender_code', 'birth_date'], true) && $value === null)) {
                        throw new DomainException('ข้อมูลนักเรียนต้องเป็นข้อความ');
                    }
                    $profile[] = $value;
                }
                $profile = StudentProfileRules::normalize(...$profile);
                $row = array_replace($row, $profile);
                $codes[count($result)] = $row['student_code'];
                $row['grade_level_code'] = $this->text($raw['grade_level_code'] ?? null, 20, true);
                $row['classroom_code'] = $this->text($raw['classroom_code'] ?? null, 50);
                $entry = $this->text($raw['entry_date'] ?? null, 10);
                $this->date($entry, $year);
                $row['entry_date'] = $entry;
                if ($row['national_id'] !== null) { $nationals[$row['national_id']][] = count($result); }
                $grade = null;
                foreach ($this->grades->listActive() as $candidate) { if ($candidate['code'] === $row['grade_level_code']) { $grade = $candidate; break; } }
                if ($grade === null) { throw new DomainException('ไม่พบระดับชั้นที่เปิดใช้งาน'); }
                $row['grade_level_id'] = $grade['id']; $row['classroom_id'] = null;
                if ($row['classroom_code'] !== null) {
                    foreach ($this->classrooms->listForSchool($schoolId, $year['id']) as $room) {
                        if ($room['code'] !== $row['classroom_code']) { continue; }
                        if ($lock) { $room = $this->classrooms->lockForSchool($schoolId, $room['id']); }
                        if ($room !== null && $room['academic_year_id'] === $year['id'] && $room['grade_level_id'] === $grade['id'] && $room['status'] === 'ACTIVE') { $row['classroom_id'] = $room['id']; }
                        break;
                    }
                    if ($row['classroom_id'] === null) { throw new DomainException('ไม่พบห้องเรียนที่เปิดใช้งานในโรงเรียน ปีการศึกษา และระดับชั้นนี้'); }
                }
                $byNational = $row['national_id'] === null ? null : $this->students->findByNationalIdForSchool($schoolId, $row['national_id']);
                $byCode = $this->students->findByCodeForSchool($schoolId, $row['student_code']);
                if (($byNational !== null && ($byCode === null || $byCode['id'] !== $byNational['id']))
                    || ($byCode !== null && array_replace($profile, array_intersect_key($byCode, $profile)) !== $profile)) {
                    $row = $this->error($row, 'CONFLICT', 'ข้อมูลตัวตนนักเรียนขัดกับข้อมูลเดิม');
                } elseif ($byCode === null) {
                    $row['student_action'] = 'CREATE'; $row['enrollment_action'] = 'CREATE';
                } else {
                    if ($byCode['status'] !== 'ACTIVE') { throw new DomainException('นักเรียนไม่ได้เปิดใช้งาน'); }
                    $row['matched_student_id'] = $byCode['id']; $row['student_action'] = 'MATCH';
                    $enrollment = $this->enrollments->findForStudentYear($schoolId, $year['id'], $byCode['id']);
                    $placement = $enrollment === null ? null : $this->placements->findActiveForEnrollment($schoolId, $enrollment['id']);
                    if ($enrollment === null) { $row['enrollment_action'] = 'CREATE'; }
                    elseif ($enrollment['status'] === 'ACTIVE' && $enrollment['grade_level_id'] === $grade['id'] && ($placement['classroom_id'] ?? null) === $row['classroom_id']) { $row['enrollment_action'] = 'NOOP'; }
                    else { $row = $this->error($row, 'CONFLICT', 'การลงทะเบียนหรือห้องเรียนขัดกับข้อมูลเดิม'); }
                }
            } catch (DomainException $exception) { $row = $this->error($row, 'ERROR', $exception->getMessage()); }
            $result[] = $row;
        }
        foreach ($this->students->duplicateCodeIndexes($codes) as $index) {
            $result[$index] = $this->error($result[$index], 'ERROR', 'ข้อมูลนักเรียนซ้ำภายในไฟล์');
        }
        foreach ($nationals as $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $index) { $result[$index] = $this->error($result[$index], 'ERROR', 'ข้อมูลนักเรียนซ้ำภายในไฟล์'); }
            }
        }
        return $result;
    }

    private function error(array $row, string $code, string $message): array
    {
        return array_replace($row, ['student_action' => 'NONE', 'enrollment_action' => 'NONE', 'matched_student_id' => null,
            'error_code' => $code, 'error_message' => 'แถว ' . $row['row_no'] . ': ' . $message]);
    }

    private function counts(array $rows): array
    {
        return ['row_count' => count($rows), 'create_student_count' => count(array_filter($rows, static fn ($r) => $r['student_action'] === 'CREATE')),
            'create_enrollment_count' => count(array_filter($rows, static fn ($r) => $r['enrollment_action'] === 'CREATE')),
            'noop_count' => count(array_filter($rows, static fn ($r) => $r['enrollment_action'] === 'NOOP')),
            'error_count' => count(array_filter($rows, static fn ($r) => $r['error_code'] !== null))];
    }

    private function text(mixed $value, int $max, bool $required = false): ?string
    {
        if ($value === null && !$required) { return null; }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) { throw new DomainException('ข้อมูลนำเข้าต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม'); }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
        if (mb_strlen($value, 'UTF-8') > $max || ($required && $value === '')) { throw new DomainException('ข้อมูลนำเข้าขาดหายหรือยาวเกินกำหนด'); }
        return $value === '' ? null : $value;
    }

    private function date(?string $date, array $year): void
    {
        if ($date === null) { return; }
        if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $date, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
            || ($year['start_date'] !== null && $date < $year['start_date']) || ($year['end_date'] !== null && $date > $year['end_date'])) {
            throw new DomainException('วันที่เริ่มลงทะเบียนต้องเป็น YYYY-MM-DD ภายในปีการศึกษา');
        }
    }
    private function lockSchool(int $schoolId): void { if ($this->schools->lockActiveById($schoolId) === null) { throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน'); } }
    private function openYear(int $schoolId, int $yearId): array
    {
        $year = $this->years->lockForSchool($schoolId, $yearId);
        if ($year === null || !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) { throw new DomainException('ไม่พบปีการศึกษาที่เปิดให้นำเข้า'); }
        return $year;
    }
    private function uniqueHash(int $schoolId, int $yearId, string $hash): void
    {
        if ($this->batches->findAppliedByHash($schoolId, $yearId, $hash) !== null) { throw new DomainException('ไฟล์นี้นำเข้าในปีการศึกษานี้แล้ว'); }
    }
    private function previewBatch(int $schoolId, int $batchId): array
    {
        $batch = $this->batches->lockForSchool($schoolId, $batchId);
        if ($batch === null || $batch['status'] !== 'PREVIEW' || $batch['is_expired']) { throw new DomainException('ไม่พบรายการตัวอย่างที่ใช้งานได้'); }
        return $batch;
    }
    private function reference(int $schoolId, int $batchId): array
    {
        try { $batch = $this->batches->findForSchool($schoolId, $batchId); }
        catch (Throwable) { throw new DomainException('ไม่สามารถอ่านรายการนำเข้าได้'); }
        if ($batch === null) { throw new DomainException('ไม่พบรายการตัวอย่างที่ใช้งานได้'); }
        return $batch;
    }
    private function transaction(callable $operation): mixed
    {
        $started = false;
        try { $this->pdo->beginTransaction(); $started = true; $result = $operation(); $this->pdo->commit(); return $result; }
        catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($exception instanceof DomainException) { throw $exception; }
            throw new DomainException('ไม่สามารถบันทึกการนำเข้าได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
