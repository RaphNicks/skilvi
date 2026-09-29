<?php
use App\Core\View;
use App\Services\AuthService;
View::partial('head', ['title' => 'Log in — Skilvi', 'description' => 'Log in or create a free Skilvi account with Google or email.']);
$gSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.1-3.5 5.6-6.7 6.5l6.3 5.3C38.5 36.9 44 31.2 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>';
$gNext = '';
$n = $_GET['next'] ?? '';
if (is_string($n) && str_starts_with($n, '/') && !str_starts_with($n, '//') && !str_contains($n, '://')) {
    $gNext = '?next=' . rawurlencode($n);
}
$gHref = '/api/auth/google/start' . $gNext;
?>
<body data-chrome="public" data-page="login">
<?php View::partial('header_public'); ?>

<main class="container page-pad" style="max-width:480px">
  <div class="center" style="margin-bottom:18px">
    <a class="brand" href="/index.html" style="justify-content:center"><img class="brand-logo" id="lgMark" src="/assets/img/skilvi-logo-word.png" alt="Skilvi"></a>
    <p class="small faint mt-1" data-cms="login.tag">Email login, Naira-ready, escrow-protected.</p>
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

      <form id="loginForm" class="tab-panel active" data-panel="login" autocomplete="on">
        <a class="btn btn-google btn-block" href="<?= e($gHref) ?>"><?= $gSvg ?> Continue with Google</a>
        <p class="auth-split"><span>or</span></p>
        <input type="checkbox" id="loginEmailOpen" class="auth-email-check">
        <label for="loginEmailOpen" class="btn btn-secondary btn-block">Continue with email</label>
        <div class="auth-email-slide">
          <div class="auth-email-in">
            <div class="field mb-2">
              <label for="loginEmail">Email <span class="req" aria-hidden="true">*</span></label>
              <input class="input" id="loginEmail" name="identifier" type="email" inputmode="email" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="you@example.com" required>
            </div>
            <div class="field mb-3">
              <label for="loginPass">Password</label>
              <input class="input" id="loginPass" name="password" type="password" autocomplete="current-password" placeholder="Your password" required>
              <span class="hint" style="text-align:right"><a class="link" href="/forgot-password.html" style="font-size:12px">Forgot password?</a></span>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
          </div>
        </div>
      </form>

      <form id="regForm" class="tab-panel" data-panel="register" autocomplete="off">
        <style>
          #regDobWrap { display: none; }
          #regForm:has(#joinAs_w:checked) #regDobWrap,
          #regForm:has(#joinAs_b:checked) #regDobWrap { display: block; }
          .reg-pane { display: none; }
          #regForm:has(#regStepMethod:checked) .reg-pane-method,
          #regForm:has(#regStepJoin:checked) .reg-pane-join,
          #regForm:has(#regStepDetails:checked) .reg-pane-details { display: block; }
        </style>
        <input type="radio" name="reg_step" id="regStepMethod" class="reg-step-radio" value="method" checked>
        <input type="radio" name="reg_step" id="regStepJoin" class="reg-step-radio" value="join">
        <input type="radio" name="reg_step" id="regStepDetails" class="reg-step-radio" value="details">

        <div class="reg-pane reg-pane-method">
          <a class="btn btn-google btn-block" href="<?= e($gHref) ?>"><?= $gSvg ?> Continue with Google</a>
          <p class="auth-split"><span>or</span></p>
          <input type="checkbox" id="regEmailOpen" class="auth-email-check">
          <label for="regEmailOpen" class="btn btn-secondary btn-block">Continue with email</label>
          <div class="auth-email-slide">
            <div class="auth-email-in">
              <div class="field mb-2">
                <label for="regEmail">Email <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="regEmail" name="email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="you@example.com" required>
              </div>
              <div class="field mb-3">
                <label for="regPass">Password <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="regPass" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required minlength="8" readonly>
              </div>
              <label for="regStepJoin" class="btn btn-primary btn-block btn-lg" data-reg-next="join">Continue</label>
            </div>
          </div>
        </div>

        <div class="reg-pane reg-pane-join">
          <h2 class="auth-step-h">Are you signing up as a…</h2>
          <p class="small muted mt-1 mb-3">You can change this later. Both is free — dashboards stay separate.</p>
          <div class="auth-choices mb-3">
            <input type="radio" name="join_as" id="joinAs_w" class="auth-choice-check" value="worker" required>
            <label class="auth-choice" for="joinAs_w">
              <span class="auth-choice-t">Worker</span>
              <span class="auth-choice-d">Offer your skills. Get paid through escrow.</span>
            </label>
            <input type="radio" name="join_as" id="joinAs_c" class="auth-choice-check" value="client">
            <label class="auth-choice" for="joinAs_c">
              <span class="auth-choice-t">Client</span>
              <span class="auth-choice-d">Hire trusted talent and pay safely.</span>
            </label>
            <input type="radio" name="join_as" id="joinAs_b" class="auth-choice-check" value="both">
            <label class="auth-choice" for="joinAs_b">
              <span class="auth-choice-t">Both</span>
              <span class="auth-choice-d">Hire and work. Two dashboards, one account.</span>
            </label>
          </div>
          <label for="regStepDetails" class="btn btn-primary btn-block btn-lg" data-reg-next="details">Continue</label>
          <p class="center mt-2"><label for="regStepMethod" class="link" data-reg-back="method">Back</label></p>
        </div>

        <div class="reg-pane reg-pane-details">
          <h2 class="auth-step-h">A few details</h2>
          <p class="small muted mt-1 mb-3">Then we email you a code to open the account.</p>
          <div class="field mb-2">
            <label for="regName">Full name <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="regName" name="full_name" type="text" autocomplete="name" autocapitalize="words" placeholder="e.g. Chinedu Okafor" required minlength="2" maxlength="80">
          </div>
          <div class="field mb-2">
            <label for="regPhone">Phone <span class="faint" style="font-weight:500">(optional)</span></label>
            <input class="input" id="regPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+234 803 000 0000">
          </div>
          <div class="form-row-2 mb-2">
            <div class="field">
              <label for="regCountry">Country <span class="req" aria-hidden="true">*</span></label>
              <select class="select" id="regCountry" name="country" required><option value="">Select country</option></select>
            </div>
            <div class="field">
              <label for="regState">State / region <span class="req" aria-hidden="true">*</span></label>
              <select class="select" id="regState" name="state" required disabled><option value="">Select state / region</option></select>
            </div>
          </div>
          <div class="field mb-2">
            <label for="regCity">City <span class="req" aria-hidden="true">*</span></label>
            <select class="select" id="regCity" name="city" required disabled><option value="">Select city</option></select>
          </div>
          <div class="field mb-2" id="regDobWrap">
            <label for="regDob">Date of birth <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="regDob" name="dob" type="date">
            <span class="hint">Workers must be 16 or older.</span>
          </div>
          <div class="field mb-2">
            <label for="regGender">Gender <span class="faint" style="font-weight:500">(optional)</span></label>
            <select class="select" id="regGender" name="gender">
              <option value="">Prefer not to say</option>
              <option value="female">Female</option>
              <option value="male">Male</option>
              <option value="prefer_not">Prefer not to say</option>
            </select>
          </div>
          <div class="field mb-2">
            <label for="regHeard">How did you hear about Skilvi? <span class="req" aria-hidden="true">*</span></label>
            <select class="select" id="regHeard" name="heard_about" required>
              <option value="">Select one</option>
              <option value="google">Google / search</option>
              <option value="instagram">Instagram</option>
              <option value="facebook">Facebook</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="tiktok">TikTok</option>
              <option value="friend">A friend told me</option>
              <option value="youtube">YouTube</option>
              <option value="other">Other</option>
            </select>
          </div>
          <label class="check-row" style="padding:0">
            <input type="checkbox" name="terms" required style="accent-color:var(--royal-600);width:15px;height:15px">
            <span class="small" style="color:var(--ink-2)">I agree to the <a href="/terms.html">Terms of Service</a> and <a href="/privacy.html">Privacy Policy</a>.</span>
          </label>
          <button class="btn btn-primary btn-block btn-lg mt-3" type="submit">Create free account</button>
          <p class="center mt-2"><label for="regStepJoin" class="link" data-reg-back="join">Back</label></p>
        </div>
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
<script src="/js/geo.js?v=1"></script>
<script src="/js/auth.js?v=45"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    if (window.SkGeo) {
      window.SkGeo.bind({ country: "#regCountry", state: "#regState", city: "#regCity", phone: "#regPhone", values: { country: "Nigeria" } });
    }
  });
</script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const I = SkIconSvg;
    document.querySelector("#lgTrust").innerHTML = I.shield + "<span><b>Safe by design.</b> Accounts are email-verified, and every order is paid into escrow — neither side can run.</span>";
  });
</script>
</body>
</html>
