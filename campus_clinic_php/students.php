<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Student Records";

/** Read and validate the student form. Returns [data, errors]. */
function student_input() {
    $d = [
        "student_no" => trim((string)($_POST["student_no"] ?? "")),
        "full_name"  => trim((string)($_POST["full_name"] ?? "")),
        "college"    => trim((string)($_POST["college"] ?? "")),
        "course"     => trim((string)($_POST["course"] ?? "")),
        "year_level" => trim((string)($_POST["year_level"] ?? "")),
        "contact_no" => trim((string)($_POST["contact_no"] ?? "")),
    ];

    $errors = [];

    if ($d["student_no"] === "" || $d["full_name"] === "") {
        $errors[] = "Student number and full name are required.";
    }

    $limits = ["student_no" => 50, "full_name" => 120, "college" => 120, "course" => 120, "year_level" => 30, "contact_no" => 50];
    foreach ($limits as $field => $max) {
        if (mb_strlen($d[$field]) > $max) {
            $errors[] = ucfirst(str_replace("_", " ", $field)) . " is too long (max {$max} characters).";
        }
    }

    if ($d["contact_no"] !== "" && !preg_match('/^[0-9+\-\s()]{7,50}$/', $d["contact_no"])) {
        $errors[] = "Contact number can only contain digits, spaces, + - ( ) and must be at least 7 characters.";
    }

    return [$d, $errors];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $user = $_SESSION["user"];

    if ($action === "add") {
        [$d, $errors] = student_input();

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->prepare("INSERT INTO students(student_no,full_name,college,course,year_level,contact_no) VALUES(?,?,?,?,?,?)")
                    ->execute([$d["student_no"], $d["full_name"], $d["college"], $d["course"], $d["year_level"], $d["contact_no"]]);
                record_activity($user["id"], $user["username"], "create_student", "Added student: " . $d["full_name"]);
                flash("success", "Student record added.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") { // duplicate student_no
                    flash("error", "A student with number {$d['student_no']} already exists.");
                } else {
                    error_log("Add student failed: " . $e->getMessage());
                    flash("error", "Could not add the student record.");
                }
            }
        }
    } elseif ($action === "edit") {
        $id = (int)($_POST["id"] ?? 0);
        [$d, $errors] = student_input();
        if ($id < 1) {
            $errors[] = "Student not found.";
        }

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->prepare("UPDATE students SET student_no=?, full_name=?, college=?, course=?, year_level=?, contact_no=? WHERE id=?")
                    ->execute([$d["student_no"], $d["full_name"], $d["college"], $d["course"], $d["year_level"], $d["contact_no"], $id]);
                record_activity($user["id"], $user["username"], "edit_student", "Updated student ID {$id}");
                flash("success", "Student record updated.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") {
                    flash("error", "Another student already uses number {$d['student_no']}.");
                } else {
                    error_log("Edit student failed: " . $e->getMessage());
                    flash("error", "Could not update the student record.");
                }
            }
        }
    } elseif ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);

        if (!is_admin()) {
            flash("error", "Only an administrator can delete student records.");
        } elseif ($id < 1) {
            flash("error", "Student not found.");
        } else {
            try {
                $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
                record_activity($user["id"], $user["username"], "delete_student", "Deleted student ID {$id}");
                flash("success", "Student record deleted.");
            } catch (Throwable $e) {
                error_log("Delete student failed: " . $e->getMessage());
                flash("error", "Could not delete the student record. Students with consultation history must be retained.");
            }
        }
    }

    redirect("students.php"); // refreshing the page no longer repeats the action
}

$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel">
<div class="panel-head">
    <h2>Students</h2>
    <input type="search" id="student-search" class="search-input" aria-label="Search students by name or number" placeholder="Search by name or student number">
</div>
<table id="student-table">
<thead>
<tr><th>Student No.</th><th>Name</th><th>College</th><th>Course</th><th>Year</th><th>Contact</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($students as $s): ?>
<tr>
    <td><?= h($s["student_no"]) ?><br><small>Record ID: <?= (int)$s["id"] ?></small><br><a href="assessments.php?student_id=<?= (int)$s["id"] ?>">Assessment / visit history</a></td>
    <td><?= h($s["full_name"]) ?></td>
    <td><?= h($s["college"]) ?></td>
    <td><?= h($s["course"]) ?></td>
    <td><?= h($s["year_level"]) ?></td>
    <td><?= h($s["contact_no"]) ?></td>
    <td>
        <div class="action-stack">
            <button type="button" class="btn small secondary"
                onclick="document.getElementById('edit-<?= (int)$s["id"] ?>').showModal()">Edit</button>
            <?php if (is_admin()): ?>
            <form method="post" onsubmit="return confirm('Delete this student record?')">
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
                    <label>Student No.<input type="text" name="student_no" value="<?= h($s["student_no"]) ?>" maxlength="50" required></label>
                    <label>Full Name<input type="text" name="full_name" value="<?= h($s["full_name"]) ?>" maxlength="120" required></label>
                    <label>College<input type="text" name="college" value="<?= h($s["college"]) ?>" maxlength="120"></label>
                    <label>Course<input type="text" name="course" value="<?= h($s["course"]) ?>" maxlength="120"></label>
                    <label>Year Level<input type="text" name="year_level" value="<?= h($s["year_level"]) ?>" maxlength="30"></label>
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

<section class="panel">
<details class="form-disclosure" id="add-student">
<summary>Add Student</summary>
<form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label>Student No.<input name="student_no" maxlength="50" required></label>
    <label>Full Name<input name="full_name" maxlength="120" required></label>
    <label>College<input name="college" maxlength="120"></label>
    <label>Course<input name="course" maxlength="120"></label>
    <label>Year Level<input name="year_level" maxlength="30"></label>
    <label>Contact No.<input name="contact_no" maxlength="50"></label>
    <div><button class="btn primary">Save Student</button></div>
</form>
</details>
</section>

<script>
// Search only the visible data columns (student no, name, college, course),
// not the hidden edit dialog text.
document.getElementById("student-search").addEventListener("input", function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll("#student-table tbody tr").forEach(function (row) {
        var text = [0, 1, 2, 3].map(function (i) { return row.cells[i].textContent; }).join(" ").toLowerCase();
        row.style.display = text.indexOf(q) > -1 ? "" : "none";
    });
});
</script>
<?php include __DIR__ . "/includes/footer.php"; ?>