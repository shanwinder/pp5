<?php
// File: pages/teacher_dashboard.php
// Role: Teacher Dashboard (ระบบ ปพ.5 ออนไลน์)
// Requirements: PHP 8+, session auth, RBAC. Uses /assets/css/pp5-core.css

//session_start();
//require_once __DIR__ . '/../includes/auth.php';
// require_once __DIR__ . '/../includes/db.php'; // <-- เชื่อมต่อฐานข้อมูลจริงเมื่อพร้อม
// require_once __DIR__ . '/../includes/rbac.php'; // <-- ถ้ามีไฟล์ RBAC แยก

// ==== DEMO ONLY (ลบออกเมื่อเชื่อมต่อจริง) ====
$_SESSION['role'] = $_SESSION['role'] ?? 'teacher';
$_SESSION['display_name'] = $_SESSION['display_name'] ?? 'ครูสมชาย ใจดี';
$_SESSION['school_name'] = $_SESSION['school_name'] ?? 'โรงเรียนบ้านนาอุดม';
// ตัวเลือกปี/ภาคเรียน (ตัวอย่าง) — ในระบบจริงดึงจากตาราง academic_years และ terms
$years = ['2567/2', '2568/1', '2568/2'];
$year = $_GET['year'] ?? '2568/1';
$term = $_GET['term'] ?? '1';
$term_status = 'เปิดกรอกคะแนน'; // หรือ 'ล็อกภาคเรียน'

// ตัวอย่างสถิติ (ในระบบจริงคำนวณจาก DB)
$stats = [
    ['label' => 'จำนวนนักเรียนในความรับผิดชอบ', 'value' => 32],
    ['label' => 'รายวิชาในภาคเรียนนี้', 'value' => 5],
    ['label' => 'รายการคะแนนที่ยังไม่ครบ', 'value' => 12],
    ['label' => 'ผู้มีเวลาเรียน < 80%', 'value' => 3],
];

$lowAttendance = [
    ['code' => '401', 'name' => 'เด็กชายวชิรวิชญ์ ใจดี', 'pct' => 74.5, 'absent' => 18],
    ['code' => '402', 'name' => 'เด็กหญิงปภัสรา ชื่นใจ', 'pct' => 77.0, 'absent' => 16],
    ['code' => '409', 'name' => 'เด็กชายณรงค์ฤทธิ์ เก่งงาน', 'pct' => 79.5, 'absent' => 15],
];

$myTasks = [
    ['title' => 'บันทึกผลตัวชี้วัด วิทยาการคำนวณ (ป.4/1–4/3)', 'due' => 'ภายใน 10 ต.ค. 2568', 'url' => '/pages/assess/score-entry.php?subject=SCI-COMP-401'],
    ['title' => 'อัปเดตเวลาเรียน คาบวันจันทร์', 'due' => 'วันนี้', 'url' => '/pages/attendance/mark.php?class=P4A&date=today'],
    ['title' => 'บันทึก “อ่าน-คิดวิเคราะห์-เขียน” กลุ่ม P4/1', 'due' => 'ภายใน 12 ต.ค. 2568', 'url' => '/pages/rwt/entry.php?class=P4A'],
];

// ===== Helper (HTML Escape) =====
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ===== Guard: เฉพาะครู =====
if (($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: /auth/login.php');
    exit;
}
?>
<!doctype html>
<html lang="th" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ดครู • ระบบ ปพ.5</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pp5-core.css">
</head>

<body>
    <div class="app">
        <!-- Header -->
        <header class="header" role="banner">
            <div class="navbar justify-between">
                <div class="d-flex items-center gap-4">
                    <a class="navbar-brand" href="/pages/teacher_dashboard.php" aria-label="ไปหน้าแดชบอร์ดครู">ระบบ ปพ.5</a>
                    <nav class="nav d-none d-md-flex" aria-label="เมนูด่วน">
                        <a href="/pages/attendance/mark.php" class="<?php /* set active ตามหน้า */ ?>">เวลาเรียน</a>
                        <a href="/pages/assess/score-entry.php">บันทึกผลตัวชี้วัด</a>
                        <a href="/pages/reports/popor5_generate.php">พิมพ์ ปพ.5</a>
                    </nav>
                </div>
                <div class="d-flex items-center gap-3">
                    <form method="get" class="d-flex items-center gap-2" aria-label="เลือกปีการศึกษาและภาคเรียน">
                        <label for="year" class="small text-muted">ปี/ภาค</label>
                        <select id="year" name="year" class="input" style="max-width: 9rem">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= h($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= h($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="term" name="term" class="input" style="max-width: 5rem">
                            <option value="1" <?= $term === '1' ? 'selected' : '' ?>>1</option>
                            <option value="2" <?= $term === '2' ? 'selected' : '' ?>>2</option>
                        </select>
                        <button class="btn btn-secondary btn-sm" type="submit">แสดง</button>
                    </form>
                    <div class="d-none d-md-flex flex-column text-end">
                        <span class="small"><?= h($_SESSION['school_name']) ?></span>
                        <strong class="small"><?= h($_SESSION['display_name']) ?></strong>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="/auth/logout.php">ออกจากระบบ</a>
                </div>
            </div>
        </header>

        <!-- Body: Sidebar + Main -->
        <div class="app-body">
            <!-- Sidebar -->
            <aside class="sidebar" role="complementary" aria-label="เมนูด้านข้าง">
                <ul class="menu">
                    <li><a class="active" aria-current="page" href="/pages/teacher_dashboard.php">แดชบอร์ด</a></li>
                    <li><a href="/pages/attendance/mark.php">บันทึกเวลาเรียน</a></li>
                    <li><a href="/pages/assess/score-entry.php">บันทึกผลตัวชี้วัด</a></li>
                    <li><a href="/pages/rwt/entry.php">อ่าน-คิดวิเคราะห์-เขียน</a></li>
                    <li><a href="/pages/traits/entry.php">คุณลักษณะอันพึงประสงค์</a></li>
                    <li><a href="/pages/activities/entry.php">กิจกรรมพัฒนาผู้เรียน</a></li>
                    <li><a href="/pages/reports/popor5_generate.php">รายงาน ปพ.5</a></li>
                    <li><a href="/pages/settings/profile.php">ตั้งค่า</a></li>
                </ul>
            </aside>

            <!-- Main -->
            <main class="bg-subtle">
                <div class="container p-4">
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb mb-3" aria-label="breadcrumb">
                        <a href="/pages/teacher_dashboard.php">หน้าแรก</a>
                        <span class="divider">/</span>
                        <span aria-current="page">แดชบอร์ดครู</span>
                    </nav>

                    <!-- Term status / alerts -->
                    <?php if ($term_status === 'เปิดกรอกคะแนน'): ?>
                        <div class="alert alert-success" role="status">ภาคเรียนนี้ <strong>เปิดกรอกคะแนน</strong> — กรุณาบันทึกผลตัวชี้วัดให้ครบทุกวิชา</div>
                    <?php else: ?>
                        <div class="alert alert-warning" role="status">ภาคเรียนนี้ <strong>ถูกล็อก</strong> — แก้ไขคะแนนไม่ได้ กรุณาติดต่อผู้ดูแลระบบ</div>
                    <?php endif; ?>

                    <!-- KPI Cards -->
                    <div class="row">
                        <?php foreach ($stats as $i => $st): ?>
                            <div class="col-12 col-sm-6 col-lg-3 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="h3 mb-2"><?= h($st['value']) ?></div>
                                        <div class="small text-muted"><?= h($st['label']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row">
                        <!-- Tasks -->
                        <div class="col-12 col-lg-9 mb-4">
                            <div class="card">
                                <div class="card-header">งานของฉัน (<?= h($year) ?> ภาคเรียนที่ <?= h($term) ?>)</div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>รายการ</th>
                                                    <th style="width: 180px">กำหนดส่ง</th>
                                                    <th style="width: 120px" class="text-end">ดำเนินการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($myTasks as $t): ?>
                                                    <tr>
                                                        <td><?= h($t['title']) ?></td>
                                                        <td><span class="badge badge-light"><?= h($t['due']) ?></span></td>
                                                        <td class="text-end">
                                                            <a class="btn btn-primary btn-sm" href="<?= h($t['url']) ?>">ไปยังหน้า</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-12 col-lg-3 mb-4">
                            <div class="card">
                                <div class="card-header">เมนูด่วน</div>
                                <div class="card-body stack">
                                    <a class="btn btn-secondary w-100" href="/pages/attendance/mark.php">บันทึกเวลาเรียนวันนี้</a>
                                    <a class="btn btn-secondary w-100" href="/pages/assess/score-entry.php">กรอกคะแนนตัวชี้วัด</a>
                                    <a class="btn btn-secondary w-100" href="/pages/reports/popor5_generate.php">สร้างรายงาน ปพ.5</a>
                                    <a class="btn btn-secondary w-100" href="/pages/settings/profile.php">โปรไฟล์ & การตั้งค่า</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance risk list -->
                    <div class="card mb-4">
                        <div class="card-header">รายชื่อนักเรียนที่มีเวลาเรียนต่ำกว่า 80%</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px">เลขที่</th>
                                            <th>ชื่อ-สกุล</th>
                                            <th style="width: 140px">ร้อยละเวลาเรียน</th>
                                            <th style="width: 140px">ชั่วโมงที่ขาด</th>
                                            <th style="width: 120px" class="text-end">การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lowAttendance as $s): ?>
                                            <tr>
                                                <td><?= h($s['code']) ?></td>
                                                <td><?= h($s['name']) ?></td>
                                                <td><span class="badge badge-secondary"><?= number_format($s['pct'], 1) ?>%</span></td>
                                                <td><?= h($s['absent']) ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-link" href="/pages/attendance/mark.php?student=<?= h($s['code']) ?>">ตรวจสอบ</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted">หมายเหตุ: ระบบแนะนำให้นัดหมายสอนซ่อมเสริมสำหรับผู้ที่ต่ำกว่า 80% ก่อนตัดสินผลรายวิชา</p>
                        </div>
                    </div>

                    <!-- Footer note -->
                    <p class="small text-muted">© <?= date('Y') ?> ระบบ ปพ.5 • <?= h($_SESSION['school_name']) ?></p>
                </div>
            </main>
        </div>
    </div>
</body>

</html>