<?php
use App\Core\View;
View::partial('head', ['title' => 'Reset password — Skilvi', 'description' => 'Reset your Skilvi password.']);
?>
<body data-chrome="public" data-page="forgot">
<?php View::partial('header_public'); ?>

<main class="container page-pad" style="max-width:480px">
  <div class="card card-pad mt-2">
    <div id="fpForm">
      <h1 style="font-size:22px">Reset your password</h1>
      <p class="small muted mt-1">Enter the phone number or email on your account. We’ll send a 6-digit code — it expires in 10 minutes.</p>
      <form id="fpSend" class="mt-3">
        <div class="field mb-2">
          <label>Phone number or email</label>
          <input class="input" name="identifier" type="text" placeholder="+234 803 000 0000 or you@example.com" required>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Send code</button>
      </form>
      <div class="row spread mt-3">
        <a class="link" href="/login.html" style="font-size:13px">← Back to log in</a>
        <a class="link" href="/help.html" style="font-size:13px">Need help?</a>
      </div>
    </div>
    <div id="fpSent" style="display:none">
      <h1 style="font-size:22px">Check your phone</h1>
      <p class="small muted mt-1">If an account matches <b class="mono" id="fpEcho">the number you entered</b>, a code is on its way. It expires in 10 minutes.</p>
      <p class="small faint" id="devCode" style="display:none"></p>
      <form id="fpReset" class="mt-3">
        <div class="field mb-2">
          <label>6-digit code</label>
          <input class="input" name="code" inputmode="numeric" maxlength="6" placeholder="000000" required>
        </div>
        <div class="field mb-3">
          <label>New password</label>
          <input class="input" name="password" type="password" placeholder="At least 8 characters" required minlength="8">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Set new password</button>
      </form>
      <p class="center small faint mt-2"><button class="link" style="font-size:13px" type="button" id="fpResend">Didn’t get it? Resend code</button></p>
      <a class="btn btn-secondary btn-block mt-3" href="/login.html">Back to log in</a>
    </div>
  </div>
</main>

<?php View::partial('footer_public'); ?>
<script src="/js/auth.js"></script>
</body>
</html>
