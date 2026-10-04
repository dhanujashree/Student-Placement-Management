<?php
session_start();
require_once __DIR__ . '/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    json_response(['success'=>false,'message'=>'Admin login required.'],401);
}

$action = $_GET['action'] ?? '';
$data = request_data();

function require_int($value): int { return (int)($value ?? 0); }

if ($action === 'stats') {
    $queries = [
        'students'=>'SELECT COUNT(*) FROM students',
        'companies'=>'SELECT COUNT(*) FROM companies',
        'drives'=>'SELECT COUNT(*) FROM placement_drives',
        'applications'=>'SELECT COUNT(*) FROM applications',
        'selected'=>"SELECT COUNT(*) FROM applications WHERE status='Selected'",
        'rejected'=>"SELECT COUNT(*) FROM applications WHERE status='Rejected'"
    ];
    $stats=[]; foreach($queries as $k=>$q) $stats[$k]=(int)$pdo->query($q)->fetchColumn();
    $dept = $pdo->query("SELECT department, COUNT(*) total, SUM(CASE WHEN a.status='Selected' THEN 1 ELSE 0 END) selected FROM students s LEFT JOIN applications a ON a.student_id=s.student_id GROUP BY department ORDER BY department")->fetchAll();
    $company = $pdo->query("SELECT c.company_name, SUM(CASE WHEN a.status='Selected' THEN 1 ELSE 0 END) selected FROM companies c LEFT JOIN placement_drives d ON d.company_id=c.company_id LEFT JOIN applications a ON a.drive_id=d.drive_id GROUP BY c.company_id ORDER BY selected DESC,c.company_name")->fetchAll();
    json_response(['success'=>true,'stats'=>$stats,'department_stats'=>$dept,'company_stats'=>$company]);
}

if ($action === 'students') {
    $stmt=$pdo->query('SELECT student_id,register_no,name,email,phone,dob,gender,department,year,cgpa,tenth_percentage,twelfth_percentage,skills,certifications,resume,created_at FROM students ORDER BY created_at DESC');
    json_response(['success'=>true,'students'=>$stmt->fetchAll()]);
}

if ($action === 'student_get') {
    $id=require_int($data['student_id']??$_GET['student_id']??0); $stmt=$pdo->prepare('SELECT student_id,register_no,name,email,phone,dob,gender,department,year,cgpa,tenth_percentage,twelfth_percentage,skills,certifications,resume,created_at FROM students WHERE student_id=?'); $stmt->execute([$id]); $row=$stmt->fetch(); if(!$row) json_response(['success'=>false,'message'=>'Student not found.'],404); json_response(['success'=>true,'student'=>$row]);
}

if ($action === 'student_update') {
    $id=require_int($data['student_id']??0); $stmt=$pdo->prepare('UPDATE students SET name=?,email=?,phone=?,department=?,year=?,cgpa=?,tenth_percentage=?,twelfth_percentage=?,skills=?,certifications=? WHERE student_id=?');
    $stmt->execute([clean_string($data['name']??''),strtolower(clean_string($data['email']??'')),clean_string($data['phone']??''),clean_string($data['department']??''),require_int($data['year']??0),($data['cgpa']??'')===''?null:(float)$data['cgpa'],($data['tenth_percentage']??'')===''?null:(float)$data['tenth_percentage'],($data['twelfth_percentage']??'')===''?null:(float)$data['twelfth_percentage'],clean_string($data['skills']??''),clean_string($data['certifications']??''),$id]);
    json_response(['success'=>true,'message'=>'Student updated successfully.']);
}

if ($action === 'student_delete') {
    $id=require_int($data['student_id']??0); $stmt=$pdo->prepare('DELETE FROM students WHERE student_id=?'); $stmt->execute([$id]); json_response(['success'=>true,'message'=>'Student deleted.']);
}

if ($action === 'companies') { $stmt=$pdo->query('SELECT * FROM companies ORDER BY company_name'); json_response(['success'=>true,'companies'=>$stmt->fetchAll()]); }

if ($action === 'company_save') {
    $id=require_int($data['company_id']??0); $fields=[clean_string($data['company_name']??''),clean_string($data['industry']??''),clean_string($data['location']??''),clean_string($data['website']??''),clean_string($data['contact_person']??''),strtolower(clean_string($data['email']??'')),clean_string($data['description']??'')];
    if(!$fields[0]) json_response(['success'=>false,'message'=>'Company name is required.'],422);
    if($id){$stmt=$pdo->prepare('UPDATE companies SET company_name=?,industry=?,location=?,website=?,contact_person=?,email=?,description=? WHERE company_id=?');$stmt->execute([...$fields,$id]);$msg='Company updated successfully.';} else {$stmt=$pdo->prepare('INSERT INTO companies (company_name,industry,location,website,contact_person,email,description) VALUES (?,?,?,?,?,?,?)');$stmt->execute($fields);$msg='Company added successfully.';} json_response(['success'=>true,'message'=>$msg]);
}
if ($action === 'company_delete') { $id=require_int($data['company_id']??0); try{$stmt=$pdo->prepare('DELETE FROM companies WHERE company_id=?');$stmt->execute([$id]);json_response(['success'=>true,'message'=>'Company deleted.']);}catch(PDOException $e){json_response(['success'=>false,'message'=>'Company cannot be deleted while it has placement drives.'],409);} }

if ($action === 'drives') { $stmt=$pdo->query('SELECT d.*,c.company_name FROM placement_drives d JOIN companies c ON c.company_id=d.company_id ORDER BY d.drive_date DESC'); json_response(['success'=>true,'drives'=>$stmt->fetchAll()]); }
if ($action === 'drive_save') {
    $id=require_int($data['drive_id']??0); $fields=[require_int($data['company_id']??0),clean_string($data['job_role']??''),(float)($data['package']??0),clean_string($data['location']??''),clean_string($data['drive_date']??''),clean_string($data['deadline']??''),(float)($data['min_cgpa']??0),(float)($data['min_tenth']??0),(float)($data['min_twelfth']??0),clean_string($data['eligible_departments']??'ALL'),require_int($data['eligible_year']??0),clean_string($data['required_skills']??''),clean_string($data['job_description']??''),require_int($data['openings']??1),clean_string($data['selection_process']??'')];
    if(!$fields[0]||!$fields[1]||!$fields[4]||!$fields[5]) json_response(['success'=>false,'message'=>'Company, role, drive date and deadline are required.'],422);
    if($id){$stmt=$pdo->prepare('UPDATE placement_drives SET company_id=?,job_role=?,package=?,location=?,drive_date=?,deadline=?,min_cgpa=?,min_tenth=?,min_twelfth=?,eligible_departments=?,eligible_year=?,required_skills=?,job_description=?,openings=?,selection_process=? WHERE drive_id=?');$stmt->execute([...$fields,$id]);$msg='Drive updated successfully.';}else{$stmt=$pdo->prepare('INSERT INTO placement_drives (company_id,job_role,package,location,drive_date,deadline,min_cgpa,min_tenth,min_twelfth,eligible_departments,eligible_year,required_skills,job_description,openings,selection_process) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($fields);$msg='Drive added successfully.';}json_response(['success'=>true,'message'=>$msg]);
}
if ($action === 'drive_delete') { $id=require_int($data['drive_id']??0); try{$stmt=$pdo->prepare('DELETE FROM placement_drives WHERE drive_id=?');$stmt->execute([$id]);json_response(['success'=>true,'message'=>'Drive deleted.']);}catch(PDOException $e){json_response(['success'=>false,'message'=>'Drive cannot be deleted while applications exist.'],409);} }

if ($action === 'applications') { $stmt=$pdo->query("SELECT a.*,s.register_no,s.name student_name,s.email student_email,s.resume,d.job_role,d.package,c.company_name,r.result_status FROM applications a JOIN students s ON s.student_id=a.student_id JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id LEFT JOIN results r ON r.application_id=a.application_id ORDER BY a.applied_date DESC"); json_response(['success'=>true,'applications'=>$stmt->fetchAll()]); }
if ($action === 'application_status') { $id=require_int($data['application_id']??0); $status=clean_string($data['status']??'Applied'); $allowed=['Applied','Shortlisted','Aptitude Cleared','Technical Cleared','HR Cleared','Selected','Rejected']; if(!in_array($status,$allowed,true))json_response(['success'=>false,'message'=>'Invalid status.'],422); $stmt=$pdo->prepare('UPDATE applications SET status=? WHERE application_id=?');$stmt->execute([$status,$id]); json_response(['success'=>true,'message'=>'Application status updated.']); }

if ($action === 'interviews') { $stmt=$pdo->query("SELECT i.*,a.application_id,s.name student_name,s.register_no,d.job_role,c.company_name FROM interviews i JOIN applications a ON a.application_id=i.application_id JOIN students s ON s.student_id=a.student_id JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id ORDER BY i.interview_date ASC,i.interview_time ASC"); json_response(['success'=>true,'interviews'=>$stmt->fetchAll()]); }
if ($action === 'interview_save') { $id=require_int($data['interview_id']??0); $fields=[require_int($data['application_id']??0),clean_string($data['round_name']??''),clean_string($data['interview_date']??''),clean_string($data['interview_time']??''),clean_string($data['venue']??''),clean_string($data['mode']??'Offline'),clean_string($data['meeting_link']??'')]; if(!$fields[0]||!$fields[1]||!$fields[2]||!$fields[3])json_response(['success'=>false,'message'=>'Application, round, date and time are required.'],422); if($id){$stmt=$pdo->prepare('UPDATE interviews SET application_id=?,round_name=?,interview_date=?,interview_time=?,venue=?,mode=?,meeting_link=? WHERE interview_id=?');$stmt->execute([...$fields,$id]);$msg='Interview updated successfully.';}else{$stmt=$pdo->prepare('INSERT INTO interviews (application_id,round_name,interview_date,interview_time,venue,mode,meeting_link) VALUES (?,?,?,?,?,?,?)');$stmt->execute($fields);$msg='Interview scheduled successfully.';}json_response(['success'=>true,'message'=>$msg]); }
if ($action === 'interview_delete') { $id=require_int($data['interview_id']??0);$stmt=$pdo->prepare('DELETE FROM interviews WHERE interview_id=?');$stmt->execute([$id]);json_response(['success'=>true,'message'=>'Interview deleted.']); }

if ($action === 'results') { $stmt=$pdo->query("SELECT r.*,a.application_id,s.name student_name,s.register_no,d.job_role,d.package,c.company_name FROM results r JOIN applications a ON a.application_id=r.application_id JOIN students s ON s.student_id=a.student_id JOIN placement_drives d ON d.drive_id=a.drive_id JOIN companies c ON c.company_id=d.company_id ORDER BY r.result_id DESC"); json_response(['success'=>true,'results'=>$stmt->fetchAll()]); }
if ($action === 'result_save') { $id=require_int($data['result_id']??0);$app=require_int($data['application_id']??0);$status=clean_string($data['result_status']??'Pending');$remarks=clean_string($data['remarks']??'');$allowed=['Selected','Rejected','Pending'];if(!$app||!in_array($status,$allowed,true))json_response(['success'=>false,'message'=>'Valid application and result status are required.'],422);$pdo->beginTransaction();try{if($id){$stmt=$pdo->prepare('UPDATE results SET application_id=?,result_status=?,remarks=? WHERE result_id=?');$stmt->execute([$app,$status,$remarks,$id]);}else{$stmt=$pdo->prepare('INSERT INTO results (application_id,result_status,remarks) VALUES (?,?,?) ON DUPLICATE KEY UPDATE result_status=VALUES(result_status),remarks=VALUES(remarks)');$stmt->execute([$app,$status,$remarks]);}$appStatus=$status==='Selected'?'Selected':($status==='Rejected'?'Rejected':'Applied');$stmt=$pdo->prepare('UPDATE applications SET status=? WHERE application_id=?');$stmt->execute([$appStatus,$app]);$pdo->commit();json_response(['success'=>true,'message'=>'Result updated successfully.']);}catch(Throwable $e){$pdo->rollBack();json_response(['success'=>false,'message'=>'Unable to update result.'],500);} }

if ($action === 'notifications') { $stmt=$pdo->query('SELECT * FROM notifications ORDER BY created_at DESC');json_response(['success'=>true,'notifications'=>$stmt->fetchAll()]); }
if ($action === 'notification_save') { $title=clean_string($data['title']??'');$message=clean_string($data['message']??'');if(!$title||!$message)json_response(['success'=>false,'message'=>'Title and message are required.'],422);$stmt=$pdo->prepare('INSERT INTO notifications (title,message) VALUES (?,?)');$stmt->execute([$title,$message]);json_response(['success'=>true,'message'=>'Notification published.']); }
if ($action === 'notification_delete') { $id=require_int($data['notification_id']??0);$stmt=$pdo->prepare('DELETE FROM notifications WHERE notification_id=?');$stmt->execute([$id]);json_response(['success'=>true,'message'=>'Notification deleted.']); }

if ($action === 'report') {
    $stats=['students'=>(int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),'companies'=>(int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),'drives'=>(int)$pdo->query('SELECT COUNT(*) FROM placement_drives')->fetchColumn(),'applications'=>(int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),'selected'=>(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Selected'")->fetchColumn()];
    $dept=$pdo->query("SELECT department, COUNT(DISTINCT student_id) students, COUNT(DISTINCT CASE WHEN status='Selected' THEN student_id END) selected FROM (SELECT s.department,s.student_id,a.status FROM students s LEFT JOIN applications a ON a.student_id=s.student_id) x GROUP BY department ORDER BY department")->fetchAll();
    $company=$pdo->query("SELECT c.company_name, COUNT(DISTINCT CASE WHEN a.status='Selected' THEN a.student_id END) selected FROM companies c LEFT JOIN placement_drives d ON d.company_id=c.company_id LEFT JOIN applications a ON a.drive_id=d.drive_id GROUP BY c.company_id ORDER BY c.company_name")->fetchAll();
    $status=$pdo->query('SELECT status,COUNT(*) total FROM applications GROUP BY status ORDER BY total DESC')->fetchAll();
    json_response(['success'=>true,'stats'=>$stats,'department'=>$dept,'company'=>$company,'status'=>$status]);
}

json_response(['success'=>false,'message'=>'Invalid admin action.'],400);
