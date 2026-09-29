<?php
use App\Core\View;
View::partial('head', ['title' => 'Finish your account — Skilvi', 'description' => 'Add the details Google does not share so we can open your Skilvi account.']);
$me = $me ?? null;
?>
<body data-chrome="public" data-page="complete-profile">
<?php View::partial('header_public'); ?>

<main class="container page-pad" style="max-width:480px">
  <div class="center" style="margin-bottom:18px">
    <a class="brand" href="/index.html" style="justify-content:center"><img class="brand-logo" src="/assets/img/skilvi-logo-word.png" alt="Skilvi"></a>
    <p class="small faint mt-1">Google signed you in. Finish these details to open your account.</p>
  </div>

  <div class="card card-pad">
    <form id="completeForm" autocomplete="on">
      <style>
        #cpDobWrap { display: none; }
        #completeForm:has(#cpJoin_w:checked) #cpDobWrap,
        #completeForm:has(#cpJoin_b:checked) #cpDobWrap { display: block; }
        .reg-pane { display: none; }
        #completeForm:has(#cpStepJoin:checked) .reg-pane-join,
        #completeForm:has(#cpStepDetails:checked) .reg-pane-details { display: block; }
      </style>
      <input type="radio" name="cp_step" id="cpStepJoin" class="reg-step-radio" value="join" checked>
      <input type="radio" name="cp_step" id="cpStepDetails" class="reg-step-radio" value="details">

      <div class="reg-pane reg-pane-join">
        <h2 class="auth-step-h">Are you signing up as a…</h2>
        <p class="small muted mt-1 mb-3">You can change this later. Both is free — dashboards stay separate.</p>
        <div class="auth-choices mb-3">
          <input type="radio" name="join_as" id="cpJoin_w" class="auth-choice-check" value="worker" required>
          <label class="auth-choice" for="cpJoin_w">
            <span class="auth-choice-t">Worker</span>
            <span class="auth-choice-d">Offer your skills. Get paid through escrow.</span>
          </label>
          <input type="radio" name="join_as" id="cpJoin_c" class="auth-choice-check" value="client">
          <label class="auth-choice" for="cpJoin_c">
            <span class="auth-choice-t">Client</span>
            <span class="auth-choice-d">Hire trusted talent and pay safely.</span>
          </label>
          <input type="radio" name="join_as" id="cpJoin_b" class="auth-choice-check" value="both">
          <label class="auth-choice" for="cpJoin_b">
            <span class="auth-choice-t">Both</span>
            <span class="auth-choice-d">Hire and work. Two dashboards, one account.</span>
          </label>
        </div>
        <label for="cpStepDetails" class="btn btn-primary btn-block btn-lg" data-cp-next="details">Continue</label>
      </div>

      <div class="reg-pane reg-pane-details">
        <h2 class="auth-step-h">A few details</h2>
        <p class="small muted mt-1 mb-3">Then your account is ready.</p>
        <div class="field mb-2">
          <label for="cpName">Full name <span class="req" aria-hidden="true">*</span></label>
          <input class="input" id="cpName" name="full_name" type="text" autocomplete="name" autocapitalize="words" required minlength="2" maxlength="80" value="<?= e((string) ($me['full_name'] ?? '')) ?>">
        </div>
        <div class="field mb-2">
          <label for="cpEmail">Email</label>
          <input class="input" id="cpEmail" type="email" value="<?= e((string) ($me['email'] ?? '')) ?>" disabled>
          <span class="hint">From Google — this is how you sign in.</span>
        </div>
        <div class="field mb-2">
          <label for="cpPhone">Phone <span class="faint" style="font-weight:500">(optional)</span></label>
          <input class="input" id="cpPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+234 803 000 0000">
        </div>
        <div class="form-row-2 mb-2">
          <div class="field">
            <label for="cpCountry">Country <span class="req" aria-hidden="true">*</span></label>
            <select class="select" id="cpCountry" name="country" required><option value="">Select country</option></select>
          </div>
          <div class="field">
            <label for="cpState">State / region <span class="req" aria-hidden="true">*</span></label>
            <select class="select" id="cpState" name="state" required disabled><option value="">Select state / region</option></select>
          </div>
        </div>
        <div class="field mb-2">
          <label for="cpCity">City <span class="req" aria-hidden="true">*</span></label>
          <select class="select" id="cpCity" name="city" required disabled><option value="">Select city</option></select>
        </div>
        <div class="field mb-2" id="cpDobWrap">
          <label for="cpDob">Date of birth <span class="req" aria-hidden="true">*</span></label>
          <input class="input" id="cpDob" name="dob" type="date">
          <span class="hint">Workers must be 16 or older.</span>
        </div>
        <div class="field mb-2">
          <label for="cpGender">Gender <span class="faint" style="font-weight:500">(optional)</span></label>
          <select class="select" id="cpGender" name="gender">
            <option value="">Prefer not to say</option>
            <option value="female">Female</option>
            <option value="male">Male</option>
            <option value="prefer_not">Prefer not to say</option>
          </select>
        </div>
        <div class="field mb-2">
          <label for="cpHeard">How did you hear about Skilvi? <span class="req" aria-hidden="true">*</span></label>
          <select class="select" id="cpHeard" name="heard_about" required>
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
        <p class="center mt-2"><label for="cpStepJoin" class="link" data-cp-back="join">Back</label></p>
      </div>
    </form>
  </div>
</main>

<?php View::partial('footer_public'); ?>
<script src="/js/geo.js?v=1"></script>
<script src="/js/auth.js?v=45"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    if (window.SkGeo) {
      window.SkGeo.bind({ country: "#cpCountry", state: "#cpState", city: "#cpCity", phone: "#cpPhone", values: { country: "Nigeria" } });
    }
  });
</script>
</body>
</html>
