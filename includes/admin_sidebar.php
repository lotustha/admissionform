<?php
// includes/admin_sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
// Determine active tab for settings
$active_tab = $_GET['tab'] ?? '';

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';

// Fetch school name for sidebar header
$_sidebar_school_name = 'Admission Portal';
$_sidebar_logo = '';
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM app_settings WHERE `key` IN ('school_name','logo_path')");
    $rows_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_menus as $row) {
        if ($row['key'] === 'school_name')
            $_sidebar_school_name = $row['value'] ?: 'Admission Portal';
        if ($row['key'] === 'logo_path')
            $_sidebar_logo = $row['value'] ?? '';
    }
}
catch (Exception $e) {
}
?>
<style>
.sidebar-scroll::-webkit-scrollbar {
    width: 4px;
}
.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 99px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: #10b981;
}
.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: #334155 transparent;
}

/* Mobile sidebar overlay */
#mobile-sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 40;
    backdrop-filter: blur(2px);
}
#mobile-sidebar-backdrop.open { display: block; }
#mobile-sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 280px;
    transform: translateX(-100%);
    transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
    z-index: 50;
    background: #0f172a;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 24px rgba(0,0,0,0.4);
}
#mobile-sidebar.open { transform: translateX(0); }
@media (min-width: 768px) {
    #mobile-sidebar, #mobile-sidebar-backdrop { display: none !important; }
}
</style>
<!-- Mobile sidebar backdrop -->
<div id="mobile-sidebar-backdrop" onclick="closeMobileSidebar()"></div>

<!-- Mobile Slide-Over Sidebar -->
<div id="mobile-sidebar" class="text-sm text-slate-300">
    <div class="h-14 flex items-center justify-between px-5 bg-slate-950 text-white shrink-0 border-b border-slate-800">
        <div class="flex items-center gap-3">
            <?php if ($_sidebar_logo): ?>
                <img src="<?php echo htmlspecialchars($_sidebar_logo); ?>" class="w-7 h-7 rounded-full object-contain bg-white p-0.5 flex-shrink-0" onerror="this.style.display='none'">
            <?php else: ?>
                <div class="w-7 h-7 rounded-lg bg-emerald-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            <?php endif; ?>
            <span class="font-bold text-sm truncate"><?php echo htmlspecialchars($_sidebar_school_name); ?></span>
        </div>
        <button onclick="closeMobileSidebar()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <!-- Mobile nav links (same as desktop) -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto sidebar-scroll space-y-0.5">
        <?php
        // reuse sidebar nav items
        $mo_items = [
            ['url'=>'dashboard.php','label'=>'Dashboard','icon'=>'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z','roles'=>['Super Admin','Academic Staff','Cashier','Viewer']],
            ['url'=>'inquiries.php','label'=>'Quick Inquiries','icon'=>'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z','roles'=>['Super Admin','Academic Staff','Cashier','Viewer']],
            ['url'=>'applications.php','label'=>'Full Admissions','icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z','roles'=>['Super Admin','Academic Staff','Cashier','Viewer']],
            ['url'=>'interviews.php','label'=>'Interviews','icon'=>'M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'admin_add_application.php','label'=>'Add Manual Entry','icon'=>'M12 4v16m8-8H4','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'publish_results.php','label'=>'Publish Results','icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'fee_collection.php','label'=>'Fee Collection','icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z','roles'=>['Super Admin','Cashier']],
            ['url'=>'reports.php','label'=>'Detailed Reports','icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z','roles'=>['Super Admin','Cashier','Viewer']],
            ['url'=>'manage_sessions.php','label'=>'Academic Sessions','icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'manage_classes.php','label'=>'Classes & Seats','icon'=>'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'manage_entrance.php','label'=>'Entrance Exams','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'manage_academics.php','label'=>'Faculties Info','icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'manage_settings.php','label'=>'General Settings','icon'=>'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z','roles'=>['Super Admin','Academic Staff']],
            ['url'=>'manage_settings.php?tab=fees','label'=>'Fee Config','icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z','roles'=>['Super Admin']],
            ['url'=>'manage_staff.php','label'=>'Manage Staff','icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z','roles'=>['Super Admin']],
        ];
        foreach ($mo_items as $item) {
            if (!in_array($admin_role, $item['roles'])) continue;
            $active = (strpos($current_page, basename(parse_url($item['url'],PHP_URL_PATH))) === 0);
            $cls = $active ? 'bg-emerald-600 text-white font-semibold shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white font-medium';
            echo '<a href="'.$item['url'].'" class="flex items-center px-3 py-2.5 rounded-lg transition-all '.$cls.' mb-0.5" onclick="closeMobileSidebar()">';
            echo '<svg class="w-5 h-5 mr-3 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="'.$item['icon'].'"></path></svg>';
            echo htmlspecialchars($item['label']);
            echo '</a>';
        }
        ?>
    </nav>
    <div class="px-4 py-3 border-t border-slate-800 text-xs text-slate-500 text-center">
        <?php echo htmlspecialchars($admin_role); ?>
    </div>
</div>

<div class="flex h-screen overflow-hidden bg-gray-50 font-sans">
    
    <!-- Desktop Sidebar -->
    <aside class="w-64 bg-slate-900 text-sm text-slate-300 flex-shrink-0 hidden md:flex flex-col shadow-2xl z-20 border-r border-slate-800">
        <!-- Logo / School Name -->
        <div class="h-16 flex items-center px-5 bg-slate-950 text-white shrink-0 gap-3 border-b border-slate-800">
            <?php if ($_sidebar_logo): ?>
                <img src="<?php echo htmlspecialchars($_sidebar_logo); ?>" class="w-8 h-8 rounded-full object-contain bg-white p-0.5 flex-shrink-0 shadow-sm" onerror="this.style.display='none'">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center flex-shrink-0 shadow-md">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            <?php endif; ?>
            <span class="font-bold text-sm tracking-wide truncate"><?php echo htmlspecialchars($_sidebar_school_name); ?></span>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll">

            <!-- Dashboard -->
            <a href="dashboard.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'dashboard.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'dashboard.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>

            <!-- Core Processing -->
            <div class="pt-6 pb-2 px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Core Processing</div>

            <a href="inquiries.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'inquiries.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'inquiries.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Quick Inquiries
            </a>

            <a href="applications.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'applications.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'applications.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Full Admissions
            </a>

            <?php if (in_array($admin_role, ['Super Admin', 'Academic Staff'])): ?>
            <a href="interviews.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'interviews.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'interviews.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                Interviews
            </a>

            <a href="admin_add_application.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'admin_add_application.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'admin_add_application.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Manual Entry
            </a>

            <a href="publish_results.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'publish_results.php' ? 'bg-violet-600 text-white font-medium shadow-md shadow-violet-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'publish_results.php' ? 'text-violet-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Publish Results
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['Super Admin', 'Cashier', 'Viewer'])): ?>
            <!-- Operations & Finance -->
            <div class="pt-6 pb-2 px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Operations & Finance</div>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['Super Admin', 'Cashier'])): ?>
            <a href="fee_collection.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'fee_collection.php' ? 'bg-teal-600 text-white font-medium shadow-md shadow-teal-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'fee_collection.php' ? 'text-teal-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Fee Collection
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['Super Admin', 'Cashier', 'Viewer'])): ?>
            <a href="reports.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'reports.php' ? 'bg-amber-600 text-white font-medium shadow-md shadow-amber-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'reports.php' ? 'text-amber-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Detailed Reports
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['Super Admin', 'Academic Staff'])): ?>
            <!-- Academic Setup -->
            <div class="pt-6 pb-2 px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Academic Setup</div>
            
            <a href="manage_sessions.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'manage_sessions.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'manage_sessions.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Academic Sessions
            </a>

            <a href="manage_classes.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'manage_classes.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'manage_classes.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Classes & Seats
            </a>

            <a href="manage_entrance.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'manage_entrance.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'manage_entrance.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                Entrance Exams
            </a>
            
            <a href="manage_academics.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'manage_academics.php' ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'manage_academics.php' ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Faculties Info
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['Super Admin', 'Academic Staff'])): ?>
            <!-- System & Admin section -->
            <div class="pt-6 pb-2 px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">System & Admin</div>
            
            <a href="manage_settings.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo ($current_page == 'manage_settings.php' && $active_tab != 'fees') ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo ($current_page == 'manage_settings.php' && $active_tab != 'fees') ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                General Settings
            </a>

            <?php if ($admin_role === 'Super Admin'): ?>
            <a href="manage_settings.php?tab=fees" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo ($current_page == 'manage_settings.php' && $active_tab == 'fees') ? 'bg-emerald-600 text-white font-medium shadow-md shadow-emerald-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo ($current_page == 'manage_settings.php' && $active_tab == 'fees') ? 'text-emerald-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Application Fee Config
            </a>
            
            <a href="manage_staff.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 <?php echo $current_page == 'manage_staff.php' ? 'bg-blue-600 text-white font-medium shadow-md shadow-blue-900/50' : 'hover:bg-slate-800 hover:text-white text-slate-400 font-medium'; ?>">
                <svg class="w-5 h-5 mr-3 shrink-0 <?php echo $current_page == 'manage_staff.php' ? 'text-blue-200' : 'text-slate-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Manage Staff
            </a>
            <?php endif; ?>
            <?php endif; ?>
            
        </nav>
        
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#f8fafc]">

        <!-- Top Navbar -->
        <header class="h-14 bg-white border-b border-gray-100 shadow-sm flex items-center justify-between px-4 shrink-0 z-10">
            <!-- Left: hamburger (mobile) + breadcrumb -->
            <div class="flex items-center gap-3">
                <!-- Hamburger button — mobile only -->
                <button id="mobile-menu-btn" onclick="openMobileSidebar()" class="md:hidden flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:bg-slate-100 transition" aria-label="Open navigation menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <span class="text-slate-500 font-semibold text-sm truncate hidden md:block"><?php echo htmlspecialchars($_sidebar_school_name); ?> &mdash; Admin Panel</span>
                <span class="text-slate-700 font-bold text-sm truncate md:hidden"><?php echo htmlspecialchars($_sidebar_school_name); ?></span>
            </div>

            <!-- Right: actions -->
            <div class="flex items-center gap-2">
                <!-- Quick links -->
                <a href="dashboard.php" title="Dashboard" class="hidden sm:flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 px-3 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Home
                </a>
                <a href="fee_collection.php" title="Fee Collection" class="hidden sm:flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-teal-600 hover:bg-teal-50 px-3 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Fees
                </a>

                <!-- Divider -->
                <div class="hidden sm:block w-px h-6 bg-gray-200 mx-1"></div>

                <!-- Admin avatar + name + sign out -->
                <div class="flex items-center gap-2.5 pl-1">
                    <div class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center text-white text-xs font-black shadow-sm">
                        <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-sm font-bold text-gray-800 leading-none"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wider leading-none mt-0.5"><?php echo htmlspecialchars($admin_role); ?></div>
                    </div>
                    <a href="dashboard.php?logout=1"
                        title="Sign Out"
                        class="ml-1 flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition border border-gray-100 hover:border-red-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span class="hidden sm:inline">Sign Out</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Scrollable page content -->
        <div class="flex-1 overflow-y-auto w-full p-4 sm:p-6 lg:p-10 relative">

<script>
function openMobileSidebar() {
    document.getElementById('mobile-sidebar').classList.add('open');
    document.getElementById('mobile-sidebar-backdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeMobileSidebar() {
    document.getElementById('mobile-sidebar').classList.remove('open');
    document.getElementById('mobile-sidebar-backdrop').classList.remove('open');
    document.body.style.overflow = '';
}
// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMobileSidebar();
});
</script>

