# Employee Management & Work Tracking Portal

Laravel 12 + MySQL + Blade + Bootstrap 5. The frontend and backend are both in this one Laravel app.
The layout is mobile-first: phones get a bottom navigation bar and desktops get a sidebar.

## Setup

Requirements: PHP 8.2+, Composer, MySQL 8 (XAMPP / Laragon work fine).

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# create an empty MySQL database named employee_portal, then:
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000. Every demo account uses the password **`password`**.

| Employee ID | Role | Lands on |
|---|---|---|
| `EMP001` (Riya Sharma) | Employee | `/employee/dashboard` |
| `EMP002`, `EMP003` | Employee | `/employee/dashboard` |
| `MGR001` (Rohit Sharma) | Manager | `/manager/dashboard` |
| `ADM001` | Super Admin | `/admin/dashboard` |
| `HR001` | HR | `/admin/dashboard` |

To reset the demo data: `php artisan migrate:fresh --seed`

## Folder structure

```
app/
├── Enums/                    Role, AttendanceStatus, TaskStatus, LeadStatus, LeaveStatus, LeaveType
├── Http/
│   ├── Controllers/
│   │   ├── Auth/             LoginController (login / sign-out)
│   │   ├── Employee/         one controller per employee screen
│   │   ├── Manager/          dashboard, team monitoring, assign work, approvals
│   │   ├── Admin/            dashboard, employees, leave approvals
│   │   ├── HomeController    sends each role to its own dashboard
│   │   ├── ProfileController / AnnouncementController   (shared by all roles)
│   ├── Middleware/EnsureUserHasRole.php   →  ->middleware('role:manager,team_leader')
│   └── Requests/             form validation (lead, leave, employee, assign work)
├── Models/                   Eloquent models + relations
├── Services/                 business rules (controllers stay thin)
│   ├── AttendanceService     check-in, live timer, checkout rules
│   ├── BreakService          start / end break
│   ├── AttendanceCalendarService   monthly calendar
│   ├── TaskService           assign → start → progress → submit
│   ├── LeadService · LeaveService · PerformanceService · TeamMonitorService
│   └── AuditLogger           writes audit_logs
└── Support/Duration.php      "05h 20m" / "05:20:15" formatting
config/portal.php             company name, deduction rate, lead options, task categories, sidebar menus
database/migrations/          all tables
database/seeders/             demo data that matches the UI designs
resources/views/
├── layouts/                  app (portal shell) · auth
├── partials/                 sidebar, topbar, bottom-nav, flash, shared rows/modals
├── components/               <x-stat-tile> <x-progress> <x-badge> <x-empty-state>
├── auth/  employee/  manager/  admin/  account/
public/css/app.css            design system (colours, tiles, timer ring, calendar…)
public/js/app.js              live timer, modals, confirm dialogs (vanilla JS, no build step)
routes/web.php                all routes, grouped by portal
```

## Employee screens → routes

| # | Screen | URL | Route name |
|---|---|---|---|
| 1 | Login | `/login` | `login` |
| 2 | Employee Dashboard | `/employee/dashboard` | `employee.dashboard` |
| 3 | Check-in / Attendance | `/employee/attendance` | `employee.attendance.today` |
| 4 | Live Work Timer | `/employee/work-timer` | `employee.work-timer` |
| 5 | Break Management | `/employee/breaks` | `employee.breaks.index` |
| 6 | My Assigned Work | `/employee/tasks` | `employee.tasks.index` |
| 7 | Task Details | `/employee/tasks/{id}` | `employee.tasks.show` |
| 8 | Create Lead | `/employee/leads/create` | `employee.leads.create` |
| 9 | My Leads | `/employee/leads` | `employee.leads.index` |
| 10 | My Attendance (calendar) | `/employee/attendance/history` | `employee.attendance.history` |
| 11 | Leave History + Apply | `/employee/leave-history` | `employee.leaves.index` |
| 12 | Leave Charges & Summary | `/employee/leave-charges` | `employee.leaves.charges` |
| 13 | My Performance | `/employee/performance` | `employee.performance` |
| 14 | Announcements | `/notifications` | `notifications.index` |
| 15 | Profile & Settings | `/profile` | `profile.show` |

Manager portal: `/manager/dashboard`, `/manager/team`, `/manager/assign-work`, `/manager/approvals`
Admin portal: `/admin/dashboard`, `/admin/employees`, `/admin/employees/create`, `/admin/leaves`

Run `php artisan route:list` to see every route.

## Key business rules (implemented in `AttendanceService`)

- **Worked time** = (now or checkout) − check-in − total break time. The server calculates it, and the browser only ticks the display between syncs (`/employee/work-timer/status`, every 60 s).
- **Remaining time** = required − worked. It never goes below zero.
- **Normal checkout is blocked** while any of these is true: required hours are not finished, a break is still running, or a *mandatory* task for today has not been submitted.
- **Exception checkout** (shift end, illness or emergency) is always available. It needs a reason and goes to the manager for review, so no one is held at work indefinitely.
- **Website sign-out is separate from attendance checkout.** Signing out never closes an open attendance session.
- **Leave approval** charges the leave balance. If the balance is too small, the leave becomes unpaid and a deduction is added to `payroll_records`.
- Login, check-in/out, breaks, task submits, leads, leave actions and employee changes are all written to `audit_logs`.

## Planned next phase (from the spec)

Department/shift/target management screens, lead verification, attendance correction requests, payroll settings, report exports, a role-permission matrix, and a token-based REST API (Laravel Sanctum) for a mobile app.
