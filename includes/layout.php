<?php
// layout.php — shared sidebar header
// Requires: $pageTitle (string), $activePage ('dashboard'|'sync'|'dbcompare')
// Usage: include at top of page after <?php, then call layoutFooter() at bottom

function layoutHeader($pageTitle, $activePage) {
    $pages = [
        'dashboard' => ['href' => 'dashboard.php', 'icon' => 'bi-grid-1x2-fill',      'label' => 'Dashboard'],
        'sync'      => ['href' => 'index.php',     'icon' => 'bi-cloud-upload-fill',  'label' => 'ส่งข้อมูล'],
        'settings'  => ['href' => 'settings.php',  'icon' => 'bi-gear-fill',          'label' => 'ตั้งค่า'],
        'guide'     => ['href' => 'guide.php',     'icon' => 'bi-question-circle-fill','label' => 'วิธีการใช้งาน'],
    ];
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> — Smog-Epinorth</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,400;0,500;0,600;0,700;1,400;1,700&display=swap" rel="stylesheet">
<style>
:root {
    /* ─── Typography (CK spec) — Sarabun (Google Fonts) ─── */
    --font-family: 'Sarabun', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
    --font-size-xs:   13px;
    --font-size-sm:   15px;
    --font-size-md:   16px;
    --font-weight-base: 400;
    --line-height-base: 26px;
    --font-weight-heading: 700;

    /* ─── Color tokens (CK spec) ─── */
    --color-text-primary:   #000000;
    --color-text-secondary: #475569;
    --color-text-tertiary:  #64748b;
    --color-text-inverse:   #1d4ed8;
    --color-surface-muted:  #ffffff;
    --color-surface-raised: #f8fafc;
    --color-surface-strong: #eff6ff;

    /* ─── Spacing scale (CK spec) ─── */
    --space-1: 2px;
    --space-2: 8px;
    --space-3: 10px;
    --space-4: 12px;
    --space-5: 16px;
    --space-6: 20px;
    --space-7: 24px;
    --space-8: 32px;

    /* ─── Radius (CK spec) ─── */
    --radius-xs: 12px;
    --radius-sm: 16px;

    /* ─── Shadow (CK spec) ─── */
    --shadow-1: rgba(0,0,0,0) 0 0 0 0, rgba(0,0,0,0) 0 0 0 0, rgba(0,0,0,0) 0 0 0 0,
                rgb(241,245,249) 0 0 0 1px,
                rgba(0,0,0,.1) 0 1px 3px 0,
                rgba(0,0,0,.1) 0 1px 2px -1px;

    /* ─── Motion (CK spec) ─── */
    --motion-instant: 150ms;
    --motion-fast:    300ms;
    --motion-easing:  cubic-bezier(.4, 0, .2, 1);

    /* ─── Legacy / app-specific ─── */
    --sidebar-bg:            #0f172a;
    --sidebar-hover:         #1e293b;
    --sidebar-active:        var(--color-text-inverse);
    --sidebar-text:          #94a3b8;
    --sidebar-text-active:   #ffffff;
    --topbar-h:              64px;
    --sidebar-w:             250px;
    --primary:               var(--color-text-inverse);
    --primary-hover:         #1e40af;
}
* { box-sizing: border-box; }
html, body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
body {
    margin: 0;
    background: var(--color-surface-strong);
    min-height: 100vh;
    font-family: var(--font-family);
    font-weight: var(--font-weight-base);
    font-size: var(--font-size-md);
    line-height: var(--line-height-base);
    color: var(--color-text-primary);
}

/* ─── Focus-visible (WCAG 2.2 AA) ─── */
*:focus { outline: none; }
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[tabindex]:focus-visible {
    outline: 2px solid var(--color-text-inverse);
    outline-offset: 2px;
    border-radius: var(--radius-xs);
}
.form-control:focus-visible, .form-select:focus-visible {
    outline: none;
    border-color: var(--color-text-inverse);
    box-shadow: 0 0 0 3px rgba(29,78,216,.18);
}
/* Respect user's reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
}

/* Sidebar */
.sidebar {
    position: fixed; top: 0; left: 0; bottom: 0;
    width: var(--sidebar-w);
    background: var(--sidebar-bg);
    display: flex; flex-direction: column;
    z-index: 100;
    box-shadow: 1px 0 3px rgba(0,0,0,.04);
}
.sidebar-brand {
    padding: 22px 22px 18px;
    border-bottom: 1px solid rgba(255,255,255,.06);
    display: flex; align-items: center; gap: 12px;
}
.sidebar-brand .brand-logo {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
    box-shadow: 0 4px 12px rgba(59,130,246,.35);
}
.sidebar-brand .brand-text { flex: 1; min-width: 0; }
.sidebar-brand .brand-name {
    font-size: 16px; font-weight: var(--font-weight-heading); color: #fff;
    letter-spacing: -0.01em; line-height: 1.2;
}
.sidebar-brand .brand-sub {
    font-size: 12px; color: var(--sidebar-text); margin-top: 3px;
    font-weight: 400;
}
.sidebar-nav { flex: 1; padding: 16px 0; overflow-y: auto; }
.nav-label {
    font-size: 10px; font-weight: 600; letter-spacing: 1.2px;
    color: #475569; text-transform: uppercase;
    padding: 18px 22px 8px;
}
.nav-item a {
    display: flex; align-items: center; gap: 14px;
    padding: 11px 22px; color: var(--sidebar-text);
    text-decoration: none; font-size: 15px; font-weight: 500;
    transition: all .18s ease;
    border-left: 3px solid transparent;
    position: relative;
}
.nav-item a:hover { background: var(--sidebar-hover); color: #e2e8f0; }
.nav-item a.active {
    background: linear-gradient(90deg, rgba(59,130,246,.18) 0%, rgba(59,130,246,.02) 100%);
    color: var(--sidebar-text-active);
    border-left-color: var(--sidebar-active);
    font-weight: 600;
}
.nav-item a .bi { font-size: 17px; width: 20px; text-align: center; }
.sidebar-footer {
    padding: 16px 22px;
    border-top: 1px solid rgba(255,255,255,.06);
    font-size: 11px; color: #475569;
    display: flex; align-items: center; gap: 6px;
}
.sidebar-footer .status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px rgba(16,185,129,.6);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

/* Main layout */
.main-wrap {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex; flex-direction: column;
}
.topbar {
    height: var(--topbar-h);
    background: var(--color-surface-muted);
    border-bottom: 1px solid var(--color-surface-raised);
    display: flex; align-items: center;
    padding: 0 var(--space-8);
    justify-content: space-between;
    position: sticky; top: 0; z-index: 50;
}
.topbar-title {
    font-size: 22px; font-weight: var(--font-weight-heading); color: var(--color-text-primary);
    letter-spacing: -0.01em;
}
.topbar-date {
    font-size: var(--font-size-sm); color: var(--color-text-secondary); font-weight: 500;
    padding: var(--space-2) var(--space-4);
    background: var(--color-surface-raised);
    border-radius: var(--radius-xs);
}
.page-content { padding: var(--space-8); flex: 1; }

/* Cards */
.card {
    border: 0;
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-1);
    background: var(--color-surface-muted);
    transition: box-shadow var(--motion-fast) var(--motion-easing);
}
.card-header {
    border-radius: var(--radius-sm) var(--radius-sm) 0 0 !important;
    background: var(--color-surface-muted) !important;
    border-bottom: 1px solid var(--color-surface-raised) !important;
    padding: var(--space-5) var(--space-6) !important;
}
.card-body { padding: var(--space-6) !important; }
.card-header h6 {
    font-size: 16px !important;
    font-weight: var(--font-weight-heading) !important;
    letter-spacing: -0.01em;
}

/* Stat cards */
.stat-card {
    border-radius: var(--radius-sm); padding: var(--space-6);
    border: 0;
    background: var(--color-surface-muted);
    box-shadow: var(--shadow-1);
    transition: box-shadow var(--motion-fast) var(--motion-easing);
    position: relative; overflow: hidden;
}
.stat-card .stat-icon {
    width: 44px; height: 44px; border-radius: var(--radius-xs);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}
.stat-card .stat-val {
    font-size: 30px; font-weight: var(--font-weight-heading); color: var(--color-text-primary);
    margin-top: var(--space-4); letter-spacing: -0.02em;
    font-feature-settings: 'tnum';
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: var(--font-size-sm); color: var(--color-text-tertiary);
    font-weight: 500; margin-top: var(--space-1);
}

/* Buttons */
.btn {
    font-family: var(--font-family);
    font-weight: 500;
    font-size: var(--font-size-sm);
    border-radius: var(--radius-xs);
    padding: var(--space-3) var(--space-5);
    transition: background-color var(--motion-instant) var(--motion-easing),
                color var(--motion-instant) var(--motion-easing);
    min-height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-primary {
    background: var(--color-text-inverse);
    border-color: var(--color-text-inverse);
    color: #fff;
}
.btn-primary:hover:not(:disabled) {
    background: var(--primary-hover);
    border-color: var(--primary-hover);
}
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline-primary { color: var(--color-text-inverse); border-color: var(--color-text-inverse); }
.btn-outline-primary:hover { background: var(--color-text-inverse); }

/* Form controls */
.form-control, .form-select, .input-group-text {
    font-family: var(--font-family);
    border-radius: var(--radius-xs);
    border-color: #e2e8f0;
    font-size: var(--font-size-sm);
    padding: var(--space-3) var(--space-4);
    min-height: 40px;
}
.form-control:disabled { background: var(--color-surface-raised); cursor: not-allowed; }
.input-group .form-control { border-radius: 0 var(--radius-xs) var(--radius-xs) 0; }
.input-group .input-group-text {
    border-radius: var(--radius-xs) 0 0 var(--radius-xs);
    background: var(--color-surface-raised);
    color: var(--color-text-secondary);
    border-color: #e2e8f0;
}
.form-label {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    font-weight: 500;
    margin-bottom: var(--space-2);
}

/* Table */
.table th {
    font-size: var(--font-size-xs); font-weight: 600;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--color-text-tertiary);
    border-bottom: 1px solid var(--color-surface-raised);
    padding: var(--space-4);
}
.table td {
    font-size: var(--font-size-sm); vertical-align: middle;
    border-color: var(--color-surface-raised);
    padding: var(--space-5) var(--space-4);
}
.table tbody tr { transition: background var(--motion-instant) var(--motion-easing); }
.table tbody tr:hover { background: var(--color-surface-strong); }
.table-light { --bs-table-bg: var(--color-surface-raised); }

/* Progress */
.progress { border-radius: 8px; background: #f1f5f9; }
.progress-bar { font-family: var(--font-th); }

/* Error panel — beautiful */
.error-panel {
    display: flex; gap: 18px;
    padding: 22px 24px;
    background: linear-gradient(135deg, #fef2f2 0%, #fef7f0 100%);
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 12px;
}
.error-panel .error-icon {
    width: 48px; height: 48px; flex-shrink: 0;
    background: #fee2e2; color: #dc2626;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
}
.error-panel .error-content { flex: 1; }
.error-panel .error-title {
    font-size: 16px; font-weight: 700; color: #991b1b;
    margin-bottom: 4px; letter-spacing: -0.01em;
}
.error-panel .error-reason {
    font-size: 14px; color: #7f1d1d;
    margin-bottom: 14px;
}
.error-panel .error-check-title {
    font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .6px;
    color: #991b1b; margin-bottom: 8px;
}
.error-panel .error-check-list {
    margin: 0 0 12px 0; padding-left: 18px;
    font-size: 14px; color: #7f1d1d; line-height: 1.8;
}
.error-panel .error-check-list li { margin-bottom: 2px; }
.error-panel .error-raw {
    margin-top: 10px; font-size: 12px;
    border-top: 1px dashed #fca5a5; padding-top: 10px;
}
.error-panel .error-raw summary {
    cursor: pointer; color: #b91c1c; font-weight: 500;
    user-select: none;
}
.error-panel .error-raw summary:hover { color: #7f1d1d; }
.error-panel .error-raw code {
    display: block; margin-top: 8px;
    background: rgba(0,0,0,.04); padding: 10px 12px;
    border-radius: 6px; font-family: 'Consolas', monospace;
    font-size: 12px; color: #7f1d1d;
    word-break: break-all; white-space: pre-wrap;
}

/* Alert customization */
.alert {
    border-radius: var(--radius-xs);
    border: 0; font-size: var(--font-size-sm);
    padding: var(--space-4) var(--space-5);
}
.alert-info { background: var(--color-surface-strong); color: var(--color-text-inverse); }
.alert-success { background: #ecfdf5; color: #065f46; }
.alert-danger { background: #fef2f2; color: #991b1b; }
.alert-warning { background: #fffbeb; color: #92400e; }

/* Links */
a { color: var(--color-text-inverse); text-decoration: none; transition: color var(--motion-instant) var(--motion-easing); }
a:hover { color: var(--primary-hover); text-decoration: underline; }

/* Badge tokens */
.badge-success-soft { background: #dcfce7; color: #15803d; border-radius: var(--radius-xs); font-weight: 600; font-size: var(--font-size-xs); padding: var(--space-1) var(--space-3); }
.badge-danger-soft  { background: #fee2e2; color: #b91c1c; border-radius: var(--radius-xs); font-weight: 600; font-size: var(--font-size-xs); padding: var(--space-1) var(--space-3); }

/* Scrollbar */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ─── Dashboard polish (gradient + smooth) ─── */
.stat-card {
    background: linear-gradient(160deg, #ffffff 0%, #fbfdff 100%);
    transition: transform var(--motion-fast) var(--motion-easing),
                box-shadow var(--motion-fast) var(--motion-easing);
}
.stat-card::before {
    content: ''; position: absolute; inset: 0 0 auto 0; height: 3px;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    opacity: .9;
}
.stat-card.accent-success::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card.accent-info::before    { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
.stat-card.accent-warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -10px rgba(15,23,42,.22), var(--shadow-1);
}
.stat-card .stat-icon {
    box-shadow: 0 6px 14px -4px rgba(59,130,246,.35);
}
.card { transition: transform var(--motion-fast) var(--motion-easing), box-shadow var(--motion-fast) var(--motion-easing); }
.card:hover { box-shadow: 0 12px 28px -12px rgba(15,23,42,.18), var(--shadow-1); }

/* fade-in-up ตอนโหลด */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-in { animation: fadeInUp var(--motion-fast) var(--motion-easing) both; }
@media (prefers-reduced-motion: reduce) { .animate-in { animation: none; } }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo"><i class="bi bi-activity"></i></div>
        <div class="brand-text">
            <div class="brand-name">Smog-Epinorth</div>
            <div class="brand-sub">Data Sync Platform</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">เมนูหลัก</div>
        <?php foreach ($pages as $key => $page): ?>
        <div class="nav-item">
            <a href="<?php echo $page['href']; ?>" class="<?php echo $activePage === $key ? 'active' : ''; ?>">
                <i class="bi <?php echo $page['icon']; ?>"></i>
                <?php echo $page['label']; ?>
            </a>
        </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span>System Online · <?php echo date('Y'); ?></span>
    </div>
</aside>

<div class="main-wrap">
    <div class="topbar">
        <div class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></div>
        <div class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y', strtotime('now')); ?></div>
    </div>
    <div class="page-content">
<?php
}

function layoutFooter() {
    echo '</div></div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}
