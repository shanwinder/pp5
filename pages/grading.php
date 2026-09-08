<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบกรอกคะแนน ปพ.5 - Excel Mode</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container-fluid py-4 px-3 px-lg-4">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
        <div class="mb-3 mb-md-0 d-flex align-items-center">
            <div class="shadow-sm bg-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                <i class="bi bi-file-earmark-spreadsheet-fill fs-3" style="color: #217346;"></i>
            </div>
            <div>
                <h2 class="mb-0 head-title" style="color: #111827;">บันทึกคะแนนและผลการเรียน</h2>
                <p class="text-muted mb-0 mt-1" style="font-size: 0.9rem;"><i class="bi bi-layout-text-window-reverse"></i> Excel-like Grid Mode Active</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <span class="badge-custom badge-primary-custom d-flex align-items-center gap-2">
                <i class="bi bi-book"></i> ภาษาไทย (ท14101)
            </span>
            <span class="badge-custom badge-info-custom d-flex align-items-center gap-2">
                <i class="bi bi-people"></i> ชั้น ป.4
            </span>
            <span class="badge-custom badge-success-custom d-flex align-items-center gap-2">
                <i class="bi bi-calendar3"></i> เทอม 1/2567
            </span>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="glass-card shadow-sm">
        <div class="table-responsive" style="max-height: 70vh;">
            <form action="../actions/save_grading.php" method="POST" id="gradingForm">
                <!-- Changed to table-bordered to show clear grid lines -->
                <table class="table table-bordered table-grading mb-0">
                    <thead class="text-center align-middle sticky-top" style="z-index: 20;">
                        <tr>
                            <th rowspan="2" width="5%" class="bg-light">เลขที่</th>
                            <th rowspan="2" width="10%" class="bg-light">รหัส</th>
                            <th rowspan="2" width="22%" class="text-start ps-4 bg-light">ชื่อ - สกุล</th>
                            <th colspan="3" class="bg-light">คะแนนเก็บระหว่างเรียน (70)</th>
                            <th rowspan="2" width="10%" class="bg-light"><div class="fw-bold">ปลายภาค</div><div class="fw-normal mt-1">(30)</div></th>
                            <th rowspan="2" width="10%" class="bg-light"><div class="fw-bold">รวม</div><div class="fw-normal mt-1">(100)</div></th>
                            <th rowspan="2" width="10%" class="bg-light"><div class="fw-bold">เกรด</div></th>
                        </tr>
                        <tr>
                            <th width="8%" class="bg-light" style="font-weight: 500;">หน่วยที่ 1 (25)</th>
                            <th width="8%" class="bg-light" style="font-weight: 500;">หน่วยที่ 2 (25)</th>
                            <th width="9%" class="bg-light" style="font-weight: 500;">กลางภาค (20)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Student 1 -->
                        <tr class="student-row align-middle">
                            <td class="text-center text-muted static-cell">1</td>
                            <td class="text-center text-muted static-cell">64001</td>
                            <td class="ps-3 fw-medium text-dark static-cell">
                                ด.ช. สมชาย ใจดี
                            </td>
                            <input type="hidden" name="enrollment_id[]" value="101">
                            <td class="input-cell"><input type="number" class="score-input" name="c1[]" min="0" max="25" value="" tabindex="1"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="c2[]" min="0" max="25" value="" tabindex="2"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="midterm[]" min="0" max="20" value="" tabindex="3"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="final[]" min="0" max="30" value="" tabindex="4"></td>
                            <td class="text-center total-col total-score static-cell">0</td>
                            <td class="text-center grade-col final-grade static-cell">-</td>
                        </tr>
                        
                        <!-- Student 2 -->
                        <tr class="student-row align-middle">
                            <td class="text-center text-muted static-cell">2</td>
                            <td class="text-center text-muted static-cell">64002</td>
                            <td class="ps-3 fw-medium text-dark static-cell">
                                ด.ญ. สมศรี รักเรียน
                            </td>
                            <input type="hidden" name="enrollment_id[]" value="102">
                            <td class="input-cell"><input type="number" class="score-input" name="c1[]" min="0" max="25" value="20" tabindex="5"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="c2[]" min="0" max="25" value="22" tabindex="6"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="midterm[]" min="0" max="20" value="18" tabindex="7"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="final[]" min="0" max="30" value="28" tabindex="8"></td>
                            <td class="text-center total-col total-score static-cell">0</td>
                            <td class="text-center grade-col final-grade static-cell">-</td>
                        </tr>

                        <!-- Student 3 -->
                        <tr class="student-row align-middle">
                            <td class="text-center text-muted static-cell">3</td>
                            <td class="text-center text-muted static-cell">64003</td>
                            <td class="ps-3 fw-medium text-dark static-cell">
                                ด.ช. มุ่งมั่น ขยันยิ่ง
                            </td>
                            <input type="hidden" name="enrollment_id[]" value="103">
                            <td class="input-cell"><input type="number" class="score-input" name="c1[]" min="0" max="25" value="12" tabindex="9"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="c2[]" min="0" max="25" value="14" tabindex="10"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="midterm[]" min="0" max="20" value="9" tabindex="11"></td>
                            <td class="input-cell"><input type="number" class="score-input" name="final[]" min="0" max="30" value="18" tabindex="12"></td>
                            <td class="text-center total-col total-score static-cell">0</td>
                            <td class="text-center grade-col final-grade static-cell">-</td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        
        <!-- Footer in Card -->
        <div class="d-flex justify-content-between align-items-center p-3" style="background: #f9fafb; border-top: 1px solid #e5e7eb;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-muted"></i>
                <small class="text-muted fw-medium border-end pe-2">ใช้ลูกศรเลื่อนช่องได้</small>
                <small class="text-muted fw-medium">สามารถลากเมาส์คลุมเพื่อ Copy / Paste ได้</small>
            </div>
            <button type="button" class="btn btn-save-custom d-flex align-items-center gap-2" onclick="document.getElementById('gradingForm').submit();">
                <i class="bi bi-cloud-arrow-up-fill"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/grading.js"></script>
</body>
</html>
