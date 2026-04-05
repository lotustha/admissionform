<?php
// process_form.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!check_rate_limit('process_form', 5, 60)) {
    die("Too many requests. Please try again later.");
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    die("Invalid security token. Please refresh the page and try again.");
}

// Ensure DB schema is up to date before starting any transactions
try {
    $pdo->exec("ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS birth_cert_path VARCHAR(255) NULL AFTER document_path;");
    $pdo->exec("ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS student_email VARCHAR(150) NULL AFTER student_last_name;");
    $pdo->exec("ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS form_type ENUM('Admission', 'Inquiry') DEFAULT 'Admission' AFTER status;");
}
catch (PDOException $e) {
}

try {
    $pdo->beginTransaction();

    // 1. Check Capacity constraints (if entrance schedule is provided)
    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
    if ($schedule_id) {
        $stmt = $pdo->prepare("SELECT e.*, (e.total_capacity - (SELECT COUNT(*) FROM admission_inquiries a WHERE a.schedule_id = e.id)) AS available_seats FROM entrance_schedules e WHERE e.id = ? FOR UPDATE");
        $stmt->execute([$schedule_id]);
        $schedule = $stmt->fetch();

        if (!$schedule || $schedule['available_seats'] <= 0) {
            throw new Exception("The selected entrance exam slot is full. Please choose another.");
        }
    }

    // 2. Handle File Uploads
    $uploadDir = __DIR__ . '/assets/uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $pp_photo_path = '';
    $document_path = '';
    $birth_cert_path = '';

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $allowed_images = ['image/jpeg', 'image/png', 'image/jpg'];
    $allowed_docs = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

    if (isset($_FILES['pp_photo']) && $_FILES['pp_photo']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['pp_photo']['tmp_name']);
        if (!in_array($mime, $allowed_images)) {
            throw new Exception("Invalid Passport Photo format. Only JPG/PNG allowed.");
        }

        $ext = pathinfo($_FILES['pp_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'pp_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['pp_photo']['tmp_name'], $uploadDir . $filename)) {
            $pp_photo_path = 'assets/uploads/documents/' . $filename;
        }
        else {
            throw new Exception("Failed to upload Passport Photo.");
        }
    }

    if (isset($_FILES['marksheet_doc']) && $_FILES['marksheet_doc']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['marksheet_doc']['tmp_name']);
        if (!in_array($mime, $allowed_docs)) {
            throw new Exception("Invalid Marksheet format. Only PDF/JPG/PNG allowed.");
        }

        $ext = pathinfo($_FILES['marksheet_doc']['name'], PATHINFO_EXTENSION);
        $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['marksheet_doc']['tmp_name'], $uploadDir . $filename)) {
            $document_path = 'assets/uploads/documents/' . $filename;
        }
    }

    if (isset($_FILES['birth_cert']) && $_FILES['birth_cert']['error'] === UPLOAD_ERR_OK) {
        $mime = finfo_file($finfo, $_FILES['birth_cert']['tmp_name']);
        if (!in_array($mime, $allowed_docs)) {
            throw new Exception("Invalid Birth Certificate format. Only PDF/JPG/PNG allowed.");
        }

        $ext = pathinfo($_FILES['birth_cert']['name'], PATHINFO_EXTENSION);
        $filename = 'bc_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['birth_cert']['tmp_name'], $uploadDir . $filename)) {
            $birth_cert_path = 'assets/uploads/documents/' . $filename;
        }
    }
    // finfo is an object in PHP 8+, no need to close

    // 3. Generate Roll Number (Only for Admissions)
    $entrance_roll_no = null;
    if (($_POST['form_type'] ?? 'Admission') === 'Admission') {
        $entrance_roll_no = generateRollNumber($pdo);
    }

    // 4. Insert Record into admission_inquiries
    $sql = "INSERT INTO admission_inquiries (
        student_first_name, student_last_name, student_email, dob_bs, dob_ad, gender,
        pp_photo_path, address_province, address_district, address_municipality, address_ward_village,
        father_name, father_contact, mother_name, mother_contact,
        local_guardian_name, guardian_contact, guardian_relation,
        applied_class, faculty_id, optional_subject_1, optional_subject_2,
        previous_school_name, gpa_or_percentage, see_symbol_no,
        schedule_id, entrance_roll_no, document_path, birth_cert_path, declaration_accepted, status, form_type, session_id
    ) VALUES (
        :student_first_name, :student_last_name, :student_email, :dob_bs, :dob_ad, :gender,
        :pp_photo_path, :address_province, :address_district, :address_municipality, :address_ward_village,
        :father_name, :father_contact, :mother_name, :mother_contact,
        :local_guardian_name, :guardian_contact, :guardian_relation,
        :applied_class, :faculty_id, :optional_subject_1, :optional_subject_2,
        :previous_school_name, :gpa_or_percentage, :see_symbol_no,
        :schedule_id, :entrance_roll_no, :document_path, :birth_cert_path, :declaration_accepted, 'Pending', :form_type, :session_id
    )";

    $stmt = $pdo->prepare($sql);
    // Helper to capitalize names properly (e.g. "shristi sashankar" → "Shristi Sashankar")
    $ucname = fn($v) => ucwords(strtolower(trim($v ?? '')));
    $stmt->execute([
        'student_first_name' => $ucname($_POST['student_first_name']),
        'student_last_name'  => $ucname($_POST['student_last_name']),
        'student_email' => $_POST['student_email'] ?? null,
        'dob_bs' => $_POST['dob_bs'],
        'dob_ad' => !empty($_POST['dob_ad']) ? $_POST['dob_ad'] : null,
        'gender' => $_POST['gender'],
        'pp_photo_path' => $pp_photo_path,
        'address_province' => $_POST['address_province'],
        'address_district' => $_POST['address_district'],
        'address_municipality' => $_POST['address_municipality'],
        'address_ward_village' => $_POST['address_ward_village'],
        'father_name'        => $ucname($_POST['father_name']),
        'father_contact' => $_POST['father_contact'],
        'mother_name'        => !empty($_POST['mother_name']) ? $ucname($_POST['mother_name']) : null,
        'mother_contact' => $_POST['mother_contact'] ?? null,
        'local_guardian_name' => !empty($_POST['local_guardian_name']) ? $ucname($_POST['local_guardian_name']) : null,
        'guardian_contact' => $_POST['guardian_contact'] ?? null,
        'guardian_relation' => $_POST['guardian_relation'] ?? null,
        'applied_class' => $_POST['applied_class'],
        'faculty_id' => !empty($_POST['faculty_id']) ? $_POST['faculty_id'] : null,
        'optional_subject_1' => !empty($_POST['optional_subject_1']) ? $_POST['optional_subject_1'] : null,
        'optional_subject_2' => !empty($_POST['optional_subject_2']) ? $_POST['optional_subject_2'] : null,
        'previous_school_name' => $_POST['previous_school_name'] ?? null,
        'gpa_or_percentage' => !empty($_POST['gpa_or_percentage']) ? $_POST['gpa_or_percentage'] : null,
        'see_symbol_no' => $_POST['see_symbol_no'] ?? null,
        'schedule_id' => $schedule_id,
        'entrance_roll_no' => $entrance_roll_no,
        'document_path' => $document_path,
        'birth_cert_path' => $birth_cert_path,
        'declaration_accepted' => isset($_POST['declaration_accepted']) ? 1 : 0,
        'form_type' => $_POST['form_type'] ?? 'Admission',
        'session_id' => !empty($_POST['session_id']) ? (int)$_POST['session_id'] : null,
    ]);

    $inquiry_id = $pdo->lastInsertId();
    $pdo->commit();

    // -- EMAIL NOTIFICATION START --
    $settings = getSchoolSettings($pdo);
    $org_email = $settings['org_email'] ?? '';
    $student_email = $_POST['student_email'] ?? '';
    $emails_to_send = [];
    if (!empty($org_email) && filter_var($org_email, FILTER_VALIDATE_EMAIL)) $emails_to_send[] = $org_email;
    if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) $emails_to_send[] = $student_email;

    if (!empty($emails_to_send)) {
        $form_type_str = $_POST['form_type'] ?? 'Admission';
        $student_name = ($_POST['student_first_name'] ?? '') . " " . ($_POST['student_last_name'] ?? '');
        $subject = "Your " . $form_type_str . " Application - " . ($settings['school_name'] ?? 'School');

        // Build Attractive HTML Email Body
        $html_message = "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background-color: #059669; color: #ffffff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 24px;'>Application Received</h2>
                </div>
                <div style='padding: 30px; color: #333333;'>
                    <p style='font-size: 16px;'>Hello <b>{$student_name}</b>,</p>
                    <p style='font-size: 15px; line-height: 1.6;'>Thank you for submitting your " . strtolower($form_type_str) . " request to <b>" . htmlspecialchars($settings['school_name'] ?? 'our school') . "</b>.</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'>Applied Class</td><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>" . htmlspecialchars($_POST['applied_class'] ?? 'N/A') . "</td></tr>
                        <tr><td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'>Contact Phone</td><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>" . htmlspecialchars($_POST['father_contact'] ?? 'N/A') . "</td></tr>";
        
        if (!empty($entrance_roll_no)) {
            $html_message .= "<tr><td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'>Entrance Roll No</td><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; color: #059669;'>" . htmlspecialchars($entrance_roll_no) . "</td></tr>";
        }
        
        $html_message .= "
                    </table>
                    
                    <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin-top: 25px;'>
                        <h3 style='margin: 0 0 10px; font-size: 16px; color: #065f46;'>📋 Access Your Student Dashboard</h3>
                        <p style='font-size: 14px; color: #333; margin: 0 0 12px;'>You can now log in to your <strong>Student Dashboard</strong> to track your application status, upload documents, and more.</p>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr><td style='padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; font-size: 13px; color: #666; width: 40%;'>Login URL</td><td style='padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; font-weight: bold; font-size: 13px;'><a href='" . htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/status_check.php') . "' style='color: #059669;'>Student Portal Login</a></td></tr>" . 
                        (!empty($entrance_roll_no) ? "
                            <tr><td style='padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 13px; color: #666;'>Your Roll Number</td><td style='padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: bold; font-size: 15px; color: #b91c1c; letter-spacing: 1px;'>" . htmlspecialchars($entrance_roll_no) . "</td></tr>" : '') . "
                            <tr><td style='padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; font-size: 13px; color: #666;'>Date of Birth (BS)</td><td style='padding: 8px 12px; background: #fff; border: 1px solid #e5e7eb; font-weight: bold; font-size: 13px;'>" . htmlspecialchars($_POST['dob_bs'] ?? '') . "</td></tr>
                        </table>
                        <p style='font-size: 12px; color: #6b7280; margin: 10px 0 0;'>Use the Roll Number and Date of Birth (BS) above to log in.</p>
                    </div>
                    
                    <div style='background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 15px; margin-top: 15px;'>
                        <p style='font-size: 13px; color: #92400e; margin: 0;'>⏳ <strong>Next Step:</strong> Your admit card and entrance details will be available once your application is reviewed and the admission fee is confirmed. Please visit the school office during working hours to complete your fee payment.</p>
                    </div>
                    
                    <p style='margin-top: 20px; font-size: 14px; color: #666;'>Your detailed application form is attached to this email for your records.</p>
                </div>
                <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #999;'>
                    " . htmlspecialchars($settings['school_name'] ?? '') . " &copy; " . date('Y') . "
                </div>
            </div>
        </body>
        </html>";

        // Generate PDF Attachments if Dompdf is available
        $dynamic_attachments = [];
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            if (class_exists('\\Dompdf\\Dompdf')) {
                require_once __DIR__ . '/includes/pdf_generators.php';
                
                // Fetch the full student record (with joins) for PDF generation
                $pdf_stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                                           FROM admission_inquiries i 
                                           LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                                           LEFT JOIN faculties f ON i.faculty_id = f.id
                                           WHERE i.id = ?");
                $pdf_stmt->execute([$inquiry_id]);
                $pdf_student = $pdf_stmt->fetch(PDO::FETCH_ASSOC);
                
                $base_dir = realpath(__DIR__) . '/';
                $dompdf_options = new \Dompdf\Options();
                $dompdf_options->set('isRemoteEnabled', true);
                $dompdf_options->set('chroot', realpath(__DIR__));
                
                // Application Form PDF
                $app_html = generateApplicationFormHTML($pdf_student, $settings, $base_dir);
                $dompdf_app = new \Dompdf\Dompdf($dompdf_options);
                $dompdf_app->loadHtml($app_html);
                $dompdf_app->setPaper('A4', 'portrait');
                $dompdf_app->render();
                $dynamic_attachments['Application_Form.pdf'] = $dompdf_app->output();

                // Admit Card PDF — attach if "Allow Unpaid Admit Cards" is enabled
                $allow_unpaid = $settings['allow_unpaid_admit_card'] ?? '0';
                if ($allow_unpaid === '1' && ($_POST['form_type'] ?? 'Admission') === 'Admission' && !empty($schedule_id)) {
                    $admit_html = generateAdmitCardHTML($pdf_student, $settings, $base_dir);
                    $dompdf_admit = new \Dompdf\Dompdf($dompdf_options);
                    $dompdf_admit->loadHtml($admit_html);
                    $dompdf_admit->setPaper('A4', 'portrait');
                    $dompdf_admit->render();
                    $admit_roll = preg_replace('/[^A-Za-z0-9_-]/', '', $entrance_roll_no ?? 'NA');
                    $dynamic_attachments['Admit_Card_' . $admit_roll . '.pdf'] = $dompdf_admit->output();
                }
            }
        }

        // Physical File Attachments
        $file_attachments = [];
        if (!empty($pp_photo_path) && file_exists(__DIR__ . '/' . $pp_photo_path)) $file_attachments[] = __DIR__ . '/' . $pp_photo_path;
        if (!empty($document_path) && file_exists(__DIR__ . '/' . $document_path)) $file_attachments[] = __DIR__ . '/' . $document_path;
        if (!empty($birth_cert_path) && file_exists(__DIR__ . '/' . $birth_cert_path)) $file_attachments[] = __DIR__ . '/' . $birth_cert_path;

        $from_domain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'school-portal.local';
        $boundary = md5(time());
        $headers = "From: admissions@" . $from_domain . "\r\n";
        $headers .= "Reply-To: admissions@" . $from_domain . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $html_message . "\r\n\r\n";

        // Dynamic Attachments (PDFs)
        foreach ($dynamic_attachments as $filename => $content) {
            $encoded_content = chunk_split(base64_encode($content));
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $body .= $encoded_content . "\r\n\r\n";
        }

        // Physical Attachments
        foreach ($file_attachments as $file) {
            $content = chunk_split(base64_encode(file_get_contents($file)));
            $filename = basename($file);
            $mime_type = mime_content_type($file) ?: 'application/octet-stream';
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$mime_type}; name=\"{$filename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $body .= $content . "\r\n\r\n";
        }
        $body .= "--{$boundary}--";

        // Send to unique recipients
        $emails_to_send = array_unique($emails_to_send);
        foreach ($emails_to_send as $recipient) {
            @mail($recipient, $subject, $body, $headers);
        }
    }
    // -- EMAIL NOTIFICATION END --

    // Redirect based on form type and schedule
    if (($_POST['form_type'] ?? 'Admission') === 'Inquiry') {
        header("Location: inquiry_success.php?id=" . $inquiry_id);
    } else {
        // Auto log in the student so they have immediate dashboard access
        $_SESSION['student_id'] = $inquiry_id;
        $_SESSION['student_type'] = 'admission';

        // Always redirect to the beautiful confirmation page, even if there is an admit card,
        // so they understand their dashboard is active.
        header("Location: admission_success.php?id=" . $inquiry_id);
    }
    exit;

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("<div style='font-family:sans-serif; text-align:center; padding: 50px; background-color:#FEF2F2; color:#991B1B;'>
            <h2 style='font-size:24px; font-weight:bold; margin-bottom:10px;'>Submission Failed</h2>
            <p style='color:#7F1D1D; font-size:16px;'>" . htmlspecialchars($e->getMessage()) . "</p>
            <br>
            <a href='index.php' style='color:#047857; font-weight:bold; text-decoration:none; padding:10px 20px; background:#D1FAE5; border-radius:6px;'>Go Back to Form</a>
         </div>");
}
?>