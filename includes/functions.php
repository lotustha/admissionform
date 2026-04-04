<?php
// includes/functions.php
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Fetch all faculties.
 */
function getFaculties($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM faculties");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch optional subjects for a given faculty.
 */
function getFacultySubjects($pdo, $faculty_id) {
    if (!$faculty_id) return [];
    $stmt = $pdo->prepare("SELECT * FROM faculty_subjects WHERE faculty_id = ?");
    $stmt->execute([$faculty_id]);
    return $stmt->fetchAll();
}

/**
 * Fetch available entrance exam slots for a given class/faculty.
 * Filters out slots where available_seats <= 0.
 */
function getAvailableSlots($pdo, $class_name, $faculty_id = null) {
    // Determine capacity minus already booked slots.
    if ($faculty_id) {
         $sql = "SELECT e.*, 
                    (e.total_capacity - (SELECT COUNT(*) FROM admission_inquiries a WHERE a.schedule_id = e.id)) AS available_seats 
                 FROM entrance_schedules e 
                 WHERE e.class_name = :class_name AND e.faculty_id = :faculty_id AND e.exam_date >= CURDATE()
                 HAVING available_seats > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['class_name' => $class_name, 'faculty_id' => $faculty_id]);
    } else {
        $sql = "SELECT e.*, 
                    (e.total_capacity - (SELECT COUNT(*) FROM admission_inquiries a WHERE a.schedule_id = e.id)) AS available_seats 
                 FROM entrance_schedules e 
                 WHERE e.class_name = :class_name AND e.exam_date >= CURDATE()
                 HAVING available_seats > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['class_name' => $class_name]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Generate a unique entrance roll number
 */
function generateRollNumber($pdo) {
    $year = "2083";
    // We can use a simple format like ADM-2083-0001
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM admission_inquiries");
    $row = $stmt->fetch();
    $next_id = ($row['max_id'] ?? 0) + 1;
    
    // Pad to 4 digits
    $padded_id = str_pad($next_id, 4, '0', STR_PAD_LEFT);
    return "ADM-{$year}-{$padded_id}";
}

/**
 * Fetch and randomly select one Gemini API key from app_settings.
 */
function getRandomGeminiKey($pdo) {
    $stmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key` = 'gemini_api_keys' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    
    if (!$row || empty(trim($row['value']))) {
        return null;
    }
    
    $keysArray = array_filter(array_map('trim', explode(',', $row['value'])));
    
    if (empty($keysArray)) {
        return null;
    }
    
    return $keysArray[array_rand($keysArray)];
}

/**
 * Get overall school settings from app_settings key-value table.
 * Returns a flat associative array matching the old school_settings column names.
 */
function getSchoolSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * CSRF Protection
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Basic Rate Limiting using Session
 */
function check_rate_limit($action, $max_requests, $time_window_seconds) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    if (!isset($_SESSION['rate_limit'][$action])) {
        $_SESSION['rate_limit'][$action] = [];
    }
    
    $_SESSION['rate_limit'][$action] = array_filter($_SESSION['rate_limit'][$action], function($timestamp) use ($now, $time_window_seconds) {
        return ($now - $timestamp) < $time_window_seconds;
    });
    
    if (count($_SESSION['rate_limit'][$action]) >= $max_requests) {
        return false;
    }
    
    $_SESSION['rate_limit'][$action][] = $now;
    return true;
}

/**
 * Send Approval Notification Email (no admit card — that comes after payment).
 * Tells the student their application was approved and instructs them to pay at the school.
 */
function sendApprovalEmail($pdo, $inquiry_id) {
    $stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                           FROM admission_inquiries i 
                           LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                           LEFT JOIN faculties f ON i.faculty_id = f.id
                           WHERE i.id = ?");
    $stmt->execute([$inquiry_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || empty($student['student_email'])) {
        return false;
    }

    $settings = getSchoolSettings($pdo);
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $org_email = $settings['school_email'] ?? ($settings['org_email'] ?? 'noreply@school.com');
    $roll = htmlspecialchars($student['entrance_roll_no'] ?? 'N/A');
    $student_name = htmlspecialchars($student['student_first_name']);
    $applied_class = htmlspecialchars($student['applied_class']);
    $faculty = !empty($student['faculty_name']) ? ' — ' . htmlspecialchars($student['faculty_name']) : '';

    $to = $student['student_email'];
    $subject = "Application Approved — " . $school_name;

    $allow_unpaid_admit = $settings['allow_unpaid_admit_card'] ?? '0';

    if ($allow_unpaid_admit === '1') {
        $html = "
        <html>
        <body style='font-family: Arial, sans-serif; background:#f4f7f6; margin:0; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background:#059669; color:#fff; padding:20px; text-align:center;'>
                    <h2 style='margin:0; font-size:24px;'>Application Approved 🎉</h2>
                </div>
                <div style='padding:30px; color:#333;'>
                    <p style='font-size:16px;'>Congratulations <b>{$student_name}</b>!</p>
                    <p style='font-size:15px; line-height:1.6;'>Your application for <strong>{$applied_class}{$faculty}</strong> has been <strong style='color:#059669;'>approved</strong>.</p>
                    
                    <div style='background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:20px; margin:20px 0;'>
                        <h3 style='margin:0 0 10px; font-size:16px; color:#065f46;'>📎 Admit Card Attached</h3>
                        <p style='font-size:14px; color:#047857; margin:0; line-height:1.6;'>Your entrance examination admit card is attached to this email. Please print it and bring it to the examination hall.</p>
                    </div>

                    <div style='background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:15px;'>
                        <p style='font-size:13px; color:#92400e; margin:0;'>💰 <strong>Note:</strong> Please ensure you visit the school's administration office to complete your admission/entrance fee payment.</p>
                    </div>
                    
                    <p style='margin-top:20px; font-size:14px; color:#666;'>Best Regards,<br><strong>" . htmlspecialchars($school_name) . "</strong></p>
                </div>
                <div style='background:#f9f9f9; padding:15px; text-align:center; font-size:12px; color:#999;'>
                    " . htmlspecialchars($school_name) . " &copy; " . date('Y') . "
                </div>
            </div>
        </body>
        </html>";

        $dynamic_attachments = [];
        $autoload = realpath(__DIR__ . '/../vendor/autoload.php');
        if ($autoload && file_exists($autoload)) {
            require_once $autoload;
            if (class_exists('\\Dompdf\\Dompdf')) {
                require_once __DIR__ . '/pdf_generators.php';
                $base_dir = realpath(__DIR__ . '/../') . '/';
                $dompdf_options = new \Dompdf\Options();
                $dompdf_options->set('isRemoteEnabled', true);
                $dompdf_options->set('chroot', realpath(__DIR__ . '/../'));

                $admit_html = generateAdmitCardHTML($student, $settings, $base_dir);
                $dompdf_admit = new \Dompdf\Dompdf($dompdf_options);
                $dompdf_admit->loadHtml($admit_html);
                $dompdf_admit->setPaper('A4', 'portrait');
                $dompdf_admit->render();
                $dynamic_attachments['Admit_Card_' . preg_replace('/[^A-Za-z0-9_-]/', '', $roll) . '.pdf'] = $dompdf_admit->output();
            }
        }

        $boundary = md5(time() . mt_rand());
        $from_domain = 'school-portal.local';
        if (isset($_SERVER['HTTP_HOST'])) {
            $from_domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
        }
        $sender_email = $org_email ?: "admissions@{$from_domain}";

        $headers = "From: " . $sender_email . "\r\n";
        $headers .= "Reply-To: " . $sender_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";

        foreach ($dynamic_attachments as $filename => $content) {
            $encoded = chunk_split(base64_encode($content));
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $body .= $encoded . "\r\n\r\n";
        }
        $body .= "--{$boundary}--";

        return @mail($to, $subject, $body, $headers);

    } else {
        $html = "
        <html>
        <body style='font-family: Arial, sans-serif; background:#f4f7f6; margin:0; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background:#059669; color:#fff; padding:20px; text-align:center;'>
                    <h2 style='margin:0; font-size:24px;'>Application Approved 🎉</h2>
                </div>
                <div style='padding:30px; color:#333;'>
                    <p style='font-size:16px;'>Congratulations <b>{$student_name}</b>!</p>
                    <p style='font-size:15px; line-height:1.6;'>Your application for <strong>{$applied_class}{$faculty}</strong> has been <strong style='color:#059669;'>approved</strong>.</p>
                    
                    <div style='background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:20px; margin:20px 0;'>
                        <h3 style='margin:0 0 10px; font-size:16px; color:#92400e;'>⏳ Next Step: Fee Payment</h3>
                        <p style='font-size:14px; color:#78350f; margin:0; line-height:1.6;'>Please visit the school's administration office during working hours to complete your <strong>admission/entrance fee payment</strong>. Bring your roll number: <strong style='color:#b91c1c;'>{$roll}</strong></p>
                    </div>

                    <div style='background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:15px;'>
                        <p style='font-size:13px; color:#065f46; margin:0;'>📋 After payment is confirmed, your <strong>Admit Card</strong> and <strong>Payment Receipt</strong> will be emailed to you and will be available on your Student Dashboard.</p>
                    </div>
                    
                    <p style='margin-top:20px; font-size:14px; color:#666;'>Best Regards,<br><strong>" . htmlspecialchars($school_name) . "</strong></p>
                </div>
                <div style='background:#f9f9f9; padding:15px; text-align:center; font-size:12px; color:#999;'>
                    " . htmlspecialchars($school_name) . " &copy; " . date('Y') . "
                </div>
            </div>
        </body>
        </html>";

        $headers = "From: " . $org_email . "\r\n";
        $headers .= "Reply-To: " . $org_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return @mail($to, $subject, $html, $headers);
    }
}

/**
 * Send Payment Confirmation Email with Admit Card + Receipt PDFs attached.
 * Called after admin confirms payment in view_application.php or fee_collection.php.
 *
 * @param PDO $pdo
 * @param int $student_id
 * @return bool
 */
function sendPaymentConfirmationEmail($pdo, $student_id) {
    // Fetch full student record with joins
    $stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                           FROM admission_inquiries i 
                           LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                           LEFT JOIN faculties f ON i.faculty_id = f.id
                           WHERE i.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) return false;

    $settings = getSchoolSettings($pdo);
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $org_email = $settings['org_email'] ?? '';
    $student_email = $student['student_email'] ?? '';
    $roll = $student['entrance_roll_no'] ?? 'N/A';
    $student_name = htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']);
    $amount = number_format((float)($student['payment_amount'] ?? 0), 2);

    // Determine recipients
    $recipients = [];
    if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) $recipients[] = $student_email;
    if (!empty($org_email) && filter_var($org_email, FILTER_VALIDATE_EMAIL)) $recipients[] = $org_email;
    $recipients = array_unique($recipients);

    if (empty($recipients)) return false;

    // Generate PDFs via DOMPDF
    $dynamic_attachments = [];
    $autoload = realpath(__DIR__ . '/../vendor/autoload.php');
    if ($autoload && file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('\\Dompdf\\Dompdf')) {
            require_once __DIR__ . '/pdf_generators.php';

            $base_dir = realpath(__DIR__ . '/../') . '/';
            $dompdf_options = new \Dompdf\Options();
            $dompdf_options->set('isRemoteEnabled', true);
            $dompdf_options->set('chroot', realpath(__DIR__ . '/../'));

            // Admit Card PDF
            $admit_html = generateAdmitCardHTML($student, $settings, $base_dir);
            $dompdf_admit = new \Dompdf\Dompdf($dompdf_options);
            $dompdf_admit->loadHtml($admit_html);
            $dompdf_admit->setPaper('A4', 'portrait');
            $dompdf_admit->render();
            $dynamic_attachments['Admit_Card_' . preg_replace('/[^A-Za-z0-9_-]/', '', $roll) . '.pdf'] = $dompdf_admit->output();

            // Receipt PDF
            $receipt_html = generateReceiptHTML($student, $settings, $base_dir);
            $dompdf_receipt = new \Dompdf\Dompdf($dompdf_options);
            $dompdf_receipt->loadHtml($receipt_html);
            $dompdf_receipt->setPaper('A4', 'portrait');
            $dompdf_receipt->render();
            $invoice_number = $student['invoice_number'] ?? ('INV' . str_pad($student['id'], 3, '0', STR_PAD_LEFT));
            $dynamic_attachments['Receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '', $invoice_number) . '.pdf'] = $dompdf_receipt->output();
        }
    }

    // Build email
    $subject = "Payment Confirmed — Admit Card & Receipt — " . $school_name;

    $html_message = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <div style='background-color: #059669; color: #ffffff; padding: 20px; text-align: center;'>
                <h2 style='margin: 0; font-size: 24px;'>Payment Confirmed ✓</h2>
            </div>
            <div style='padding: 30px; color: #333333;'>
                <p style='font-size: 16px;'>Dear <b>{$student_name}</b>,</p>
                <p style='font-size: 15px; line-height: 1.6;'>Your payment of <strong>Rs. {$amount}</strong> for <strong>" . htmlspecialchars($student['applied_class']) . "</strong> admission has been successfully confirmed at <b>" . htmlspecialchars($school_name) . "</b>.</p>
                
                <div style='background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 20px; margin-top: 20px;'>
                    <h3 style='margin: 0 0 10px; font-size: 16px; color: #065f46;'>📎 Attached Documents</h3>
                    <ul style='margin: 0; padding-left: 18px; font-size: 14px; color: #047857; line-height: 1.8;'>
                        <li><strong>Admit Card</strong> — Print and bring to the entrance examination hall</li>
                        <li><strong>Payment Receipt</strong> — Keep for your records</li>
                    </ul>
                </div>

                <div style='background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; margin-top: 15px;'>
                    <p style='font-size: 13px; color: #1e40af; margin: 0;'>📋 You can also access your admit card and receipt anytime from your <strong>Student Dashboard</strong> using your Roll Number (<strong>" . htmlspecialchars($roll) . "</strong>) and Date of Birth (BS).</p>
                </div>

                <p style='margin-top: 20px; font-size: 14px; color: #666;'>Best regards,<br><strong>" . htmlspecialchars($school_name) . "</strong></p>
            </div>
            <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #999;'>
                " . htmlspecialchars($school_name) . " &copy; " . date('Y') . "
            </div>
        </div>
    </body>
    </html>";

    // MIME boundary
    $boundary = md5(time() . mt_rand());
    $from_domain = 'school-portal.local';
    if (isset($_SERVER['HTTP_HOST'])) {
        $from_domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
    }

    $headers = "From: admissions@{$from_domain}\r\n";
    $headers .= "Reply-To: " . ($org_email ?: "admissions@{$from_domain}") . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $html_message . "\r\n\r\n";

    // Attach PDFs
    foreach ($dynamic_attachments as $filename => $content) {
        $encoded = chunk_split(base64_encode($content));
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $body .= $encoded . "\r\n\r\n";
    }
    $body .= "--{$boundary}--";

    // Send to all recipients
    $success = true;
    foreach ($recipients as $to) {
        if (!@mail($to, $subject, $body, $headers)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Send Entrance Exam Result Email to student (and institute copy).
 * Different templates for Pass / Fail / Waitlisted.
 *
 * @param PDO $pdo
 * @param int $student_id
 * @return bool
 */
function sendResultEmail($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT i.*, f.faculty_name
                           FROM admission_inquiries i
                           LEFT JOIN faculties f ON i.faculty_id = f.id
                           WHERE i.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) return false;

    $settings = getSchoolSettings($pdo);
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $org_email = $settings['org_email'] ?? '';
    $student_email = $student['student_email'] ?? '';
    $student_name = htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']);
    $roll = htmlspecialchars($student['entrance_roll_no'] ?? 'N/A');
    $class = htmlspecialchars($student['applied_class']);
    $faculty = !empty($student['faculty_name']) ? ' — ' . htmlspecialchars($student['faculty_name']) : '';

    $marks = (float)($student['marks_obtained'] ?? 0);
    $total = (float)($student['total_marks'] ?? 100);
    $percentage = $total > 0 ? round(($marks / $total) * 100, 1) : 0;
    $result_status = $student['result_status'] ?? 'Pending';
    $remarks = htmlspecialchars($student['result_remarks'] ?? '');

    // Recipients
    $recipients = [];
    if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) $recipients[] = $student_email;
    if (!empty($org_email) && filter_var($org_email, FILTER_VALIDATE_EMAIL)) $recipients[] = $org_email;
    $recipients = array_unique($recipients);
    if (empty($recipients)) return false;

    // Status-specific styling
    $status_configs = [
        'Pass' => [
            'color' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0',
            'icon' => '🎉', 'title' => 'Congratulations! You Passed!',
            'badge_bg' => '#059669', 'badge_text' => 'PASSED',
        ],
        'Fail' => [
            'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca',
            'icon' => '📋', 'title' => 'Entrance Exam Result',
            'badge_bg' => '#dc2626', 'badge_text' => 'NOT SELECTED',
        ],
        'Waitlisted' => [
            'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a',
            'icon' => '⏳', 'title' => 'You Are Waitlisted',
            'badge_bg' => '#d97706', 'badge_text' => 'WAITLISTED',
        ],
    ];
    $cfg = $status_configs[$result_status] ?? $status_configs['Fail'];

    // Status-specific message blocks
    $action_block = '';
    if ($result_status === 'Pass') {
        $action_block = "
            <div style='background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:20px; margin-top:20px;'>
                <h3 style='margin:0 0 10px; font-size:16px; color:#065f46;'>✅ Next Step: Complete Your Enrollment</h3>
                <p style='font-size:14px; color:#047857; margin:0; line-height:1.6;'>Please visit the school administration office with your original documents to complete the enrollment process. Bring your <strong>Roll Number: {$roll}</strong></p>
            </div>";
    } elseif ($result_status === 'Waitlisted') {
        $action_block = "
            <div style='background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:20px; margin-top:20px;'>
                <h3 style='margin:0 0 10px; font-size:16px; color:#92400e;'>⏳ What Happens Next?</h3>
                <p style='font-size:14px; color:#78350f; margin:0; line-height:1.6;'>You have been placed on our waiting list. If a seat becomes available, we will contact you directly. Please keep your contact details up to date.</p>
            </div>";
    } else {
        $action_block = "
            <div style='background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:20px; margin-top:20px;'>
                <p style='font-size:14px; color:#0369a1; margin:0; line-height:1.6;'>Thank you for your participation. For any queries regarding the result, please contact the school administration office.</p>
            </div>";
    }

    $remarks_block = !empty($remarks) ? "
        <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-top:15px;'>
            <p style='font-size:12px; color:#64748b; margin:0 0 5px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;'>Remarks</p>
            <p style='font-size:14px; color:#334155; margin:0;'>{$remarks}</p>
        </div>" : '';

    $subject = "Entrance Exam Result — " . $school_name;

    $html_message = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <div style='background-color: {$cfg['color']}; color: #ffffff; padding: 24px; text-align: center;'>
                <div style='font-size:36px; margin-bottom:8px;'>{$cfg['icon']}</div>
                <h2 style='margin: 0; font-size: 22px;'>{$cfg['title']}</h2>
            </div>
            <div style='padding: 30px; color: #333333;'>
                <p style='font-size: 16px;'>Dear <b>{$student_name}</b>,</p>
                <p style='font-size: 15px; line-height: 1.6;'>Your entrance examination result for <strong>{$class}{$faculty}</strong> at <b>" . htmlspecialchars($school_name) . "</b> has been published.</p>

                <!-- Result Card -->
                <div style='background:{$cfg['bg']}; border:2px solid {$cfg['border']}; border-radius:12px; padding:24px; margin:20px 0; text-align:center;'>
                    <div style='display:inline-block; background:{$cfg['badge_bg']}; color:#fff; font-size:13px; font-weight:bold; padding:6px 20px; border-radius:20px; letter-spacing:1px; margin-bottom:16px;'>{$cfg['badge_text']}</div>
                    <div style='margin-top:12px;'>
                        <span style='font-size:42px; font-weight:900; color:{$cfg['color']};'>{$marks}</span>
                        <span style='font-size:20px; color:#94a3b8; font-weight:bold;'>/ {$total}</span>
                    </div>
                    <div style='font-size:14px; color:#64748b; margin-top:8px; font-weight:600;'>Percentage: {$percentage}%</div>
                    <div style='font-size:12px; color:#94a3b8; margin-top:6px;'>Roll No: {$roll}</div>
                </div>

                {$remarks_block}
                {$action_block}

                <div style='background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:15px; margin-top:15px;'>
                    <p style='font-size:13px; color:#1e40af; margin:0;'>📋 You can also view your complete result on your <strong>Student Dashboard</strong> using your Roll Number and Date of Birth (BS).</p>
                </div>

                <p style='margin-top:20px; font-size:14px; color:#666;'>Best regards,<br><strong>" . htmlspecialchars($school_name) . "</strong></p>
            </div>
            <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #999;'>
                " . htmlspecialchars($school_name) . " &copy; " . date('Y') . "
            </div>
        </div>
    </body>
    </html>";

    // Send
    $from_domain = 'school-portal.local';
    if (isset($_SERVER['HTTP_HOST'])) {
        $from_domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
    }

    $headers = "From: admissions@{$from_domain}\r\n";
    $headers .= "Reply-To: " . ($org_email ?: "admissions@{$from_domain}") . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $success = true;
    foreach ($recipients as $to) {
        if (!@mail($to, $subject, $html_message, $headers)) {
            $success = false;
        }
    }

    return $success;
}

?>
