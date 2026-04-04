<?php
// includes/pdf_generators.php
// Generates HTML strings for PDF rendering via DOMPDF (email attachments)

/**
 * Generate the full Admit Card HTML for DOMPDF rendering.
 * Mirrors the look of print_admit_card.php but uses absolute file paths for images.
 */
function generateAdmitCardHTML($student, $settings, $base_dir) {
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $logo_path = (!empty($settings['logo_path']) && file_exists($base_dir . $settings['logo_path'])) 
        ? $base_dir . $settings['logo_path'] : '';
    $photo_path = (!empty($student['pp_photo_path']) && file_exists($base_dir . $student['pp_photo_path'])) 
        ? $base_dir . $student['pp_photo_path'] : '';
    
    $logo_html = $logo_path 
        ? '<img src="'.htmlspecialchars($logo_path).'" style="max-height:70px; max-width:70px; border-radius:50%; object-fit:cover;">' 
        : '<div style="width:80px;"></div>';
    $photo_html = $photo_path 
        ? '<img src="'.htmlspecialchars($photo_path).'" style="width:120px;height:120px;object-fit:cover;border:2px solid #cbd5e1;">' 
        : '<div style="width:120px;height:120px;border:1px solid #ccc;text-align:center;line-height:120px;font-size:10px;background:#f8fafc;">No Photo</div>';

    $exam_date_f = !empty($student['exam_date']) ? htmlspecialchars(date('d M, Y (l)', strtotime($student['exam_date']))) : 'TBD';
    $exam_time_f = !empty($student['exam_time']) ? htmlspecialchars(date('h:i A', strtotime($student['exam_time']))) : 'TBD';
    $venue = !empty($student['venue']) ? htmlspecialchars($student['venue']) : 'TBD';
    $roll = htmlspecialchars($student['entrance_roll_no'] ?? 'N/A');
    $name = htmlspecialchars(strtoupper($student['student_first_name'] . ' ' . $student['student_last_name']));
    $class_faculty = htmlspecialchars($student['applied_class'] . (!empty($student['faculty_name']) ? ' - ' . $student['faculty_name'] : ''));
    $gender_dob = htmlspecialchars($student['gender']) . ' / ' . htmlspecialchars($student['dob_bs']);
    $father = htmlspecialchars($student['father_name'] ?? '');
    $contact = htmlspecialchars($student['father_contact'] ?? ($student['guardian_contact'] ?? ''));

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: sans-serif; font-size: 14px; color: #1e293b; margin: 0; padding: 20px; }
        .admit-card { border: 2px solid #059669; overflow: hidden; position: relative; }
        .watermark { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 70px; color: rgba(5,150,105,0.05); font-weight: 900; white-space: nowrap; z-index: 0; }
        .card-header { background: #059669; color: white; padding: 15px 20px; text-align: center; }
        .card-header h1 { margin: 8px 0 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        .card-header p { margin: 4px 0 0; font-size: 12px; opacity: 0.9; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 15px 0; color: #065f46; letter-spacing: 2px; }
        .title span { border-bottom: 2px dashed #059669; padding-bottom: 4px; }
        .body-row { padding: 10px 30px; }
        .photo-box { float: left; width: 130px; margin-right: 25px; text-align: center; }
        .sig-box-small { width: 120px; height: 50px; border: 1px solid #cbd5e1; margin-top: 12px; font-size: 9px; color: #94a3b8; text-align: center; padding-top: 35px; }
        .info-box { float: left; width: 400px; }
        .clear { clear: both; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 7px 5px; border-bottom: 1px solid #eee; }
        .label { font-weight: bold; color: #64748b; font-size: 11px; text-transform: uppercase; width: 45%; }
        .val { font-weight: bold; font-size: 13px; color: #0f172a; }
        .exam-box { background: #f0fdf4; border: 1px solid #10b981; padding: 12px; margin: 15px 30px; }
        .exam-box table { width: 100%; }
        .exam-box td { text-align: center; border-bottom: none; border-right: 1px solid #10b981; padding: 8px; }
        .exam-box td:last-child { border-right: none; }
        .exam-label { font-size: 10px; color: #065f46; font-weight: bold; text-transform: uppercase; }
        .exam-val { font-size: 13px; font-weight: bold; color: #022c22; margin-top: 4px; }
        .rules { margin: 0 30px 20px; padding-top: 15px; border-top: 1px dashed #cbd5e1; font-size: 11px; color: #475569; }
        .rules h4 { margin: 0 0 8px; color: #334155; font-size: 12px; }
        .rules ul { margin: 0; padding-left: 18px; }
        .rules li { margin-bottom: 5px; }
        .footer-sigs { margin: 0 30px 20px; }
        .footer-sigs table { width: 100%; }
        .footer-sigs td { text-align: center; border-bottom: none; padding-top: 35px; }
        .sig-line { border-top: 1px solid #0f172a; font-size: 11px; font-weight: bold; padding-top: 6px; display: inline-block; width: 180px; }
    </style></head><body>
        <div class="admit-card">
            <div class="watermark">' . htmlspecialchars($school_name) . '</div>
            <div class="card-header">
                ' . $logo_html . '
                <h1>' . htmlspecialchars($school_name) . '</h1>
                <p>Entrance Examination - ID: #' . str_pad($student['id'], 5, '0', STR_PAD_LEFT) . '</p>
            </div>
            <div class="title"><span>ADMIT CARD</span></div>
            <div class="body-row">
                <div class="photo-box">
                    ' . $photo_html . '
                    <div class="sig-box-small">Applicant\'s Signature</div>
                </div>
                <div class="info-box">
                    <table>
                        <tr><td class="label">Applicant Name</td><td class="val">' . $name . '</td></tr>
                        <tr><td class="label">Roll Number</td><td class="val" style="color:#b91c1c;font-size:16px;">' . $roll . '</td></tr>
                        <tr><td class="label">Applied Class / Program</td><td class="val">' . $class_faculty . '</td></tr>
                        <tr><td class="label">Gender / DOB (BS)</td><td class="val">' . $gender_dob . '</td></tr>
                        <tr><td class="label">Father\'s Name</td><td class="val">' . $father . '</td></tr>
                        <tr><td class="label">Contact Number</td><td class="val">' . $contact . '</td></tr>
                    </table>
                </div>
                <div class="clear"></div>
            </div>
            <div class="exam-box">
                <table><tr>
                    <td><div class="exam-label">Date of Examination</div><div class="exam-val">' . $exam_date_f . '</div></td>
                    <td><div class="exam-label">Time</div><div class="exam-val">' . $exam_time_f . '</div></td>
                    <td><div class="exam-label">Examination Venue</div><div class="exam-val">' . $venue . '</div></td>
                </tr></table>
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
            <div class="footer-sigs">
                <table><tr>
                    <td><div class="sig-line">Date: ' . date('Y-m-d') . '</div></td>
                    <td><div class="sig-line">Authorized Signatory</div></td>
                </tr></table>
            </div>
        </div>
    </body></html>';
}

/**
 * Generate the full Application Form HTML for DOMPDF rendering.
 * Mirrors the look of print_application.php but uses absolute file paths.
 */
function generateApplicationFormHTML($student, $settings, $base_dir) {
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $logo_path = (!empty($settings['logo_path']) && file_exists($base_dir . $settings['logo_path'])) 
        ? $base_dir . $settings['logo_path'] : '';
    $photo_path = (!empty($student['pp_photo_path']) && file_exists($base_dir . $student['pp_photo_path'])) 
        ? $base_dir . $student['pp_photo_path'] : '';
    
    $logo_html = $logo_path 
        ? '<img src="'.htmlspecialchars($logo_path).'" style="max-width:70px;max-height:70px;border-radius:50%;object-fit:cover;">' 
        : '';
    $photo_html = $photo_path 
        ? '<img src="'.htmlspecialchars($photo_path).'" style="width:100%;height:100%;object-fit:cover;">' 
        : '<span style="font-size:10px;color:#9ca3af;">PP PHOTO</span>';

    $form_type = $student['form_type'] ?? 'Admission';
    $roll_html = ($form_type === 'Admission') 
        ? '<span style="font-weight:bold;font-size:12px;color:#b91c1c;">' . htmlspecialchars($student['entrance_roll_no'] ?? '') . '</span>' 
        : '<span style="font-weight:bold;font-size:12px;color:#2563eb;">INQUIRY</span>';
    
    $pay_st = $student['payment_status'] ?? 'Pending';
    $pay_html = ($pay_st === 'Paid') 
        ? '<span style="color:#059669;font-weight:bold;">✓ PAID</span>' 
            . (!empty($student['payment_amount']) ? ' — Rs. ' . number_format($student['payment_amount'], 2) : '')
            . (!empty($student['payment_method']) ? ' (' . htmlspecialchars($student['payment_method']) . ')' : '')
        : '<span style="color:#b91c1c;font-weight:bold;">⏳ PENDING</span>';

    $e = function($key) use ($student) { return htmlspecialchars($student[$key] ?? 'N/A'); };

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: sans-serif; font-size: 12px; line-height: 1.5; color: #333; margin: 0; padding: 15px; }
        .header { text-align: center; border-bottom: 2px solid #059669; padding-bottom: 8px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; color: #064e3b; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 12px; color: #4b5563; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 15px; text-transform: uppercase; text-decoration: underline; }
        .section-title { background: #ecfdf5; border-left: 4px solid #10b981; padding: 3px 8px; font-weight: bold; font-size: 13px; margin: 12px 0 8px; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td { padding: 5px 4px; vertical-align: top; }
        .td-label { font-weight: 600; width: 22%; color: #4b5563; font-size: 11px; }
        .td-value { width: 28%; border-bottom: 1px dotted #ccc; font-weight: bold; font-size: 12px; }
        .declaration { margin-top: 20px; font-size: 11px; text-align: justify; color: #4b5563; }
        .sig-box { width: 160px; border-top: 1px solid #000; text-align: center; padding-top: 4px; font-weight: bold; font-size: 11px; }
    </style></head><body>
        <div class="header">
            ' . $logo_html . '
            <h1>' . htmlspecialchars($school_name) . '</h1>
            <p>Application Form - 2083 BS &nbsp; | &nbsp; ID: ' . str_pad($student['id'], 5, '0', STR_PAD_LEFT) . ' &nbsp; ' . $roll_html . '</p>
        </div>
        <div class="title">' . ($form_type === 'Admission' ? 'Admission Form' : 'Inquiry Form') . '</div>
        
        <div class="section-title">Program Details</div>
        <table>
            <tr><td class="td-label">Applied Class:</td><td class="td-value" colspan="3">' . $e('applied_class') . '</td></tr>
            ' . (!empty($student['faculty_name']) ? '<tr><td class="td-label">Faculty:</td><td class="td-value" colspan="3">' . htmlspecialchars($student['faculty_name']) . '</td></tr>' : '') . '
            <tr><td class="td-label">Fee Status:</td><td class="td-value" colspan="3">' . $pay_html . '</td></tr>
        </table>

        <div class="section-title">Personal Details</div>
        <table>
            <tr><td class="td-label">First Name:</td><td class="td-value">' . htmlspecialchars(strtoupper($student['student_first_name'] ?? '')) . '</td><td class="td-label">Last Name:</td><td class="td-value">' . htmlspecialchars(strtoupper($student['student_last_name'] ?? '')) . '</td></tr>
            <tr><td class="td-label">Gender:</td><td class="td-value">' . $e('gender') . '</td><td class="td-label">DOB (BS):</td><td class="td-value">' . $e('dob_bs') . '</td></tr>
            <tr><td class="td-label">Email:</td><td class="td-value" colspan="3">' . $e('student_email') . '</td></tr>
        </table>

        <div class="section-title">Family Information</div>
        <table>
            <tr><td class="td-label">Father\'s Name:</td><td class="td-value">' . $e('father_name') . '</td><td class="td-label">Contact No:</td><td class="td-value">' . $e('father_contact') . '</td></tr>
            <tr><td class="td-label">Mother\'s Name:</td><td class="td-value">' . htmlspecialchars($student['mother_name'] ?? 'N/A') . '</td><td class="td-label">Contact No:</td><td class="td-value">' . htmlspecialchars($student['mother_contact'] ?? 'N/A') . '</td></tr>
            <tr><td class="td-label">Local Guardian:</td><td class="td-value">' . htmlspecialchars($student['local_guardian_name'] ?? 'N/A') . '</td><td class="td-label">Relation / No:</td><td class="td-value">' . htmlspecialchars(($student['guardian_relation'] ?? '') . ' / ' . ($student['guardian_contact'] ?? '')) . '</td></tr>
        </table>

        <div class="section-title">Address Information</div>
        <table>
            <tr><td class="td-label">Province:</td><td class="td-value">' . $e('address_province') . '</td><td class="td-label">District:</td><td class="td-value">' . $e('address_district') . '</td></tr>
            <tr><td class="td-label">Municipality:</td><td class="td-value">' . $e('address_municipality') . '</td><td class="td-label">Ward & Vill:</td><td class="td-value">' . $e('address_ward_village') . '</td></tr>
        </table>

        <div class="section-title">Academic Background</div>
        <table>
            <tr><td class="td-label">Previous School:</td><td class="td-value" colspan="3">' . htmlspecialchars($student['previous_school_name'] ?? 'N/A') . '</td></tr>
            <tr><td class="td-label">GPA/Percentage:</td><td class="td-value">' . htmlspecialchars($student['gpa_or_percentage'] ?? 'N/A') . '</td><td class="td-label">SEE Symbol:</td><td class="td-value">' . htmlspecialchars($student['see_symbol_no'] ?? 'N/A') . '</td></tr>
        </table>

        <div class="declaration">
            <b>Declaration:</b> I hereby declare that all the information provided above is true and correct to the best of my knowledge. I agree to abide by the rules and regulations of the institution. If any information is found incorrect, my admission can be cancelled at any time.
        </div>

        <table style="margin-top:40px;"><tr>
            <td style="border-bottom:none;"><div class="sig-box">Student\'s Signature</div></td>
            <td style="border-bottom:none;"><div class="sig-box">Guardian\'s Signature</div></td>
            <td style="border-bottom:none;text-align:right;"><div class="sig-box">Principal / Auth. Signatory</div></td>
        </tr></table>

        <div style="text-align:center;font-size:9px;color:#9ca3af;margin-top:15px;">
            Generated on ' . date('Y-m-d H:i:s') . '. Status: ' . htmlspecialchars($student['status'] ?? 'Pending') . '
        </div>
    </body></html>';
}

/**
 * Generate a compact Payment Receipt HTML for DOMPDF rendering (email attachment).
 */
function generateReceiptHTML($student, $settings, $base_dir) {
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $school_address = $settings['address'] ?? '';
    $school_phone = $settings['contact_phone'] ?? '';
    $invoice_prefix = $settings['invoice_prefix'] ?? 'INV';
    
    $invoice_number = $student['invoice_number'] ?? ($invoice_prefix . str_pad($student['id'], 3, '0', STR_PAD_LEFT));
    $amount = (float)($student['payment_amount'] ?? 0);
    $method = htmlspecialchars($student['payment_method'] ?? 'Cash');
    $reference = htmlspecialchars($student['payment_reference'] ?? '');
    $payment_date = !empty($student['payment_date']) ? date('d M, Y', strtotime($student['payment_date'])) : date('d M, Y');
    $name = htmlspecialchars(strtoupper($student['student_first_name'] . ' ' . $student['student_last_name']));
    $roll = htmlspecialchars($student['entrance_roll_no'] ?? 'N/A');
    $class_faculty = htmlspecialchars($student['applied_class'] . (!empty($student['faculty_name']) ? ' - ' . $student['faculty_name'] : ''));
    
    $logo_path = (!empty($settings['logo_path']) && file_exists($base_dir . $settings['logo_path'])) 
        ? $base_dir . $settings['logo_path'] : '';
    $logo_html = $logo_path 
        ? '<img src="'.htmlspecialchars($logo_path).'" style="max-height:55px;max-width:55px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;padding:2px;">' 
        : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 20px; }
        .receipt { border: 1px solid #e2e8f0; position: relative; }
        .watermark { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 80px; color: rgba(5,150,105,0.04); font-weight: 900; white-space: nowrap; z-index: 0; letter-spacing: 8px; }
        .top-bar { height: 5px; background: linear-gradient(90deg, #059669, #10b981, #34d399, #10b981, #059669); }
        .header { padding: 20px 30px; display: table; width: 100%; border-bottom: 2px solid #f1f5f9; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; text-align: right; vertical-align: middle; }
        .org-name { font-size: 17px; font-weight: 800; color: #064e3b; text-transform: uppercase; }
        .org-sub { font-size: 10px; color: #64748b; margin-top: 2px; }
        .inv-label { font-size: 9px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; }
        .inv-num { font-size: 18px; font-weight: 700; color: #059669; margin-top: 2px; }
        .inv-status { display: inline-block; background: #dcfce7; color: #166534; font-size: 10px; font-weight: 700; padding: 2px 10px; border-radius: 10px; margin-top: 4px; letter-spacing: 1px; }
        .title-bar { background: linear-gradient(135deg, #065f46, #059669); color: white; text-align: center; padding: 10px; font-size: 14px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; }
        .body { padding: 20px 30px; position: relative; z-index: 1; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .info-col h4 { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .info-row { display: table; width: 100%; padding: 3px 0; font-size: 11px; }
        .info-row .lbl { display: table-cell; color: #64748b; width: 45%; }
        .info-row .val { display: table-cell; font-weight: 700; color: #0f172a; text-align: right; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #f1f5f9; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 10px 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        .items-table th:last-child, .items-table th:nth-child(3) { text-align: right; }
        .items-table td { padding: 12px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        .items-table td:last-child, .items-table td:nth-child(3) { text-align: right; font-weight: 600; }
        .total-box { width: 220px; float: right; border: 2px solid #e2e8f0; margin-top: 0; }
        .total-row { display: table; width: 100%; padding: 8px 14px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
        .total-row .lbl { display: table-cell; color: #64748b; }
        .total-row .val { display: table-cell; text-align: right; font-weight: 600; }
        .total-grand { background: linear-gradient(135deg, #065f46, #059669); padding: 12px 14px; }
        .total-grand .lbl { color: white; font-weight: 700; font-size: 12px; }
        .total-grand .val { color: white; font-weight: 800; font-size: 14px; }
        .clear { clear: both; }
        .payment-box { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px 16px; margin-top: 20px; display: table; width: 100%; }
        .payment-box .icon { display: table-cell; width: 35px; vertical-align: middle; }
        .payment-box .text { display: table-cell; vertical-align: middle; }
        .payment-box .title { font-size: 12px; font-weight: 700; color: #065f46; }
        .payment-box .meta { font-size: 10px; color: #047857; margin-top: 2px; }
        .footer { padding: 20px 30px; border-top: 2px solid #f1f5f9; margin-top: 30px; }
        .note { background: #fffbeb; border: 1px solid #fde68a; padding: 10px 14px; font-size: 10px; color: #92400e; line-height: 1.5; }
        .sigs { display: table; width: 100%; margin-top: 40px; }
        .sig-block { display: table-cell; text-align: center; width: 50%; }
        .sig-line { border-top: 1px solid #1e293b; font-size: 11px; font-weight: 700; color: #334155; padding-top: 6px; display: inline-block; width: 160px; }
        .gen-info { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 15px; }
    </style></head><body>
        <div class="receipt">
            <div class="watermark">PAID</div>
            <div class="top-bar"></div>
            <div class="header">
                <div class="header-left">
                    ' . $logo_html . '
                    <div style="display:inline-block;vertical-align:middle;margin-left:10px;">
                        <div class="org-name">' . htmlspecialchars($school_name) . '</div>
                        ' . ($school_address ? '<div class="org-sub">' . htmlspecialchars($school_address) . '</div>' : '') . '
                        ' . ($school_phone ? '<div class="org-sub">Tel: ' . htmlspecialchars($school_phone) . '</div>' : '') . '
                    </div>
                </div>
                <div class="header-right">
                    <div class="inv-label">Invoice Number</div>
                    <div class="inv-num">' . htmlspecialchars($invoice_number) . '</div>
                    <div class="inv-status">✓ PAID</div>
                </div>
            </div>
            <div class="title-bar">Payment Receipt</div>
            <div class="body">
                <div class="info-grid">
                    <div class="info-col">
                        <h4>Billed To</h4>
                        <div class="info-row"><span class="lbl">Student Name</span><span class="val">' . $name . '</span></div>
                        <div class="info-row"><span class="lbl">Roll Number</span><span class="val">' . $roll . '</span></div>
                        <div class="info-row"><span class="lbl">Applied For</span><span class="val">' . $class_faculty . '</span></div>
                    </div>
                    <div class="info-col">
                        <h4>Payment Details</h4>
                        <div class="info-row"><span class="lbl">Invoice No.</span><span class="val" style="color:#059669;">' . htmlspecialchars($invoice_number) . '</span></div>
                        <div class="info-row"><span class="lbl">Payment Date</span><span class="val">' . $payment_date . '</span></div>
                        <div class="info-row"><span class="lbl">Method</span><span class="val">' . $method . '</span></div>
                        ' . ($reference ? '<div class="info-row"><span class="lbl">Reference</span><span class="val">' . $reference . '</span></div>' : '') . '
                    </div>
                </div>
                <table class="items-table">
                    <thead><tr><th>#</th><th>Description</th><th>Rate</th><th>Amount</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Admission / Entrance Examination Fee</strong><br><span style="font-size:10px;color:#94a3b8;">For ' . $class_faculty . '</span></td>
                            <td>Rs. ' . number_format($amount, 2) . '</td>
                            <td>Rs. ' . number_format($amount, 2) . '</td>
                        </tr>
                    </tbody>
                </table>
                <div class="total-box">
                    <div class="total-row"><span class="lbl">Subtotal</span><span class="val">Rs. ' . number_format($amount, 2) . '</span></div>
                    <div class="total-row total-grand"><span class="lbl">Total Paid</span><span class="val">Rs. ' . number_format($amount, 2) . '</span></div>
                </div>
                <div class="clear"></div>
                <div class="payment-box">
                    <div class="text">
                        <div class="title">Payment Received Successfully</div>
                        <div class="meta">Rs. ' . number_format($amount, 2) . ' received via ' . $method . ' on ' . $payment_date . ($reference ? " (Ref: {$reference})" : '') . '</div>
                    </div>
                </div>
            </div>
            <div class="footer">
                <div class="note"><strong>Note:</strong> This is a computer-generated receipt. Please retain for your records.</div>
                <div class="sigs">
                    <div class="sig-block"><div class="sig-line">Received By</div></div>
                    <div class="sig-block"><div class="sig-line">Authorized Signatory</div></div>
                </div>
                <div class="gen-info">' . htmlspecialchars($invoice_number) . ' • Generated on ' . date('Y-m-d H:i:s') . ' • ' . htmlspecialchars($school_name) . '</div>
            </div>
        </div>
    </body></html>';
}

/**
 * Generate Entrance Exam Result Card HTML for DOMPDF
 * Shows marks, percentage, status badge, and remarks.
 */
function generateResultCardHTML($student, $settings, $base_dir) {
    $school_name = $settings['school_name'] ?? 'School Admission Portal';
    $address = $settings['address'] ?? '';
    
    // Setup Logo
    $logo_html = '';
    if (!empty($settings['logo_path'])) {
        $logo_path = $base_dir . '/' . ltrim($settings['logo_path'], '/');
        if (file_exists($logo_path)) {
            $type = pathinfo($logo_path, PATHINFO_EXTENSION);
            $data = file_get_contents($logo_path);
            if ($data !== false) {
                $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                $logo_html = '<img src="' . $base64 . '" alt="Logo" class="logo">';
            }
        }
    }

    $name = htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']);
    $roll = htmlspecialchars($student['entrance_roll_no'] ?? 'N/A');
    $class_faculty = htmlspecialchars($student['applied_class']);
    if (!empty($student['faculty_name'])) {
        $class_faculty .= ' - ' . htmlspecialchars($student['faculty_name']);
    }

    $marks = (float)($student['marks_obtained'] ?? 0);
    $total = (float)($student['total_marks'] ?? 100);
    $percentage = $total > 0 ? round(($marks / $total) * 100, 1) : 0;
    $status = $student['result_status'] ?? 'Pending';
    // Translate status for badge
    $status_label = $status;
    if ($status === 'Pass') $status_label = 'PASSED';
    elseif ($status === 'Fail') $status_label = 'NOT SELECTED';
    elseif ($status === 'Waitlisted') $status_label = 'WAITLISTED';

    $remarks = htmlspecialchars($student['result_remarks'] ?? '');
    
    $status_color = '#64748b'; // default slate
    if ($status === 'Pass') $status_color = '#059669'; // emerald
    elseif ($status === 'Fail') $status_color = '#dc2626'; // red
    elseif ($status === 'Waitlisted') $status_color = '#d97706'; // amber

    return '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Exam Result</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 14px; margin:0; padding:10px; background:#fff; color:#334155; }
        .card { border: 2px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .header { background: #f8fafc; padding: 20px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .school-name { margin: 0; padding: 0; font-size: 22px; font-weight: bold; color: #0f172a; }
        .school-addr { font-size: 13px; color: #64748b; margin-top: 5px; }
        .title { background: #1e293b; color: #fff; padding: 10px; text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .content { padding: 30px; }
        .info-table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        .info-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .info-table .lbl { font-weight: bold; color: #64748b; width: 25%; }
        .info-table .val { font-weight: bold; color: #0f172a; width: 25%; }
        .result-box { text-align: center; border: 2px solid ' . $status_color . '; border-radius: 12px; padding: 25px; background: ' . $status_color . '11; margin-bottom: 30px; }
        .marks-lg { font-size: 48px; font-weight: bold; color: ' . $status_color . '; line-height:1; }
        .marks-tot { font-size: 24px; color: #94a3b8; font-weight: bold; }
        .pct { font-size: 18px; font-weight: bold; color: #475569; margin-top: 10px; }
        .badge { display: inline-block; background: ' . $status_color . '; color: #fff; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin-bottom: 15px; }
        .remarks { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .remarks-lbl { font-size: 12px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px; }
        .footer { padding: 15px 30px; padding-bottom: 30px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; display: table; width: 100%; }
        .f-left { display: table-cell; text-align: left; vertical-align: middle; }
        .f-right { display: table-cell; text-align: right; vertical-align: middle; width: 180px; }
        .sig-line { border-top: 1px solid #334155; padding-top: 5px; width: 150px; display: inline-block; text-align: center; margin-top: 30px; }
    </style></head><body>
    <div class="card">
        <div class="header">
            ' . $logo_html . '
            <div class="school-name">' . htmlspecialchars($school_name) . '</div>
            <div class="school-addr">' . htmlspecialchars($address) . '</div>
        </div>
        <div class="title">Entrance Examination Result</div>
        <div class="content">
            <table class="info-table">
                <tr><td class="lbl">Student Name</td><td class="val" colspan="3">' . $name . '</td></tr>
                <tr><td class="lbl">Roll Number</td><td class="val">' . $roll . '</td><td class="lbl">Class/Faculty</td><td class="val">' . $class_faculty . '</td></tr>
            </table>
            
            <div class="result-box">
                <div class="badge">' . $status_label . '</div>
                <div>
                    <span class="marks-lg">' . $marks . '</span>
                    <span class="marks-tot">/ ' . $total . '</span>
                </div>
                <div class="pct">Percentage: ' . $percentage . '%</div>
            </div>

            ' . (!empty($remarks) ? '<div class="remarks"><div class="remarks-lbl">Remarks</div><div>' . $remarks . '</div></div>' : '') . '
        </div>
        <div class="footer">
            <div class="f-left"><p style="margin:0;">Generated on: ' . date('d M Y, h:i A') . '</p></div>
            <div class="f-right"><div class="sig-line">Authorized Signatory</div></div>
        </div>
    </div>
    </body></html>';
}

