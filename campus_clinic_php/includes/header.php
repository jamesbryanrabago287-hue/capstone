<?php require_once __DIR__ . "/auth.php"; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title ?? "Campus Clinic System") ?> | ISAT U</title>
<link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/style.css') ?>">
<style>
/* Logout button: matches the other sidebar items (no white browser-default box) */
.logout-form { margin: 0; }
.sidebar button.nav-logout {
  -webkit-appearance: none;
  appearance: none;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin: 14px 0 0;
  padding: 16px 12px 11px !important;
  background: transparent !important;
  border: 0 !important;
  border-top: 1px solid rgba(255,255,255,0.12) !important;
  border-radius: 0 0 10px 10px !important;
  box-shadow: none !important;
  font-family: inherit;
  font-weight: 600;
  font-size: 0.93rem;
  text-align: left;
  color: #fff6d6 !important;
  cursor: pointer;
}
.sidebar button.nav-logout:hover { background: rgba(242,183,5,0.16) !important; }
@media (max-width: 900px) {
  .sidebar button.nav-logout { width: auto; margin: 0; white-space: nowrap; border-top: 0 !important; border-radius: 10px !important; padding: 11px 12px !important; }
}
</style>
<link rel="stylesheet" href="assets/staff-ui.css?v=<?= (int)filemtime(__DIR__ . '/../assets/staff-ui.css') ?>">
</head>
<body class="<?= isset($_SESSION['user']) ? 'staff-app' : '' ?>">
<?php if (isset($_SESSION["user"])): ?>
<?php
    $alert_count=count(medicine_attention_rows(clinic_stock_rows($pdo)));

    $current_page = basename($_SERVER["PHP_SELF"]);
    $nav_items = [
        ["dashboard.php", " ", "Dashboard"],
        ["medicines.php", " ", "Medicines"],
        ["transactions.php", " ", "Manage Inventory"],
        ["students.php", " ", "Students"],
        ["assessments.php", " ", "Pre-Assessments"],
        ["alerts.php", " ", "Alerts"],
        ["analytics.php", " ", "Decision Support"],
        ["reports.php", " ", "Reports"],
    ];
    if ($_SESSION["user"]["role"] === "admin") {
        $nav_items[] = ["users.php", " ", "Manage Accounts"];
    }
?>
<div class="layout">
<aside class="sidebar">
    <div class="brand">
        <img src="assets/isatu-logo.png" alt="ISAT U" class="brand-logo" onerror="this.style.display='none'">
        <div>Campus Clinic<span>ISAT U · Medicine Inventory</span></div>
    </div>
    <nav>
        <?php foreach ($nav_items as $item): ?>
            <a href="<?= $item[0] ?>" class="<?= $current_page === $item[0] ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="<?= h((['dashboard.php'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z','medicines.php'=>'M8 3h8v4H8z M7 7h10v14H7z M9 14h6 M12 11v6','transactions.php'=>'M3 7h17 M16 3l4 4-4 4 M21 17H4 M8 13l-4 4 4 4','students.php'=>'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M4 21v-3a8 8 0 0 1 16 0v3','assessments.php'=>'M8 4H5v17h14V4h-3 M8 3h8v4H8z M8 12h8 M8 16h6','alerts.php'=>'M12 3 2 21h20L12 3z M12 9v5 M12 17v1','analytics.php'=>'M4 3v18h17 M7 15l5-5 4 3 5-8','reports.php'=>'M5 3h10l4 4v14H5z M14 3v5h5 M8 12h8 M8 16h8','users.php'=>'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M6 6l2 2 M16 16l2 2 M6 18l2-2 M16 8l2-2 M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0'])[$item[0]] ?? '') ?>"></path></svg></span>
                <span class="nav-label"><?= h($item[2]) ?></span>
                <?php if ($item[0] === "alerts.php" && $alert_count > 0): ?>
                    <span class="notification-badge"><?= $alert_count ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <form method="post" action="logout.php" class="logout-form" onsubmit="return confirm('Are you sure you want to log out?');">
            <?= csrf_field() ?>
            <button type="submit" class="nav-logout">
                <span class="nav-icon"> </span>
                <span class="nav-label">Logout</span>
            </button>
        </form>
    </nav>
</aside>
<main class="content">
<header class="topbar">
    <div class="topbar-row">
        <div>
            <h1><?= h($page_title ?? "Campus Clinic System") ?></h1>
            <small><?= h($_SESSION["user"]["full_name"]) ?> · <?= h(ucfirst($_SESSION["user"]["role"])) ?></small>
        </div>
        <a href="alerts.php" class="bell-button" aria-label="Alerts">
            🔔
            <?php if ($alert_count > 0): ?><span class="bell-count"><?= $alert_count ?></span><?php endif; ?>
        </a>
    </div>
</header>
<?php endif; ?>
<?php if(isset($_SESSION['user'])):
$guides=[
 'dashboard.php'=>['Clinic overview','Check stock levels and alerts. Use the menu to record medicine movements, review student assessments or prepare reports.',null,null],
 'medicines.php'=>['Medicine records','Add or update medicine and batch details here. To change quantities, use Manage Inventory.','#add-medicine','Add medicine'],
 'transactions.php'=>['Record a stock movement','Receive deliveries, issue medicine, dispose of stock or correct an actual count. Every saved movement stays in history.','#record-movement','Record movement'],
 'students.php'=>['Student records','Find an official student record, update their details or open assessment and consultation history.','#add-student','Add student'],
 'assessments.php'=>['Review a submitted assessment','Find the student reference, verify identity when they arrive, then start or continue the consultation.',null,null],
 'consultation.php'=>['Complete the consultation','Verify the student ? record findings ? record any medicine issued ? complete the visit. Save findings before using the medicine form.',null,null],
 'alerts.php'=>['Stock and expiry alerts','Review medicines needing attention. Record disposal in Manage Inventory when expired stock is physically removed.',null,null],
 'analytics.php'=>['Reorder recommendations','Review stock and recent usage before deciding what to order. These suggestions support staff judgment.',null,null],
 'reports.php'=>['Prepare a procurement report','Choose the document and year, review quantities and costs, then preview or print. Check figures before using the document.',null,null],
 'users.php'=>['Staff accounts','Manage authorized accounts and access. Give each staff member their own account.',null,null]
];$guide=$guides[basename($_SERVER['PHP_SELF'])] ?? null;
if($guide): ?><section class="page-guide" aria-label="Page instructions"><div><strong><?= h($guide[0]) ?></strong><p><?= h($guide[1]) ?></p></div><?php if($guide[2]): ?><a class="btn primary" href="<?= h($guide[2]) ?>"><?= h($guide[3]) ?></a><?php endif; ?></section><?php endif; endif; ?>
