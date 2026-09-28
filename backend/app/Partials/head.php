<?php
/** @var string $title */
/** @var string $description */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>(function(){try{var p=localStorage.getItem("skilvi_theme")||"system";var dark=p==="dark"||(p!=="light"&&window.matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.setAttribute("data-theme",dark?"dark":"light");document.documentElement.setAttribute("data-theme-pref",p);}catch(e){}})();</script>
  <style id="skLoaderCss">html.sk-loading{background:#F8FAFC}html[data-theme="dark"].sk-loading{background:#10141C}html.sk-loading body{visibility:hidden}html.sk-loading #skLoader{visibility:visible;display:flex}#skLoader{display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;background:#F8FAFC}html[data-theme="dark"] #skLoader{background:#10141C}#skLoader video{width:min(420px,86vw);height:auto;display:block}</style>
  <script>(function(){try{if(sessionStorage.getItem("skilvi_nav")==="1")document.documentElement.classList.add("sk-loading")}catch(e){}})();</script>
  <link rel="preload" href="/assets/video/skilvi-loader.webm" as="video" type="video/webm">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
  <link rel="icon" href="/assets/img/skilvi-favicon.png?v=2" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=2">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="stylesheet" href="/assets/css/skilvi.css?v=33">
</head>
