<?php
declare(strict_types=1);
if (!isset($pageTitle)) $pageTitle = 'Сметограм';
$user = current_user();
$view = $_GET['view'] ?? 'projects';
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
<link href="/assets/css/app.css?v=20261004-2" rel="stylesheet">
</head><body>
<div class="app-shell">
<aside class="sidebar" id="appSidebar">
  <div class="brand"><span class="brand-mark">S</span><span>сметограм</span></div>
  <button class="mobile-close" onclick="document.getElementById('appSidebar').classList.remove('sidebar-open')"><i class="bi bi-x-lg"></i></button>
  <div class="workspace-label">РАБОЧЕЕ ПРОСТРАНСТВО</div>
  <nav class="nav-list">
    <a class="nav-item <?=($view==='projects'?'active':'')?>" href="dashboard.php"><span class="nav-icon"><i class="bi bi-grid-1x2"></i></span>Мои проекты</a>
    <a class="nav-item <?=($view==='scan'?'active':'')?>" href="workspace.php?view=scan<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>Смета из файла</a>
    <a class="nav-item <?=($view==='ai'?'active':'')?>" href="ai.php<?=($projectId?'?id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-stars"></i></span>ИИ и распознавание</a>
    <a class="nav-item <?=($view==='measurements'?'active':'')?>" href="workspace.php?view=measurements<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-rulers"></i></span>Замеры</a>
    <a class="nav-item <?=($view==='schedule'?'active':'')?>" href="workspace.php?view=schedule<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-calendar3"></i></span>График работ</a>
    <a class="nav-item <?=($view==='analytics'?'active':'')?>" href="workspace.php?view=analytics<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-bar-chart"></i></span>Графики</a>
    <a class="nav-item <?=($view==='team'?'active':'')?>" href="workspace.php?view=team<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-people"></i></span>Команда</a>
  </nav>
  <div class="sidebar-spacer"></div>
  <div class="trial-card"><div class="trial-icon"><i class="bi bi-stars"></i></div><div><strong>Первый проект бесплатно</strong><span>Без карты и обязательств</span></div><i class="bi bi-arrow-up-right"></i></div>
  <nav class="nav-list bottom-nav">
    <a class="nav-item <?=($view==='settings'?'active':'')?>" href="workspace.php?view=settings"><span class="nav-icon"><i class="bi bi-sliders2"></i></span>Настройки</a>
    <a class="nav-item <?=($view==='documents'?'active':'')?>" href="workspace.php?view=documents<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-file-text"></i></span>Документы</a>
    <a class="nav-item <?=($view==='chat'?'active':'')?>" href="workspace.php?view=chat<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-chat"></i></span>Чат проекта</a>
    <a class="nav-item <?=($view==='acceptance'?'active':'')?>" href="workspace.php?view=acceptance<?=($projectId?'&id='.$projectId:'')?>"><span class="nav-icon"><i class="bi bi-check2-square"></i></span>Приёмка</a>
    <a class="nav-item <?=($view==='billing'?'active':'')?>" href="workspace.php?view=billing"><span class="nav-icon"><i class="bi bi-credit-card"></i></span>Тариф</a>
  </nav>
  <div class="profile"><div class="avatar"><?=e($initials)?></div><div><strong><?=e($user['name']??'Пользователь')?></strong><span>Пользователь</span></div><a href="logout.php" class="muted-icon" title="Выйти"><i class="bi bi-box-arrow-right"></i></a></div>
</aside>
<div class="sidebar-backdrop" onclick="document.getElementById('appSidebar').classList.remove('sidebar-open')"></div>
<main class="main-content">
<header class="topbar">
  <div class="breadcrumbs"><button class="mobile-menu" onclick="document.getElementById('appSidebar').classList.add('sidebar-open')"><i class="bi bi-list"></i></button><span>Рабочее пространство</span><span class="slash">/</span><strong><?=e($pageTitle)?></strong></div>
  <div class="topbar-actions"><a href="dashboard.php" class="icon-button" title="Проекты"><i class="bi bi-search"></i></a><div class="top-avatar"><?=e($initials)?></div></div>
</header>
