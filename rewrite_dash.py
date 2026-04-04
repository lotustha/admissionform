import sys

with open('dashboard.php', 'r', encoding='utf-8') as f:
    orig = f.read()

php_top = orig[:orig.find('<!DOCTYPE html>')]

html_part = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Dashboard — <?php echo htmlspecialchars($settings['school_name'] ?? 'Admin'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack-all.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .grid-stack-item-content { background: white; border-radius: 1.25rem; border: 1px solid #f3f4f6; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05); overflow: hidden; display: flex; flex-direction: column; cursor: default; }
        
        /* Drag Handle - Default State */
        .drag-handle { padding: 16px 20px; border-bottom: 1px solid transparent; background: white; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; cursor: default; transition: background 0.2s, border-color 0.2s; }
        .drag-handle.no-border { border-bottom: none; }
        
        /* Edit Mode State */
        .is-editing .grid-stack-item-content { border-style: dashed; border-color: #cbd5e1; }
        .is-editing .drag-handle { cursor: grab; background: #f8fafc; border-color: #e2e8f0; }
        .is-editing .drag-handle:active { cursor: grabbing; }
        .is-editing .edit-hint { display: inline-flex; }
        .edit-hint { display: none; }

        .chart-container-wrap { flex: 1 1 auto; position: relative; min-height: 0; width: 100%; padding: 16px; }
        .gs-bg-gradient { background: linear-gradient(to bottom right, #10b981, #047857); border:none; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); color: white; }
        .is-editing .gs-bg-gradient .drag-handle { background: transparent; border-color: rgba(255,255,255,0.2); }
    </style>
</head>
<body class="bg-gray-50 font-sans overflow-x-hidden">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-[1400px] mx-auto w-full px-6 py-6">

    <!-- Header -->
    <div class="mb-7 flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Dashboard Overview</h1>
            <p class="text-sm font-medium text-gray-500 mt-1.5 flex items-center gap-2">
                <?php if ($active_session): ?>
                    Active Session: <span class="text-emerald-600 font-bold"><?php echo htmlspecialchars($active_session['session_label']); ?></span>
                    <?php if (!$active_session['admission_open']): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-md font-bold uppercase tracking-widest">Admissions Closed</span><?php endif; ?>
                    <?php if (!$active_session['inquiry_open']): ?><span class="text-[10px] bg-amber-100 text-amber-600 px-2 py-0.5 rounded-md font-bold uppercase tracking-widest">Inquiries Closed</span><?php endif; ?>
                <?php else: ?>
                    <span class="text-orange-500 font-bold">⚠ No active session set. <a href="manage_sessions.php" class="underline">Set one</a>.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right text-sm text-gray-400 flex flex-col items-end">
            <div class="font-bold text-gray-900"><?php echo date('l, F j, Y'); ?></div>
            <div class="text-xs font-semibold mt-0.5"><?php echo htmlspecialchars($settings['school_name'] ?? ''); ?></div>
            <div class="flex gap-2 mt-3">
                <button id="editLayoutBtn" class="inline-flex py-1.5 px-3 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg shadow-sm transition-all items-center gap-1.5 font-bold text-xs border border-transparent">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Edit Layout</span>
                </button>
                <button onclick="localStorage.removeItem('admin_dashboard_layout'); window.location.reload();" class="inline-flex py-1.5 px-3 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg shadow-sm transition-all items-center gap-1.5 font-bold text-xs"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Reset</button>
            </div>
        </div>
    </div>

    <!-- GridStack Container -->
    <div class="grid-stack">
    
        <!-- Stat: Total -->
        <div class="grid-stack-item" gs-id="stat-total" gs-w="3" gs-h="2" gs-x="0" gs-y="0">
            <div class="grid-stack-item-content hover:-translate-y-1 hover:shadow-lg transition-all relative drag-handle no-border group flex-col justify-center align-start p-6">
                <i class="edit-hint absolute top-3 right-3 text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div class="text-3xl font-black text-gray-900 mb-1 leading-none"><?php echo number_format($stats['total']); ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Total Submissions</div>
            </div>
        </div>

        <!-- Stat: Today -->
        <div class="grid-stack-item" gs-id="stat-today" gs-w="3" gs-h="2" gs-x="3" gs-y="0">
            <div class="grid-stack-item-content hover:-translate-y-1 hover:shadow-lg transition-all relative drag-handle no-border group flex-col justify-center align-start p-6">
                <i class="edit-hint absolute top-3 right-3 text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="text-3xl font-black text-gray-900 mb-1 leading-none"><?php echo number_format($today); ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Today's Forms</div>
            </div>
        </div>

        <!-- Stat: Pending -->
        <div class="grid-stack-item" gs-id="stat-pending" gs-w="3" gs-h="2" gs-x="6" gs-y="0">
            <div class="grid-stack-item-content hover:-translate-y-1 hover:shadow-lg transition-all relative drag-handle no-border group flex-col justify-center align-start p-6">
                <i class="edit-hint absolute top-3 right-3 text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="text-3xl font-black text-gray-900 mb-1 leading-none"><?php echo number_format($stats['pending']); ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Awaiting Review</div>
            </div>
        </div>

        <!-- Stat: Admitted -->
        <div class="grid-stack-item" gs-id="stat-admitted" gs-w="3" gs-h="2" gs-x="9" gs-y="0">
            <div class="grid-stack-item-content hover:-translate-y-1 hover:shadow-xl transition-all relative drag-handle no-border gs-bg-gradient group flex-col justify-center align-start p-6">
                <i class="edit-hint absolute top-3 right-3 text-white/50 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm shadow flex items-center justify-center mb-4 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div class="text-3xl font-black text-white mb-1 leading-none"><?php echo number_format($stats['admitted']); ?></div>
                <div class="text-xs font-bold text-emerald-100 uppercase tracking-widest mt-1">Total Admitted</div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="grid-stack-item" gs-id="chart-trend" gs-w="8" gs-h="5" gs-x="0" gs-y="2">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> Submission Trend (7 Days)</h3>
                    <i class="edit-hint text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                </div>
                <div class="chart-container-wrap">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Class Breakdown Pie Chart -->
        <div class="grid-stack-item" gs-id="chart-class" gs-w="4" gs-h="6" gs-x="8" gs-y="2">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path></svg> Today's Distribution</h3>
                    <i class="edit-hint text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                </div>
                <div class="flex flex-col flex-1 relative overflow-hidden bg-gray-50/50">
                    <?php if (empty($class_data)): ?>
                        <div class="text-sm text-gray-400 font-medium text-center m-auto p-4">No apps today.</div>
                    <?php else: ?>
                        <div class="chart-container-wrap m-auto !p-0 mt-4 mb-2" style="min-height:160px;"><canvas id="classChart"></canvas></div>
                        <div class="w-full flex flex-col border-t border-gray-100 overflow-y-auto">
                            <?php foreach ($class_labels as $i => $lbl): ?>
                            <div class="flex justify-between text-xs py-2 px-5 border-b border-gray-100/50 last:border-0 hover:bg-white transition-colors">
                                <span class="text-gray-600 font-bold"><?php echo htmlspecialchars($lbl); ?></span>
                                <span class="font-black text-emerald-600"><?php echo $class_data[$i]; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form Breakdown -->
        <div class="grid-stack-item" gs-id="widget-breakdown" gs-w="4" gs-h="5" gs-x="0" gs-y="7">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg> Pipeline Status</h3>
                    <i class="edit-hint text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                </div>
                <div class="flex-1 w-full p-6 flex flex-col justify-center overflow-hidden">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center group bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-200 flex items-center justify-center shrink-0"><span class="w-2 h-2 rounded-full bg-slate-500"></span></div>
                                <span class="text-sm text-gray-700 font-bold">Inquiries</span>
                            </div>
                            <a href="inquiries.php" class="text-xl font-black text-slate-700 hover:text-emerald-600 transition-colors shrink-0"><?php echo number_format($stats['inquiries']); ?></a>
                        </div>
                        <div class="flex justify-between items-center group bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"><span class="w-2 h-2 rounded-full bg-emerald-500"></span></div>
                                <span class="text-sm text-gray-700 font-bold">Admissions</span>
                            </div>
                            <a href="applications.php" class="text-xl font-black text-emerald-700 hover:text-emerald-500 transition-colors shrink-0"><?php echo number_format($stats['admissions']); ?></a>
                        </div>
                        
                        <div class="w-full h-px bg-gray-100 my-1"></div>
                        
                        <div class="flex justify-between items-center px-2">
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded bg-blue-500 shrink-0"></span><span class="text-xs text-gray-600 font-bold">Approved</span></div>
                            <span class="text-sm font-black text-blue-600"><?php echo number_format($stats['approved']); ?></span>
                        </div>
                        <div class="flex justify-between items-center px-2">
                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded bg-red-500 shrink-0"></span><span class="text-xs text-gray-600 font-bold">Rejected</span></div>
                            <span class="text-sm font-black text-red-500"><?php echo number_format($stats['rejected']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Intelligence Widget -->
        <div class="grid-stack-item" gs-id="widget-intel" gs-w="4" gs-h="5" gs-x="4" gs-y="7">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> System Intelligence</h3>
                    <i class="edit-hint text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                </div>
                <div class="flex flex-col gap-4 p-5 flex-1 overflow-hidden bg-gray-50/50 justify-center">
                    <div class="bg-gradient-to-r from-teal-500 to-emerald-600 rounded-2xl shadow p-5 text-white flex items-center justify-between shrink-0">
                        <div>
                            <h3 class="text-[10px] font-bold uppercase tracking-widest opacity-80 mb-1">Total Revenue</h3>
                            <div class="text-2xl font-black truncate">Rs. <?php echo number_format($revenue); ?></div>
                        </div>
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 flex-1">
                        <div class="bg-white rounded-2xl border border-rose-100 shadow-sm p-3 flex flex-col justify-center items-center text-center">
                            <div class="text-2xl font-black text-rose-500 leading-none mb-1 shadow-rose-100"><?php echo number_format($missing_docs); ?></div>
                            <div class="text-[9px] uppercase font-bold text-gray-500 mt-1">Missing Docs</div>
                        </div>
                        <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-3 flex flex-col justify-center items-center text-center">
                            <div class="text-2xl font-black text-amber-500 leading-none mb-1 shadow-amber-100"><?php echo number_format($unscheduled); ?></div>
                            <div class="text-[9px] uppercase font-bold text-gray-500 mt-1">Unscheduled</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Exams Widget -->
        <div class="grid-stack-item" gs-id="widget-exams" gs-w="4" gs-h="6" gs-x="8" gs-y="8">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> Upcoming Exams</h3>
                    <div class="absolute right-4 flex items-center gap-2">
                        <i class="edit-hint text-slate-300 w-4 h-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                    </div>
                </div>
                <div class="flex-1 w-full overflow-y-auto bg-white">
                    <?php if (empty($upcoming_exams)): ?>
                        <div class="flex items-center justify-center h-full p-6 text-center text-sm text-gray-400 font-bold">No upcoming exams.</div>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-50">
                            <?php foreach ($upcoming_exams as $ue): ?>
                                <?php 
                                    $remaining = max(0, $ue['total_capacity'] - $ue['booked']); 
                                    $pct_booked = ($ue['total_capacity'] > 0) ? min(100, ($ue['booked'] / $ue['total_capacity']) * 100) : 0;
                                    $bar_color = 'bg-indigo-500'; $text_color = 'text-emerald-600'; $bg_badge="bg-emerald-50";
                                    if ($remaining <= 5 && $remaining > 0) { $bar_color = 'bg-orange-500'; $text_color = 'text-orange-600'; $bg_badge='bg-orange-50'; } 
                                    elseif ($remaining == 0) { $bar_color = 'bg-red-500'; $text_color = 'text-red-600'; $bg_badge='bg-red-50'; }
                                ?>
                                <li class="p-5 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <div class="font-black text-gray-800 text-xs truncate mr-2"><?php echo htmlspecialchars($ue['class_name'] . ($ue['faculty_name'] ? ' ('.$ue['faculty_name'].')' : '')); ?></div>
                                        <div class="text-[10px] uppercase tracking-widest font-black <?php echo $text_color; ?> <?php echo $bg_badge; ?> px-1.5 py-0.5 rounded-sm shrink-0"><?php echo $remaining; ?> Left</div>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mb-2.5 font-bold">
                                        <?php echo date('M d', strtotime($ue['exam_date'])) . ' &bull; ' . date('h:i A', strtotime($ue['exam_time'])); ?>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1 overflow-hidden"><div class="<?php echo $bar_color; ?> h-1 rounded-full" style="width: <?php echo $pct_booked; ?>%;"></div></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Submissions Widget -->
        <div class="grid-stack-item" gs-id="widget-recent" gs-w="8" gs-h="7" gs-x="0" gs-y="12">
            <div class="grid-stack-item-content">
                <div class="drag-handle relative border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-xs font-extrabold text-gray-700 uppercase tracking-widest flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Daily Feed</h3>
                    <i class="edit-hint text-slate-300 w-4 h-4 absolute right-4"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></i>
                </div>
                <div class="overflow-auto flex-1 bg-white">
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="text-[10px] text-gray-400 uppercase tracking-wider bg-white sticky top-0 z-10 shadow-sm">
                            <tr><th class="px-5 py-3 text-left">Student</th><th class="px-5 py-3 text-left">Class</th><th class="px-5 py-3 text-left">Date</th><th class="px-5 py-3 text-left">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($recent)): ?>
                            <tr><td colspan="4" class="px-5 py-12 text-center text-gray-400 text-xs font-bold">No submissions yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recent as $r):
                                $badge = ['Pending'=>'bg-yellow-100 text-yellow-700','Approved'=>'bg-blue-100 text-blue-700','Rejected'=>'bg-red-100 text-red-700','Admitted'=>'bg-emerald-100 text-emerald-700'];
                                $bc = $badge[$r['status']] ?? 'bg-gray-100 text-gray-600';
                            ?>
                            <tr class="hover:bg-slate-50 transition cursor-default">
                                <td class="px-5 py-3.5">
                                    <a href="view_application.php?id=<?php echo $r['id']; ?>" class="font-black text-gray-900 hover:text-emerald-600 transition-colors" onmousedown="event.stopPropagation()"><?php echo htmlspecialchars($r['student_first_name'].' '.$r['student_last_name']); ?></a>
                                    <?php if($r['form_type']==='Admission'): ?><div class="text-[9px] font-black uppercase text-emerald-500 mt-1">Admission</div><?php endif;?>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600 text-xs font-bold"><?php echo htmlspecialchars($r['applied_class']); ?></td>
                                <td class="px-5 py-3.5 text-gray-400 text-[11px] font-bold"><?php echo date('M d', strtotime($r['submission_date'])); ?></td>
                                <td class="px-5 py-3.5"><span class="px-2.5 py-1 rounded-sm text-[10px] font-black uppercase tracking-wider <?php echo $bc; ?>"><?php echo $r['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>
</main></div>

<script>
let isEditMode = false;

// Initialize GridStack securely statically
const grid = GridStack.init({
    cellHeight: 75,
    margin: 15,
    minRow: 1,
    handle: '.drag-handle',
    float: true,
    staticGrid: true // Static default (no resize flags show, no dragging)
});

// Load saved layout
const savedLayout = localStorage.getItem('admin_dashboard_layout');
if (savedLayout) {
    grid.load(JSON.parse(savedLayout));
    // Force static after load in case GridStack internally overrides it via object
    grid.setStatic(true);
}

// Edit Mode Toggle
const editBtn = document.getElementById('editLayoutBtn');
editBtn.addEventListener('click', () => {
    isEditMode = !isEditMode;
    grid.setStatic(!isEditMode);
    document.body.classList.toggle('is-editing', isEditMode);
    
    if(isEditMode) {
        editBtn.innerHTML = `<svg class="w-4 h-4 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Save Layout</span>`;
        editBtn.className = "inline-flex py-1.5 px-3 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded-lg shadow-sm transition-all items-center gap-1.5 font-bold text-xs border border-emerald-200";
    } else {
        editBtn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg><span>Edit Layout</span>`;
        editBtn.className = "inline-flex py-1.5 px-3 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg shadow-sm transition-all items-center gap-1.5 font-bold text-xs border border-transparent";
        
        // Save the exact user coordinates instantly.
        localStorage.setItem('admin_dashboard_layout', JSON.stringify(grid.save(false)));
    }
});

// Prevent chart from breaking during drag mapping resize issues
const resizeObserver = new ResizeObserver(() => {
    if(window.trendChartInst) window.trendChartInst.resize();
    if(window.classChartInst) window.classChartInst.resize();
});
document.querySelectorAll('.chart-container-wrap').forEach(el => resizeObserver.observe(el));

// Chart.js 
const tc = document.getElementById('trendChart');
if(tc){
    window.trendChartInst = new Chart(tc.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Submissions', data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', borderWidth: 3, tension: 0.4, fill: true, pointBackgroundColor: '#10b981', pointRadius: 2, pointHoverRadius: 6
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, border: {display:false, dash:[4,4]}, grid: {color: '#f3f4f6', tickLength:0}, ticks: {stepSize: 1, color: '#9ca3af', font:{weight:'bold', size:10}} }, x: { border: {display:false}, grid: {display:false}, ticks: {color: '#9ca3af', font:{weight:'bold', size:10}} } }
        }
    });
}

<?php if (!empty($class_data)): ?>
const cc = document.getElementById('classChart');
if(cc){
    window.classChartInst = new Chart(cc.getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($class_labels); ?>,
            datasets: [{ data: <?php echo json_encode($class_data); ?>, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#0ea5e9'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: {titleFont:{size:11}, bodyFont:{size:13, weight:'bold'}, padding:10} } }
    });
}
<?php endif; ?>
</script>
</body>
</html>
"""

with open('dashboard.php', 'w', encoding='utf-8') as f:
    f.write(php_top + html_part)

print("dashboard.php successfully rewritten!")
