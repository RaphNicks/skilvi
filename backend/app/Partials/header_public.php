<?php
$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = basename($path) ?: 'index.html';
$talent = in_array($file, ['search.html', 'worker-profile.html', 'service-detail.html'], true);
$cats = $file === 'category.html';
$work = in_array($file, ['jobs.html', 'job-detail.html', 'post-job.html'], true);
$login = in_array($file, ['login.html', 'forgot-password.html'], true);
$act = static function (bool $on): string {
    return $on ? ' class="active"' : '';
};
?>
<header class="lp-nav">
  <div class="lp-wrap lp-nav-in">
    <a class="lp-logo" href="/index.html" aria-label="Skilvi home"><img src="/assets/img/skilvi-logo-word.png" alt="Skilvi — Discover. Learn. Earn."></a>
    <nav class="lp-nav-mid" aria-label="Primary">
      <a href="/search.html"<?= $act($talent) ?>>Find Talent</a>
      <a href="/category.html"<?= $act($cats) ?>>Categories</a>
      <a href="/jobs.html"<?= $act($work) ?>>Find Work</a>
    </nav>
    <div class="lp-nav-right">
      <button type="button" class="icon-btn theme-switch" data-theme-switch aria-label="Display mode" title="Display: System — click to change">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
      </button>
      <a class="lp-signin<?= $login ? ' active' : '' ?>" href="/login.html">Sign In</a>
      <a class="lp-btn lp-btn-solid lp-btn-sm" href="/login.html#regForm">Sign Up</a>
    </div>
    <label class="lp-burger" for="lpMenu" aria-label="Menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </label>
  </div>
  <input type="checkbox" id="lpMenu" class="lp-menu-check">
  <div class="lp-menu">
    <div class="lp-menu-in">
      <a href="/search.html"<?= $act($talent) ?>>Find Talent</a>
      <a href="/category.html"<?= $act($cats) ?>>Categories</a>
      <a href="/jobs.html"<?= $act($work) ?>>Find Work</a>
      <a href="/login.html">Sign In</a>
      <a class="lp-btn lp-btn-solid lp-btn-sm" href="/login.html#regForm">Sign Up — it's free</a>
    </div>
  </div>
</header>
