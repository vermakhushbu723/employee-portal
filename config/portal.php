<?php

/*
|--------------------------------------------------------------------------
| Portal configuration
|--------------------------------------------------------------------------
| Business settings and the sidebar menu for each portal (employee,
| manager, admin). A user's portal comes from App\Enums\Role::portal().
*/

return [

    'company' => env('PORTAL_COMPANY', 'Employee Portal'),

    // Amount deducted per unpaid leave day.
    'leave_deduction_per_day' => (int) env('LEAVE_DEDUCTION_PER_DAY', 500),

    // Carbon day-of-week numbers treated as weekly off (0 = Sunday).
    'weekly_off' => [0],

    /*
    | Work-based attendance. Score = average of three parts (each max 100%):
    |   active time / shift time, leads created / target, interested+converted / target.
    | e.g. 8h + 20 + 20 = 100% (Full), 4h + 10 + 10 = 50% (Half), 2h + 5 + 5 = 25% (Quarter).
    */
    'work_score' => [
        'default_targets' => ['leads' => 20, 'interested' => 20],   // per day; admin/manager can set per employee
        'grades' => ['full' => 100, 'half' => 50, 'quarter' => 25], // minimum score for each grade; below quarter = absent
        'idle_after_seconds' => 60,                                  // no activity for 1 minute = not working
    ],

    // Daily work report e-mail (sent at punch-out and after an admin correction)
    'report_mail' => [
        'enabled' => (bool) env('REPORT_MAIL_ENABLED', true),
        'cc_admins' => (bool) env('REPORT_MAIL_CC_ADMINS', true),
    ],

    'lead' => [
        'cities' => ['Delhi', 'Gurugram', 'Noida', 'Mumbai', 'Bengaluru', 'Lucknow', 'Pune', 'Jaipur', 'Hyderabad', 'Kolkata'],
        'sources' => ['Cold Call', 'Website', 'Facebook', 'Instagram', 'Referral', 'Walk-in', 'WhatsApp', 'Other'],
        'interests' => ['Home Loan', 'Personal Loan', 'Credit Card', 'Insurance', 'Mutual Fund', 'Business Loan', 'Other'],
    ],

    'task_categories' => [
        'telecalling' => ['label' => 'Outbound Calling', 'icon' => 'telephone-fill', 'tone' => 'success'],
        'lead_generation' => ['label' => 'Lead Generation', 'icon' => 'people-fill', 'tone' => 'primary'],
        'follow_up' => ['label' => 'Follow-up Calls', 'icon' => 'person-lines-fill', 'tone' => 'warning'],
        'data_entry' => ['label' => 'Data Update', 'icon' => 'file-earmark-text-fill', 'tone' => 'purple'],
        'report' => ['label' => 'Daily Report', 'icon' => 'clipboard-data-fill', 'tone' => 'danger'],
        'field_work' => ['label' => 'Field Work', 'icon' => 'geo-alt-fill', 'tone' => 'info'],
        'verification' => ['label' => 'Customer Verification', 'icon' => 'patch-check-fill', 'tone' => 'info'],
    ],

    // Mobile bottom navigation per portal (a "More" button that opens the full menu is always added last).
    'bottom_nav' => [
        'employee' => [
            ['route' => 'employee.dashboard', 'icon' => 'house-door', 'label' => 'Home'],
            ['route' => 'employee.tasks.index', 'active' => 'employee.tasks.*', 'icon' => 'briefcase', 'label' => 'Work'],
            ['route' => 'employee.work-timer', 'icon' => 'stopwatch', 'label' => 'Timer', 'fab' => true],
            ['route' => 'employee.leads.index', 'active' => 'employee.leads.*', 'icon' => 'people', 'label' => 'Leads'],
        ],
        'manager' => [
            ['route' => 'manager.dashboard', 'icon' => 'house-door', 'label' => 'Home'],
            ['route' => 'manager.team', 'icon' => 'people', 'label' => 'Team'],
            ['route' => 'manager.assign-work.create', 'icon' => 'plus-lg', 'label' => 'Assign Work', 'fab' => true],
            ['route' => 'manager.approvals.index', 'icon' => 'clipboard-check', 'label' => 'Approvals'],
        ],
        'admin' => [
            ['route' => 'admin.dashboard', 'icon' => 'house-door', 'label' => 'Home'],
            ['route' => 'admin.employees.index', 'active' => ['admin.employees.index', 'admin.employees.edit'], 'icon' => 'person-badge', 'label' => 'Staff'],
            ['route' => 'admin.employees.create', 'icon' => 'person-plus', 'label' => 'Add Employee', 'fab' => true],
            ['route' => 'admin.late-requests.index', 'icon' => 'alarm', 'label' => 'Late'],
        ],
    ],

    'menus' => [
        'employee' => [
            ['heading' => 'Workspace'],
            ['route' => 'employee.dashboard', 'icon' => 'house-door', 'label' => 'Dashboard'],
            ['route' => 'employee.attendance.today', 'icon' => 'fingerprint', 'label' => 'Check-in / Attendance'],
            ['route' => 'employee.work-timer', 'icon' => 'stopwatch', 'label' => 'Live Work Timer'],
            ['route' => 'employee.breaks.index', 'icon' => 'cup-hot', 'label' => 'Break Management'],
            ['route' => 'employee.tasks.index', 'active' => 'employee.tasks.*', 'icon' => 'briefcase', 'label' => 'My Assigned Work'],
            ['heading' => 'Leads'],
            ['route' => 'employee.leads.create', 'icon' => 'person-plus', 'label' => 'Create Lead'],
            ['route' => 'employee.leads.index', 'active' => ['employee.leads.index', 'employee.leads.show'], 'icon' => 'people', 'label' => 'My Leads'],
            ['heading' => 'Records'],
            ['route' => 'employee.attendance.history', 'icon' => 'calendar3', 'label' => 'My Attendance'],
            ['route' => 'employee.leaves.index', 'icon' => 'calendar2-x', 'label' => 'Leave History'],
            ['route' => 'employee.leaves.charges', 'icon' => 'currency-rupee', 'label' => 'Leave Charges'],
            ['route' => 'employee.performance', 'icon' => 'bar-chart-line', 'label' => 'My Performance'],
            ['heading' => 'Account'],
            ['route' => 'notifications.index', 'icon' => 'megaphone', 'label' => 'Announcements'],
            ['route' => 'profile.show', 'icon' => 'person-circle', 'label' => 'My Profile'],
        ],

        'manager' => [
            ['heading' => 'Team'],
            ['route' => 'manager.dashboard', 'icon' => 'speedometer2', 'label' => 'Dashboard'],
            ['route' => 'manager.team', 'icon' => 'broadcast', 'label' => 'Live Monitoring'],
            ['route' => 'manager.reports.daily', 'icon' => 'clipboard-data', 'label' => 'Daily Work Report'],
            ['route' => 'manager.targets', 'icon' => 'bullseye', 'label' => 'Daily Targets'],
            ['route' => 'manager.assign-work.create', 'icon' => 'clipboard-plus', 'label' => 'Assign Work'],
            ['route' => 'manager.approvals.index', 'icon' => 'check2-square', 'label' => 'Approvals'],
            ['heading' => 'Account'],
            ['route' => 'notifications.index', 'icon' => 'megaphone', 'label' => 'Announcements'],
            ['route' => 'profile.show', 'icon' => 'person-circle', 'label' => 'My Profile'],
        ],

        'admin' => [
            ['heading' => 'Administration'],
            ['route' => 'admin.dashboard', 'icon' => 'speedometer2', 'label' => 'Dashboard'],
            ['route' => 'admin.employees.index', 'active' => 'admin.employees.*', 'icon' => 'person-badge', 'label' => 'Employees'],
            // 'children' => 'departments': sidebar shows every department as a dropdown sub-menu
            ['route' => 'admin.departments.index', 'active' => 'admin.departments.*', 'icon' => 'building', 'label' => 'Department Data', 'children' => 'departments'],
            ['route' => 'admin.data.index', 'active' => 'admin.data.*', 'icon' => 'cloud-arrow-up', 'label' => 'Data Upload & Share'],
            ['route' => 'admin.leads.interested', 'icon' => 'hand-thumbs-up', 'label' => 'Interested Leads'],
            ['route' => 'admin.reports.daily', 'icon' => 'clipboard-data', 'label' => 'Daily Work Report'],
            ['route' => 'admin.late-requests.index', 'icon' => 'alarm', 'label' => 'Late Check-in Requests'],
            ['route' => 'admin.leaves.index', 'icon' => 'calendar2-check', 'label' => 'Leave Approvals'],
            ['heading' => 'Account'],
            ['route' => 'notifications.index', 'icon' => 'megaphone', 'label' => 'Announcements'],
            ['route' => 'profile.show', 'icon' => 'person-circle', 'label' => 'My Profile'],
        ],
    ],

];
