<?php
// print_admit_card.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

// Allow access for admins OR logged-in students (students can only view their own)
$is_admin = isset($_SESSION['admin_id']);
$is_student = isset($_SESSION['student_id']);

if (!$is_admin && !$is_student) {
    die("Unauthorized access. Please log in.");
}

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$logo = $settings['logo_path'] ?? '';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    die("Invalid access. Application ID required.");
}

// Students can only view their own admit card
if ($is_student && !$is_admin && (int)$id !== (int)$_SESSION['student_id']) {
    die("Unauthorized access. You can only view your own admit card.");
}

$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                       FROM admission_inquiries i 
                       LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                       LEFT JOIN faculties f ON i.faculty_id = f.id
                       WHERE i.id = ?");
$stmt->execute([$id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Application not found.");
}

// Prepare Photo
$photo_src = !empty($student['pp_photo_path']) ? $student['pp_photo_path'] : '';

// Generate QR Code Payload using Composer Library
$qr_payload = json_encode([
    'action' => 'scan_attendance',
    'id' => $student['id'],
    'roll' => $student['entrance_roll_no']
]);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$options = new QROptions([
    'version'         => 5,
    'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
    'outputBase64'    => true,
    'eccLevel'        => \chillerlan\QRCode\Common\EccLevel::L,
    'addQuietzone'    => false,
]);

$qrCode = new QRCode($options);
$qr_url = $qrCode->render($qr_payload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admit Card - <?php echo htmlspecialchars($student['student_first_name']); ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #e2e8f0; margin: 0; padding: 20px; color: #1e293b; }
        .admit-card { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; border: 2px solid #059669; border-radius: 8px; padding: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .card-header { background: #059669; color: white; padding: 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 4px solid #047857; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card-header img { max-height: 70px; max-width: 70px; border-radius: 50%; object-fit: cover; }
        .header-text { flex-grow: 1; text-align: center; }
        .header-text h1 { margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .header-text p { margin: 5px 0 0 0; font-size: 14px; opacity: 0.9; }
        .title { text-align: center; font-size: 22px; font-weight: bold; margin: 20px 0; color: #065f46; letter-spacing: 2px;}
        .title span { border-bottom: 2px dashed #059669; padding-bottom: 5px; }
        .card-body { padding: 10px 40px; display: flex; gap: 30px; }
        .photo-area { width: 130px; display: flex; flex-direction: column; align-items: center;}
        .photo { width: 110px; height: 110px; border: 2px solid #cbd5e1; border-radius: 4px; object-fit: cover; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 10px;text-align: center;}
        .signature-box { width: 110px; height: 45px; border: 1px solid #cbd5e1; margin-top: 10px; font-size: 10px; color: #94a3b8; display: flex; align-items: end; justify-content: center; padding-bottom: 5px; box-sizing: border-box;}
        .info-area { flex-grow: 1; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
        .info-group { margin-bottom: 0px; }
        .info-label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold; margin-bottom: 2px; }
        .info-value { font-size: 14px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px;}
        
        .exam-details { margin: 15px 40px; background: #f0fdf4; border: 1px solid #10b981; border-radius: 6px; padding: 12px 20px; display: flex; justify-content: space-between; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .exam-item { text-align: center; flex: 1;}
        .exam-label { font-size: 10px; color: #065f46; font-weight: bold; text-transform: uppercase; }
        .exam-val { font-size: 14px; font-weight: bold; color: #022c22; margin-top: 4px; }
        
        .rules { margin: 0 40px 15px 40px; padding-top: 15px; border-top: 1px dashed #cbd5e1; font-size: 11px; color: #475569; }
        .rules h4 { margin: 0 0 8px 0; color: #334155; font-size: 13px;}
        .rules ul { margin: 0; padding-left: 20px; }
        .rules li { margin-bottom: 4px; }
        
        .footer { display: flex; justify-content: space-between; margin: 0 40px 20px 40px; }
        .sig-line { width: 220px; border-top: 1px solid #0f172a; text-align: center; padding-top: 5px; font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 25px; }
        
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 90px; color: rgba(5, 150, 105, 0.05); font-weight: 900; white-space: nowrap; z-index: 0; pointer-events: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .card-content { position: relative; z-index: 1; }
        
        @media print {
            body { background: white; padding: 10mm; }
            .admit-card { border: 2px solid #000; box-shadow: none; border-radius: 0; width: 100%; max-width: 100%; margin: 0; }
            .card-header { background: #e5e5e5 !important; color: #000 !important; border-bottom: 2px solid #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .exam-details { background: #f9f9f9 !important; border: 1px solid #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .watermark { color: rgba(0, 0, 0, 0.04) !important; }
            .title span { border-bottom: 2px dashed #000; }
        }
    </style>
</head>
<body>
    <div class="admit-card">
        <div class="watermark"><?php echo htmlspecialchars($school_name); ?></div>
        
        <div class="card-content">
            <div class="card-header">
                <?php if ($logo): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
                <?php else: ?>
                    <div style="width: 80px;"></div>
                <?php endif; ?>
                <div class="header-text">
                    <h1><?php echo htmlspecialchars($school_name); ?></h1>
                    <p>Entrance Examination - <?php echo date('Y'); ?></p>
                </div>
                <div style="width: 80px; text-align: right;">
                    <span style="font-size: 11px; opacity: 0.9; font-weight: bold;">ID: #<?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
            </div>
            
            <div class="title"><span>ADMIT CARD</span></div>
            
            <div class="card-body">
                <div class="photo-area">
                    <?php if ($photo_src): ?>
                        <img src="<?php echo htmlspecialchars($photo_src); ?>" alt="Photo" class="photo">
                    <?php else: ?>
                        <div class="photo">No Photo</div>
                    <?php endif; ?>
                    <div class="signature-box">Applicant's Signature</div>
                    
                    <!-- QR Code -->
                    <div style="margin-top: 10px; width: 110px; box-sizing: border-box; text-align: center; border: 1px solid #cbd5e1; padding: 5px; border-radius: 4px; background: white;">
                        <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Code" style="width: 100%; height: auto; display: block;" crossorigin="anonymous">
                        <span style="font-size: 8px; font-weight: bold; color: #64748b; text-transform: uppercase; display: block; margin-top: 2px;">Scan at Gate</span>
                    </div>
                </div>
                
                <div class="info-area">
                    <div class="info-grid">
                        <div class="info-group">
                            <div class="info-label">Applicant Name</div>
                            <div class="info-value"><?php echo htmlspecialchars(strtoupper($student['student_first_name'] . ' ' . $student['student_last_name'])); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Roll Number</div>
                            <div class="info-value" style="color: #b91c1c; font-size: 18px;"><?php echo htmlspecialchars($student['entrance_roll_no'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Applied Class / Program</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['applied_class'] . ($student['faculty_name'] ? ' - ' . $student['faculty_name'] : '')); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Gender / DOB (BS)</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?> / <?php echo htmlspecialchars($student['dob_bs']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Father's Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['father_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['father_contact'] ?: $student['guardian_contact']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="exam-details">
                <div class="exam-item">
                    <div class="exam-label">Date of Examination</div>
                    <div class="exam-val"><?php echo $student['exam_date'] ? htmlspecialchars(date('d M, Y (l)', strtotime($student['exam_date']))) : 'TBD'; ?></div>
                </div>
                <div class="exam-item" style="border-left: 1px solid #10b981; padding-left: 20px;">
                    <div class="exam-label">Time</div>
                    <div class="exam-val"><?php echo $student['exam_time'] ? htmlspecialchars(date('h:i A', strtotime($student['exam_time']))) : 'TBD'; ?></div>
                </div>
                <div class="exam-item" style="border-left: 1px solid #10b981; padding-left: 20px;">
                    <div class="exam-label">Examination Venue</div>
                    <div class="exam-val"><?php echo $student['venue'] ? htmlspecialchars($student['venue']) : 'TBD'; ?></div>
                </div>
            </div>
            
            <div class="rules">
                <h4>Instructions to Candidates:</h4>
                <ul>
                    <li>Bring this printed Admit Card and a valid Photo ID to the examination center.</li>
                    <li>Candidates must reach the examination center at least 30 minutes before the commencement of the exam.</li>
                    <li>Electronic devices like mobile phones, smartwatches, and calculators are strictly prohibited.</li>
                    <li>Use only Black or Blue pen. Pencil is not allowed for answering on the OMR sheet.</li>
                    <li>Candidates arriving 15 minutes after the exam starts will not be permitted to enter.</li>
                </ul>
            </div>
            
            <div class="footer">
                <div class="sig-line">Date block: <?php echo date('Y-m-d'); ?></div>
                <div class="sig-line">Authorized Signatory</div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500); 
        };
    </script>
</body>
</html>
