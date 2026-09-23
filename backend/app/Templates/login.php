<?php
use App\Core\View;
use App\Services\AuthService;
View::partial('head', ['title' => 'Log in — Skilvi', 'description' => 'Log in or create a free Skilvi account with your email.']);
?>
<body data-chrome="public" data-page="login">
<?php View::partial('header_public'); ?>

<main class="container page-pad" style="max-width:480px">
  <div class="center" style="margin-bottom:18px">
    <a class="brand" href="/index.html" style="justify-content:center"><img class="brand-logo" id="lgMark" src="/assets/img/skilvi-logo-word.png" alt="Skilvi"></a>
    <p class="small faint mt-1">Email login, Naira-ready, escrow-protected.</p>
  </div>

  <div class="card card-pad">
    <?php if (!empty($me)): ?>
      <div class="alert alert-info mb-3">
        <span>You're signed in as <b><?= e((string) ($me['full_name'] ?? $me['name'] ?? 'you')) ?></b>.
          <a href="<?= e(AuthService::homeFor($me)) ?>">Open dashboard</a>
          · <a href="/logout.html">Sign out</a> to test login again.</span>
      </div>
    <?php endif; ?>
    <div id="authStepMain">
      <div class="tabs mb-3" id="authTabs">
        <a class="tab auth-tab active" data-mode="login" href="#loginForm">Log in</a>
        <a class="tab auth-tab" data-mode="register" href="#regForm">Create account</a>
      </div>

      <form id="regForm" class="tab-panel" data-panel="register" autocomplete="on">
        <div class="field mb-2">
          <label>Full name</label>
          <input class="input" name="full_name" placeholder="e.g. Chinedu Okafor" required>
        </div>
        <div class="form-row-2 mb-2">
          <div class="field">
            <label>Email <span style="color:var(--red)">*</span></label>
            <input class="input" name="email" type="email" placeholder="you@example.com" required>
          </div>
          <div class="field">
            <label>Phone (optional)</label>
            <input class="input" name="phone" type="tel" placeholder="+234 803 000 0000">
          </div>
        </div>
        <div class="field mb-2">
          <label>Password</label>
          <input class="input" name="password" type="password" placeholder="At least 8 characters" required minlength="8">
        </div>
        <div class="field mb-3">
          <label>I am joining as…</label>
          <div class="row" data-radio style="gap:8px">
            <input type="radio" name="join_as" id="joinAs_w" class="pill-check" value="worker">
            <label class="radio-pill" for="joinAs_w" style="flex:1"><span class="rd"></span>Worker</label>
            <input type="radio" name="join_as" id="joinAs_c" class="pill-check" value="client" checked>
            <label class="radio-pill" for="joinAs_c" style="flex:1"><span class="rd"></span>Client</label>
            <input type="radio" name="join_as" id="joinAs_b" class="pill-check" value="both">
            <label class="radio-pill" for="joinAs_b" style="flex:1"><span class="rd"></span>Both</label>
          </div>
          <span class="hint">Joining as both is free — your dashboards stay separate.</span>
        </div>
        <label class="check-row" style="padding:0">
          <input type="checkbox" name="terms" required style="accent-color:var(--royal-600);width:15px;height:15px">
          <span class="small" style="color:var(--ink-2)">I agree to the <a href="/terms.html">Terms of Service</a> and <a href="/privacy.html">Privacy Policy</a>.</span>
        </label>
        <button class="btn btn-primary btn-block btn-lg mt-3" type="submit">Create free account</button>
      </form>

      <form id="loginForm" class="tab-panel active" data-panel="login" autocomplete="on">
        <div class="field mb-2">
          <label>Email</label>
          <input class="input" name="identifier" type="email" placeholder="you@example.com" required>
        </div>
        <div class="field mb-3">
          <label>Password</label>
          <input class="input" name="password" type="password" placeholder="Your password" required>
          <span class="hint" style="text-align:right"><a class="link" href="/forgot-password.html" style="font-size:12px">Forgot password?</a></span>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Continue</button>
      </form>
    </div>

    <div id="authStepOtp" style="display:none">
      <h3 style="font-size:17px">Check your email</h3>
      <p class="small muted mt-1">We sent a 6-digit code to <b class="mono" id="otpMask">your email</b>. It expires in 10 minutes.</p>
      <p class="small faint" id="devCode" style="display:none"></p>
      <form id="otpForm" class="mt-3">
        <input type="hidden" name="purpose" id="otpPurpose" value="login">
        <div class="row" style="justify-content:center;gap:8px" id="otpBoxes">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 1">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 2">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 3">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 4">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 5">
          <input class="input otp-box" inputmode="numeric" maxlength="1" aria-label="Digit 6">
        </div>
        <button class="btn btn-primary btn-block btn-lg mt-3" type="submit">Verify &amp; continue</button>
        <p class="center small mt-2" id="otpStatus" style="color:var(--ink-2)"></p>
      </form>
      <p class="center small faint mt-2"><button class="link" style="font-size:13px" type="button" id="otpResend">Didn’t get it? Resend code</button></p>
    </div>
  </div>

  <div class="alert alert-info mt-3" id="lgTrust"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg><span><b>Safe by design.</b> Accounts are email-verified, and every order is paid into escrow — neither side can run.</span></div>
</main>

<?php View::partial('footer_public'); ?>
<script src="/js/auth.js?v=19"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const I = SkIconSvg;
    document.querySelector("#lgTrust").innerHTML = I.shield + "<span><b>Safe by design.</b> Accounts are email-verified, and every order is paid into escrow — neither side can run.</span>";
  });
</script>
</body>
</html>
