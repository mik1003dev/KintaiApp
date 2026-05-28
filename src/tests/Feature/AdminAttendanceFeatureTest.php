<?php

namespace Tests\Feature;

use App\Models\Stamp;
use App\Models\StampBreak;
use App\Models\StampCorrectionRequest;
use App\Models\StampRequestBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // テストケースID: 12-1, 12-2
    // 管理者日次勤怠一覧の当日表示と選択日表示を確認する。
    public function test_admin_daily_attendance_list_shows_user_records_for_selected_day()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 10:00:00'));
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user', 'name' => '日次 スタッフ']);
        /** @var User $otherDateStaff */
        $otherDateStaff = User::factory()->create(['role' => 'user', 'name' => '別日 スタッフ']);
        $stamp = $this->stamp($staff, '2026-05-22');
        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => '2026-05-22 13:00:00',
        ]);
        $this->stamp($otherDateStaff, '2026-05-21');

        $this->actingAs($admin)->get('/admin/attendance/list')
            ->assertOk()
            ->assertSee('2026年5月22日の勤怠')
            ->assertSee('前日')
            ->assertSee('翌日')
            ->assertSee('日次 スタッフ')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('1:00')
            ->assertSee('8:00')
            ->assertDontSee('別日 スタッフ');
    }

    // テストケースID: 13-1, 13-6
    // 管理者勤怠詳細の選択データ表示と修正保存を確認する。
    public function test_admin_attendance_detail_shows_selected_attendance_and_updates_it()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user', 'name' => '詳細 スタッフ']);
        $stamp = $this->stamp($staff);
        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => '2026-05-22 13:00:00',
        ]);

        $this->actingAs($admin)->get("/admin/attendance/{$stamp->id}")
            ->assertOk()
            ->assertSee('詳細 スタッフ')
            ->assertSee('2026年')
            ->assertSee('5月22日')
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false);

        $this->actingAs($admin)->post("/admin/attendance/{$stamp->id}", [
            'clock_in_at' => '08:45',
            'clock_out_at' => '18:15',
            'breaks' => [
                ['break_start_at' => '12:15', 'break_end_at' => '13:15'],
            ],
            'note' => '管理者修正',
        ])->assertRedirect(route('admin.attendance.show', ['id' => $stamp->id]));

        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'note' => '管理者修正',
        ]);
        $this->assertDatabaseHas('stamp_breaks', [
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:15:00',
            'break_end_at' => '2026-05-22 13:15:00',
        ]);
    }

    // テストケースID: 13-2, 13-3, 13-4, 13-5
    // 管理者勤怠修正の時刻と備考のバリデーションを確認する。
    public function test_admin_attendance_update_validates_times_breaks_and_note()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user']);
        $stamp = $this->stamp($staff);

        $this->from("/admin/attendance/{$stamp->id}")
            ->actingAs($admin)
            ->post("/admin/attendance/{$stamp->id}", [
                'clock_in_at' => '19:00',
                'clock_out_at' => '18:00',
                'note' => '不正時刻',
            ])->assertSessionHasErrors([
                'clock_in_at' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);

        $this->from("/admin/attendance/{$stamp->id}")
            ->actingAs($admin)
            ->post("/admin/attendance/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'breaks' => [
                    ['break_start_at' => '18:10', 'break_end_at' => '18:30'],
                ],
                'note' => '不正休憩',
            ])->assertSessionHasErrors([
                'breaks.0.break_start_at' => '休憩時間が不適切な値です',
            ]);

        $this->from("/admin/attendance/{$stamp->id}")
            ->actingAs($admin)
            ->post("/admin/attendance/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
            ])->assertSessionHasErrors([
                'note' => '備考を記入してください',
            ]);
    }

    // 追加ケース: 当日の未来時刻では管理者勤怠修正できないことを確認する。
    public function test_admin_cannot_update_attendance_with_future_time_for_today()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 10:00:00'));
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $staff->id,
            'work_date' => '2026-05-22',
            'clock_in_at' => '2026-05-22 09:00:00',
            'clock_out_at' => '2026-05-22 09:30:00',
            'status' => 'finished',
            'note' => '修正前',
        ]);

        $this->from("/admin/attendance/{$stamp->id}")
            ->actingAs($admin)
            ->post("/admin/attendance/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '10:30',
                'note' => '未来時刻の修正',
            ])->assertRedirect("/admin/attendance/{$stamp->id}")
            ->assertSessionHasErrors([
                'clock_out_at' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);

        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'note' => '修正前',
        ]);
    }

    // テストケースID: 14-1, 14-2
    // スタッフ一覧と選択スタッフの月次勤怠一覧を確認する。
    public function test_admin_staff_list_and_monthly_attendance_show_staff_data()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 10:00:00'));
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'name' => '管理者']);
        /** @var User $staff */
        $staff = User::factory()->create([
            'role' => 'user',
            'name' => '月次 スタッフ',
            'email' => 'monthly@example.com',
        ]);
        $stamp = $this->stamp($staff);
        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => '2026-05-22 13:00:00',
        ]);

        $this->actingAs($admin)->get('/admin/staff/list')
            ->assertOk()
            ->assertSee('月次 スタッフ')
            ->assertSee('monthly@example.com')
            ->assertDontSee('<td>管理者</td>', false);

        $this->actingAs($admin)->get("/admin/attendance/staff/{$staff->id}?month=2026-05")
            ->assertOk()
            ->assertSee('月次 スタッフさんの勤怠')
            ->assertSee('2026年05月')
            ->assertSee('前月')
            ->assertSee('翌月')
            ->assertSee('05/22')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('1:00')
            ->assertSee('8:00')
            ->assertSee('CSV出力');
    }

    // テストケースID: 15-1, 15-2, 15-3, 15-4
    // 管理者申請一覧、申請詳細、承認反映を確認する。
    public function test_admin_request_list_detail_and_approval_apply_requested_values()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user', 'name' => '申請 スタッフ']);
        $pendingStamp = $this->stamp($staff);
        $approvedStamp = $this->stamp($staff, '2026-05-21');
        StampBreak::create([
            'stamp_id' => $pendingStamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => '2026-05-22 13:00:00',
        ]);
        $pending = $this->request($pendingStamp, $staff, 'pending', '承認待ちの申請');
        $this->request($approvedStamp, $staff, 'approved', '承認済みの申請');

        $this->actingAs($admin)->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('承認待ちの申請')
            ->assertDontSee('承認済みの申請');

        $this->actingAs($admin)->get('/stamp_correction_request/list?status=approved')
            ->assertOk()
            ->assertSee('承認済みの申請');

        $this->actingAs($admin)->get("/stamp_correction_request/approve/{$pending->id}")
            ->assertOk()
            ->assertSee('申請 スタッフ')
            ->assertSee('09:30')
            ->assertSee('18:30')
            ->assertSee('承認');

        Carbon::setTestNow(Carbon::parse('2026-05-23 10:00:00'));
        $this->actingAs($admin)->post("/stamp_correction_request/approve/{$pending->id}")
            ->assertRedirect(route('admin.request_show', [
                'attendance_correct_request_id' => $pending->id,
            ]));

        $pending->refresh();
        $pendingStamp->refresh();

        $this->assertSame('approved', $pending->status);
        $this->assertSame($admin->id, $pending->approved_by);
        $this->assertSame('2026-05-22 09:30:00', $pendingStamp->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('stamp_breaks', [
            'stamp_id' => $pendingStamp->id,
            'break_start_at' => '2026-05-22 12:30:00',
            'break_end_at' => '2026-05-22 13:30:00',
        ]);
    }

    // 追加ケース: 未ログインで管理者勤怠一覧へアクセスした場合の遷移先を確認する。
    public function test_guest_is_redirected_to_admin_login_for_admin_attendance_list()
    {
        $this->get('/admin/attendance/list')
            ->assertRedirect(route('admin.login'));
    }

    // 追加ケース: 一般ユーザーが管理者勤怠一覧へアクセスできないことを確認する。
    public function test_general_user_cannot_access_admin_attendance_list()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/admin/attendance/list')
            ->assertForbidden();
    }

    // 追加ケース: 管理者が管理者勤怠一覧へアクセスできることを確認する。
    public function test_admin_can_access_admin_attendance_list()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/attendance/list')
            ->assertOk();
    }

    // 追加ケース: 管理者ログイン成功時の管理画面遷移を確認する。
    public function test_admin_can_login_from_admin_login_form()
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'login_type' => 'admin',
        ]);

        $response->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();
    }

    // 追加ケース: 一般ユーザーが管理者ログインフォームで認証されないことを確認する。
    public function test_general_user_cannot_login_from_admin_login_form()
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'role' => 'user',
        ]);

        $response = $this->from('/admin/login')->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
            'login_type' => 'admin',
        ]);

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    // 追加ケース: 管理者ログアウト後の遷移先を確認する。
    public function test_admin_logout_redirects_to_admin_login()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/logout', [
            'logout_destination' => 'admin',
        ])->assertRedirect('/admin/login');
    }

    // 追加ケース: 一般ユーザーログアウト後の遷移先を確認する。
    public function test_general_user_logout_redirects_to_user_login()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/logout')
            ->assertRedirect('/login');
    }

    // 追加ケース: 管理者修正で同一時刻が入力された場合の境界値を確認する。
    public function test_admin_can_update_attendance_when_times_are_equal()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $staff->id,
            'work_date' => '2026-05-20',
            'clock_in_at' => '2026-05-20 11:17:00',
            'clock_out_at' => '2026-05-20 11:17:00',
            'status' => 'finished',
        ]);

        $this->actingAs($admin)->post("/admin/attendance/{$stamp->id}", [
            'clock_in_at' => '11:17',
            'clock_out_at' => '11:17',
            'breaks' => [
                [
                    'break_start_at' => '11:17',
                    'break_end_at' => '11:17',
                ],
            ],
            'note' => '同時刻の修正',
        ])->assertRedirect(route('admin.attendance.show', ['id' => $stamp->id]));

        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'note' => '同時刻の修正',
        ]);
    }

    // 追加ケース: スタッフ別勤怠CSVに対象月の全日が出力されることを確認する。
    public function test_admin_staff_attendance_csv_outputs_all_days_in_month()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'user']);

        $stamp = Stamp::create([
            'user_id' => $staff->id,
            'work_date' => '2026-04-01',
            'clock_in_at' => '2026-04-01 09:00:00',
            'clock_out_at' => '2026-04-01 18:00:00',
            'status' => 'finished',
        ]);

        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-04-01 12:00:00',
            'break_end_at' => '2026-04-01 13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get("/admin/attendance/staff/{$staff->id}/csv?month=2026-04");

        $response->assertOk();

        $csv = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
        $lines = array_map('trim', explode("\n", trim($csv)));

        $this->assertCount(31, $lines);
        $this->assertSame('日付,出勤,退勤,休憩,合計', $lines[0]);
        $this->assertSame('2026/04/01,09:00,18:00,1:00,8:00', $lines[1]);
        $this->assertSame('2026/04/02,,,,', $lines[2]);
        $this->assertSame('2026/04/30,,,,', $lines[30]);
    }

    private function stamp(User $user, $date = '2026-05-22'): Stamp
    {
        return Stamp::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $date . ' 09:00:00',
            'clock_out_at' => $date . ' 18:00:00',
            'status' => 'finished',
            'note' => '元の備考',
        ]);
    }

    private function request(Stamp $stamp, User $user, $status, $note): StampCorrectionRequest
    {
        $date = $stamp->work_date->format('Y-m-d');
        $request = StampCorrectionRequest::create([
            'stamp_id' => $stamp->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => $date . ' 09:30:00',
            'requested_clock_out_at' => $date . ' 18:30:00',
            'requested_note' => $note,
            'status' => $status,
        ]);

        StampRequestBreak::create([
            'stamp_correction_request_id' => $request->id,
            'break_start_at' => $date . ' 12:30:00',
            'break_end_at' => $date . ' 13:30:00',
        ]);

        return $request;
    }
}
