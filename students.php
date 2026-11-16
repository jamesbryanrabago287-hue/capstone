<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Patient Records";

require_once __DIR__.'/includes/patients.php';ensure_patient_schema($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $user = $_SESSION["user"];

    if ($action === "add") {
        [$d, $errors] = patient_input($_POST);

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->prepare("INSERT INTO students(student_no,full_name,college,course,year_level,contact_no,patient_type) VALUES(?,?,?,?,?,?,?)")
                    ->execute([$d["student_no"], $d["full_name"], $d["college"], $d["course"], $d["year_level"], $d["contact_no"], $d["patient_type"]]);
                record_activity($user["id"], $user["username"], "create_patient", "Added patient: " . $d["full_name"]);
                flash("success", "Patient record added.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") { // duplicate student_no
                    flash("error", "A patient with ID {$d['student_no']} already exists.");
                } else {
                    error_log("Add student failed: " . $e->getMessage());
                    flash("error", "Could not add the patient record.");
                }
            }
        }
    } elseif ($action === "edit") {
        $id = (int)($_POST["id"] ?? 0);
        [$d, $errors] = patient_input($_POST);
        if ($id < 1) {
            $errors[] = "Patient not found.";
        }

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->prepare("UPDATE students SET student_no=?, full_name=?, college=?, course=?, year_level=?, contact_no=?, patient_type=? WHERE id=?")
                    ->execute([$d["student_no"], $d["full_name"], $d["college"], $d["course"], $d["year_level"], $d["contact_no"], $d["patient_type"], $id]);
                record_activity($user["id"], $user["username"], "edit_patient", "Updated patient ID {$id}");
                flash("success", "Patient record updated.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") {
                    flash("error", "Another patient already uses ID {$d['student_no']}.");
                } else {
                    error_log("Edit student failed: " . $e->getMessage());
                    flash("error", "Could not update the patient record.");
                }
            }
        }
    } elseif ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);

        if (!is_admin()) {
            flash("error", "Only an administrator can delete patient records.");
        } elseif ($id < 1) {
            flash("error", "Patient not found.");
        } else {
            try {
                $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
                record_activity($user["id"], $user["username"], "delete_patient", "Deleted patient ID {$id}");
                flash("success", "Patient record deleted.");
            } catch (Throwable $e) {
                error_log("Delete student failed: " . $e->getMessage());
                flash("error", "Could not delete the patient record. Patients with consultation history must be retained.");
            }
        }
    }

    redirect("patients.php"); // refreshing the page no longer repeats the action
}

$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel">
<div class="panel-head student-list-head">
    <div class="list-heading-actions"><h2>Patients</h2><button type="button" class="btn primary" data-open-dialog="add-student">Add Patient</button></div>
    <input type="search" id="student-search" class="search-input" aria-label="Search patients by name or ID" placeholder="Search by name, ID or patient type">
</div>
<table id="student-table">
<thead>
<tr><th>Student / Employee ID</th><th>Type</th><th>Name</th><th>Department / College</th><th>Course</th><th>Year</th><th>Contact</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($students as $s): ?>
<tr>
    <td><?= h($s["student_no"] ?? "Not provided") ?><br><small>Record ID: <?= (int)$s["id"] ?></small><br><a href="patient_history.php?id=<?= (int)$s["id"] ?>">Visit history</a></td>
    <td><?= h($s['patient_type']) ?></td><td><?= h($s["full_name"]) ?></td>
    <td><?= h($s["college"]) ?></td>
    <td><?= h($s["course"]) ?></td>
    <td><?= h($s["year_level"]) ?></td>
    <td><?= h($s["contact_no"]) ?></td>
    <td>
        <div class="action-stack">
            <button type="button" class="btn small secondary"
                onclick="document.getElementById('edit-<?= (int)$s["id"] ?>').showModal()">Edit</button>
            <?php if (is_admin()): ?>
            <form method="post" onsubmit="return confirm('Delete this patient record?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s["id"] ?>">
                <button class="btn small danger" type="submit">Delete</button>
            </form>
            <?php endif; ?>
        </div>

        <dialog id="edit-<?= (int)$s["id"] ?>">
            <h3>Edit <?= h($s["full_name"]) ?></h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$s["id"] ?>">
                <div class="form-grid">
                    <?= patient_type_field($s['patient_type']) ?>
                    <label>Student / Employee ID<input type="text" name="student_no" value="<?= h($s["student_no"] ?? "") ?>" maxlength="50" required></label>
                    <label>Full Name<input type="text" name="full_name" value="<?= h($s["full_name"]) ?>" maxlength="120" required></label>
                    <label>Department / College<input type="text" name="college" value="<?= h($s["college"]) ?>" maxlength="120"></label>
                    <label data-student-field>Course<input type="text" name="course" value="<?= h($s["course"]) ?>" maxlength="120"></label>
                    <label data-student-field>Year Level<input type="text" name="year_level" value="<?= h($s["year_level"]) ?>" maxlength="30"></label>
                    <label>Contact No.<input type="text" name="contact_no" value="<?= h($s["contact_no"]) ?>" maxlength="50"></label>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </form>
        </dialog>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

<dialog id="add-student" class="record-dialog" aria-labelledby="add-student-title"><div class="record-dialog-heading"><h3 id="add-student-title">Add Patient</h3><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Close</button></div>
<form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <?= patient_type_field() ?>
    <label>Student / Employee ID<input name="student_no" maxlength="50" required></label>
    <label>Full Name<input name="full_name" maxlength="120" required></label>
    <label>Department / College<input name="college" maxlength="120"></label>
    <label data-student-field>Course<input name="course" maxlength="120"></label>
    <label data-student-field>Year Level<input name="year_level" maxlength="30"></label>
    <label>Contact No.<input name="contact_no" maxlength="50"></label>
    <div><button class="btn primary">Save Patient</button></div>
</form>
</dialog>

<script>
// Search only the visible data columns (student no, name, college, course),
// not the hidden edit dialog text.
document.getElementById("student-search").addEventListener("input", function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll("#student-table tbody tr").forEach(function (row) {
        var text = [0, 1, 2, 3, 4, 5].map(function (i) { return row.cells[i].textContent; }).join(" ").toLowerCase();
        row.style.display = text.indexOf(q) > -1 ? "" : "none";
    });
});
</script>
<script src="assets/patients.js?v=<?= filemtime(__DIR__.'/assets/patients.js') ?>" defer></script>
<?php include __DIR__ . "/includes/footer.php"; ?>