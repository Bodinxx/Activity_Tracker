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
  <button class="navbar-toggle" id="navbar-toggle" aria-expanded="false" aria-label="Toggle menu">☰</button>
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

    <!-- SECTION 1: Log Today (Entry Form) -->
    <div class="card">
      <div class="card-title"><span class="icon">📝</span> Log Today</div>

      <div class="form-row">
        <div class="form-group">
          <label for="log-date">Date</label>
          <input type="date" id="log-date" class="form-control">
        </div>
        <div class="form-group">
          <label for="log-time">Time</label>
          <input type="time" id="log-time" class="form-control">
        </div>
      </div>

      <div class="log-section-row">
        <div class="log-section">
          <div class="log-section-label">Sleep</div>
          <div class="log-section-content">
            <div class="form-group">
              <label for="sleep-hours">Hours</label>
              <input type="number" id="sleep-hours" class="form-control" placeholder="e.g. 7.5" min="0" max="24" step="0.25">
            </div>
            <button type="button" class="btn btn-secondary" id="sleep-add-btn">Log</button>
          </div>
        </div>

        <div class="log-section">
          <div class="log-section-label">Clean Meals</div>
          <div class="log-section-content">
            <div class="form-group">
              <label for="meal-count">Meals</label>
              <input type="number" id="meal-count" class="form-control" placeholder="e.g. 1" min="0" step="1">
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="meal-quick-btn">+1</button>
            <button type="button" class="btn btn-secondary" id="meal-add-btn">Log</button>
          </div>
        </div>

        <div class="log-section">
          <div class="log-section-label">Water</div>
          <div class="log-section-content">
            <div class="form-group">
              <label for="water-count">Glasses</label>
              <input type="number" id="water-count" class="form-control" placeholder="e.g. 1" min="0" step="0.5">
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="water-quick-btn">+1</button>
            <button type="button" class="btn btn-secondary" id="water-add-btn">Log</button>
          </div>
        </div>

        <div class="log-section">
          <div class="log-section-label">Steps</div>
          <div class="log-section-content">
            <div class="form-group">
              <label for="steps-count">Steps</label>
              <input type="number" id="steps-count" class="form-control" placeholder="e.g. 1000" min="0" step="50">
            </div>
            <button type="button" class="btn btn-secondary" id="steps-add-btn">Log</button>
          </div>
        </div>
      </div>

      <div class="log-section">
        <div class="log-section-label">Activity</div>
        <div class="form-row">
          <div class="form-group">
            <label for="activity-select">Activity</label>
            <select id="activity-select" class="form-control">
              <option value="">— Loading… —</option>
            </select>
          </div>
          <div class="form-group">
            <label for="activity-qty">Quantity</label>
            <input type="number" id="activity-qty" class="form-control" placeholder="Qty" min="0.1" step="0.1">
          </div>
          <div class="form-group">
            <label for="activity-unit">Unit</label>
            <input type="text" id="activity-unit" class="form-control" placeholder="Unit" disabled>
          </div>
        </div>
        <button type="button" class="btn btn-secondary btn-full" id="activity-add-btn">Log Activity</button>
      </div>

      <div class="form-group">
        <label for="entry-note">Note (optional)</label>
        <textarea id="entry-note" class="form-control" placeholder="e.g. Morning walk, light snack, felt energized"></textarea>
      </div>
    </div>

    <!-- SECTION 2 & 3: Week Label + Weekly Metrics (Compact) -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
        <div class="card-title" style="margin-bottom:0"><span class="icon">📅</span> <span id="week-label">Week 15, 2026</span></div>
      </div>

      <div class="points-total">
        <div class="points-number" id="total-points">0.0</div>
        <div class="points-label">Activity Points This Week</div>
        <div class="points-pct" id="points-pct">0% of target</div>
      </div>

      <div class="progress-wrapper" id="progress-points">
        <div class="progress-header">
          <span class="progress-label">Points Progress</span>
          <span><span class="progress-value">0 pts</span> / <span class="progress-goal">300 target</span></span>
        </div>
        <div class="progress-bar-track">
          <div class="progress-bar-fill" style="width:0%"></div>
        </div>
      </div>

      <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:1rem">
        <div class="card-title" style="margin-bottom:.75rem"><span class="icon">📊</span> Weekly Metrics</div>

        <div class="progress-wrapper" id="progress-steps">
          <div class="progress-header">
            <span class="progress-label">👟 Steps (Daily Avg)</span>
            <span><span class="progress-value">0 avg</span> / <span class="progress-goal">6000 goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>

        <div class="progress-wrapper" id="progress-sleep">
          <div class="progress-header">
            <span class="progress-label">😴 Sleep (Daily Avg)</span>
            <span><span class="progress-value">0h avg</span> / <span class="progress-goal">7h goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>

        <div class="progress-wrapper" id="progress-meals">
          <div class="progress-header">
            <span class="progress-label">🥗 Clean Meals (Weekly)</span>
            <span><span class="progress-value">0 meals</span> / <span class="progress-goal">14 goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>

        <div class="progress-wrapper" id="progress-water">
          <div class="progress-header">
            <span class="progress-label">💧 Water (Weekly)</span>
            <span><span class="progress-value">0 glasses</span> / <span class="progress-goal">56 goal</span></span>
          </div>
          <div class="progress-bar-track"><div class="progress-bar-fill" style="width:0%"></div></div>
        </div>
      </div>
    </div>

    <!-- SECTION 4: Daily Log (Aggregated by Day) -->
    <div class="card">
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

    <!-- SECTION 5: Day Breakdown (Individual Entries) -->
    <div class="card">
      <div class="card-title"><span class="icon">📋</span> Day Breakdown</div>

      <div class="form-group">
        <label for="detail-date">Select Date to View Entries</label>
        <input type="date" id="detail-date" class="form-control">
      </div>

      <div id="detail-summary" class="detail-summary">
        <p style="color:var(--text-muted);font-size:.85rem">Select a date to view individual logged entries.</p>
      </div>
    </div>

  </div>
</div>

<div id="toast-container"></div>
<script src="js/app.js"></script>
<script src="js/dashboard.js"></script>
</body>
</html>
