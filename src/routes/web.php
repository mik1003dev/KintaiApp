<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

Route::get('/admin/login', function () {
    return view('auth.admin_login');
})->middleware('guest')->name('admin.login');

Route::middleware(['auth', 'verified.user'])->group(function () {
    // 勤怠打刻画面を表示する
    Route::get(
        '/attendance',
        [AttendanceController::class, 'index']
    )->name('attendance.index');

    // 出勤ボタン押下時の処理
    Route::post(
        '/attendance/clock-in',
        [AttendanceController::class, 'clockIn']
    )->name('attendance.clock_in');

    // 休憩入ボタン押下時の処理
    Route::post(
        '/attendance/break-in',
        [AttendanceController::class, 'breakIn']
    )->name('attendance.break_in');

    // 休憩戻ボタン押下時の処理
    Route::post(
        '/attendance/break-out',
        [AttendanceController::class, 'breakOut']
    )->name('attendance.break_out');

    // 退勤ボタン押下時の処理
    Route::post(
        '/attendance/clock-out',
        [AttendanceController::class, 'clockOut']
    )->name('attendance.clock_out');

    // 勤怠一覧画面を表示する
    Route::get(
        '/attendance/list',
        [AttendanceController::class, 'list']
    )->name('attendance.list');

    // 勤怠詳細画面を日付指定で表示する（未出勤日用）
    Route::get(
        '/attendance/detail/date/{date}',
        [AttendanceController::class, 'showByDate']
    )->name('attendance.show_by_date');

    // 勤怠詳細画面を表示する
    Route::get(
        '/attendance/detail/{id}',
        [AttendanceController::class, 'show']
    )->name('attendance.show');

    // 勤怠修正申請を送信する
    Route::post(
        '/attendance/detail/{id}',
        [AttendanceController::class, 'updateRequest']
    )->name('attendance.update_request');

    // 申請一覧画面（一般ユーザー / 管理者 共通パス）
    Route::get(
        '/stamp_correction_request/list',
        [AttendanceController::class, 'requestList']
    )->name('attendance.request_list');
});

Route::middleware('admin')->group(function () {
    // 管理者 日次勤怠一覧画面
    Route::get(
        '/admin/attendance/list',
        [AttendanceController::class, 'adminAttendanceList']
    )->name('admin.attendance_list');

    // 修正申請承認画面（管理者）
    Route::get(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [AttendanceController::class, 'adminRequestShow']
    )->name('admin.request_show');

    // 修正申請承認処理（管理者）
    Route::post(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [AttendanceController::class, 'approveRequest']
    )->name('admin.request_approve');

    // 管理者 勤怠詳細 修正処理
    Route::post(
        '/admin/attendance/{id}',
        [AttendanceController::class, 'adminAttendanceUpdate']
    )->name('admin.attendance.update');

    // スタッフ一覧
    Route::get(
        '/admin/staff/list',
        [AttendanceController::class, 'adminStaffList']
    )->name('admin.staff.list');

    // スタッフ別月次勤怠一覧
    Route::get(
        '/admin/attendance/staff/{id}',
        [AttendanceController::class, 'adminStaffAttendanceList']
    )->name('admin.staff.attendance.list');

    // 管理者 勤怠詳細画面を日付指定で表示する（スタッフ別一覧の未出勤日用）
    Route::get(
        '/admin/attendance/detail/{user}/{date}',
        [AttendanceController::class, 'adminAttendanceShowByDate']
    )->name('admin.attendance.show_by_date');

    // 管理者 勤怠詳細画面
    Route::get(
        '/admin/attendance/{id}',
        [AttendanceController::class, 'adminAttendanceShow']
    )->name('admin.attendance.show');

    // CSV出力
    Route::get(
        '/admin/attendance/staff/{id}/csv',
        [AttendanceController::class, 'adminStaffAttendanceCsv']
    )->name('admin.staff.attendance.csv');
});
