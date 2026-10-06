<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
if(!is_admin($user)){http_response_code(403);exit('Доступ только для главного администратора.');}

$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        check_csrf();
        $action=(string)($_POST['action']??'');
        if($action==='update_role'){
            $uid=(int)($_POST['user_id']??0);
            $role=(string)($_POST['role']??'user');
            if($uid<=0 || !in_array($role,['user','admin'],true)) throw new RuntimeException('Некорректные данные.');
            if($uid===(int)$user['id'] && $role!=='admin') throw new RuntimeException('Нельзя снять права главного с самого себя.');
            $q=$pdo->prepare('UPDATE users SET role=? WHERE id=?');
            $q->execute([$role,$uid]);
            $notice='Права пользователя обновлены.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$users=$pdo->query('SELECT id,name,email,username,role,subscriptionPlan,subscriptionStatus,createdAt,lastSignedIn FROM users ORDER BY role DESC,createdAt ASC,id ASC')->fetchAll();
$pageTitle='Сотрудники и доступ';require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap">
  <div class="page-heading">
    <div><div class="eyebrow">УПРАВЛЕНИЕ</div><h1>Сотрудники и доступ</h1><p class="lede">Назначайте сотрудников на проекты и управляйте правами пользователей.</p></div>
    <span class="status status-active"><i></i> Главный администратор</span>
  </div>
  <?php if($error): ?><div class="alert alert-danger mt-4"><?=e($error)?></div><?php endif; ?>
  <?php if($notice): ?><div class="alert alert-success mt-4"><?=e($notice)?></div><?php endif; ?>

  <div class="module-panel mt-4">
    <div class="panel-heading"><div><h2>Пользователи</h2><p>Администратор работает без подписки. Для остальных действуют обычные условия тарифов.</p></div></div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Пользователь</th><th>Роль</th><th>Тариф</th><th>Последний вход</th><th></th></tr></thead>
        <tbody>
        <?php foreach($users as $u): ?>
          <tr>
            <td><strong><?=e($u['name'] ?: ($u['username'] ? '@'.$u['username'] : ($u['email'] ?: 'Пользователь #'.$u['id'])))?></strong><div class="small text-muted"><?=e($u['email'] ?: ($u['username'] ? '@'.$u['username'] : ''))?></div></td>
            <td><span class="badge rounded-pill <?=($u['role']==='admin'?'text-bg-dark':'text-bg-light')?>"><?= $u['role']==='admin'?'Главный':'Сотрудник' ?></span></td>
            <td><?=e($u['subscriptionPlan'] ?: 'free')?></td>
            <td><?=e($u['lastSignedIn'] ? date('d.m.Y H:i',strtotime($u['lastSignedIn'])) : '—')?></td>
            <td class="text-end">
              <?php if((int)$u['id']!==(int)$user['id']): ?>
              <form method="post" class="d-inline-flex gap-2 align-items-center">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                <select name="role" class="form-select form-select-sm">
                  <option value="user" <?=$u['role']==='user'?'selected':''?>>Сотрудник</option>
                  <option value="admin" <?=$u['role']==='admin'?'selected':''?>>Главный</option>
                </select>
                <button class="outline-button" type="submit">Сохранить</button>
              </form>
              <?php else: ?><span class="text-muted small">Это ваш аккаунт</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bottom-callout mt-4"><i class="bi bi-people"></i><div><strong>Назначение на проект</strong><p>Откройте проект → Команда → Назначить сотрудника. Там можно выбрать пользователя и дать ему роль: прораб, бригада, дизайнер или заказчик.</p></div></div>
</section>
<?php require __DIR__.'/includes/app_footer.php'; ?>