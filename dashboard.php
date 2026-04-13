<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$username = htmlspecialchars($_SESSION['user']);
$role     = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – Activity Tracker</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body data-theme="dark">

<!-- Navbar -->
<nav class="navbar">
  <span class="navbar-brand">🏃 Activity Tracker</span>
  <ul class="navbar-nav">
    <li><a href="dashboard.php" class="active">Dashboard</a></li>
    <li><a href="profile.php">Profile</a></li>
    <?php if ($role === 'admin'): ?>
    <li><a href="admin.php">Admin</a></li>
    <?php endif; ?>
  </ul>
  <div class="navbar-right">
    <span class="navbar-user">👤 <strong id="navbar-username"><?= $username ?></strong></span>
    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
  </div>
</nav>

<div class="page-container">
  <div class="page-header">
    <h1>My Week</h1>
    <p>Log your daily activities and track weekly progress</p>
  </div>

  <div class="dashboard-grid">

    <!-- LEFT: Log Today -->
    <div class="card">
      <div class="card-title"><span class="icon">📝</span> Log Today</div>

      <div class="form-group">
        <label for="log-date">Date</label>
        <input type="date" id="log-date" class="form-control">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="log-steps">Steps</label>
          <input type="number" id="log-steps" class="form-control" placeholder="e.g. 8000" min="0">
        </div>
        <div class="form-group">
          <label for="log-water">Water (glasses)</label>
          <input type="number" id="log-water" class="form-control" placeholder="e.g. 8" min="0" step="0.5">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="log-sleep">Sleep (hours)</label>
          <input type="number" id="log-sleep" class="form-control" placeholder="e.g. 7.5" min="0" max="24" step="0.5">
        </div>
        <div class="form-group">
          <label for="log-meals">Clean Meals</label>
          <input type="number" id="log-meals" class="form-control" placeholder="e.g. 3" min="0" max="10">
        </div>
      </div>

      <div class="divider"></div>
      <div class="card-title" style="margin-bottom:.75rem"><span class="icon">🏋️</span> Activities</div>

      <div class="activity-add-row">
        <div class="form-group" style="margin:0">
          <label for="activity-select">Activity</label>
          <select id="activity-select" class="form-control">
            <option value="">— Loading… —</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label for="activity-qty">Quantity</label>
          <input type="number" id="activity-qty" class="form-control" placeholder="Qty" min="0.1" step="0.1" style="width:90px">
        </div>
        <div style="padding-top:1.6rem">
          <button class="btn btn-secondary" id="add-activity-btn">+ Add</button>
        </div>
      </div>

      <ul class="activity-list" id="pending-activities">
        <li style="color:var(--text-muted);font-size:.85rem;padding:.25rem 0">No activities added yet.</li>
      </ul>

      <div style="font-size:.85rem;color:var(--accent);margin:.5rem 0 1rem;font-weight:500" id="preview-points">0.00 pts (unsaved)</div>

      <button class="btn btn-primary btn-full" id="save-day-btn">💾 Save Day</button>
    </div>

    <!-- RIGHT: Week Progress -->
    <div>

      <!-- Week label + points -->
      <div class="card mb-3" style="margin-bottom:1.5rem">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
          <div class="card-title" style="margin-bottom:0"><span class="icon">📅</span> <span id="week-label">Current Week</span></div>
        </div>

        <div class="points-total">
          <div class="points-number" id="total-points">0.0</div>
          <div class="points-label">Activity Points This Week</div>
          <div class="points-pct" id="points-pct">0% of target</div>
        </div>

        <!-- Points progress bar -->
        <div class="progress-wrapper" id="progress-points">
          <div class="progress-header">
            <span class="progress-label">Points Progress</span>
            <span><span class="progress-value">0 pts</span> / <span class="progress-goal">300 target</span></span>
          </div>
          <div class="progress-bar-track">
            <div class="progress-bar-fill" style="width:0%"></div>
          </div>
        </div>
      </div>

      <!-- Metric cards -->
      <div class="card">
        <div class="card-title"><span class="icon">📊</span> Weekly Metrics</div>

        <div class="progress-wrapper" id="progress-steps">
          <div class="progress-header">
            <span class="progress-label">👟 Steps (Daily Average)</span>
            <span><span class="progress-value">0 avg</span> / <span class="progress-goal">6000 goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>

        <div class="progress-wrapper" id="progress-sleep">
          <div class="progress-header">
            <span class="progress-label">😴 Sleep (Daily Average)</span>
            <span><span class="progress-value">0h avg</span> / <span class="progress-goal">7h goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>

        <div class="progress-wrapper" id="progress-meals">
          <div class="progress-header">
            <span class="progress-label">🥗 Clean Meals (Weekly Total)</span>
            <span><span class="progress-value">0 meals</span> / <span class="progress-goal">14 goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>
      </div>

      <!-- Week log table -->
      <div class="card mt-3" style="margin-top:1.5rem">
        <div class="card-title"><span class="icon">🗓️</span> Daily Log</div>
        <div class="table-wrapper">
          <table class="day-table">
            <thead>
              <tr>
                <th>Day</th>
                <th>Steps</th>
                <th>Sleep</th>
                <th>Water</th>
                <th>Meals</th>
                <th>Points</th>
                <th>Activities</th>
              </tr>
            </thead>
            <tbody id="week-table-body">
              <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:1.5rem">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<div id="toast-container"></div>
<script src="js/app.js"></script>
<script src="js/dashboard.js"></script>
</body>
</html>
