<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signing in — Skilvi</title>
</head>
<body>
  <p style="font-family:system-ui,sans-serif;padding:24px;color:#334">Signing you in…</p>
  <script>
    (function () {
      var token = <?= json_encode((string) ($token ?? ''), JSON_UNESCAPED_SLASHES) ?>;
      var next = <?= json_encode((string) ($next ?? '/complete-profile.html'), JSON_UNESCAPED_SLASHES) ?>;
      try { if (token) sessionStorage.setItem("skilvi_auth", token); } catch (e) {}
      location.replace(next || "/complete-profile.html");
    })();
  </script>
</body>
</html>
