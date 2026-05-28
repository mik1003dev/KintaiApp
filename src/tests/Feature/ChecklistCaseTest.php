<?php

namespace Tests\Feature;

use App\Models\Stamp;
use App\Models\StampBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // テストケースID: 1-1 会員登録時、名前未入力のバリデーションを確認する。
    public function test_registration_requires_name_for_checklist()
    {
        $this->from('/register')->post('/register', [
            'email' => 'name-required@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    // テストケースID: 1-2 会員登録時、メールアドレス未入力のバリデーションを確認する。
    public function test_registration_requires_email_for_checklist()
    {
        $this->from('/register')->post('/register', [
            'name' => '登録 ユーザー',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    // テストケースID: 1-3 会員登録時、8文字未満パスワードのバリデーションを確認する。
    public function test_registration_rejects_short_password_for_checklist()
    {
        $this->from('/register')->post('/register', [
            'name' => '登録 ユーザー',
            'email' => 'registration@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    // テストケースID: 1-4 会員登録時、確認用パスワード不一致のバリデーションを確認する。
    public function test_registration_rejects_password_confirmation_mismatch_for_checklist()
    {
        $this->from('/register')->post('/register', [
            'name' => '登録 ユーザー',
            'email' => 'confirmation@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
    }

    // テストケースID: 1-5 会員登録時、パスワード未入力のバリデーションを確認する。
    public function test_registration_requires_password_for_checklist()
    {
        $this->from('/register')->post('/register', [
            'name' => '登録 ユーザー',
            'email' => 'registration@example.com',
        ])->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    // テストケースID: 2-1 一般ユーザーログイン時、メールアドレス未入力を確認する。
    public function test_general_login_requires_email_for_checklist()
    {
        $this->from('/login')->post('/login', ['password' => 'password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    // テストケースID: 2-2 一般ユーザーログイン時、パスワード未入力を確認する。
    public function test_general_login_requires_password_for_checklist()
    {
        $this->from('/login')->post('/login', ['email' => 'login@example.com'])
            ->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    // テストケースID: 2-3 一般ユーザーログイン時、未登録情報を確認する。
    public function test_general_login_rejects_unregistered_credentials_for_checklist()
    {
        $this->from('/login')->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }

    // テストケースID: 3-1 管理者ログイン時、メールアドレス未入力を確認する。
    public function test_admin_login_requires_email_for_checklist()
    {
        $this->from('/admin/login')->post('/login', [
            'login_type' => 'admin',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    // テストケースID: 3-2 管理者ログイン時、パスワード未入力を確認する。
    public function test_admin_login_requires_password_for_checklist()
    {
        $this->from('/admin/login')->post('/login', [
            'login_type' => 'admin',
            'email' => 'admin@example.com',
        ])->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    // テストケースID: 3-3 管理者ログイン時、未登録情報を確認する。
    public function test_admin_login_rejects_unregistered_credentials_for_checklist()
    {
        $this->from('/admin/login')->post('/login', [
            'login_type' => 'admin',
            'email' => 'missing-admin@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }

    // テストケースID: 5-2 勤務中の勤怠ステータス表示を確認する。
    public function test_working_status_is_displayed_on_stamp_screen_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 09:00:00'));
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'working', null);

        $this->actingAs($user)->get('/attendance')->assertSee('出勤中');
    }

    // テストケースID: 5-3 休憩中の勤怠ステータス表示を確認する。
    public function test_break_status_is_displayed_on_stamp_screen_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 12:00:00'));
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'on_break', null);

        $this->actingAs($user)->get('/attendance')->assertSee('休憩中');
    }

    // テストケースID: 5-4 退勤済の勤怠ステータス表示を確認する。
    public function test_finished_status_is_displayed_on_stamp_screen_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 18:00:00'));
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'finished');

        $this->actingAs($user)->get('/attendance')->assertSee('退勤済');
    }

    // テストケースID: 6-1 勤務外ユーザーの出勤ボタン表示を確認する。
    public function test_clock_in_button_is_available_to_off_duty_user_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 08:50:00'));
        $this->actingAs($this->user())->get('/attendance')->assertSee('出勤');
    }

    // テストケースID: 7-1 勤務中ユーザーの休憩入ボタン表示を確認する。
    public function test_break_button_is_available_to_working_user_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 12:00:00'));
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'working', null);

        $this->actingAs($user)->get('/attendance')->assertSee('休憩入');
    }

    // テストケースID: 7-2 一日に複数回休憩できることを確認する。
    public function test_user_can_take_multiple_breaks_in_one_day_for_checklist()
    {
        $user = $this->user();
        $stamp = $this->stamp($user, '2026-05-22', 'working', null);

        Carbon::setTestNow(Carbon::parse('2026-05-22 10:00:00'));
        $this->actingAs($user)->post('/attendance/break-in');
        Carbon::setTestNow(Carbon::parse('2026-05-22 10:15:00'));
        $this->actingAs($user)->post('/attendance/break-out');
        Carbon::setTestNow(Carbon::parse('2026-05-22 15:00:00'));
        $this->actingAs($user)->post('/attendance/break-in');
        Carbon::setTestNow(Carbon::parse('2026-05-22 15:10:00'));
        $this->actingAs($user)->post('/attendance/break-out');

        $this->assertSame(2, StampBreak::where('stamp_id', $stamp->id)->count());
    }

    // テストケースID: 8-1 勤務中ユーザーの退勤ボタン表示を確認する。
    public function test_clock_out_button_is_available_to_working_user_for_checklist()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 17:50:00'));
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'working', null);

        $this->actingAs($user)->get('/attendance')->assertSee('退勤');
    }

    // テストケースID: 8-3 退勤時刻が勤怠一覧に反映されることを確認する。
    public function test_clocked_out_record_is_visible_on_attendance_list_for_checklist()
    {
        $user = $this->user();
        $this->stamp($user, '2026-05-22', 'finished');

        $this->actingAs($user)->get('/attendance/list?month=2026-05')
            ->assertSee('05/22(金)')
            ->assertSee('09:00')
            ->assertSee('18:00');
    }

    // テストケースID: 9-3 一般ユーザー勤怠一覧の前月表示を確認する。
    public function test_user_attendance_list_moves_to_previous_month_for_checklist()
    {
        $user = $this->user();
        $this->stamp($user, '2026-04-10', 'finished');

        $this->actingAs($user)->get('/attendance/list?month=2026-04')
            ->assertSee('2026/04')
            ->assertSee('04/10(金)');
    }

    // テストケースID: 9-4 一般ユーザー勤怠一覧の翌月表示を確認する。
    public function test_user_attendance_list_moves_to_next_month_for_checklist()
    {
        $user = $this->user();
        $this->stamp($user, '2026-06-10', 'finished');

        $this->actingAs($user)->get('/attendance/list?month=2026-06')
            ->assertSee('2026/06')
            ->assertSee('06/10(水)');
    }

    // テストケースID: 9-5 一般ユーザー勤怠一覧から詳細へ遷移できることを確認する。
    public function test_user_attendance_list_has_detail_link_for_checklist()
    {
        $user = $this->user();
        $stamp = $this->stamp($user, '2026-05-22', 'finished');

        $this->actingAs($user)->get('/attendance/list?month=2026-05')
            ->assertSee(route('attendance.show', ['id' => $stamp->id]), false);
    }

    // テストケースID: 12-3 管理者日次勤怠一覧の前日表示を確認する。
    public function test_admin_daily_list_moves_to_previous_day_for_checklist()
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->stamp($staff, '2026-05-21', 'finished');

        $this->actingAs($admin)->get('/admin/attendance/list?date=2026-05-21')
            ->assertSee('2026年5月21日の勤怠');
    }

    // テストケースID: 12-4 管理者日次勤怠一覧の翌日表示を確認する。
    public function test_admin_daily_list_moves_to_next_day_for_checklist()
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->stamp($staff, '2026-05-23', 'finished');

        $this->actingAs($admin)->get('/admin/attendance/list?date=2026-05-23')
            ->assertSee('2026年5月23日の勤怠');
    }

    // テストケースID: 14-3 スタッフ別勤怠一覧の前月表示を確認する。
    public function test_admin_staff_attendance_moves_to_previous_month_for_checklist()
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->stamp($staff, '2026-04-10', 'finished');

        $this->actingAs($admin)->get("/admin/attendance/staff/{$staff->id}?month=2026-04")
            ->assertSee('2026年04月')
            ->assertSee('04/10');
    }

    // テストケースID: 14-4 スタッフ別勤怠一覧の翌月表示を確認する。
    public function test_admin_staff_attendance_moves_to_next_month_for_checklist()
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->stamp($staff, '2026-06-10', 'finished');

        $this->actingAs($admin)->get("/admin/attendance/staff/{$staff->id}?month=2026-06")
            ->assertSee('2026年06月')
            ->assertSee('06/10');
    }

    // テストケースID: 14-5 スタッフ別勤怠一覧から詳細へ遷移できることを確認する。
    public function test_admin_staff_attendance_has_detail_link_for_checklist()
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $stamp = $this->stamp($staff, '2026-05-22', 'finished');

        $this->actingAs($admin)->get("/admin/attendance/staff/{$staff->id}?month=2026-05")
            ->assertSee(route('admin.attendance.show', ['id' => $stamp->id]), false);
    }

    private function user($role = 'user'): User
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => $role]);

        return $user;
    }

    private function stamp(User $user, $date, $status, $clockOut = '18:00:00'): Stamp
    {
        return Stamp::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $date . ' 09:00:00',
            'clock_out_at' => $clockOut ? $date . ' ' . $clockOut : null,
            'status' => $status,
        ]);
    }
}
