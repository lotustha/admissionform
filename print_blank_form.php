<?php
// print_blank_form.php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings    = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Form';
$logo        = $settings['logo_path'] ?? '';
$address     = $settings['address'] ?? '';
$phone       = $settings['contact_phone'] ?? '';

$type = $_GET['type'] ?? 'Admission';
if (!in_array($type, ['Admission', 'Inquiry'])) $type = 'Admission';
$isAdmission = ($type === 'Admission');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blank <?php echo htmlspecialchars($type); ?> Form</title>
<style>
/* ─── Reset ─────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── Screen chrome ─────────────────────────────────── */
body {
    background: #e8edf2;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.toolbar {
    background: #1e293b;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar button {
    padding: 7px 20px;
    font-size: 10pt;
    font-weight: 700;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.btn-print { background: #059669; color: #fff; }
.btn-close { background: #475569; color: #fff; }
.toolbar span { color: #94a3b8; font-size: 9pt; }

/* ─── Print rules ────────────────────────────────────── */
@media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    @page { size: A4 portrait; margin: 0; }
    .page { box-shadow: none !important; margin: 0 !important; }
}

/* ─── A4 Page shell ──────────────────────────────────── */
.page {
    width: 210mm;
    height: 297mm;
    background: #fff;
    margin: 16px auto;
    padding: 10mm 10mm 8mm 10mm;
    box-shadow: 0 4px 40px rgba(0,0,0,0.18);
    display: flex;
    flex-direction: column;
    gap: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ─── HEADER ─────────────────────────────────────────── */
.header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 7px;
    border-bottom: 3px solid #059669;
    flex-shrink: 0;
}
.hlogo {
    width: 62px; height: 62px;
    object-fit: contain;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    padding: 2px;
    flex-shrink: 0;
}
.hlogo-placeholder {
    width: 62px; height: 62px;
    background: #f0fdf4;
    border: 2px solid #059669;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.hlogo-placeholder svg { width: 30px; height: 30px; color: #059669; }
.htext { flex: 1; text-align: center; }
.htext .school-name {
    font-size: 16pt;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #0f172a;
    line-height: 1.1;
}
.htext .school-sub {
    font-size: 8pt;
    color: #64748b;
    margin-top: 2px;
}
.htext .form-badge {
    display: inline-block;
    background: #059669;
    color: #fff;
    font-size: 9pt;
    font-weight: 700;
    padding: 3px 14px;
    border-radius: 20px;
    margin-top: 5px;
    letter-spacing: 0.5px;
}
.photo-area {
    flex-shrink: 0;
    text-align: center;
}
.photo-box {
    width: 68px; height: 82px;
    border: 1.5px dashed #059669;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 7pt;
    color: #64748b;
    line-height: 1.4;
    text-align: center;
    background: #f0fdf4;
}

/* ─── OFFICE BAR ─────────────────────────────────────── */
.office-bar {
    flex-shrink: 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 5px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 8pt;
    color: #475569;
    margin-top: 5px;
}
.obar-title {
    font-weight: 800;
    color: #059669;
    white-space: nowrap;
    font-size: 7.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.obar-field {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
}
.obar-field label { white-space: nowrap; font-weight: 600; font-size: 7.5pt; }
.obar-line { flex: 1; border-bottom: 1px solid #94a3b8; height: 14px; }

/* ─── SECTION ────────────────────────────────────────── */
.section {
    display: flex;
    flex-direction: column;
    margin-top: 6px;
}
/* flex-grow weights */
.s-program  { flex-grow: 1.2; }
.s-student  { flex-grow: 1.8; }
.s-address  { flex-grow: 1.0; }
.s-family   { flex-grow: 2.6; }
.s-academic { flex-grow: 1.6; }
.s-declare  { flex-grow: 2.2; }

/* ─── Section title ──────────────────────────────────── */
.sec-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    margin-bottom: 5px;
}
.sec-num {
    background: #059669;
    color: #fff;
    font-size: 7.5pt;
    font-weight: 800;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.sec-title {
    font-size: 9.5pt;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    border-bottom: 2px solid #059669;
    flex: 1;
    padding-bottom: 2px;
}

/* ─── Field grid ─────────────────────────────────────── */
.fields {
    flex-grow: 1;
    display: grid;
    align-content: stretch;
    gap: 0 8px;
}
.c2 { grid-template-columns: 1fr 1fr; }
.c3 { grid-template-columns: 1fr 1fr 1fr; }
.c4 { grid-template-columns: 1fr 1fr 1fr 1fr; }

.field {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding-bottom: 4px;
    border-bottom: 1.5px solid #cbd5e1;
    margin-bottom: 0;
    position: relative;
}
.field label {
    font-size: 7pt;
    font-weight: 700;
    color: #059669;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
    display: block;
}
.field .write { flex-grow: 1; min-height: 14px; }

/* Gender checkboxes inline */
.gender-opts { display: flex; gap: 10px; font-size: 8pt; font-weight: 400; color: #374151; margin-top: 2px; }
.chk { display: inline-flex; align-items: center; gap: 3px; }
.chk-box { width: 10px; height: 10px; border: 1px solid #374151; border-radius: 2px; display: inline-block; }

/* ─── Table ──────────────────────────────────────────── */
.tbl-wrap { flex-grow: 1; display: flex; flex-direction: column; }
table {
    width: 100%;
    border-collapse: collapse;
    flex-grow: 1;
    height: 100%;
}
thead tr { background: #059669; }
th {
    color: #fff;
    font-size: 8pt;
    font-weight: 700;
    padding: 4px 8px;
    text-align: left;
    border: 1px solid #047857;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
td {
    border: 1px solid #cbd5e1;
    padding: 3px 8px;
    font-size: 9pt;
    vertical-align: middle;
}
tbody tr:nth-child(even) td { background: #f8fafc; }
.row-label {
    font-size: 8pt;
    font-weight: 700;
    color: #374151;
}
table tbody tr { height: 33%; }

/* ─── Declaration ────────────────────────────────────── */
.declare-body {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 6px 0 0;
}
.declare-text {
    font-size: 8.5pt;
    color: #374151;
    line-height: 1.55;
    background: #f8fafc;
    border-left: 3px solid #059669;
    padding: 6px 10px;
    border-radius: 0 4px 4px 0;
}
.sig-row { display: flex; justify-content: space-around; gap: 20px; }
.sig-box { flex: 1; text-align: center; }
.sig-space { height: 36px; }
.sig-line {
    border-top: 1.5px solid #334155;
    padding-top: 4px;
    font-size: 8pt;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.sig-sub { font-size: 7pt; color: #94a3b8; margin-top: 1px; }
</style>
</head>
<body onload="window.print()">

<div class="toolbar">
    <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
    <button class="btn-close" onclick="window.close()">&#x2715; Close</button>
    <span>Paper: A4 &bull; Margins: None &bull; Scale: 100% &bull; Background graphics: ON</span>
</div>

<div class="page">

    <!-- ① HEADER -->
    <div class="header">
        <?php if($logo): ?>
            <img class="hlogo" src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
        <?php else: ?>
            <div class="hlogo-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        <?php endif; ?>

        <div class="htext">
            <div class="school-name"><?php echo htmlspecialchars($school_name); ?></div>
            <?php if($address || $phone): ?>
                <div class="school-sub"><?php echo htmlspecialchars(trim($address . ($phone ? '  |  Ph: '.$phone : ''))); ?></div>
            <?php endif; ?>
            <span class="form-badge"><?php echo htmlspecialchars($type === 'Admission' ? 'Admission Application Form' : 'Inquiry Form'); ?></span>
        </div>

        <?php if($isAdmission): ?>
        <div class="photo-area">
            <div class="photo-box">Affix<br>Passport<br>Photo<br>Here</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ② OFFICE USE -->
    <div class="office-bar">
        <span class="obar-title">&#9635; Office Use</span>
        <div class="obar-field"><label>Date:</label><div class="obar-line"></div></div>
        <div class="obar-field"><label>Reg No.:</label><div class="obar-line"></div></div>
        <div class="obar-field"><label>Received By:</label><div class="obar-line"></div></div>
        <div class="obar-field"><label>Signature:</label><div class="obar-line"></div></div>
    </div>

    <!-- ③ PROGRAM DETAILS -->
    <div class="section s-program">
        <div class="sec-head">
            <div class="sec-num">1</div>
            <div class="sec-title">Program Details</div>
        </div>
        <div class="fields <?php echo $isAdmission ? 'c4' : 'c2'; ?>">
            <div class="field"><label>Applied Class</label><div class="write"></div></div>
            <div class="field"><label>Faculty / Stream</label><div class="write"></div></div>
            <?php if($isAdmission): ?>
            <div class="field"><label>Optional Subject I</label><div class="write"></div></div>
            <div class="field"><label>Optional Subject II</label><div class="write"></div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ④ STUDENT DETAILS -->
    <div class="section s-student">
        <div class="sec-head">
            <div class="sec-num">2</div>
            <div class="sec-title">Student Information</div>
        </div>
        <div class="fields c3">
            <div class="field"><label>First Name</label><div class="write"></div></div>
            <div class="field"><label>Last Name</label><div class="write"></div></div>
            <div class="field"><label>Email Address</label><div class="write"></div></div>
            <div class="field"><label>Date of Birth (BS)</label><div class="write"></div></div>
            <div class="field"><label>Date of Birth (AD)</label><div class="write"></div></div>
            <div class="field">
                <label>Gender</label>
                <div class="gender-opts">
                    <span class="chk"><span class="chk-box"></span> Male</span>
                    <span class="chk"><span class="chk-box"></span> Female</span>
                    <span class="chk"><span class="chk-box"></span> Other</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ⑤ ADDRESS -->
    <div class="section s-address">
        <div class="sec-head">
            <div class="sec-num">3</div>
            <div class="sec-title">Permanent Address</div>
        </div>
        <div class="fields c4">
            <div class="field"><label>Province</label><div class="write"></div></div>
            <div class="field"><label>District</label><div class="write"></div></div>
            <div class="field"><label>Municipality / VDC</label><div class="write"></div></div>
            <div class="field"><label>Ward No. / Village</label><div class="write"></div></div>
        </div>
    </div>

    <!-- ⑥ FAMILY DETAILS -->
    <div class="section s-family">
        <div class="sec-head">
            <div class="sec-num">4</div>
            <div class="sec-title">Family &amp; Guardian Details</div>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:20%">Relation</th>
                        <th>Full Name</th>
                        <th style="width:22%">Occupation</th>
                        <th style="width:22%">Contact Number</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="row-label">Father</td><td></td><td></td><td></td></tr>
                    <tr><td class="row-label">Mother</td><td></td><td></td><td></td></tr>
                    <tr><td class="row-label">Local Guardian</td><td></td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($isAdmission): ?>
    <!-- ⑦ ACADEMIC BACKGROUND -->
    <div class="section s-academic">
        <div class="sec-head">
            <div class="sec-num">5</div>
            <div class="sec-title">Academic Background</div>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Previous School / College Name</th>
                        <th style="width:16%">Board</th>
                        <th style="width:18%">GPA / Percentage</th>
                        <th style="width:18%">SEE Symbol No.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="height:100%"><td></td><td></td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ⑧ DECLARATION -->
    <div class="section s-declare">
        <div class="sec-head">
            <div class="sec-num"><?php echo $isAdmission ? '6' : '5'; ?></div>
            <div class="sec-title">Declaration</div>
        </div>
        <div class="declare-body">
            <div class="declare-text">
                I hereby solemnly declare that all the information furnished above is correct, complete and true to the best of my knowledge and belief. I understand that any false or misleading information may result in cancellation of my application. If selected, I agree to abide by all rules, regulations and code of conduct of the institution.
            </div>
            <div class="sig-row">
                <div class="sig-box">
                    <div class="sig-space"></div>
                    <div class="sig-line">Signature of Applicant</div>
                    <div class="sig-sub">Date: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                </div>
                <div class="sig-box">
                    <div class="sig-space"></div>
                    <div class="sig-line">Signature of Parent / Guardian</div>
                    <div class="sig-sub">Date: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                </div>
            </div>
        </div>
    </div>

</div><!-- .page -->
</body>
</html>
