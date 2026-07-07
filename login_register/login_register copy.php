<?php
session_start();
require_once 'config.php';

function back($msg){
    $_SESSION['login_error']=$msg;
    header("Location: index.php");
    exit();
}

function getIP(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* =========================
   REGISTRO ADMIN PRINCIPAL
========================= */
if(isset($_POST['register_admin'])){

    $name=trim($_POST['name']);
    $email=trim($_POST['email']);
    $password=password_hash($_POST['password'],PASSWORD_DEFAULT);
    $admin_key=password_hash($_POST['admin_key'],PASSWORD_DEFAULT);

    $q=$conn->query("SELECT id FROM admins WHERE admin_level='super' LIMIT 1");
    if($q && $q->num_rows>0){
        back('El administrador principal ya existe');
    }

    $stmt=$conn->prepare("INSERT INTO admins
    (name,email,password,role,status,created_by,admin_level,admin_key,payment_status,paid,admin_approved,created_by_admin,commission,is_online,foto,user_quota,max_perfiles,telefono,pais)
    VALUES(?,?,?,'admin','active','admin','super',?,'approved','yes','yes',0,0,0,'',9999,9999,'','Argentina')");

    $stmt->bind_param("ssss",$name,$email,$password,$admin_key);

    if($stmt->execute()){
        $_SESSION['admin_register_success']='Administrador principal creado';
        $_SESSION['show_admin_register']=false;
    }

    header("Location:index.php");
    exit();
}

/* =========================
   LOGIN ADMIN / CREATOR
========================= */
if(isset($_POST['admin_login'])){

    $email=trim($_POST['email']);
    $password=$_POST['password'];
    $adminKey=$_POST['admin_key'] ?? '';

    $stmt=$conn->prepare("SELECT * FROM admins WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $res=$stmt->get_result();

    if($res->num_rows!==1){
        back("Credenciales incorrectas");
    }

    $admin=$res->fetch_assoc();

    if(!password_verify($password,$admin['password'])){
        back("Credenciales incorrectas");
    }

    if($admin['status']!=='active'){
        back("Cuenta suspendida o pendiente");
    }

    if($admin['role']==='admin' && $admin['admin_level']==='super'){
        if(empty($adminKey)){
            back("Ingrese la clave administrativa");
        }

        if(!password_verify($adminKey,$admin['admin_key'])){
            back("Clave administrativa incorrecta");
        }
    }

    $conn->query("UPDATE admins SET is_online=1 WHERE id=".(int)$admin['id']);

    $_SESSION['id']=$admin['id'];
    $_SESSION['user_id']=$admin['id'];
    $_SESSION['name']=$admin['name'];
    $_SESSION['email']=$admin['email'];
    $_SESSION['role']=$admin['role'];
    $_SESSION['admin_level']=$admin['admin_level'];

    if($admin['role']==='creator'){
        header("Location: creator_page.php");
    } else {
        header("Location: Administrador.php");
    }
    exit();
}

/* =========================
   LOGIN USUARIO
========================= */
if(isset($_POST['login'])){

    $email=trim($_POST['email']);
    $password=$_POST['password'];

    $stmt=$conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $res=$stmt->get_result();

    if($res->num_rows!==1){
        back("Credenciales incorrectas");
    }

    $user=$res->fetch_assoc();

    if(!password_verify($password,$user['password'])){
        back("Credenciales incorrectas");
    }

    if($user['status']!=='active'){
        back("Cuenta pendiente o suspendida");
    }

    $upd=$conn->prepare("UPDATE users SET last_login=NOW(),is_online=1 WHERE id=?");
    $upd->bind_param("i",$user['id']);
    $upd->execute();

    $deviceToken=$_COOKIE['device_token'] ?? bin2hex(random_bytes(32));

    setcookie(
        "device_token",
        $deviceToken,
        time()+31536000,
        "/",
        "",
        false,
        true
    );

    $ua=$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $browser='Desconocido';
    if(strpos($ua,'Edg')!==false) $browser='Edge';
    elseif(strpos($ua,'Chrome')!==false) $browser='Chrome';
    elseif(strpos($ua,'Firefox')!==false) $browser='Firefox';
    elseif(strpos($ua,'Safari')!==false) $browser='Safari';

    $os='Desconocido';
    if(preg_match('/windows/i',$ua)) $os='Windows';
    elseif(preg_match('/android/i',$ua)) $os='Android';
    elseif(preg_match('/iphone/i',$ua)) $os='iPhone';
    elseif(preg_match('/ipad/i',$ua)) $os='iPad';
    elseif(preg_match('/mac/i',$ua)) $os='MacOS';
    elseif(preg_match('/linux/i',$ua)) $os='Linux';

    $deviceType=preg_match('/mobile/i',$ua)?'Mobile':'PC';
    $deviceBrand = 'Dispositivo';

if (preg_match('/SM-/i', $ua)) {
    $deviceBrand = 'Samsung';
}
elseif (preg_match('/Redmi/i', $ua)) {
    $deviceBrand = 'Xiaomi Redmi';
}
elseif (preg_match('/Mi /i', $ua)) {
    $deviceBrand = 'Xiaomi';
}
elseif (preg_match('/Motorola/i', $ua)) {
    $deviceBrand = 'Motorola';
}
elseif (preg_match('/iPhone/i', $ua)) {
    $deviceBrand = 'iPhone';
}
elseif (preg_match('/iPad/i', $ua)) {
    $deviceBrand = 'iPad';
}
elseif (preg_match('/Windows/i', $ua)) {
    $deviceBrand = 'PC Windows';
}
elseif (preg_match('/Macintosh/i', $ua)) {
    $deviceBrand = 'Mac';
}

$deviceName =
$deviceBrand .
' • ' .
$browser;

    $ip = getIP();

$country = 'Argentina';
$province = 'Desconocido';
$city = 'Desconocido';

$geo = @json_decode(
    file_get_contents("http://ip-api.com/json/$ip?fields=status,country,regionName,city"),
    true
);

if(
    $geo &&
    isset($geo['status']) &&
    $geo['status'] === 'success'
){

    $country  = $geo['country'] ?? 'Argentina';
    $province = $geo['regionName'] ?? 'Desconocido';
    $city     = $geo['city'] ?? 'Desconocido';

}

    $conn->query("UPDATE dispositivos SET is_active=0 WHERE last_ping < NOW() - INTERVAL 15 MINUTE");

    $blocked=$conn->prepare("SELECT id FROM dispositivos WHERE user_id=? AND blocked=1 AND token=? LIMIT 1");
    $blocked->bind_param("is",$user['id'],$deviceToken);
    $blocked->execute();

    if($blocked->get_result()->num_rows>0){
        back("Este dispositivo fue bloqueado");
    }

    $act=$conn->prepare("SELECT COUNT(*) total FROM dispositivos WHERE user_id=? AND is_active=1 AND blocked=0");
    $act->bind_param("i",$user['id']);
    $act->execute();
    $activos=(int)$act->get_result()->fetch_assoc()['total'];

    $max=max(1,(int)$user['max_perfiles']);

    $ex=$conn->prepare("SELECT id FROM dispositivos WHERE user_id=? AND token=? LIMIT 1");
    $ex->bind_param("is",$user['id'],$deviceToken);
    $ex->execute();

    if($ex->get_result()->num_rows===0 && $activos >= $max){
        back("Límite de dispositivos alcanzado ($max)");
    }

    $stmt=$conn->prepare("
    INSERT INTO dispositivos
    (user_id,email,token,device_name,device_type,browser,blocked,last_ping,is_active,ip_address,country,province,city,os,login_time)
VALUES(?,?,?,?,?,?,0,NOW(),1,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE
    device_name=VALUES(device_name),
    device_type=VALUES(device_type),
    browser=VALUES(browser),
    ip_address=VALUES(ip_address),
    country=VALUES(country),
province=VALUES(province),
city=VALUES(city),
os=VALUES(os),
    is_active=1,
    last_ping=NOW()
    ");

    $stmt->bind_param(
    "issssssssss",
    $user['id'],
    $user['email'],
    $deviceToken,
    $deviceName,
    $deviceType,
    $browser,
    $ip,
    $country,
    $province,
    $city,
    $os
);
    $stmt->execute();

    $_SESSION['id']=$user['id'];
    $_SESSION['user_id']=$user['id'];
    $_SESSION['name']=$user['name'];
    $_SESSION['email']=$user['email'];
    $_SESSION['role']='user';

    header("Location: perfiles.php");
    exit();
}

header("Location:index.php");
exit();
?>
