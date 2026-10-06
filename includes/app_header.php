<?php
declare(strict_types=1);
if (!isset($pageTitle)) $pageTitle = 'Сметограм';
$user = current_user();
$view = $_GET['view'] ?? 'projects';
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));\n$isProjectPage = $currentPage === 'project.php';
$projectCount = 0;
if ($user) {
    try {
        if (is_admin($user)) {
            $projectCount = (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        } else {
            $countQuery = $pdo->prepare('
                SELECT COUNT(DISTINCT p.id)
                FROM projects p
                LEFT JOIN projectmembers pm ON pm.projectId = p.id
                WHERE p.ownerId = ? OR pm.userId = ?
            ');
            $countQuery->execute([(int)$user['id'], (int)$user['id']]);
            $projectCount = (int)$countQuery->fetchColumn();
        }
    } catch (Throwable $e) {
        $projectCount = 0;
    }
}
$initials = 'АК';
if ($user && !empty($user['name'])) {
    $parts = preg_split('/\s+/u', trim((string)$user['name']));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? 'А',0,1).mb_substr($parts[1] ?? '',0,1));
}
?><!doctype html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($pageTitle)?> — Сметограм</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/app.css?v=20261006-layout3" rel="stylesheet">
</head><body>
<div class="app-shell">
<aside class="sidebar" id="appSidebar">
  <div class="brand"><span class="brand-mark">S</span><span>сметограм</span></div>
  <button class="mobile-close" onclick="document.getElementById('appSidebar').classList.remove('sidebar-open')"><i class="bi bi-x-lg"></i></button>
  <div class="workspace-label">РАБОЧЕЕ ПРОСТРАНСТВО</div>
  <nav class="nav-list">
    <a class="nav-item <?=($view==='projects'?'active':'')?>" href="dashboard.php"><span class="nav-icon"><i class="bi bi-grid-1x2"></i></span><span class="nav-label">Мои проекты</span><span class="nav-count"><?= (int)$projectCount ?></span></a>
    <?php if ($projectId): ?>
    <a class="nav-item <?=($currentPage==='catalog.php'?'active':'')?>" href="catalog.php?id=<?=$projectId?>"><span class="nav-icon"><i class="bi bi-journal-text"></i></span><span class="nav-label">Каталог</span></a>
    <?php endif; ?>
    <a class="nav-item <?=($view==='scan'?'active':'')?>" href="workspace.php?view=scan<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-file-earmark-arrow-up"></i></span><span class="nav-label">Смета из файла</span></a>
    <a class="nav-item <?=($view==='measurements'?'active':'')?>" href="workspace.php?view=measurements<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-rulers"></i></span><span class="nav-label">Замеры</span></a>
    <a class="nav-item <?=($view==='schedule'?'active':'')?>" href="workspace.php?view=schedule<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-calendar3"></i></span><span class="nav-label">График работ</span></a>
    <a class="nav-item <?=($view==='analytics'?'active':'')?>" href="workspace.php?view=analytics<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-bar-chart"></i></span><span class="nav-label">Графики</span></a>
    <a class="nav-item <?=($view==='team'?'active':'')?>" href="workspace.php?view=team<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-label">Команда</span></a>
    <?php if (is_admin($user)): ?>
    <a class="nav-item <?=($pageTitle==='Сотрудники и доступ'?'active':'')?>" href="admin.php"><span class="nav-icon"><i class="bi bi-person-gear"></i></span><span class="nav-label">Сотрудники</span><span class="nav-count">∞</span></a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-spacer"></div>
  <div class="trial-card"><div class="trial-icon"><i class="bi bi-stars"></i></div><div><strong>Первый проект бесплатно</strong><span>Без карты и обязательств</span></div><i class="bi bi-arrow-up-right"></i></div>
  <nav class="nav-list bottom-nav">
    <a class="nav-item <?=($view==='settings'?'active':'')?>" href="workspace.php?view=settings"><span class="nav-icon"><i class="bi bi-sliders2"></i></span><span class="nav-label">Настройки</span></a>
    <div class="notifications notification-wrap sidebar-notifications" id="notifications">
      <button class="nav-item notification-nav notification-toggle" type="button"><span class="nav-icon"><i class="bi bi-bell"></i></span><span class="nav-label">Уведомления</span><span class="notification-badge" id="notificationBadge" hidden>0</span></button>
      <div class="notifications-dropdown" id="notificationMenu"><div class="notifications-heading"><div><strong>Оповещения</strong><span>Изменения по вашим проектам</span></div><button class="notifications-read-all" type="button" id="readNotifications">Прочитать всё</button></div><div class="notifications-list" id="notificationList"><div class="notifications-empty"><strong>Нет новых оповещений</strong><span>Здесь появятся сообщения и изменения проекта.</span></div></div><div class="notifications-footer">Оповещения обновляются автоматически</div></div>
    </div>
  </nav>
  <div class="profile"><div class="avatar"><?=e($initials)?></div><div><strong><?=e($user['name']??'Пользователь')?></strong><span><?=e($user['email']??'')?></span></div><a href="logout.php" class="muted-icon" title="Выйти"><i class="bi bi-box-arrow-right"></i></a></div>
</aside>
<div class="sidebar-backdrop" onclick="document.getElementById('appSidebar').classList.remove('sidebar-open')"></div>
<nav class="mobile-bottom-nav" aria-label="Основная навигация">
  <a class="<?=($view==='projects' && !$isProjectPage?'active':'')?>" href="dashboard.php">
    <i class="bi bi-grid-1x2"></i><span>Проекты</span>
  </a>
  <a class="<?=($isProjectPage?'active':'')?>" href="<?= $projectId ? 'project.php?id='.$projectId : 'workspace.php?view=scan' ?>">
    <i class="bi bi-calculator"></i><span><?= $isProjectPage ? 'Смета' : 'Импорт' ?></span>
  </a>
  <a class="<?=($view==='schedule'?'active':'')?>" href="workspace.php?view=schedule<?=($projectId?'&id='.$projectId:'')?>">
    <i class="bi bi-calendar3"></i><span>График</span>
  </a>
  <a class="<?=($view==='team'?'active':'')?>" href="workspace.php?view=team<?=($projectId?'&id='.$projectId:'')?>">
    <i class="bi bi-people"></i><span>Команда</span>
  </a>
</nav>
<main class="main-content">
<header class="topbar">
  <div class="breadcrumbs"><button class="mobile-menu" onclick="document.getElementById('appSidebar').classList.add('sidebar-open')"><i class="bi bi-list"></i></button><span>Рабочее пространство</span><span class="slash">/</span><strong><?=e($pageTitle)?></strong></div>
  <div class="topbar-actions"><input type="hidden" name="csrf" value="<?=e(csrf_token()) ?>">
  <button class="icon-button search-toggle" id="globalSearchToggle" type="button" title="Поиск"><i class="bi bi-search"></i></button>
  <button class="icon-button notification-toggle top-notification-toggle" type="button" title="Оповещения"><i class="bi bi-bell"></i><span class="notification-badge" id="topNotificationBadge" hidden>0</span></button>
  <div class="top-avatar"><?=e($initials)?></div>
</div>
<div class="global-search-backdrop" id="globalSearchBackdrop" hidden>
  <div class="global-search-panel" role="dialog" aria-modal="true" aria-labelledby="globalSearchTitle">
    <div class="global-search-input-wrap"><i class="bi bi-search"></i><input id="globalSearchInput" autocomplete="off" placeholder="Поиск по проектам..." aria-label="Поиск"><button class="global-search-close" id="globalSearchClose" type="button" aria-label="Закрыть"><i class="bi bi-x-lg"></i></button></div>
    <div class="global-search-hint" id="globalSearchHint">Начните вводить название проекта, город или имя заказчика.</div>
    <div class="global-search-results" id="searchResults"></div>
    <div class="global-search-footer"><span>Быстрый поиск</span><span><kbd>Esc</kbd> закрыть</span></div>
  </div>
</div>
</header>
