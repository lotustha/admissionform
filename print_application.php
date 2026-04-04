<?php
// print_application.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

// Allow access for admins OR logged-in students (students can only view their own)
$is_admin = isset($_SESSION['admin_id']);
$is_student = isset($_SESSION['student_id']);

if (!$is_admin && !$is_student) {
    die("Unauthorized access. Please log in.");
}

if (!isset($_GET['id'])) {
    die("Invalid Request.");
}

$id = (int)$_GET['id'];

// Students can only view their own application
if ($is_student && !$is_admin && $id !== (int)$_SESSION['student_id']) {
    die("Unauthorized access. You can only view your own application.");
}
$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id LEFT JOIN entrance_schedules e ON i.schedule_id = e.id WHERE i.id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inq) {
    die("Application not found.");
}

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$logo = $settings['logo_path'] ?? '';

// Helper to check if file is an image
function isImage($path) {
    if (empty($path) || !file_exists(__DIR__ . '/' . $path)) return false;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Application - <?php echo htmlspecialchars($inq['student_first_name'] . ' ' . $inq['student_last_name']); ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #333;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .a4-sheet {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            margin: 0 auto;
            position: relative;
            padding: 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .header img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .header-text {
            flex-grow: 1;
            text-align: center;
        }
        .header-text h1 {
            margin: 0;
            font-size: 20px;
            color: #064e3b;
            text-transform: uppercase;
        }
        .header-text p {
            margin: 2px 0;
            font-size: 14px;
            color: #4b5563;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            text-decoration: underline;
        }
        
        .flex-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .photo-box {
            width: 35mm;
            height: 45mm;
            border: 1px solid #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .section-title {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 14px;
            margin: 15px 0 10px 0;
            color: #065f46;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        td {
            padding: 6px 4px;
            vertical-align: top;
        }
        .td-label {
            font-weight: 600;
            width: 20%;
            color: #4b5563;
        }
        .td-value {
            width: 30%;
            border-bottom: 1px dotted #ccc;
            font-weight: bold;
        }
        
        .full-width-value {
            border-bottom: 1px dotted #ccc;
            font-weight: bold;
        }
        
        .declaration {
            margin-top: 30px;
            font-size: 12px;
            text-align: justify;
            color: #4b5563;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .sig-box {
            width: 200px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            font-weight: bold;
        }

        .page-break {
            page-break-before: always;
        }

        .attachment-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .attachment-img {
            max-width: 100%;
            max-height: 240mm;
            display: block;
            margin: 0 auto;
            border: 1px solid #d1d5db;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }
            .a4-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

<!-- First Page: Form Data -->
<div class="a4-sheet">
    <div class="header">
        <div style="width:80px">
            <?php if ($logo): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="header-text">
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p>Application Form - 2083 BS</p>
        </div>
        <div style="width:80px; text-align:right;">
            <span style="font-size: 10px; color:#6b7280;">ID: <?php echo str_pad($inq['id'], 5, '0', STR_PAD_LEFT); ?></span><br>
            <?php if ($inq['form_type'] === 'Admission'): ?>
                <span style="font-weight:bold; font-size:12px; color:#b91c1c;"><?php echo htmlspecialchars($inq['entrance_roll_no']); ?></span>
            <?php else: ?>
                <span style="font-weight:bold; font-size:12px; color:#2563eb;">INQUIRY</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="title">
        <?php echo $inq['form_type'] === 'Admission' ? 'Admission Form' : 'Inquiry Form'; ?>
    </div>

    <div class="flex-row">
        <div style="flex-grow: 1; padding-right: 20px;">
            <div class="section-title" style="margin-top:0;">Program Details</div>
            <table>
                <tr>
                    <td class="td-label">Applied Class:</td>
                    <td class="td-value" colspan="3"><?php echo htmlspecialchars($inq['applied_class']); ?></td>
                </tr>
                <?php if ($inq['faculty_name']): ?>
                <tr>
                    <td class="td-label">Faculty:</td>
                    <td class="td-value" colspan="3"><?php echo htmlspecialchars($inq['faculty_name']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="td-label">Fee Status:</td>
                    <td class="td-value" colspan="3">
                        <?php 
                        $pay_st = $inq['payment_status'] ?? 'Pending';
                        if ($pay_st === 'Paid'): ?>
                            <span style="color:#059669; font-weight:bold;">✓ PAID</span>
                            <?php if (!empty($inq['payment_amount'])): ?>
                                — Rs. <?php echo number_format($inq['payment_amount'], 2); ?>
                            <?php endif; ?>
                            <?php if (!empty($inq['payment_method'])): ?>
                                (<?php echo htmlspecialchars($inq['payment_method']); ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#b91c1c; font-weight:bold;">⏳ PENDING</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($inq['payment_reference'])): ?>
                <tr>
                    <td class="td-label">Payment Ref:</td>
                    <td class="td-value" colspan="3"><?php echo htmlspecialchars($inq['payment_reference']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div class="section-title">Personal Details</div>
            <table>
                <tr>
                    <td class="td-label">First Name:</td>
                    <td class="td-value"><?php echo htmlspecialchars(strtoupper($inq['student_first_name'])); ?></td>
                    <td class="td-label" style="padding-left:15px;">Last Name:</td>
                    <td class="td-value"><?php echo htmlspecialchars(strtoupper($inq['student_last_name'])); ?></td>
                </tr>
                <tr>
                    <td class="td-label">Gender:</td>
                    <td class="td-value"><?php echo htmlspecialchars($inq['gender']); ?></td>
                    <td class="td-label" style="padding-left:15px;">DOB (BS):</td>
                    <td class="td-value"><?php echo htmlspecialchars($inq['dob_bs']); ?></td>
                </tr>
                <tr>
                    <td class="td-label">Email:</td>
                    <td class="td-value" colspan="3"><?php echo htmlspecialchars($inq['student_email'] ?? 'N/A'); ?></td>
                </tr>
            </table>
        </div>
        <div class="photo-box">
            <?php if (isImage($inq['pp_photo_path'])): ?>
                <img src="<?php echo htmlspecialchars($inq['pp_photo_path']); ?>" alt="PP Size Photo">
            <?php else: ?>
                <span style="font-size:10px; color:#9ca3af;">PP PHOTO</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title">Family Information</div>
    <table>
        <tr>
            <td class="td-label">Father's Name:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['father_name']); ?></td>
            <td class="td-label" style="padding-left:15px;">Contact No:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['father_contact']); ?></td>
        </tr>
        <tr>
            <td class="td-label">Mother's Name:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['mother_name'] ?? 'N/A'); ?></td>
            <td class="td-label" style="padding-left:15px;">Contact No:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['mother_contact'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <td class="td-label">Local Guardian:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['local_guardian_name'] ?? 'N/A'); ?></td>
            <td class="td-label" style="padding-left:15px;">Relation / No:</td>
            <td class="td-value"><?php echo htmlspecialchars(($inq['guardian_relation'] ?? '') . ' / ' . ($inq['guardian_contact'] ?? '')); ?></td>
        </tr>
    </table>

    <div class="section-title">Address Information</div>
    <table>
        <tr>
            <td class="td-label">Province:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['address_province']); ?></td>
            <td class="td-label" style="padding-left:15px;">District:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['address_district']); ?></td>
        </tr>
        <tr>
            <td class="td-label">Municipality:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['address_municipality']); ?></td>
            <td class="td-label" style="padding-left:15px;">Ward & Vill:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['address_ward_village']); ?></td>
        </tr>
    </table>

    <div class="section-title">Academic Background</div>
    <table>
        <tr>
            <td class="td-label">Previous School:</td>
            <td class="td-value" colspan="3"><?php echo htmlspecialchars($inq['previous_school_name'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <td class="td-label">GPA/Percentage:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['gpa_or_percentage'] ?? 'N/A'); ?></td>
            <td class="td-label" style="padding-left:15px;">SEE Symbol:</td>
            <td class="td-value"><?php echo htmlspecialchars($inq['see_symbol_no'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <div class="declaration">
        <b>Declaration:</b> I hereby declare that all the information provided above is true and correct to the best of my knowledge. I agree to abide by the rules and regulations of the institution. If any information is found incorrect, my admission can be cancelled at any time.
    </div>

    <div class="signatures">
        <div class="sig-box">Student's Signature</div>
        <div class="sig-box">Guardian's Signature</div>
        <div class="sig-box">Principal / Auth. Signatory</div>
    </div>
    
    <div style="text-align:center; font-size:10px; color:#9ca3af; margin-top:20px;">
        Generated on <?php echo date('Y-m-d H:i:s'); ?>. Status: <?php echo htmlspecialchars($inq['status']); ?>
    </div>
</div>

<!-- Print Attachments on Next Pages -->
<?php if (isImage($inq['document_path'])): ?>
<div class="a4-sheet page-break">
    <div class="attachment-title">Attachment: Academic Marksheet</div>
    <img src="<?php echo htmlspecialchars($inq['document_path']); ?>" class="attachment-img" alt="Marksheet">
</div>
<?php endif; ?>

<?php if (isImage($inq['birth_cert_path'])): ?>
<div class="a4-sheet page-break">
    <div class="attachment-title">Attachment: Birth Certificate</div>
    <img src="<?php echo htmlspecialchars($inq['birth_cert_path']); ?>" class="attachment-img" alt="Birth Certificate">
</div>
<?php endif; ?>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500); // Wait half a second for images to load
    };
</script>

</body>
</html>
