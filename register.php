<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
if(current_user())redirect('dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$name=trim($_POST['name']??'');$email=strtolower(trim($_POST['email']??''));$password=$_POST['password']??'';
if(mb_strlen($name)<2)$error='Введите имя.';
elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Введите корректный email.';
elseif(strlen($password)<8)$error='Пароль должен содержать минимум 8 символов.';
else{try{$s=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();session_regenerate_id(true);$_SESSION['user']=['id'=>$id,'name'=>$name,'email'=>$email];redirect('dashboard.php');}catch(PDOException $e){$error=$e->getCode()==='23000'?'Этот email уже зарегистрирован.':'Не удалось создать аккаунт.';}}}
$pageTitle='Регистрация';require __DIR__.'/includes/header.php';?>
<div class="auth-wrap"><div class="auth-card surface"><div class="eyebrow">СМЕТОГРАМ</div><h1>Создать аккаунт</h1><p class="text-muted">Начните создавать сметы в браузере.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="mb-3"><label class="form-label">Имя</label><input class="form-control form-control-lg" name="name" required value="<?=e($_POST['name']??'')?>"></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control form-control-lg" type="email" name="email" required value="<?=e($_POST['email']??'')?>"></div><div class="mb-4"><label class="form-label">Пароль</label><input class="form-control form-control-lg" type="password" name="password" required minlength="8"></div><button class="btn btn-primary btn-lg w-100">Создать аккаунт</button></form><p class="auth-foot">Уже есть аккаунт? <a href="login.php">Войти</a></p></div></div>
<?php require __DIR__.'/includes/footer.php';?>