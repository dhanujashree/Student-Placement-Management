<?php
session_start();
require_once __DIR__ . '/db.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    json_response(['success' => false, 'message' => 'Student login required.'], 401);
}

$studentId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$data = request_data();

function current_student(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare('SELECT student_id, register_no, name, email, phone, dob, gender, department, year, cgpa, tenth_percentage, twelfth_percentage, skills, certifications, resume, created_at FROM students WHERE student_id=?');
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) {
        json_response(['success' => false, 'message' => 'Student profile not found.'], 404);
    }
    return $student;
}

function drive_eligibility(array $student, array $drive): array {
    $reasons = [];
    $cgpa = (float)($student['cgpa'] ?? 0);
    $tenth = (float)($student['tenth_percentage'] ?? 0);
    $twelfth = (float)($student['twelfth_percentage'] ?? 0);
    
    if ($cgpa < (float)$drive['min_cgpa']) {
        $reasons[] = 'CGPA is below the minimum requirement.';
    }
    if ($tenth < (float)$drive['min_tenth']) {
        $reasons[] = '10th percentage is below the minimum requirement.';
    }
    if ($twelfth < (float)$drive['min_twelfth']) {
        $reasons[] = '12th percentage is below the minimum requirement.';
    }
    
    $departments = array_filter(array_map('trim', explode(',', (string)$drive['eligible_departments'])));
    if ($departments && !in_array(strtoupper((string)$student['department']), array_map('strtoupper', $departments), true) && !in_array('ALL', array_map('strtoupper', $departments), true)) {
        $reasons[] = 'Department is not eligible.';
    }
    
    if ((int)$drive['eligible_year'] > 0 && (int)$student['year'] !== (int)$drive['eligible_year']) {
        $reasons[] = 'Year does not match the eligible year.';
    }
    
    return ['eligible' => count($reasons) === 0, 'reasons' => $reasons];
}

if ($action === 'get_profile') {
    json_response(['success' => true, 'profile' => current_student($pdo, $studentId)]);
}

if ($action === 'update_profile') {
    $allowed = ['phone', 'dob', 'gender', 'department', 'year', 'cgpa', 'tenth_percentage', 'twelfth_percentage', 'skills', 'certifications'];
    $values = [];
    
    foreach ($allowed as $key) {
        $values[$key] = clean_string($data[$key] ?? '');
    }
    
    $values['year'] = (int)$values['year'];
    foreach (['cgpa', 'tenth_percentage', 'twelfth_percentage'] as $key) {
        $values[$key] = $values[$key] === '' ? null : (float)$values[$key];
    }
    
    if ($values['year'] < 1 || $values['year'] > 5) {
        json_response(['success' => false, 'message' => 'Year must be between 1 and 5.'], 422);
    }
    if ($values['cgpa'] !== null && ($values['cgpa'] < 0 || $values['cgpa'] > 10)) {
        json_response(['success' => false, 'message' => 'CGPA must be between 0 and 10.'], 422);
    }
    foreach (['tenth_percentage', 'twelfth_percentage'] as $key) {
        if ($values[$key] !== null && ($values[$key] < 0 || $values[$key] > 100)) {
            json_response(['success' => false, 'message' => 'Percentage values must be between 0 and 100.'], 422);
        }
    }
    
    $stmt = $pdo->prepare('UPDATE students SET phone=?, dob=?, gender=?, department=?, year=?, cgpa=?, tenth_percentage=?, twelfth_percentage=?, skills=?, certifications=? WHERE student_id=?');
    $stmt->execute([
        $values['phone'], 
        $values['dob'] ?: null, 
        $values['gender'], 
        $values['department'], 
        $values['year'], 
        $values['cgpa'], 
        $values['tenth_percentage'], 
        $values['twelfth_percentage'], 
        $values['skills'], 
        $values['certifications'], 
        $studentId
    ]);
    
    json_response([
        'success' => true, 
        'message' => 'Profile updated successfully.', 
        'profile' => current_student($pdo, $studentId)
    ]);
}

if ($action === 'upload_resume') {
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Please select a PDF resume.'], 422);
    }
    
    $file = $_FILES['resume'];
    if ($file['size'] > 2 * 1024 * 1024) {
        json_response(['success' => false, 'message' => 'Resume must be 2 MB or smaller.'], 422);
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        json_response(['success' => false, 'message' => 'Only PDF resumes are allowed.'], 422);
    }
    
    $dir = dirname(__DIR__) . '/uploads/resumes/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $filename = 'resume_' . $studentId . '_' . bin2hex(random_bytes(8)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        json_response(['success' => false, 'message' => 'Unable to save the resume.'], 500);
    }
    
    $old = current_student($pdo, $studentId)['resume'];
    if ($old && preg_match('/^[A-Za-z0-9_.-]+\.pdf$/i', $old) && is_file($dir . $old)) {
        @unlink($dir . $old);
    }
    
    $stmt = $pdo->prepare('UPDATE students SET resume=? WHERE student_id=?');
    $stmt->execute([$filename, $studentId]);
    
    json_response(['success' => true, 'message' => 'Resume uploaded successfully.', 'resume' => $filename]);
}

if ($action === 'get_drives') {
    $stmt = $pdo->query('SELECT d.*, c.company_name, c.industry, c.website FROM placement_drives d JOIN companies c ON c.company_id=d.company_id ORDER BY d.drive_date ASC');
    $student = current_student($pdo, $studentId);
    $drives = [];
    
    foreach ($stmt as $drive) {
        $elig = drive_eligibility($student, $drive);
        $check = $pdo->prepare('SELECT application_id, status, applied_date FROM applications WHERE student_id=? AND drive_id=? LIMIT 1');
        $check->execute([$studentId, $drive['drive_id']]);
        $app = $check->fetch();
        
        $drive['eligible'] = $elig['eligible'];
        $drive['eligibility_reasons'] = $elig['reasons'];
        $drive['application'] = $app ?: null;
        $drives[] = $drive;
    }
    
    json_response(['success' => true, 'drives' => $drives]);
}

if ($action === 'check_eligibility') {
    $driveId = (int)($data['drive_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT d.*, c.company_name FROM placement_drives d JOIN companies c ON c.company_id=d.company_id WHERE d.drive_id=?');
    $stmt->execute([$driveId]);
    $drive = $stmt->fetch();
    
    if (!$drive) {
        json_response(['success' => false, 'message' => 'Drive not found.'], 404);
    }
    
    $studentData = current_student($pdo, $studentId);
    $result = drive_eligibility($studentData, $drive);
    
    json_response([
        'success' => true, 
        'eligible' => $result['eligible'], 
        'reasons' => $result['reasons'], 
        'checks' => [
            ['label' => 'CGPA', 'eligible' => (float)($studentData['cgpa'] ?? 0) >= (float)$drive['min_cgpa']],
            ['label' => '10th Percentage', 'eligible' => (float)($studentData['tenth_percentage'] ?? 0) >= (float)$drive['min_tenth']],
            ['label' => '12th Percentage', 'eligible' => (float)($studentData['twelfth_percentage'] ?? 0) >= (float)$drive['min_twelfth']],
            ['label' => 'Department', 'eligible' => !in_array('Department is not eligible.', $result['reasons'], true)],
            ['label' => 'Year', 'eligible' => !in_array('Year does not match the eligible year.', $result['reasons'], true)]
        ]
    ]);
}

if ($action === 'apply') {
    $driveId = (int)($data['drive_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT d.*, c.company_name FROM placement_drives d JOIN companies c ON c.company_id=d.company_id WHERE d.drive_id=?');
    $stmt->execute([$driveId]);
    $drive = $stmt->fetch();
    
    if (!$drive) {
        json_response(['success' => false, 'message' => 'Drive not found.'], 404);
    }
    if (strtotime($drive['deadline']) < strtotime(date('Y-m-d H:i:s'))) {
        json_response(['success' => false, 'message' => 'Application deadline has passed.'], 422);
    }
    
    $elig = drive_eligibility(current_student($pdo, $studentId), $drive);
    if (!$elig['eligible']) {
        json_response(['success' => false, 'message' => 'You are not eligible to apply.', 'reasons' => $elig['reasons']], 422);
    }
    
    $stmt = $pdo->prepare('SELECT application_id FROM applications WHERE student_id=? AND drive_id=?');
    $stmt->execute([$studentId, $driveId]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'message' => 'You have already applied for this drive.'], 409);
    }
    
    $stmt = $pdo->prepare("INSERT INTO applications (student_id, drive_id, applied_date, status) VALUES (?, ?, NOW(), 'Applied')");
    $stmt->execute([$studentId, $driveId]);
    
    json_response(['success' => true, 'message' => 'Application submitted successfully.']);
}

if ($action === 'get_applications') {
    $stmt = $pdo->prepare("SELECT a.*, d.job_role, d.package, d.location, c.company_name, r.result_status, r.remarks FROM applications a JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id LEFT JOIN results r ON r.application_id=a.application_id WHERE a.student_id=? ORDER BY a.applied_date DESC");
    $stmt->execute([$studentId]);
    json_response(['success' => true, 'applications' => $stmt->fetchAll()]);
}

if ($action === 'get_interviews') {
    $stmt = $pdo->prepare("SELECT i.*, d.job_role, c.company_name FROM interviews i JOIN applications a ON a.application_id=i.application_id JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id WHERE a.student_id=? ORDER BY i.interview_date ASC, i.interview_time ASC");
    $stmt->execute([$studentId]);
    json_response(['success' => true, 'interviews' => $stmt->fetchAll()]);
}

if ($action === 'get_results') {
    $stmt = $pdo->prepare("SELECT r.*, d.job_role, d.package, c.company_name FROM results r JOIN applications a ON a.application_id=r.application_id JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id WHERE a.student_id=? ORDER BY r.result_id DESC");
    $stmt->execute([$studentId]);
    json_response(['success' => true, 'results' => $stmt->fetchAll()]);
}

if ($action === 'get_notifications') {
    $stmt = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 30');
    json_response(['success' => true, 'notifications' => $stmt->fetchAll()]);
}

if ($action === 'dashboard') {
    $counts = [];
    $queries = [
        'drives' => 'SELECT COUNT(*) FROM placement_drives WHERE deadline >= CURDATE()',
        'applications' => 'SELECT COUNT(*) FROM applications WHERE student_id=?',
        'shortlisted' => "SELECT COUNT(*) FROM applications WHERE student_id=? AND status IN ('Shortlisted', 'Aptitude Cleared', 'Technical Cleared', 'HR Cleared')",
        'interviews' => 'SELECT COUNT(*) FROM interviews i JOIN applications a ON a.application_id=i.application_id WHERE a.student_id=? AND i.interview_date >= CURDATE()',
        'selected' => "SELECT COUNT(*) FROM applications WHERE student_id=? AND status='Selected'"
    ];
    
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($key === 'drives' ? [] : [$studentId]);
        $counts[$key] = (int)$stmt->fetchColumn();
    }
    
    json_response(['success' => true, 'counts' => $counts]);
}

json_response(['success' => false, 'message' => 'Invalid student action.'], 400);