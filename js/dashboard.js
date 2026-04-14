/**
 * dashboard.js – Dashboard logic
 * Depends on window.App (app.js)
 */
(function () {
    'use strict';

    let activitiesCatalog = {};   // { name: {unit, factor, category} }
    let currentWeekDays   = {};   // { 'YYYY-MM-DD': dayData|null }
    let pendingActivities = [];   // activities added in the form but not yet saved
    let userGoals         = {};
    let userProfile       = {};

    /* ── BMR calculation (Revised Harris-Benedict, 1984) ── */
    function calculateBMR(profile) {
        const w = parseFloat(profile.weight) || 70;
        const h = parseFloat(profile.height) || 170;
        const a = parseFloat(profile.age)    || 30;
        const g = profile.gender || 'm';

        if (g === 'f') {
            return 447.593 + (9.247 * w) + (3.098 * h) - (4.330 * a);
        }
        // male
        return 88.362 + (13.397 * w) + (4.799 * h) - (5.677 * a);
    }

    function getMealThreshold(bmr) {
        return Math.round(bmr / 3);
    }

    /* ── Points calculation ── */
    function calculatePoints(activities) {
        let total = 0;
        (activities || []).forEach(function (act) {
            const catalog = activitiesCatalog[act.name];
            if (catalog) {
                total += (parseFloat(act.quantity) || 0) * catalog.factor;
            } else if (act.factor) {
                total += (parseFloat(act.quantity) || 0) * parseFloat(act.factor);
            }
        });
        return Math.round(total * 100) / 100;
    }

    /* ── ISO week helpers ── */
    function getIsoWeek(date) {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() + 4 - (d.getDay() || 7));
        const yearStart = new Date(d.getFullYear(), 0, 1);
        const week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return { year: d.getFullYear(), week: week };
    }

    function getWeekDates() {
        const today   = new Date();
        const dow     = today.getDay() || 7; // Mon=1
        const monday  = new Date(today);
        monday.setDate(today.getDate() - dow + 1);
        const dates = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(monday);
            d.setDate(monday.getDate() + i);
            dates.push(d.toISOString().split('T')[0]);
        }
        return dates;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    }

    /* ── Load activities catalog ── */
    function loadActivitiesCatalog() {
        return App.fetchJSON('./api/admin.php', { action: 'get_activities' })
            .then(function (data) {
                if (data.activities) {
                    activitiesCatalog = data.activities;
                    populateActivityDropdown();
                }
            })
            .catch(function () {});
    }

    function populateActivityDropdown() {
        const sel = document.getElementById('activity-select');
        if (!sel) return;

        // Group by category
        const categories = {};
        Object.entries(activitiesCatalog).forEach(function ([name, info]) {
            const cat = info.category || 'Other';
            if (!categories[cat]) categories[cat] = [];
            categories[cat].push({ name, ...info });
        });

        sel.innerHTML = '<option value="">— Select Activity —</option>';

        Object.keys(categories).sort().forEach(function (cat) {
            const group = document.createElement('optgroup');
            group.label = cat;
            categories[cat].sort(function (a, b) {
                return a.name.localeCompare(b.name);
            }).forEach(function (act) {
                const opt = document.createElement('option');
                opt.value = act.name;
                opt.textContent = act.name + ' (' + act.unit + ')';
                group.appendChild(opt);
            });
            sel.appendChild(group);
        });
    }

    /* ── Load week data ── */
    function loadWeekData() {
        const isoInfo = getIsoWeek(new Date());
        const weekLabel = document.getElementById('week-label');
        if (weekLabel) {
            weekLabel.innerHTML = 'Week <strong>' + isoInfo.week + '</strong>, ' + isoInfo.year;
        }

        return App.fetchJSON('./api/log.php', { action: 'get_week' })
            .then(function (data) {
                if (data.days) {
                    currentWeekDays = data.days;
                    updateProgressBars();
                    renderWeekTable();
                }
            })
            .catch(function () {
                App.showToast('Could not load week data', 'error');
            });
    }

    /* ── Progress bars ── */
    function updateProgressBars() {
        const days = Object.values(currentWeekDays).filter(Boolean);
        const goals = userGoals;

        // Steps – average
        const stepsAvg = days.length
            ? days.reduce(function (s, d) { return s + (d.steps || 0); }, 0) / days.length
            : 0;
        const stepsGoal = parseFloat(goals.avg_steps) || 6000;
        setProgressBar('progress-steps', stepsAvg, stepsGoal,
            Math.round(stepsAvg) + ' avg', Math.round(stepsGoal) + ' goal');

        // Sleep – average
        const sleepAvg = days.length
            ? days.reduce(function (s, d) { return s + (d.sleep || 0); }, 0) / days.length
            : 0;
        const sleepGoal = parseFloat(goals.sleep_goal) || 7;
        setProgressBar('progress-sleep', sleepAvg, sleepGoal,
            sleepAvg.toFixed(1) + 'h avg', sleepGoal + 'h goal');

        // Clean meals – total
        const mealsTotal = days.reduce(function (s, d) { return s + (d.meals || 0); }, 0);
        const mealsGoal  = parseFloat(goals.clean_meals_goal) || 14;
        setProgressBar('progress-meals', mealsTotal, mealsGoal,
            mealsTotal + ' meals', mealsGoal + ' goal');

        // Activity points
        let totalPoints = 0;
        Object.values(currentWeekDays).forEach(function (day) {
            if (day) totalPoints += calculatePoints(day.activities);
        });
        totalPoints = Math.round(totalPoints * 100) / 100;

        const workoutHoursGoal = parseFloat(goals.workout_hours) || 5;
        const pct = workoutHoursGoal > 0
            ? Math.min(Math.round((totalPoints / (workoutHoursGoal * 60)) * 100), 999)
            : 0;

        const ptsEl  = document.getElementById('total-points');
        const pctEl  = document.getElementById('points-pct');
        if (ptsEl) ptsEl.textContent = totalPoints.toFixed(1);
        if (pctEl) pctEl.textContent = pct + '% of target';

        setProgressBar('progress-points', totalPoints, workoutHoursGoal * 60,
            totalPoints.toFixed(1) + ' pts', Math.round(workoutHoursGoal * 60) + ' target');
    }

    function setProgressBar(id, value, goal, valueLbl, goalLbl) {
        const wrapper = document.getElementById(id);
        if (!wrapper) return;
        const fill  = wrapper.querySelector('.progress-bar-fill');
        const vLbl  = wrapper.querySelector('.progress-value');
        const gLbl  = wrapper.querySelector('.progress-goal');

        const pct = goal > 0 ? Math.min((value / goal) * 100, 100) : 0;
        if (fill) fill.style.width = pct + '%';
        if (vLbl) vLbl.textContent = valueLbl;
        if (gLbl) gLbl.textContent = goalLbl;
    }

    /* ── Week table ── */
    function renderWeekTable() {
        const tbody = document.getElementById('week-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';

        const sortedDates = Object.keys(currentWeekDays).sort();
        const today = new Date().toISOString().split('T')[0];

        sortedDates.forEach(function (date) {
            const day = currentWeekDays[date];
            const tr  = document.createElement('tr');
            if (date === today) tr.style.background = 'rgba(var(--accent-rgb,233,69,96),.06)';

            const pts = day ? calculatePoints(day.activities) : 0;
            const actNames = day && day.activities && day.activities.length
                ? day.activities.map(function (a) { return a.name; }).join(', ')
                : '—';

            tr.innerHTML =
                '<td>' + formatDate(date) + (date === today ? ' <span style="color:var(--accent);font-size:.75rem">(today)</span>' : '') + '</td>' +
                '<td>' + (day ? (day.steps || 0).toLocaleString() : '<span class="no-data">—</span>') + '</td>' +
                '<td>' + (day ? (day.sleep || 0) + 'h' : '<span class="no-data">—</span>') + '</td>' +
                '<td>' + (day ? (day.water || 0) + ' gl' : '<span class="no-data">—</span>') + '</td>' +
                '<td>' + (day ? (day.meals || 0) : '<span class="no-data">—</span>') + '</td>' +
                '<td>' + (day ? '<span style="color:var(--accent);font-weight:600">' + pts.toFixed(1) + '</span>' : '<span class="no-data">—</span>') + '</td>' +
                '<td style="font-size:.8rem;color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + App.escapeHtml(actNames) + '">' + App.escapeHtml(actNames) + '</td>';

            tbody.appendChild(tr);
        });
    }

    /* ── Pending activities list ── */
    function renderPendingActivities() {
        const list = document.getElementById('pending-activities');
        if (!list) return;

        if (pendingActivities.length === 0) {
            list.innerHTML = '<li style="color:var(--text-muted);font-size:.85rem;padding:.25rem 0">No activities added yet.</li>';
            updatePreviewPoints();
            return;
        }

        list.innerHTML = '';
        pendingActivities.forEach(function (act, idx) {
            const pts  = calculatePoints([act]);
            const li   = document.createElement('li');
            li.className = 'activity-list-item';
            li.innerHTML =
                '<span class="act-name">' + App.escapeHtml(act.name) + '</span>' +
                '<span class="act-detail">' + act.quantity + ' ' + App.escapeHtml(act.unit) + '</span>' +
                '<span class="act-pts">+' + pts.toFixed(2) + ' pts</span>' +
                '<button class="btn btn-sm btn-danger remove-act" data-idx="' + idx + '" title="Remove">&times;</button>';
            list.appendChild(li);
        });

        list.querySelectorAll('.remove-act').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingActivities.splice(parseInt(this.dataset.idx, 10), 1);
                renderPendingActivities();
            });
        });

        updatePreviewPoints();
    }

    function updatePreviewPoints() {
        const previewEl = document.getElementById('preview-points');
        if (!previewEl) return;
        const pts = calculatePoints(pendingActivities);
        previewEl.textContent = pts.toFixed(2) + ' pts (unsaved)';
    }

    /* ── Save day ── */
    function saveDay(date, data) {
        return App.fetchJSON('./api/log.php', Object.assign({ action: 'save_day', date: date }, data));
    }

    /* ── Init dashboard ── */
    function init() {
        // Set today's date as default
        const datePicker = document.getElementById('log-date');
        if (datePicker) {
            datePicker.value = new Date().toISOString().split('T')[0];
            datePicker.max   = new Date().toISOString().split('T')[0];
        }

        // Load catalog, then week data
        App.initAuth({
            onLogin: function (data) {
                userGoals   = data.goals   || {};
                userProfile = data.profile || {};
                loadActivitiesCatalog().then(function () {
                    loadWeekData();
                });

                App.startLogoutTimer();
            }
        });

        // Add activity button
        const addActBtn = document.getElementById('add-activity-btn');
        if (addActBtn) {
            addActBtn.addEventListener('click', function () {
                const sel = document.getElementById('activity-select');
                const qty = document.getElementById('activity-qty');
                if (!sel || !qty) return;

                const name = sel.value;
                const quantity = parseFloat(qty.value);

                if (!name) { App.showToast('Please select an activity', 'warning'); return; }
                if (!quantity || quantity <= 0) { App.showToast('Please enter a valid quantity', 'warning'); return; }

                const catalog = activitiesCatalog[name];
                if (!catalog) { App.showToast('Activity not found in catalog', 'error'); return; }

                pendingActivities.push({
                    name:     name,
                    quantity: quantity,
                    unit:     catalog.unit,
                    factor:   catalog.factor,
                    category: catalog.category,
                });

                renderPendingActivities();
                sel.value = '';
                qty.value = '';
            });
        }

        const waterIncBtn = document.getElementById('water-inc-btn');
        if (waterIncBtn) {
            waterIncBtn.addEventListener('click', function () {
                const input = document.getElementById('log-water');
                if (!input) return;
                const current = parseFloat(input.value) || 0;
                input.value = ((current + 1).toFixed(1)).replace(/\.0$/, '');
            });
        }

        const mealsIncBtn = document.getElementById('meals-inc-btn');
        if (mealsIncBtn) {
            mealsIncBtn.addEventListener('click', function () {
                const input = document.getElementById('log-meals');
                if (!input) return;
                const current = parseInt(input.value || '0', 10) || 0;
                input.value = current + 1;
            });
        }

        // Save day form
        const saveDayBtn = document.getElementById('save-day-btn');
        if (saveDayBtn) {
            saveDayBtn.addEventListener('click', function () {
                const date  = document.getElementById('log-date')?.value;
                const water = parseFloat(document.getElementById('log-water')?.value || 0);
                const sleep = parseFloat(document.getElementById('log-sleep')?.value || 0);
                const meals = parseInt(document.getElementById('log-meals')?.value || 0, 10);
                const steps = parseInt(document.getElementById('log-steps')?.value || 0, 10);

                if (!date) { App.showToast('Please select a date', 'warning'); return; }

                saveDayBtn.disabled = true;
                saveDayBtn.innerHTML = '<span class="spinner"></span> Saving…';

                saveDay(date, {
                    water:      water,
                    sleep:      sleep,
                    meals:      meals,
                    steps:      steps,
                    activities: pendingActivities,
                }).then(function (resp) {
                    if (resp.success) {
                        App.showToast('Day saved successfully!', 'success');
                        pendingActivities = [];
                        renderPendingActivities();
                        loadWeekData();
                    } else {
                        App.showToast(resp.error || 'Failed to save', 'error');
                    }
                }).catch(function () {
                    App.showToast('Network error saving day', 'error');
                }).finally(function () {
                    saveDayBtn.disabled = false;
                    saveDayBtn.innerHTML = '💾 Save Day';
                });
            });
        }

        // Load existing day when date changes
        const datePicker2 = document.getElementById('log-date');
        if (datePicker2) {
            datePicker2.addEventListener('change', function () {
                const d = this.value;
                if (currentWeekDays[d]) {
                    const day = currentWeekDays[d];
                    document.getElementById('log-water').value  = day.water  || '';
                    document.getElementById('log-sleep').value  = day.sleep  || '';
                    document.getElementById('log-meals').value  = day.meals  || '';
                    document.getElementById('log-steps').value  = day.steps  || '';
                    pendingActivities = (day.activities || []).slice();
                    renderPendingActivities();
                } else {
                    document.getElementById('log-water').value  = '';
                    document.getElementById('log-sleep').value  = '';
                    document.getElementById('log-meals').value  = '';
                    document.getElementById('log-steps').value  = '';
                    pendingActivities = [];
                    renderPendingActivities();
                }
            });
        }
    }

    /* ── Expose ── */
    window.Dashboard = {
        init:               init,
        loadWeekData:       loadWeekData,
        saveDay:            saveDay,
        calculatePoints:    calculatePoints,
        updateProgressBars: updateProgressBars,
        calculateBMR:       calculateBMR,
        getMealThreshold:   getMealThreshold,
    };

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
