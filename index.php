<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Points Tracker – Login</title>
<link rel="stylesheet" href="css/style.css">
<style>
  /* Apply stored theme before JS loads to avoid flash */
</style>
</head>
<body data-theme="dark">

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <h1>🏃 Activity Tracker</h1>
      <p>Track your weekly health &amp; fitness goals</p>
    </div>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="auth-tab active" data-tab="login" id="tab-login">Login</button>
      <button class="auth-tab"        data-tab="signup" id="tab-signup">Sign Up</button>
    </div>

    <!-- Login Form -->
    <div class="auth-form active" id="form-login">
      <div id="login-error" class="alert alert-error hidden"></div>

      <div class="form-group">
        <label for="login-username">Username</label>
        <input type="text" id="login-username" class="form-control" placeholder="Enter your username" autocomplete="username">
      </div>

      <div class="form-group">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" class="form-control" placeholder="Enter your password" autocomplete="current-password">
      </div>

      <button class="btn btn-primary btn-full mt-2" id="login-btn">Login</button>

      <p class="text-center mt-2" style="font-size:.88rem;color:var(--text-muted)">
        Don't have an account?
        <a href="#" id="go-signup">Sign up here</a>
      </p>
    </div>

    <!-- Signup Form -->
    <div class="auth-form" id="form-signup">
      <div id="signup-error" class="alert alert-error hidden"></div>

      <div class="form-row">
        <div class="form-group">
          <label for="signup-username">Username</label>
          <input type="text" id="signup-username" class="form-control" placeholder="Choose a username" autocomplete="username">
        </div>
        <div class="form-group">
          <label for="signup-fullname">Full Name</label>
          <input type="text" id="signup-fullname" class="form-control" placeholder="Your full name">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="signup-password">Password</label>
          <input type="password" id="signup-password" class="form-control" placeholder="Min 6 characters" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="signup-confirm">Confirm Password</label>
          <input type="password" id="signup-confirm" class="form-control" placeholder="Repeat password" autocomplete="new-password">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="signup-age">Age</label>
          <input type="number" id="signup-age" class="form-control" placeholder="e.g. 30" min="10" max="120">
        </div>
        <div class="form-group">
          <label for="signup-gender">Gender</label>
          <select id="signup-gender" class="form-control">
            <option value="m">Male</option>
            <option value="f">Female</option>
          </select>
        </div>
      </div>

      <!-- Captcha -->
      <div class="captcha-box">
        <p class="captcha-question" id="captcha-question">Loading…</p>
      </div>
      <div class="form-group">
        <label for="captcha-answer">Your Answer</label>
        <input type="number" id="captcha-answer" class="form-control" placeholder="Enter the answer">
      </div>

      <button class="btn btn-primary btn-full mt-2" id="signup-btn">Create Account</button>

      <p class="text-center mt-2" style="font-size:.88rem;color:var(--text-muted)">
        Already have an account?
        <a href="#" id="go-login">Login here</a>
      </p>
    </div>

  </div>
</div>

<div id="toast-container"></div>
<script src="js/app.js"></script>
<script>
(function () {
  // Apply stored theme immediately
  try {
    var t = localStorage.getItem('apt_theme');
    if (t) document.body.dataset.theme = t;
  } catch(_) {}

  // If already logged in, redirect
  App.initAuth({ onLogin: function() { window.location.href = 'dashboard.php'; } });

  // Tab switching
  function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(function(el) {
      el.classList.toggle('active', el.dataset.tab === tab);
    });
    document.querySelectorAll('.auth-form').forEach(function(el) {
      el.classList.toggle('active', el.id === 'form-' + tab);
    });
    if (tab === 'signup') loadCaptcha();
  }

  document.querySelectorAll('.auth-tab').forEach(function(btn) {
    btn.addEventListener('click', function() { switchTab(this.dataset.tab); });
  });
  document.getElementById('go-signup').addEventListener('click', function(e) { e.preventDefault(); switchTab('signup'); });
  document.getElementById('go-login').addEventListener('click',  function(e) { e.preventDefault(); switchTab('login'); });

  // Captcha
  function loadCaptcha() {
    fetch('captcha.php', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        document.getElementById('captcha-question').textContent = d.question || 'Error loading captcha';
      })
      .catch(function() {
        document.getElementById('captcha-question').textContent = 'Could not load captcha';
      });
  }

  function showFormError(formId, msg) {
    var el = document.getElementById(formId + '-error');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
  }
  function hideFormError(formId) {
    var el = document.getElementById(formId + '-error');
    if (el) el.classList.add('hidden');
  }

  // LOGIN
  var loginBtn = document.getElementById('login-btn');
  loginBtn.addEventListener('click', function() {
    hideFormError('login');
    var username = document.getElementById('login-username').value.trim();
    var password = document.getElementById('login-password').value;

    if (!username || !password) { showFormError('login', 'Please enter username and password.'); return; }

    loginBtn.disabled = true;
    loginBtn.textContent = 'Logging in…';

    App.fetchJSON('/api/auth.php', { action: 'login', username: username, password: password })
      .then(function(data) {
        if (data.success) {
          App.applyTheme(data.theme || 'dark');
          try { localStorage.setItem('apt_theme', data.theme || 'dark'); } catch(_) {}
          App.startLogoutTimer();
          window.location.href = 'dashboard.php';
        } else {
          showFormError('login', data.error || 'Login failed');
          loginBtn.disabled = false;
          loginBtn.textContent = 'Login';
        }
      })
      .catch(function() {
        showFormError('login', 'Network error. Please try again.');
        loginBtn.disabled = false;
        loginBtn.textContent = 'Login';
      });
  });

  // Allow Enter key on login fields
  ['login-username','login-password'].forEach(function(id) {
    document.getElementById(id).addEventListener('keydown', function(e) {
      if (e.key === 'Enter') loginBtn.click();
    });
  });

  // SIGNUP
  var signupBtn = document.getElementById('signup-btn');
  signupBtn.addEventListener('click', function() {
    hideFormError('signup');
    var username  = document.getElementById('signup-username').value.trim();
    var fullname  = document.getElementById('signup-fullname').value.trim();
    var password  = document.getElementById('signup-password').value;
    var confirm   = document.getElementById('signup-confirm').value;
    var age       = parseInt(document.getElementById('signup-age').value, 10) || 25;
    var gender    = document.getElementById('signup-gender').value;
    var captchaAns = parseInt(document.getElementById('captcha-answer').value, 10);

    if (!username)  { showFormError('signup', 'Username is required.'); return; }
    if (password.length < 6) { showFormError('signup', 'Password must be at least 6 characters.'); return; }
    if (password !== confirm) { showFormError('signup', 'Passwords do not match.'); return; }
    if (isNaN(captchaAns))   { showFormError('signup', 'Please answer the captcha.'); return; }

    signupBtn.disabled = true;
    signupBtn.textContent = 'Creating account…';

    App.fetchJSON('/api/auth.php', {
      action:          'signup',
      username:        username,
      password:        password,
      full_name:       fullname,
      age:             age,
      gender:          gender,
      captcha_answer:  captchaAns,
    }).then(function(data) {
      if (data.success) {
        App.applyTheme('dark');
        try { localStorage.setItem('apt_theme', 'dark'); } catch(_) {}
        App.startLogoutTimer();
        App.showToast('Account created! Welcome, ' + data.username + '!', 'success');
        setTimeout(function() { window.location.href = 'dashboard.php'; }, 800);
      } else {
        showFormError('signup', data.error || 'Signup failed');
        signupBtn.disabled = false;
        signupBtn.textContent = 'Create Account';
        loadCaptcha(); // Refresh captcha
      }
    }).catch(function() {
      showFormError('signup', 'Network error. Please try again.');
      signupBtn.disabled = false;
      signupBtn.textContent = 'Create Account';
    });
  });
})();
</script>
</body>
</html>
