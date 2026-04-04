<?php
// edit_application.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid Request.");
}

$id = (int)$_GET['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch current record first to check for transitions
    $checkStmt = $pdo->prepare("SELECT form_type, entrance_roll_no FROM admission_inquiries WHERE id = ?");
    $checkStmt->execute([$id]);
    $current = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // --- Handle file uploads first ---
    $uploadDir = __DIR__ . '/assets/uploads/documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $allowed_images = ['image/jpeg', 'image/png', 'image/jpg'];
    $allowed_docs   = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

    $file_updates = [];

    if (isset($_FILES['pp_photo']) && $_FILES['pp_photo']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['pp_photo']['tmp_name']);
        if (in_array($mime, $allowed_images)) {
            $ext = pathinfo($_FILES['pp_photo']['name'], PATHINFO_EXTENSION);
            $fn  = 'pp_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['pp_photo']['tmp_name'], $uploadDir . $fn)) {
                $file_updates['pp_photo_path'] = 'assets/uploads/documents/' . $fn;
            }
        } else { $msg = 'Invalid photo format. JPG/PNG only.'; }
    }
    if (isset($_FILES['marksheet_doc']) && $_FILES['marksheet_doc']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['marksheet_doc']['tmp_name']);
        if (in_array($mime, $allowed_docs)) {
            $ext = pathinfo($_FILES['marksheet_doc']['name'], PATHINFO_EXTENSION);
            $fn  = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['marksheet_doc']['tmp_name'], $uploadDir . $fn)) {
                $file_updates['document_path'] = 'assets/uploads/documents/' . $fn;
            }
        } else { $msg = 'Invalid marksheet format. PDF/JPG/PNG only.'; }
    }
    if (isset($_FILES['birth_cert']) && $_FILES['birth_cert']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['birth_cert']['tmp_name']);
        if (in_array($mime, $allowed_docs)) {
            $ext = pathinfo($_FILES['birth_cert']['name'], PATHINFO_EXTENSION);
            $fn  = 'bc_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['birth_cert']['tmp_name'], $uploadDir . $fn)) {
                $file_updates['birth_cert_path'] = 'assets/uploads/documents/' . $fn;
            }
        } else { $msg = 'Invalid birth cert format. PDF/JPG/PNG only.'; }
    }
    finfo_close($finfo);

    // Collect all fields
    $fields = [
        'form_type', 'student_first_name', 'student_last_name', 'student_email', 'dob_bs', 'dob_ad', 'gender',
        'address_province', 'address_district', 'address_municipality', 'address_ward_village',
        'father_name', 'father_occupation', 'father_contact',
        'mother_name', 'mother_occupation', 'mother_contact',
        'local_guardian_name', 'guardian_contact', 'guardian_relation',
        'applied_class', 'faculty_id', 'optional_subject_1', 'optional_subject_2',
        'previous_school_name', 'previous_board', 'gpa_or_percentage', 'see_symbol_no',
        'status', 'schedule_id'
    ];
    
    $updateParams = [];
    $updateSql = [];
    foreach ($fields as $field) {
        $val = $_POST[$field] ?? null;
        if ($val === '') $val = null;
        $updateSql[] = "$field = ?";
        $updateParams[] = $val;
    }

    // Auto-generate roll number if switching from Inquiry -> Admission and it's missing
    $new_form_type = $_POST['form_type'] ?? 'Inquiry';
    if ($new_form_type === 'Admission' && empty($current['entrance_roll_no'])) {
        require_once __DIR__ . '/includes/functions.php';
        $new_roll = generateRollNumber($pdo);
        $updateSql[] = "entrance_roll_no = ?";
        $updateParams[] = $new_roll;
    }

    // Add file upload columns if any
    foreach ($file_updates as $col => $path) {
        $updateSql[] = "$col = ?";
        $updateParams[] = $path;
    }
    $updateParams[] = $id;
    
    if (empty($msg)) {
        $stmt = $pdo->prepare("UPDATE admission_inquiries SET " . implode(', ', $updateSql) . " WHERE id = ?");
        if ($stmt->execute($updateParams)) {
            $msg = "Application updated successfully.";
        } else {
            $msg = "Failed to update application.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM admission_inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inq) {
    die("Application not found.");
}

$faculties = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$classes = $pdo->query("SELECT * FROM class_seats ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $pdo->query("SELECT * FROM entrance_schedules ORDER BY exam_date ASC")->fetchAll(PDO::FETCH_ASSOC);

function val($inq, $field) {
    return htmlspecialchars((string)($inq[$field] ?? ''));
}
function sel($inq, $field, $val) {
    return ((string)($inq[$field] ?? '')) === ((string)$val) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application #<?php echo val($inq, 'id'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex">
    <?php include 'includes/admin_sidebar.php'; ?>
    <div class="flex-1 w-full p-6">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Edit Application / Inquiry</h2>
                <a href="view_application.php?id=<?php echo $id; ?>" class="text-emerald-600 font-semibold hover:underline">&larr; Back to View</a>
            </div>
            
            <?php if($msg): ?>
                <div class="bg-emerald-100 border border-emerald-200 text-emerald-800 font-medium p-3 rounded-lg mb-4"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Form Type</label>
                        <select name="form_type" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="Inquiry" <?php echo sel($inq, 'form_type', 'Inquiry'); ?>>Inquiry</option>
                            <option value="Admission" <?php echo sel($inq, 'form_type', 'Admission'); ?>>Admission</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="Pending" <?php echo sel($inq, 'status', 'Pending'); ?>>Pending</option>
                            <option value="Approved" <?php echo sel($inq, 'status', 'Approved'); ?>>Approved</option>
                            <option value="Rejected" <?php echo sel($inq, 'status', 'Rejected'); ?>>Rejected</option>
                            <option value="Admitted" <?php echo sel($inq, 'status', 'Admitted'); ?>>Admitted</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Applied Class</label>
                        <select name="applied_class" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="">None</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['class_name']); ?>" <?php echo sel($inq, 'applied_class', $c['class_name']); ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Faculty</label>
                        <select name="faculty_id" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="">None</option>
                            <?php foreach($faculties as $f): ?>
                                <option value="<?php echo $f['id']; ?>" <?php echo sel($inq, 'faculty_id', $f['id']); ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Schedule ID (Entrance Slot)</label>
                        <select name="schedule_id" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="">None (No Exam Selected)</option>
                            <?php foreach($schedules as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo sel($inq, 'schedule_id', $s['id']); ?>><?php echo htmlspecialchars($s['class_name'] . ' - ' . date('M d h:i A', strtotime($s['exam_date'].' '.$s['exam_time'])) . ' (' . $s['venue'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h3 class="font-bold border-b border-gray-200 pb-2 mt-8 text-lg text-emerald-800">Student Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">First Name</label><input type="text" name="student_first_name" value="<?php echo val($inq, 'student_first_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Last Name</label><input type="text" name="student_last_name" value="<?php echo val($inq, 'student_last_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Email</label><input type="email" name="student_email" value="<?php echo val($inq, 'student_email'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">DOB BS</label><input type="text" name="dob_bs" value="<?php echo val($inq, 'dob_bs'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Gender</label>
                        <select name="gender" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none">
                            <option value="Male" <?php echo sel($inq, 'gender', 'Male'); ?>>Male</option>
                            <option value="Female" <?php echo sel($inq, 'gender', 'Female'); ?>>Female</option>
                            <option value="Other" <?php echo sel($inq, 'gender', 'Other'); ?>>Other</option>
                        </select>
                    </div>
                </div>

                <h3 class="font-bold border-b border-gray-200 pb-2 mt-8 text-lg text-emerald-800">Address</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Province</label><input type="text" name="address_province" value="<?php echo val($inq, 'address_province'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">District</label><input type="text" name="address_district" value="<?php echo val($inq, 'address_district'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Municipality</label><input type="text" name="address_municipality" value="<?php echo val($inq, 'address_municipality'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Ward/Village</label><input type="text" name="address_ward_village" value="<?php echo val($inq, 'address_ward_village'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                </div>

                <h3 class="font-bold border-b border-gray-200 pb-2 mt-8 text-lg text-emerald-800">Parents & Guardian</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Father's Name</label><input type="text" name="father_name" value="<?php echo val($inq, 'father_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Father's Contact</label><input type="text" name="father_contact" value="<?php echo val($inq, 'father_contact'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Mother's Name</label><input type="text" name="mother_name" value="<?php echo val($inq, 'mother_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Mother's Contact</label><input type="text" name="mother_contact" value="<?php echo val($inq, 'mother_contact'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Local Guardian Name</label><input type="text" name="local_guardian_name" value="<?php echo val($inq, 'local_guardian_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Guardian Contact</label><input type="text" name="guardian_contact" value="<?php echo val($inq, 'guardian_contact'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                </div>

                <h3 class="font-bold border-b border-gray-200 pb-2 mt-8 text-lg text-emerald-800">Academic Background</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Previous School/College</label><input type="text" name="previous_school_name" value="<?php echo val($inq, 'previous_school_name'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">GPA / Percentage</label><input type="text" name="gpa_or_percentage" value="<?php echo val($inq, 'gpa_or_percentage'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">SEE Symbol No.</label><input type="text" name="see_symbol_no" value="<?php echo val($inq, 'see_symbol_no'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Optional Subject I</label><input type="text" name="optional_subject_1" value="<?php echo val($inq, 'optional_subject_1'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Optional Subject II</label><input type="text" name="optional_subject_2" value="<?php echo val($inq, 'optional_subject_2'); ?>" class="w-full border-gray-300 border rounded-lg p-2.5 focus:ring-emerald-500 outline-none"></div>
                </div>

                <h3 class="font-bold border-b border-gray-200 pb-2 mt-8 text-lg text-emerald-800">Documents & Photo</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Passport Photo -->
                    <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Passport Photo</label>
                        <?php if (!empty($inq['pp_photo_path'])): ?>
                            <img src="<?php echo val($inq,'pp_photo_path'); ?>" class="w-20 h-20 object-cover rounded mb-2 border">
                            <p class="text-xs text-gray-400 mb-2">Current photo above</p>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 mb-2">No photo uploaded yet</p>
                        <?php endif; ?>
                        <input type="file" name="pp_photo" accept="image/jpeg,image/png" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400 mt-1">JPG/PNG only. Leave blank to keep current.</p>
                    </div>
                    <!-- Marksheet -->
                    <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Academic Marksheet</label>
                        <?php if (!empty($inq['document_path'])): ?>
                            <a href="<?php echo val($inq,'document_path'); ?>" target="_blank" class="inline-flex items-center gap-1 text-xs text-emerald-700 font-semibold mb-2 hover:underline">[View Current Document]</a><br>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 mb-2">No marksheet uploaded yet</p>
                        <?php endif; ?>
                        <input type="file" name="marksheet_doc" accept="application/pdf,image/jpeg,image/png" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400 mt-1">PDF/JPG/PNG. Leave blank to keep current.</p>
                    </div>
                    <!-- Birth Certificate -->
                    <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Birth Certificate</label>
                        <?php if (!empty($inq['birth_cert_path'])): ?>
                            <a href="<?php echo val($inq,'birth_cert_path'); ?>" target="_blank" class="inline-flex items-center gap-1 text-xs text-emerald-700 font-semibold mb-2 hover:underline">[View Current Document]</a><br>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 mb-2">No birth certificate uploaded yet</p>
                        <?php endif; ?>
                        <input type="file" name="birth_cert" accept="application/pdf,image/jpeg,image/png" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400 mt-1">PDF/JPG/PNG. Leave blank to keep current.</p>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-colors text-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
