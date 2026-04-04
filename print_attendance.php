<?php
// print_attendance.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized Access. Please login first.");
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Schedule ID");
}

$schedule_id = (int)$_GET['id'];
$sched_stmt = $pdo->prepare("SELECT e.*, f.faculty_name FROM entrance_schedules e LEFT JOIN faculties f ON e.faculty_id = f.id WHERE e.id = ?");
$sched_stmt->execute([$schedule_id]);
$schedule = $sched_stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    die("Schedule not found.");
}

$stmt = $pdo->prepare("SELECT entrance_roll_no, student_first_name, student_last_name, gender, father_contact, guardian_contact, status FROM admission_inquiries WHERE schedule_id = ? ORDER BY entrance_roll_no ASC");
$stmt->execute([$schedule_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Attendance - <?php echo htmlspecialchars($schedule['class_name']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; line-height: 1.5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #059669; padding-bottom: 20px; }
        .header h1 { margin: 0 0 10px 0; color: #065f46; font-size: 24px; text-transform: uppercase; }
        .meta-info { display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; background: #f0fdf4; padding: 10px; border: 1px solid #a7f3d0; border-radius: 4px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px 8px; text-align: left; }
        th { background-color: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        td { color: #1e293b; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .sign-col { width: 120px; }
        .no-print { text-align: right; margin-bottom: 20px; }
        .btn { padding: 8px 16px; background: #059669; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-left: 10px; text-decoration: none; display: inline-block; font-size: 14px;}
        .btn-gray { background: #64748b; }
        @media print { 
            .no-print { display: none !important; }
            body { padding: 0; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <div class="no-print">
            <button onclick="window.print()" class="btn">Print / Save as PDF</button>
            <button onclick="window.close()" class="btn btn-gray">Close</button>
        </div>
        
        <div class="header">
            <h1>Entrance Exam Attendance Sheet</h1>
        </div>
        
        <div class="meta-info">
            <div><span>Class:</span> <?php echo htmlspecialchars($schedule['class_name'] . ($schedule['faculty_name'] ? ' ('.$schedule['faculty_name'].')' : '')); ?></div>
            <div><span>Date & Time:</span> <?php echo htmlspecialchars(date('M d, Y', strtotime($schedule['exam_date'])) . ' at ' . date('h:i A', strtotime($schedule['exam_time']))); ?></div>
            <div><span>Venue:</span> <?php echo htmlspecialchars($schedule['venue']); ?></div>
            <div><span>Total Students:</span> <?php echo count($students); ?></div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">S.N.</th>
                    <th style="width: 120px; white-space: nowrap;">Roll No</th>
                    <th>Applicant Name</th>
                    <th style="width: 80px;">Gender</th>
                    <th style="width: 110px;">Contact No.</th>
                    <th class="sign-col">Signature</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No students are assigned to this schedule.</td></tr>
                <?php else: ?>
                    <?php $i=1; foreach($students as $st): ?>
                    <tr>
                        <td style="text-align: center; color: #64748b;"><?php echo $i++; ?></td>
                        <td style="font-weight: bold; white-space: nowrap;"><?php echo htmlspecialchars($st['entrance_roll_no']); ?></td>
                        <td style="font-weight: bold;"><?php echo htmlspecialchars(strtoupper($st['student_first_name'] . ' ' . $st['student_last_name'])); ?></td>
                        <td><?php echo htmlspecialchars($st['gender']); ?></td>
                        <td><?php echo htmlspecialchars($st['father_contact'] ?: $st['guardian_contact']); ?></td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 250px;">
                <hr style="border: 1px solid #cbd5e1; margin-bottom: 10px;">
                <p style="margin: 0; color: #475569; font-weight: bold;">Invigilator's Signature</p>
                <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 12px; font-weight: normal;">Date: .......................................</p>
            </div>
            <div style="text-align: center; width: 250px;">
                <hr style="border: 1px solid #cbd5e1; margin-bottom: 10px;">
                <p style="margin: 0; color: #475569; font-weight: bold;">Exam Coordinator</p>
                <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 12px; font-weight: normal;">Seal</p>
            </div>
        </div>
    </div>
</body>
</html>
