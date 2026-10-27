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
        'default_calls_target' => (int) env('DEFAULT_CALLS_TARGET', 100), // calls per day (daily work report)
        'default_meetings_target' => (int) env('DEFAULT_MEETINGS_TARGET', 0),
        'grades' => ['full' => 100, 'half' => 50, 'quarter' => 25], // minimum score for each grade; below quarter = absent
        'idle_after_seconds' => 60,                                  // no activity for 1 minute = not working
        // Inactive time during the shift extends it (up to this much); at the extended end the
        // employee is punched out automatically and the day is scored with the work done so far.
        'max_extension_minutes' => (int) env('MAX_SHIFT_EXTENSION_MINUTES', 120),
    ],

    // Daily work report e-mail (sent at punch-out and after an admin correction)
    'report_mail' => [
        'enabled' => (bool) env('REPORT_MAIL_ENABLED', true),
        'cc_admins' => (bool) env('REPORT_MAIL_CC_ADMINS', true),
    ],

    // Built-in meeting room. Video goes directly between browsers (free STUN). Some strict office /
    // mobile networks also need a TURN relay — add one here if a call keeps showing "Connection failed".
    'meeting' => [
        'turn_url' => env('MEET_TURN_URL'),      // e.g. turn:relay.example.com:3478,turns:relay.example.com:443
        'turn_user' => env('MEET_TURN_USER'),
        'turn_pass' => env('MEET_TURN_PASS'),
    ],

    // WhatsApp inside the portal: each user scans the QR in the side panel with their own phone.
    // The gateway (whatsapp-gateway/server.mjs) runs on the server and is started automatically when needed.
    'whatsapp' => [
        'enabled' => (bool) env('WA_ENABLED', true),
        'socket' => env('WA_GATEWAY_SOCKET'),            // server: /home/<user>/wa-gateway/gateway.sock
        'url' => env('WA_GATEWAY_URL', 'http://127.0.0.1:3911'), // development (no socket)
        'dir' => env('WA_GATEWAY_DIR'),                  // server: /home/<user>/wa-gateway
        'node' => env('WA_NODE_BIN', '/opt/alt/alt-nodejs24/root/usr/bin/node'),
        'key' => env('WA_GATEWAY_KEY', ''),
    ],

    // Ready messages in the lead WhatsApp / e-mail popups. {Name} {Date} {Time} {Employee} {Company} {Purpose} are filled in.
    'message_templates' => [
        'general' => [
            'whatsapp' => [
                'Service Info' => 'Hi {Name}, thank you for your interest in {Purpose}. Please find the details here — let me know if you have any questions. – {Employee}, {Company}',
                'Meeting Invitation' => 'Hi {Name}, your meeting is scheduled on {Date} at {Time}. The join link will be shared shortly. – {Employee}',
                'Follow-up' => 'Hi {Name}, just following up on our discussion. Are you still interested in {Purpose}?',
                'Document Request' => 'Hi {Name}, please share the required documents so we can proceed with your {Purpose} request.',
                'General Inquiry' => 'Hi {Name}, let me know when you are available for a quick discussion. – {Employee}, {Company}',
            ],
            'email' => [
                'Service Details' => ['Details about {Purpose}', "Hi {Name},

Thank you for your time today. Please find the details about {Purpose} attached / below. Let me know if you have any questions.

Regards,
{Employee}
{Company}"],
                'Meeting Confirmation' => ['Meeting on {Date} at {Time}', "Hi {Name},

This is to confirm our meeting on {Date} at {Time}. The join link will be shared before the meeting.

Regards,
{Employee}
{Company}"],
                'Follow-up' => ['Following up — {Purpose}', "Hi {Name},

I wanted to follow up on our recent conversation about {Purpose}. Please let me know how you would like to proceed.

Regards,
{Employee}"],
            ],
        ],
        'hr' => [
            'whatsapp' => [
                'Job Profile Info' => 'Hi {Name}, please find the {Purpose} job profile details. Let me know if you are interested. – {Employee}, {Company}',
                'Interview Invitation' => 'Hi {Name}, your online interview is scheduled on {Date} at {Time}. The join link will be shared shortly.',
                'Follow-up Message' => 'Hi {Name}, just following up on our discussion. Are you still interested in the {Purpose} profile?',
                'Document Request' => 'Hi {Name}, please share your updated resume and required documents.',
                'General Inquiry' => 'Hi {Name}, let me know if you are available for a quick discussion.',
            ],
            'email' => [
                'Job Opportunity' => ['{Purpose} — profile details', "Hi {Name},

Please find the {Purpose} job profile details attached. Let me know if you are interested and available for a discussion.

Regards,
{Employee}
{Company}"],
                'Interview Invitation' => ['Interview on {Date} at {Time}', "Hi {Name},

Your interview for {Purpose} is scheduled on {Date} at {Time}. The join link will be shared before the interview.

Regards,
{Employee}
{Company}"],
                'Offer / Joining' => ['Welcome aboard, {Name}', "Hi {Name},

Congratulations! Please find your joining details attached.

Regards,
{Employee}
{Company}"],
            ],
        ],
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
            ['route' => 'employee.attendance.today', 'icon' => 'fingerprint', 'label' => 'Check-in / Attendance', 'feature' => 'attendance'],
            ['route' => 'employee.work-timer', 'icon' => 'stopwatch', 'label' => 'Live Work Timer', 'feature' => 'attendance'],
            ['route' => 'employee.breaks.index', 'icon' => 'cup-hot', 'label' => 'Break Management', 'feature' => 'attendance'],
            ['route' => 'employee.tasks.index', 'active' => 'employee.tasks.*', 'icon' => 'briefcase', 'label' => 'My Assigned Work'],
            ['route' => 'employee.meetings.index', 'active' => 'employee.meetings.*', 'icon' => 'camera-video', 'label' => 'Meetings', 'feature' => 'meetings'],
            ['heading' => 'Leads', 'feature' => 'leads'],
            ['route' => 'employee.leads.create', 'icon' => 'person-plus', 'label' => 'Create Lead', 'feature' => 'leads'],
            ['route' => 'employee.leads.index', 'active' => ['employee.leads.index', 'employee.leads.show'], 'icon' => 'people', 'label' => 'My Leads', 'feature' => 'leads'],
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
            ['route' => 'manager.tracking', 'icon' => 'geo-alt', 'label' => 'Live Tracking'],
            ['route' => 'manager.meetings.index', 'icon' => 'camera-video', 'label' => 'Meetings'],
            ['route' => 'manager.reports.daily', 'icon' => 'clipboard-data', 'label' => 'Daily Work Report'],
            ['route' => 'manager.corrections.index', 'active' => 'manager.corrections.*', 'icon' => 'pencil-square', 'label' => 'Correction Requests'],
            ['route' => 'manager.targets', 'icon' => 'bullseye', 'label' => 'Daily Targets'],
            ['route' => 'manager.assign-work.create', 'icon' => 'clipboard-plus', 'label' => 'Assign Work'],
            ['route' => 'manager.logout-requests.index', 'icon' => 'box-arrow-right', 'label' => 'Logout Requests'],
            ['route' => 'manager.approvals.index', 'icon' => 'check2-square', 'label' => 'Approvals'],
            ['route' => 'manager.review.index', 'active' => 'manager.review.*', 'icon' => 'clipboard-check', 'label' => 'Team Leads & Review'],
            ['heading' => 'My Work', 'feature' => 'attendance'],
            ['route' => 'employee.attendance.today', 'icon' => 'fingerprint', 'label' => 'Punch In / Out', 'feature' => 'attendance'],
            ['route' => 'employee.work-timer', 'icon' => 'stopwatch', 'label' => 'Live Work Timer', 'feature' => 'attendance'],
            ['route' => 'employee.breaks.index', 'icon' => 'cup-hot', 'label' => 'Break Management', 'feature' => 'attendance'],
            ['route' => 'employee.attendance.history', 'icon' => 'calendar3', 'label' => 'My Attendance', 'feature' => 'attendance'],
            ['route' => 'employee.leads.index', 'active' => 'employee.leads.*', 'icon' => 'people', 'label' => 'My Leads', 'feature' => 'leads'],
            ['route' => 'employee.meetings.index', 'active' => 'employee.meetings.*', 'icon' => 'camera-video', 'label' => 'My Meetings', 'feature' => 'meetings'],
            ['heading' => 'Account'],
            ['route' => 'notifications.index', 'icon' => 'megaphone', 'label' => 'Announcements'],
            ['route' => 'profile.show', 'icon' => 'person-circle', 'label' => 'My Profile'],
        ],

        'admin' => [
            ['heading' => 'Administration'],
            ['route' => 'admin.dashboard', 'icon' => 'speedometer2', 'label' => 'Dashboard'],
            ['route' => 'admin.tracking', 'icon' => 'geo-alt', 'label' => 'Live Tracking'],
            ['route' => 'admin.employees.index', 'active' => 'admin.employees.*', 'icon' => 'person-badge', 'label' => 'Employees'],
            ['route' => 'admin.roles.index', 'icon' => 'shield-check', 'label' => 'Roles'],
            // 'children' => 'departments': sidebar shows every department as a dropdown sub-menu
            ['route' => 'admin.departments.index', 'active' => 'admin.departments.*', 'icon' => 'building', 'label' => 'Department Data', 'children' => 'departments'],
            ['route' => 'admin.data.index', 'active' => 'admin.data.*', 'icon' => 'cloud-arrow-up', 'label' => 'Data Upload & Share'],
            ['route' => 'admin.leads.interested', 'icon' => 'hand-thumbs-up', 'label' => 'Interested Leads'],
            ['route' => 'admin.reports.daily', 'icon' => 'clipboard-data', 'label' => 'Daily Work Report'],
            ['route' => 'admin.corrections.index', 'active' => 'admin.corrections.*', 'icon' => 'pencil-square', 'label' => 'Correction Requests'],
            ['route' => 'admin.meetings.index', 'icon' => 'camera-video', 'label' => 'Meetings'],
            ['route' => 'admin.logout-requests.index', 'icon' => 'box-arrow-right', 'label' => 'Logout Requests'],
            ['route' => 'admin.late-requests.index', 'icon' => 'alarm', 'label' => 'Late Check-in Requests'],
            ['route' => 'admin.leaves.index', 'icon' => 'calendar2-check', 'label' => 'Leave Approvals'],
            ['heading' => 'Account'],
            ['route' => 'notifications.index', 'icon' => 'megaphone', 'label' => 'Announcements'],
            ['route' => 'profile.show', 'icon' => 'person-circle', 'label' => 'My Profile'],
        ],
    ],

];
