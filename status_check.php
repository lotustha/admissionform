<?php
// status_check.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';

$result = null;
$error = null;

// Check if redirected back due to disabled account
if (isset($_GET['error']) && $_GET['error'] === 'disabled') {
    $error = "Your account has been disabled by the administrator. Please contact the school office for assistance.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_rate_limit('status_check', 10, 60)) {
        $error = "Too many requests. Please wait a minute before trying again.";
    }
    elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh the page.";
    }
    else {
        $identifier = trim($_POST['identifier'] ?? '');
        $dob_bs = trim($_POST['dob_bs'] ?? '');

        if (empty($identifier) || empty($dob_bs)) {
            $error = "Please enter your Phone/Roll Number and DOB.";
        }
        else {
            $stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                                   FROM admission_inquiries i 
                                   LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                                   LEFT JOIN faculties f ON i.faculty_id = f.id
                                   WHERE (i.entrance_roll_no = ? OR i.father_contact = ? OR i.mother_contact = ?) AND i.dob_bs = ?");
            $stmt->execute([$identifier, $identifier, $identifier, $dob_bs]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if ($row['status'] === 'Disabled') {
                    $error = "Your account has been disabled by the administrator. Please contact the school office.";
                } else {
                    $_SESSION['student_id'] = $row['id'];
                    header("Location: student_dashboard.php");
                    exit;
                }
            }
            else {
                $error = "No application found with the provided details. Please check your Phone/Roll Number and DOB.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-emerald-50 min-h-screen py-10 px-4">

<div class="max-w-xl mx-auto">
    <div class="text-center mb-8">
        <?php if (!empty($settings['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Logo" class="h-16 mx-auto mb-4">
        <?php
endif; ?>
        <h1 class="text-3xl font-bold text-emerald-800"><?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-emerald-600 mt-2">Student Portal Login</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100">
        <div class="p-6 md:p-8">
            
            <?php if (empty($result)): ?>
                <form action="" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <?php if ($error): ?>
                        <div class="bg-red-50 text-red-700 p-4 rounded-lg text-sm border border-red-200"><?php echo htmlspecialchars($error); ?></div>
                    <?php
    endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number or Roll No *</label>
                        <input type="text" name="identifier" required placeholder="e.g. 98XXXXXXXX or EC-1001" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth (BS) *</label>
                        <input type="text" name="dob_bs" required placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($_POST['dob_bs'] ?? ''); ?>" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                    </div>
                    
                    <button type="submit" class="w-full py-3 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition-colors shadow-md mt-4">Login to Dashboard</button>
                    
                    <div class="text-center mt-6">
                        <a href="index.php" class="text-sm text-emerald-600 hover:text-emerald-800 font-medium">&larr; Back to Admissions Form</a>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/chat_widget.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.querySelector('input[name="dob_bs"]');
    if(dobInput) {
        dobInput.addEventListener('input', function(e) {
            let v = this.value.replace(/\D/g, ''); // Remove non-digits
            if (v.length > 4) {
                v = v.substring(0, 4) + '-' + v.substring(4);
            }
            if (v.length > 7) {
                v = v.substring(0, 7) + '-' + v.substring(7, 9);
            }
            this.value = v;
        });
    }
});
</script>
</body>
</html>