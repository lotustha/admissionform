<?php
// Prevent running setup again if already installed
if (file_exists(__DIR__ . '/.env')) {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h2>Setup is already complete.</h2>
            <p>Please delete <b>/.env</b> if you want to reinstall.</p>
            <a href='index.php'>Go to Homepage</a>
         </div>");
}

$message = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];

    $admin_user = $_POST['admin_user'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT);

    $school_name = $_POST['school_name'];
    $gemini_api_keys = isset($_POST['gemini_api_keys']) ? trim($_POST['gemini_api_keys']) : '';

    $logo_path = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
            $logo_path = 'assets/uploads/logos/' . $filename;
        }
    }

    try {
        // 1. Connect to MySQL (without specific DB to create it first)
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. Create the Database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // 3. The Master SQL Schema
        $schema = "
        CREATE TABLE IF NOT EXISTS app_settings (
            `key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('Super Admin', 'Academic Staff', 'Cashier', 'Viewer') DEFAULT 'Super Admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS class_seats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL UNIQUE,
            total_seats INT DEFAULT 0,
            is_open BOOLEAN DEFAULT 1,
            contact_person VARCHAR(150),
            whatsapp_number VARCHAR(20)
        );

        CREATE TABLE IF NOT EXISTS faculties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            faculty_name VARCHAR(100) NOT NULL,
            requires_entrance BOOLEAN DEFAULT 0,
            incharge_name VARCHAR(200) DEFAULT NULL,
            incharge_whatsapp VARCHAR(30) DEFAULT NULL,
            incharge_photo_path VARCHAR(500) DEFAULT NULL,
            incharge_title VARCHAR(100) DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS faculty_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            faculty_id INT NOT NULL,
            subject_name VARCHAR(100) NOT NULL,
            FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS entrance_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL,
            faculty_id INT,
            exam_date DATE NOT NULL,
            exam_time VARCHAR(50) NOT NULL,
            venue VARCHAR(150) NOT NULL,
            total_capacity INT NOT NULL,
            FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS admission_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_first_name VARCHAR(100) NOT NULL,
            student_last_name VARCHAR(100) NOT NULL,
            student_email VARCHAR(150),
            dob_bs VARCHAR(15) NOT NULL,
            dob_ad DATE, 
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            pp_photo_path VARCHAR(255) NOT NULL,
            address_province VARCHAR(100) NOT NULL,
            address_district VARCHAR(100) NOT NULL,
            address_municipality VARCHAR(100) NOT NULL,
            address_ward_village VARCHAR(100) NOT NULL,
            father_name VARCHAR(150) NOT NULL,
            father_occupation VARCHAR(100),
            father_contact VARCHAR(20) NOT NULL,
            mother_name VARCHAR(150),
            mother_occupation VARCHAR(100),
            mother_contact VARCHAR(20),
            local_guardian_name VARCHAR(150),
            guardian_contact VARCHAR(20),
            guardian_relation VARCHAR(50),
            applied_class VARCHAR(50) NOT NULL,
            faculty_id INT NULL,
            optional_subject_1 VARCHAR(100) NULL,
            optional_subject_2 VARCHAR(100) NULL,
            previous_school_name VARCHAR(200),
            previous_board VARCHAR(50),
            gpa_or_percentage DECIMAL(5,2),
            see_symbol_no VARCHAR(50),
            schedule_id INT NULL,
            entrance_roll_no VARCHAR(50) UNIQUE,
            admit_card_status ENUM('Pending', 'Generated') DEFAULT 'Pending',
            document_path VARCHAR(255),
            birth_cert_path VARCHAR(255),
            payment_method VARCHAR(50),
            declaration_accepted BOOLEAN DEFAULT 1,
            status ENUM('Pending', 'Approved', 'Rejected', 'Admitted') DEFAULT 'Pending',
            form_type ENUM('Admission','Inquiry') DEFAULT 'Admission',
            session_id INT NULL DEFAULT NULL,
            submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
            FOREIGN KEY (schedule_id) REFERENCES entrance_schedules(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_applied_class (applied_class),
            INDEX idx_father_contact (father_contact)
        );

        CREATE TABLE IF NOT EXISTS academic_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_label VARCHAR(100) NOT NULL,
            start_year VARCHAR(10) NOT NULL,
            end_year VARCHAR(10) NOT NULL,
            is_active TINYINT DEFAULT 0,
            admission_open TINYINT DEFAULT 1,
            inquiry_open TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS knowledge_base (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            content TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS chat_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(255) NOT NULL UNIQUE,
            inquiry_id INT NULL,
            status ENUM('bot', 'human_requested', 'human_active', 'resolved') DEFAULT 'bot',
            assigned_to INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (inquiry_id) REFERENCES admission_inquiries(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES admins(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            sender_type ENUM('user', 'bot', 'admin') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
        );
        ";

        $pdo->exec($schema);

        // Attempt to add columns that might be missing from older versions
        $alter_queries = [
            "ALTER TABLE class_seats ADD COLUMN is_open BOOLEAN DEFAULT 1",
            "ALTER TABLE class_seats ADD COLUMN contact_person VARCHAR(150)",
            "ALTER TABLE class_seats ADD COLUMN whatsapp_number VARCHAR(20)",
            "ALTER TABLE faculties ADD COLUMN incharge_name VARCHAR(200)",
            "ALTER TABLE faculties ADD COLUMN incharge_whatsapp VARCHAR(30)",
            "ALTER TABLE faculties ADD COLUMN incharge_photo_path VARCHAR(500)",
            "ALTER TABLE faculties ADD COLUMN incharge_title VARCHAR(100)",
            "ALTER TABLE admission_inquiries ADD COLUMN form_type ENUM('Admission','Inquiry') DEFAULT 'Admission'",
            "ALTER TABLE admission_inquiries ADD COLUMN session_id INT NULL DEFAULT NULL",
            "ALTER TABLE admins ADD COLUMN role ENUM('Super Admin', 'Academic Staff', 'Cashier', 'Viewer') DEFAULT 'Super Admin'"
        ];
        
        foreach ($alter_queries as $query) {
            try { $pdo->exec($query); } catch (PDOException $e) { /* Ignore if column exists */ }
        }

        // Auto-generate classes
        $classes_to_generate = [
            'ECD', 'Class 1', 'Class 2', 'Class 3', 
            'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 
            'Class 9', 'Class 11'
        ];
        $stmt_class = $pdo->prepare("INSERT IGNORE INTO class_seats (class_name, total_seats, is_open) VALUES (?, 40, 1)");
        foreach ($classes_to_generate as $c_name) {
            try { $stmt_class->execute([$c_name]); } catch (PDOException $e) {}
        }

        // 4. Insert Initial Admin Data
        $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$admin_user, $admin_pass]);

        // Insert default academic session
        $pdo->exec("INSERT IGNORE INTO academic_sessions (id, session_label, start_year, end_year, is_active, admission_open, inquiry_open) VALUES (1, '2082-2083 BS', '2082', '2083', 1, 1, 1)");

        // 5. Insert School Settings into app_settings (key-value)
        $upsert = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $upsert->execute(['school_name', $school_name]);
        $upsert->execute(['gemini_api_keys', $gemini_api_keys]);
        $upsert->execute(['logo_path', $logo_path]);
        $upsert->execute(['form_title', 'Admission Inquiry Form']);

        // 6. Write the .env file
        $env_content = "DB_HOST=$db_host\n";
        $env_content .= "DB_NAME=$db_name\n";
        $env_content .= "DB_USER=$db_user\n";
        $env_content .= "DB_PASS=$db_pass\n";

        file_put_contents(__DIR__ . '/.env', $env_content);

        // 7. Create Upload Directories
        $dirs = [
            __DIR__ . '/uploads/incharge',
            __DIR__ . '/assets/uploads/logos',
            __DIR__ . '/assets/uploads/documents'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        $step = 2; // Success step

    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Setup Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden">
        <div class="bg-emerald-600 p-6 text-white text-center">
            <h1 class="text-2xl font-bold">Admission Portal Setup</h1>
            <p class="text-emerald-100 text-sm mt-1">Configure your database and school settings</p>
        </div>

        <div class="p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">

                    <!-- Database Settings -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">1. Database Connection</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Host</label>
                                <input type="text" name="db_host" value="localhost" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Name</label>
                                <input type="text" name="db_name" value="school_admission" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Username</label>
                                <input type="text" name="db_user" value="root" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Password</label>
                                <input type="password" name="db_pass"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                            </div>
                        </div>
                    </div>

                    <!-- Admin & School Settings -->
                    <div class="pt-4">
                        <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">2. School & Admin Details</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">School Name</label>
                                <input type="text" name="school_name" placeholder="E.g., Everest High School" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">School Logo</label>
                                <input type="file" name="logo" accept="image/*"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-1 pl-2 text-sm text-gray-500 bg-white file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Admin Username</label>
                                    <input type="text" name="admin_user" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Admin Password</label>
                                    <input type="password" name="admin_pass" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Settings -->
                    <div class="pt-4">
                        <h2 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">3. AI Configuration (OCR &
                            Auto-fill)</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gemini API Keys</label>
                            <textarea name="gemini_api_keys" placeholder="AIzaSy..., AIzaSy..." rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 border p-2 text-sm font-mono"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Optional. Add multiple keys separated by commas. These
                                power the Document OCR auto-fill feature without hitting free-tier limits as quickly.</p>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-emerald-600 text-white font-bold py-3 px-4 rounded-md hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-600/30">
                        Run Install & Database Setup
                    </button>
                </form>

            <?php else: ?>
                <!-- Success State -->
                <div class="text-center py-8">
                    <div
                        class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Setup Complete!</h2>
                    <p class="text-gray-600 mb-6">Your database tables, admin user, and config files have been successfully
                        generated.</p>
                    <div class="flex gap-4 justify-center">
                        <a href="index.php"
                            class="bg-gray-100 text-gray-700 font-semibold py-2 px-6 rounded-md hover:bg-gray-200 transition-colors">Go
                            to Form</a>
                        <a href="login.php"
                            class="bg-emerald-600 text-white font-semibold py-2 px-6 rounded-md hover:bg-emerald-700 transition-colors">Login
                            to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>