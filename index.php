<?php
// index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getSchoolSettings($pdo);
$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$has_api_key = !empty($settings['gemini_api_keys'] ?? '');

// Load active session
$active_session = null;
try {
    $active_session = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1")->fetch();
} catch (Exception $e) {
}

$session_label = $active_session ? $active_session['session_label'] : date('Y') . ' BS';
$admission_open = $active_session ? (bool) $active_session['admission_open'] : true;
$inquiry_open = $active_session ? (bool) $active_session['inquiry_open'] : true;
$active_session_id = $active_session ? $active_session['id'] : null;

// Count how many form types are open
$enabled_types = [];
if ($admission_open)
    $enabled_types[] = 'Admission';
if ($inquiry_open)
    $enabled_types[] = 'Inquiry';
$only_one_type = count($enabled_types) === 1;
$auto_open_type = $only_one_type ? $enabled_types[0] : null;

// Fetch faculties with their subjects to pass to JS
$faculties_raw = getFaculties($pdo);
$faculties = [];
foreach ($faculties_raw as $f) {
    $f['subjects'] = getFacultySubjects($pdo, $f['id']);
    $faculties[] = $f;
}

// Fetch all schedules for JS filtering
$stmt = $pdo->query("SELECT e.*, (e.total_capacity - (SELECT COUNT(*) FROM admission_inquiries a WHERE a.schedule_id = e.id)) AS available_seats FROM entrance_schedules e HAVING available_seats > 0");
$schedules = $stmt->fetchAll();

// Fetch open classes
$classes_stmt = $pdo->query("SELECT * FROM class_seats WHERE is_open = 1 ORDER BY id ASC");
$open_classes = $classes_stmt->fetchAll();

$app_data = [
    'faculties' => $faculties,
    'schedules' => $schedules,
    'open_classes' => $open_classes,
    'auto_open_type' => $auto_open_type
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .hidden-step {
            display: none;
        }

        /* Form type card hover lift */
        .form-type-card {
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .form-type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(5, 150, 105, 0.18);
        }

        .form-type-card.selected {
            border-color: #059669 !important;
            background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
        }

        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadein {
            animation: fadeSlideIn 0.4s ease forwards;
        }
    </style>
</head>

<body class="bg-emerald-50 min-h-screen">
    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14">
                <div class="flex items-center flex-shrink-0">
                    <a href="index.php" class="text-emerald-700 font-bold text-lg flex items-center gap-2">
                        <?php if (!empty($settings['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Logo" class="h-8 w-8 object-cover rounded-full border border-emerald-200">
                        <?php endif; ?>
                        <span class="hidden sm:block">Admissions Portal</span>
                    </a>
                </div>
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="print_blank_form.php" target="_blank" class="text-xs font-medium text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 px-3 py-1.5 rounded-md transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span class="hidden sm:inline">Blank Form</span>
                    </a>
                    <a href="status_check.php" class="text-xs font-semibold bg-emerald-100 text-emerald-800 hover:bg-emerald-200 px-3 py-1.5 rounded-md transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Student Login
                    </a>
                    <button type="button" onclick="localStorage.clear(); window.location.reload();" class="text-xs font-medium text-gray-600 hover:text-red-600 hover:bg-red-50 border border-gray-200 px-3 py-1.5 rounded-md transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span class="hidden sm:inline">Start Over</span>
                    </button>
                    <div class="h-4 w-px bg-gray-300 mx-1"></div>
                    <a href="login.php" class="text-xs font-medium text-gray-400 hover:text-gray-900 transition-colors p-1" title="Admin Login">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="p-4 md:p-8">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100">
        <!-- Header -->
        <div class="bg-emerald-600 p-6 text-white text-center relative pointer-events-auto">
            <?php if (!empty($settings['logo_path'])): ?>
                <div
                    class="w-20 h-20 mx-auto bg-white rounded-full flex items-center justify-center border-4 border-emerald-300 shadow-lg mb-3 overflow-hidden">
                    <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Logo"
                        class="w-full h-full object-cover">
                </div>
            <?php endif; ?>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($school_name); ?></h1>
            <p class="text-emerald-100 text-sm mt-1">
                <?php echo htmlspecialchars($settings['form_title'] ?? 'Admission Inquiry Form'); ?> &mdash;
                <?php echo htmlspecialchars($session_label); ?></p>

            <?php if (!empty($settings['address']) || !empty($settings['contact_phone']) || !empty($settings['contact_person'])): ?>
                <div
                    class="mt-3 text-emerald-100 text-xs sm:text-sm flex flex-wrap items-center justify-center gap-3 sm:gap-5 opacity-90">
                    <?php if (!empty($settings['address'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <?php echo htmlspecialchars($settings['address']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($settings['contact_phone'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                            <?php echo htmlspecialchars($settings['contact_phone']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($settings['contact_person'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <?php echo htmlspecialchars($settings['contact_person']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


        </div>

        <!-- Session Closed Notices -->
        <?php if (!$admission_open && !$inquiry_open): ?>
            <div class="mx-6 mt-6 p-4 bg-red-50 border border-red-200 rounded-xl text-center">
                <div class="text-2xl mb-2">🔒</div>
                <p class="font-bold text-red-700">Forms are currently closed.</p>
                <p class="text-sm text-red-500 mt-1">Both admissions and inquiries are closed for
                    <?php echo htmlspecialchars($session_label); ?>. Please check back later.</p>
            </div>
        <?php elseif (!$admission_open): ?>
            <div
                class="mx-6 mt-6 p-3 bg-orange-50 border border-orange-200 rounded-xl text-sm text-orange-700 text-center font-medium">
                ⚠ Full Admission applications are closed for this session. You can still submit a Quick Inquiry.
            </div>
        <?php endif; ?>
        <?php /* When only inquiry is closed, show nothing — admission form auto-selects silently below */ ?>

        <!-- Form Type Indicator (shown after selection) -->
        <div id="form_type_indicator" class="hidden"></div>
        
        <!-- Form Type Selection -->
        <?php if (!$only_one_type): ?>
            <div id="form_type_selection" class="p-6 md:p-8 pb-2">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-5 text-center">Choose how you'd like
                    to proceed</p>
                <div class="flex gap-4 <?php echo ($admission_open && $inquiry_open) ? 'max-w-md' : 'max-w-xs'; ?> mx-auto">
                    <?php if ($admission_open): ?>
                        <label id="card-admission" class="flex-1 relative cursor-pointer group">
                            <input type="radio" name="form_type_select" value="Admission" class="peer sr-only">
                            <div class="form-type-card border-2 border-gray-200 rounded-2xl p-5 text-center bg-white shadow-sm">
                                <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center mb-3"
                                    style="background:linear-gradient(135deg,#059669,#047857);">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                </div>
                                <span class="font-extrabold text-gray-900 block text-base leading-tight">Full Admission</span>
                                <span class="text-[11px] text-gray-400 mt-1 block">Complete application with entrance
                                    exam</span>
                                <div
                                    class="mt-3 inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-[11px] font-bold px-2.5 py-1 rounded-full border border-emerald-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="10" r="4" />
                                    </svg>
                                    Open
                                </div>
                            </div>
                        </label>
                    <?php endif; ?>
                    <?php if ($inquiry_open): ?>
                        <label id="card-inquiry" class="flex-1 relative cursor-pointer group">
                            <input type="radio" name="form_type_select" value="Inquiry" class="peer sr-only">
                            <div class="form-type-card border-2 border-gray-200 rounded-2xl p-5 text-center bg-white shadow-sm">
                                <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center mb-3"
                                    style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                </div>
                                <span class="font-extrabold text-gray-900 block text-base leading-tight">Quick Inquiry</span>
                                <span class="text-[11px] text-gray-400 mt-1 block">Ask a question or express interest</span>
                                <div
                                    class="mt-3 inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-[11px] font-bold px-2.5 py-1 rounded-full border border-indigo-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="10" r="4" />
                                    </svg>
                                    Open
                                </div>
                            </div>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stepper & Form (Initially Hidden) -->
        <div id="form_content_wrapper" class="hidden opacity-0 transition-opacity duration-500">
            <!-- Stepper Indicator -->
            <div class="px-6 pt-6 pb-2 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div class="text-center flex-1">
                        <div id="ind-1"
                            class="w-8 h-8 mx-auto bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm">
                            1</div>
                        <div class="text-xs text-gray-500 mt-2 font-medium">Basics</div>
                    </div>
                    <div class="w-full bg-gray-200 h-1 flex-1 mx-2 rounded">
                        <div id="line-1" class="bg-emerald-600 h-1 w-0 rounded transition-all"></div>
                    </div>
                    <div class="text-center flex-1">
                        <div id="ind-2"
                            class="w-8 h-8 mx-auto bg-gray-200 text-gray-600 rounded-full flex items-center justify-center font-bold text-sm transition-colors">
                            2</div>
                        <div class="text-xs text-gray-500 mt-2 font-medium">Parents</div>
                    </div>
                    <div class="w-full bg-gray-200 h-1 flex-1 mx-2 rounded">
                        <div id="line-2" class="bg-emerald-600 h-1 w-0 rounded transition-all"></div>
                    </div>
                    <div class="text-center flex-1">
                        <div id="ind-3"
                            class="w-8 h-8 mx-auto bg-gray-200 text-gray-600 rounded-full flex items-center justify-center font-bold text-sm transition-colors">
                            3</div>
                        <div class="text-xs text-gray-500 mt-2 font-medium">Address</div>
                    </div>
                    <div class="w-full bg-gray-200 h-1 flex-1 mx-2 rounded">
                        <div id="line-3" class="bg-emerald-600 h-1 w-0 rounded transition-all"></div>
                    </div>
                    <div class="text-center flex-1 admission-only-step transition-all duration-300">
                        <div id="ind-4"
                            class="w-8 h-8 mx-auto bg-gray-200 text-gray-600 rounded-full flex items-center justify-center font-bold text-sm transition-colors">
                            4</div>
                        <div class="text-xs text-gray-500 mt-2 font-medium">Docs</div>
                    </div>
                </div>
            </div>

            <form id="admissionForm" action="process_form.php" method="POST" enctype="multipart/form-data"
                class="p-6 md:p-8 pt-4" novalidate>
                <input type="hidden" name="form_type" id="form_type" value="Admission">
                <input type="hidden" name="session_id" value="<?php echo $active_session_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">


                <!-- STEP 1: Basics -->
                <div id="step-1" class="form-step">
                    <h2 class="text-xl font-bold text-emerald-800 mb-6 border-b pb-2 border-emerald-100">Step 1: Express
                        Auto-Fill & Student Basics</h2>

                    <!-- AI Express Onboarding -->
                    <?php if ($has_api_key): ?>
                    <div
                        class="mb-8 p-5 bg-indigo-50 rounded-xl border border-indigo-100 shadow-sm relative overflow-hidden admission-only transition-all duration-300">
                        <div
                            class="absolute top-0 right-0 w-32 h-32 bg-indigo-100 rounded-full mix-blend-multiply opacity-50 transform translate-x-12 -translate-y-12">
                        </div>

                        <div class="flex items-center justify-between mb-4 relative z-10">
                            <div>
                                <h3 class="font-bold text-indigo-900 text-lg flex items-center"><svg
                                        class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg> AI Fast Track</h3>
                                <p class="text-xs text-indigo-700 mt-1">Upload your documents first to automatically
                                    fill this entire form!</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative z-10">
                            <!-- PP Photo -->
                            <div class="bg-white p-3 rounded-lg border border-indigo-50 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Passport Photo</label>
                                <div class="text-center flex flex-col items-center">
                                    <img id="photo_preview" src="" alt="Preview"
                                        class="hidden w-16 h-16 rounded-full object-cover mb-2 border-2 border-indigo-100 shadow-sm">
                                    <input type="file" name="pp_photo" id="pp_photo" accept="image/*"
                                        class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer p-1">
                                </div>
                            </div>
                            <!-- Marksheet -->
                            <div class="bg-white p-3 rounded-lg border border-indigo-50 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Academic Marksheet</label>
                                <input type="file" name="marksheet_doc" id="marksheet_doc" accept="image/*,.pdf"
                                    class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer p-1">
                            </div>
                            <!-- Birth Certificate -->
                            <div class="bg-white p-3 rounded-lg border border-indigo-50 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Birth Certificate</label>
                                <input type="file" name="birth_cert" id="birth_cert" accept="image/*,.pdf"
                                    class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer p-1">
                            </div>
                        </div>

                        <div class="mt-4 text-right relative z-10 flex flex-col items-end">
                            <button type="button" id="ai-autofill-btn"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2.5 rounded-lg inline-flex items-center shadow-md transition-colors">
                                Analyze Documents & Pre-fill Form
                            </button>
                            <p class="text-[11px] text-indigo-700 mt-2 font-medium" id="ai-status"></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input type="text" name="student_first_name" id="student_first_name" required
                                class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="student_last_name" id="student_last_name" required
                                class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all shadow-sm">
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                        <input type="email" name="student_email" id="student_email" required
                            placeholder="To receive your digital admit card"
                            class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all shadow-sm">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">DOB (BS) *</label>
                            <input type="text" name="dob_bs" id="dob_bs" placeholder="YYYY-MM-DD" required
                                class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                            <p class="text-xs text-gray-500 mt-1">Auto-formats to YYYY-MM-DD</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                            <select name="gender" id="gender" required
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Applied Class *</label>
                        <select name="applied_class" id="applied_class" required
                            class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                            <option value="">Select Class</option>
                            <?php foreach ($open_classes as $cls): ?>
                                <option value="<?php echo htmlspecialchars($cls['class_name']); ?>">
                                    <?php echo htmlspecialchars($cls['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Class Contact Card - Shown when class is selected -->
                    <div id="class-contact-card"
                        class="hidden mt-2 mb-5 p-4 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-xl animate-fadeIn shadow-sm">
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mb-3">👤 Admissions
                            Contact</p>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <p class="font-bold text-gray-900 text-base" id="class-contact-name"></p>
                                <p class="text-xs text-indigo-700 font-medium">Class Representative</p>
                            </div>
                            <a id="class-contact-wa" href="#" target="_blank"
                                class="flex-shrink-0 hidden bg-green-500 hover:bg-green-600 text-white font-bold text-xs px-4 py-2.5 rounded-lg shadow transition-colors flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                                WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- Class 9 dynamics -->
                    <div id="class_9_section"
                        class="hidden grid-cols-1 md:grid-cols-2 gap-5 mb-5 p-4 bg-emerald-50/50 rounded-lg border border-emerald-100">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Optional I *</label>
                            <select name="optional_subject_1" id="optional_subject_1"
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Optional I</option>
                                <option value="Math">Math</option>
                                <option value="Economics">Economics</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Optional II *</label>
                            <select name="optional_subject_2" id="optional_subject_2"
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Optional II</option>
                                <option value="Account">Account</option>
                                <option value="Computer">Computer</option>
                            </select>
                        </div>
                    </div>

                    <!-- Class 11 dynamics -->
                    <div id="class_11_section"
                        class="hidden mb-5 p-4 bg-emerald-50/50 rounded-lg border border-emerald-100">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Faculty *</label>
                            <select name="faculty_id" id="faculty_id"
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Faculty</option>
                                <!-- Populated by JS -->
                            </select>
                        </div>

                        <div id="faculty_subjects_section" class="hidden mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Optional Subject</label>
                            <select name="faculty_optional_subject" id="faculty_optional_subject"
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Subject</option>
                            </select>
                        </div>



                        <!-- Incharge Contact Card - Shown when faculty is selected -->
                        <div id="incharge-card"
                            class="hidden mt-2 mb-4 p-4 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl animate-fadeIn shadow-sm">
                            <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-3">👤 Class
                                Incharge — Contact Directly</p>
                            <div class="flex items-center gap-4">
                                <div id="incharge-photo-wrap" class="hidden">
                                    <img id="incharge-photo" src=""
                                        class="w-14 h-14 rounded-full object-cover border-2 border-emerald-300 shadow-sm"
                                        alt="Incharge Photo">
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-gray-900 text-base" id="incharge-name"></p>
                                    <p class="text-xs text-emerald-700 font-medium" id="incharge-title"></p>
                                </div>
                                <a id="incharge-wa-btn" href="#" target="_blank"
                                    class="flex-shrink-0 hidden bg-green-500 hover:bg-green-600 text-white font-bold text-xs px-4 py-2.5 rounded-lg shadow transition-colors flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                    </svg>
                                    WhatsApp
                                </a>
                            </div>
                        </div>

                    </div>

                    <div id="entrance_slot_section"
                        class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-lg admission-only transition-all duration-300">
                        <label class="block text-sm font-medium text-red-800 mb-3 font-semibold">Entrance Exam Slot
                            *</label>
                        <input type="hidden" name="schedule_id" id="schedule_id">
                        <div id="schedule_chips_container" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Populated by JS -->
                        </div>
                        <p class="text-xs text-red-600 mt-3 font-medium">This class/faculty requires a mandatory
                            entrance exam.</p>
                    </div>
                </div>

                <!-- STEP 2: Parents -->
                <div id="step-2" class="form-step hidden-step">
                    <h2 class="text-xl font-bold text-emerald-800 mb-6 border-b pb-2 border-emerald-100">Parent &
                        Guardian Info</h2>

                    <div class="space-y-6">
                        <!-- Father -->
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h3 class="font-semibold text-gray-700 mb-3">Father's Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Father's Name *</label>
                                    <input type="text" name="father_name" id="father_name" required
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact No. *</label>
                                    <input type="tel" name="father_contact" id="father_contact" required
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Mother -->
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h3 class="font-semibold text-gray-700 mb-3">Mother's Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Name</label>
                                    <input type="text" name="mother_name" id="mother_name"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact No.</label>
                                    <input type="tel" name="mother_contact" id="mother_contact"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Local Guardian -->
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h3 class="font-semibold text-gray-700 mb-3">Local Guardian</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Guardian's Name</label>
                                    <input type="text" name="local_guardian_name" id="local_guardian_name"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Relation</label>
                                    <input type="text" name="guardian_relation" id="guardian_relation"
                                        placeholder="e.g. Uncle"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact No.</label>
                                    <input type="tel" name="guardian_contact" id="guardian_contact"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Geography -->
                <div id="step-3" class="form-step hidden-step">
                    <h2 class="text-xl font-bold text-emerald-800 mb-6 border-b pb-2 border-emerald-100">Geographic
                        Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                            <select name="address_province" id="address_province" required
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat cursor-pointer hover:border-gray-400 transition-colors">
                                <option value="">Select Province</option>
                                <!-- Populated via map -->
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                            <select name="address_district" id="address_district" required disabled
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white disabled:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat transition-colors">
                                <option value="">Select Province First</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Municipality / Rural Mun.
                                *</label>
                            <select name="address_municipality" id="address_municipality" required disabled
                                class="w-full appearance-none rounded-lg border border-gray-300 p-3 pr-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm outline-none bg-white disabled:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed font-medium text-gray-700 bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:1.2em_1.2em] bg-[right_1rem_center] bg-no-repeat transition-colors">
                                <option value="">Select District First</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ward / Village *</label>
                            <input type="text" name="address_ward_village" id="address_ward_village" required
                                class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Documents -->
                <div id="step-4" class="form-step hidden-step">
                    <h2 class="text-xl font-bold text-emerald-800 mb-6 border-b pb-2 border-emerald-100">Academic
                        History & Submission</h2>

                    <?php if (!$has_api_key): ?>
                    <!-- Manual Document Uploads for Step 4 -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 admission-only transition-all duration-300">
                        <h3 class="font-bold text-gray-700 mb-4 border-b border-gray-200 pb-2 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            Required Documents
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <!-- PP Photo -->
                            <div class="bg-white p-3 rounded-lg border border-gray-200 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Passport Photo</label>
                                <div class="text-center flex flex-col items-center">
                                    <img id="photo_preview" src="" alt="Preview" class="hidden w-16 h-16 rounded-full object-cover mb-2 border-2 border-gray-200 shadow-sm">
                                    <input type="file" name="pp_photo" id="pp_photo" accept="image/*" class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer p-1">
                                </div>
                            </div>
                            <!-- Marksheet -->
                            <div class="bg-white p-3 rounded-lg border border-gray-200 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Academic Marksheet</label>
                                <input type="file" name="marksheet_doc" id="marksheet_doc" accept="image/*,.pdf" class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer p-1">
                            </div>
                            <!-- Birth Certificate -->
                            <div class="bg-white p-3 rounded-lg border border-gray-200 flex flex-col justify-between">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Birth Certificate</label>
                                <input type="file" name="birth_cert" id="birth_cert" accept="image/*,.pdf" class="w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer p-1">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div
                        class="mb-6 p-4 bg-emerald-50 rounded-lg border border-emerald-200 admission-only transition-all duration-300">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Previous School Name</label>
                                <input type="text" name="previous_school_name" id="previous_school_name"
                                    class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">GPA / %</label>
                                    <input type="text" name="gpa_or_percentage" id="gpa_or_percentage"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                                <div id="see_symbol_container" class="hidden transition-opacity duration-300">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SEE Symbol No.</label>
                                    <input type="text" name="see_symbol_no" id="see_symbol_no"
                                        class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5">
                        <label class="flex items-start">
                            <input type="checkbox" name="declaration_accepted" id="declaration_accepted" required
                                class="mt-1 mr-3 h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded">
                            <span class="text-sm text-gray-600 leading-relaxed">I carefully read the form and declare
                                that all the information provided above is correct. If found incorrect, I am ready to
                                accept any disciplinary action taken by the school administration.</span>
                        </label>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="mt-8 flex justify-between pt-6 border-t border-gray-100">
                    <button type="button" id="btn-prev"
                        class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors hidden shadow-sm">Back</button>
                    <div class="flex-1"></div>
                    <button type="button" id="btn-next"
                        class="px-6 py-3 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition-colors shadow-md shadow-emerald-600/20">Continue
                        to Parents</button>
                    <button type="submit" id="btn-submit"
                        class="hidden px-8 py-3 rounded-lg bg-emerald-700 text-white font-bold hover:bg-emerald-800 transition-colors shadow-lg shadow-emerald-600/30 flex items-center justify-center min-w-[220px]">
                        <span id="btn-submit-text">Submit Application</span>
                        <svg id="btn-submit-spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </button>
                </div>

            </form>
        </div> <!-- END form_content_wrapper -->
    </div>

    <!-- Cropper Modal -->
    <div id="cropper-modal" class="fixed inset-0 z-50 hidden bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Crop Passport Photo</h3>
            <p class="text-xs text-gray-500 mb-4">Please crop your photo to a 1:1 aspect ratio focusing on your face.
            </p>
            <div class="w-full h-72 bg-gray-100 rounded border border-gray-200 overflow-hidden relative">
                <img id="cropper-image" src="" alt="Picture" class="block max-w-full">
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="btn-cancel-crop"
                    class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors">Cancel</button>
                <button type="button" id="btn-save-crop"
                    class="px-5 py-2 text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg font-medium shadow transition-colors">Apply
                    Crop</button>
            </div>
        </div>
    </div>

    <!-- Global App Data -->
    <script>
        window.APP_DATA = <?php echo json_encode($app_data); ?>;
    </script>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="assets/js/form-wizard.js"></script>

    <!-- Add AI Chatbot -->
    <?php include 'includes/chat_widget.php'; ?>

    </div>
</body>

</html>