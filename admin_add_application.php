<?php
// admin_add_application.php — Admin page to submit a new application form
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Academic Staff'])) {
    header("Location: dashboard.php");
    exit;
}

$settings     = getSchoolSettings($pdo);
$school_name  = $settings['school_name'] ?? 'School Admission Portal';

// Load active session
$active_session = null;
try {
    $active_session = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1")->fetch();
} catch (Exception $e) {}

$session_label     = $active_session ? $active_session['session_label'] : date('Y') . ' BS';
$admission_open    = true;  // Admin can always submit regardless of open status
$inquiry_open      = true;
$active_session_id = $active_session ? $active_session['id'] : null;

// Fetch faculties with their subjects to pass to JS
$faculties_raw = getFaculties($pdo);
$faculties = [];
foreach ($faculties_raw as $f) {
    $f['subjects'] = getFacultySubjects($pdo, $f['id']);
    $faculties[] = $f;
}

$stmt = $pdo->query("SELECT e.*, (e.total_capacity - (SELECT COUNT(*) FROM admission_inquiries a WHERE a.schedule_id = e.id)) AS available_seats FROM entrance_schedules e HAVING available_seats > 0");
$schedules = $stmt->fetchAll();

$classes_stmt = $pdo->query("SELECT * FROM class_seats ORDER BY id ASC");
$open_classes = $classes_stmt->fetchAll();

$app_data = [
    'faculties'    => $faculties,
    'schedules'    => $schedules,
    'open_classes' => $open_classes
];

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Application — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hidden-step { display: none; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="flex-1 w-full overflow-y-auto">
    <!-- Admin notice bar -->
    <div class="bg-amber-50 border-b border-amber-200 px-6 py-2 text-amber-800 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><strong>Admin Mode:</strong> You are entering a new application on behalf of a student. All fields apply normally. This bypasses open/closed session restrictions.</span>
        <a href="applications.php" class="ml-auto text-amber-700 hover:text-amber-900 font-semibold text-xs underline whitespace-nowrap">Back to Applications</a>
    </div>

    <div class="px-6 py-6 max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Add New Application</h1>
        <p class="text-sm text-gray-400 mb-6">Fill in the student details below. Submitted data will be saved to the system immediately.</p>

        <!-- ── Embed the exact same form as index.php but inside admin layout ── -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <!-- Reuse the form from index.php via an inner page wrapper -->
            <form id="adminAppForm" action="process_form.php" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="session_id" value="<?php echo htmlspecialchars((string)$active_session_id); ?>">

                <!-- Form Type -->
                <div class="flex gap-4 flex-wrap">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="form_type" value="Admission" checked class="accent-emerald-600">
                        <span class="font-semibold text-gray-800">Full Admission Form</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="form_type" value="Inquiry" class="accent-emerald-600">
                        <span class="font-semibold text-gray-800">Quick Inquiry Only</span>
                    </label>
                </div>

                <!-- ── SECTION 1: Program Details ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Program Details</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Applied Class <span class="text-red-500">*</span></label>
                            <select name="applied_class" required id="applied_class" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($open_classes as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['class_name']); ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="faculty_section" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Faculty / Stream</label>
                            <select name="faculty_id" id="faculty_id" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="schedule_section" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Entrance Exam Slot</label>
                            <select name="schedule_id" id="schedule_id" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                                <option value="">-- No Exam / Select Later --</option>
                                <?php foreach ($schedules as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['class_name'] . ' — ' . date('M d, Y', strtotime($s['exam_date'])) . ' ' . date('h:i A', strtotime($s['exam_time'])) . ' @ ' . $s['venue'] . ' (' . $s['available_seats'] . ' seats left)'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="opt_sub_wrap" class="hidden md:col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Optional Subject I</label>
                                <select name="optional_subject_1" id="optional_subject_1" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none text-sm"><option value="">-- None --</option></select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Optional Subject II</label>
                                <select name="optional_subject_2" id="optional_subject_2" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none text-sm"><option value="">-- None --</option></select>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- ── SECTION 2: Student Details ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Student Details</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="student_first_name" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="student_last_name" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Student Email</label>
                            <input type="email" name="student_email" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth (BS) <span class="text-red-500">*</span></label>
                            <input type="text" name="dob_bs" placeholder="e.g. 2065-04-15" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth (AD)</label>
                            <input type="date" name="dob_ad" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="">-- Select --</option>
                                <option>Male</option><option>Female</option><option>Other</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- ── SECTION 3: Address ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Permanent Address</legend>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Province <span class="text-red-500">*</span></label>
                            <input type="text" name="address_province" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">District <span class="text-red-500">*</span></label>
                            <input type="text" name="address_district" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Municipality <span class="text-red-500">*</span></label>
                            <input type="text" name="address_municipality" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Ward/Village <span class="text-red-500">*</span></label>
                            <input type="text" name="address_ward_village" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                    </div>
                </fieldset>

                <!-- ── SECTION 4: Family Details ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Family Details</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Father's Name <span class="text-red-500">*</span></label>
                            <input type="text" name="father_name" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Father's Occupation</label>
                            <input type="text" name="father_occupation" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Father's Contact <span class="text-red-500">*</span></label>
                            <input type="text" name="father_contact" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Mother's Name</label>
                            <input type="text" name="mother_name" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Mother's Occupation</label>
                            <input type="text" name="mother_occupation" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Mother's Contact</label>
                            <input type="text" name="mother_contact" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Local Guardian Name</label>
                            <input type="text" name="local_guardian_name" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Guardian Contact</label>
                            <input type="text" name="guardian_contact" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Guardian Relation</label>
                            <input type="text" name="guardian_relation" placeholder="e.g. Uncle, Aunt" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                    </div>
                </fieldset>

                <!-- ── SECTION 5: Academic Background ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Academic Background</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Previous School / College</label>
                            <input type="text" name="previous_school_name" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Board</label>
                            <input type="text" name="previous_board" placeholder="e.g. NEB, SEE" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">GPA / Percentage</label>
                            <input type="text" name="gpa_or_percentage" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">SEE Symbol No.</label>
                            <input type="text" name="see_symbol_no" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none">
                        </div>
                    </div>
                </fieldset>

                <!-- ── SECTION 6: Documents ── -->
                <fieldset class="border border-gray-200 rounded-lg p-5 space-y-4">
                    <legend class="text-sm font-bold text-emerald-700 px-2 uppercase tracking-wider">Documents &amp; Photo</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Passport Photo <span class="text-red-500">*</span></label>
                            <input type="file" name="pp_photo" accept="image/jpeg,image/png" required class="w-full text-sm text-gray-500 border border-gray-300 rounded-lg p-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="text-xs text-gray-400 mt-1">JPG/PNG only</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Academic Marksheet</label>
                            <input type="file" name="marksheet_doc" accept="application/pdf,image/jpeg,image/png" class="w-full text-sm text-gray-500 border border-gray-300 rounded-lg p-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="text-xs text-gray-400 mt-1">PDF/JPG/PNG</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Birth Certificate</label>
                            <input type="file" name="birth_cert" accept="application/pdf,image/jpeg,image/png" class="w-full text-sm text-gray-500 border border-gray-300 rounded-lg p-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="text-xs text-gray-400 mt-1">PDF/JPG/PNG</p>
                        </div>
                    </div>
                </fieldset>

                <!-- Submit -->
                <div class="flex items-center gap-4 pt-2">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-10 rounded-xl shadow-lg transition-colors text-base">
                        Submit Application
                    </button>
                    <a href="applications.php" class="text-gray-500 hover:text-gray-700 font-medium text-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div></main></div>

<script>
const APP_DATA = <?php echo json_encode($app_data); ?>;

// Show/hide faculty & schedule selectors based on class selection
document.getElementById('applied_class').addEventListener('change', function() {
    const cls = this.value;
    const facultySection = document.getElementById('faculty_section');
    const scheduleSection = document.getElementById('schedule_section');
    const optSubWrap = document.getElementById('opt_sub_wrap');

    // Show faculty if faculties exist for this class
    const hasFaculty = APP_DATA.faculties.length > 0;
    facultySection.classList.toggle('hidden', !hasFaculty || !cls);

    // Show schedule if available slots exist for this class
    const relevantSchedules = APP_DATA.schedules.filter(s => s.class_name === cls);
    scheduleSection.classList.toggle('hidden', relevantSchedules.length === 0);

    // Reset and repopulate schedule options
    const schedSel = document.getElementById('schedule_id');
    schedSel.innerHTML = '<option value="">-- No Exam / Select Later --</option>';
    relevantSchedules.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = `${s.class_name} — ${s.exam_date} ${s.exam_time} @ ${s.venue} (${s.available_seats} seats)`;
        schedSel.appendChild(opt);
    });
});

// Show optional subjects when faculty is selected
document.getElementById('faculty_id').addEventListener('change', function() {
    const fid = parseInt(this.value);
    const faculty = APP_DATA.faculties.find(f => f.id === fid);
    const subjects = faculty ? faculty.subjects : [];
    const wrap = document.getElementById('opt_sub_wrap');
    wrap.classList.toggle('hidden', subjects.length === 0);

    ['optional_subject_1','optional_subject_2'].forEach(id => {
        const sel = document.getElementById(id);
        sel.innerHTML = '<option value="">-- None --</option>';
        subjects.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.subject_name;
            opt.textContent = s.subject_name;
            sel.appendChild(opt);
        });
    });
});
</script>
</body>
</html>
