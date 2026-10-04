<?php
session_start();
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';
$data = request_data();

if ($action === 'register') {
    $registerNo = clean_string($data['register_no'] ?? '');
    $name = clean_string($data['name'] ?? '');
    $email = strtolower(clean_string($data['email'] ?? ''));
    $phone = clean_string($data['phone'] ?? '');
    $password = (string)($data['password'] ?? '');
    $department = clean_string($data['department'] ?? '');
    $year = (int)($data['year'] ?? 0);

    if (!$registerNo || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password || !$department || $year < 1 || $year > 5) {
        json_response(['success'=>false,'message'=>'Please provide valid registration details.'], 422);
    }
    if (strlen($password) < 8) {
        json_response(['success'=>false,'message'=>'Password must contain at least 8 characters.'], 422);
    }

    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE register_no = ? OR email = ? LIMIT 1');
    $stmt->execute([$registerNo, $email]);
    if ($stmt->fetch()) {
        json_response(['success'=>false,'message'=>'Register number or email already exists.'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO students (register_no,name,email,phone,password,department,year) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$registerNo,$name,$email,$phone,$hash,$department,$year]);
    json_response(['success'=>true,'message'=>'Registration successful. You can now log in.']);
}

if ($action === 'login') {
    $loginId = clean_string($data['login_id'] ?? '');
    $password = (string)($data['password'] ?? '');
    $role = clean_string($data['role'] ?? 'student');

    if (!$loginId || !$password || !in_array($role, ['student','admin'], true)) {
        json_response(['success'=>false,'message'=>'Login ID, password and role are required.'], 422);
    }

    if ($role === 'admin') {
        $stmt = $pdo->prepare('SELECT admin_id,name,email,password FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower($loginId)]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = (int)$user['admin_id'];
            $_SESSION['name'] = $user['name'];
            json_response(['success'=>true,'role'=>'admin','name'=>$user['name'],'redirect'=>'admin-dashboard.html']);
        }
    } else {
        $stmt = $pdo->prepare('SELECT student_id,register_no,name,email,password FROM students WHERE register_no = ? OR email = ? LIMIT 1');
        $stmt->execute([$loginId, strtolower($loginId)]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'student';
            $_SESSION['user_id'] = (int)$user['student_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['register_no'] = $user['register_no'];
            json_response(['success'=>true,'role'=>'student','name'=>$user['name'],'redirect'=>'student-dashboard.html']);
        }
    }
    json_response(['success'=>false,'message'=>'Invalid login credentials.'], 401);
}

if ($action === 'session') {
    json_response(['success'=>true,'logged_in'=>isset($_SESSION['user_id']),'role'=>$_SESSION['role'] ?? null,'name'=>$_SESSION['name'] ?? null]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json_response(['success'=>true,'message'=>'Logged out successfully.']);
}

json_response(['success'=>false,'message'=>'Invalid authentication action.'], 400);
