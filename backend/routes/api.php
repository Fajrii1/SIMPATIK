<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeeklyIssueController;
use App\Http\Controllers\Api\WorkloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Aplikasi Penjadwalan & Monitoring BPS Kota Palembang
|--------------------------------------------------------------------------
*/

// Autentikasi Publik
Route::post('/login', [AuthController::class, 'login']);

// Endpoint Terproteksi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Endpoint Terproteksi Kewajiban Ganti Kata Sandi
    Route::middleware('password.changed')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        // Dashboard Adaptif Multi-Role
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Manajemen Pengguna (Khusus Admin)
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword']);

        // Manajemen Tim Kerja & Keanggotaan
        Route::apiResource('teams', TeamController::class);
        Route::get('teams/{team}/members', [TeamController::class, 'members']);
        Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);

        // Pengaturan Sistem
        Route::get('settings', [SettingController::class, 'index']);
        Route::match(['put', 'patch'], 'settings', [SettingController::class, 'update']);

        // Manajemen Kegiatan Statistik (PRD Bagian 14)
        Route::apiResource('activities', ActivityController::class);
        Route::patch('activities/{activity}/complete', [ActivityController::class, 'complete']);
        Route::patch('activities/{activity}/reopen', [ActivityController::class, 'reopen']);

        // Penugasan Pegawai ke Kegiatan & Pratinjau Beban Kerja (PRD Bagian 14)
        Route::get('activities/{activity}/assignments', [AssignmentController::class, 'indexForActivity']);
        Route::post('activities/{activity}/assignments', [AssignmentController::class, 'storeForActivity']);
        Route::post('assignments/preview', [AssignmentController::class, 'preview']);
        Route::apiResource('assignments', AssignmentController::class)->only(['show', 'update', 'destroy']);

        // Monitoring Beban Kerja (PRD Bagian 14)
        Route::get('workload', [WorkloadController::class, 'index']);
        Route::get('workload/warnings', [WorkloadController::class, 'warnings']);
        Route::get('workload/users/{id}', [WorkloadController::class, 'userDetail']);

        // Kalender Kegiatan Lintas Tim (PRD Bagian 14)
        Route::get('calendar', [CalendarController::class, 'index']);

        // Kendala Mingguan (PRD Bagian 14)
        Route::get('issues/carried', [WeeklyIssueController::class, 'carried']);
        Route::patch('issues/{id}/status', [WeeklyIssueController::class, 'updateStatus']);
        Route::apiResource('issues', WeeklyIssueController::class);

        // Notifikasi In-App (PRD Bagian 14)
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Audit Log (Khusus Admin)
        Route::get('audit-logs', [AuditLogController::class, 'index']);

        // Pelaporan dan Ekspor (PDF/CSV)
        Route::get('reports/workload', [ReportController::class, 'workloadReport']);
        Route::get('reports/activities', [ReportController::class, 'activitiesReport']);
    });
});
