<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<dl class="pp5-gradebook-metadata">
  <div><dt>ปีการศึกษา</dt><dd><?= $escape($offering['year_be']) ?> <?= App\Support\View::render('ui/status', ['status'=>$offering['academic_year_status'], 'kind'=>'academic-year']) ?></dd></div>
  <div><dt>ห้องเรียน</dt><dd><?= $escape($offering['classroom_code'].' — '.$offering['classroom_name']) ?></dd></div>
  <div><dt>รายวิชา</dt><dd><?= $escape($offering['subject_code'].' — '.$offering['subject_name']) ?></dd></div>
  <div><dt>ภาคเรียน</dt><dd><?= $escape($offering['term_no']) ?></dd></div>
  <div><dt>สถานะการเปิดรายวิชา</dt><dd><?= App\Support\View::render('ui/status', ['status'=>$offering['status']]) ?></dd></div>
</dl>
