<?php

namespace Tests\Feature;

use App\Models\Stamp;
use App\Models\StampBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // テストケースID: 4-1, 5-1
    // 現在日時と勤務外ステータスが打刻画面へ表示されることを確認する。
    public function test_stamp_screen_displays_the_current_date_time_and_off_duty_status()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 08:35:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/attendance')
            ->assertOk()
            ->assertSee('勤務外')
            ->assertSee('2026年5月22日')
            ->assertSee('08:35')
            ->assertSee('出勤');
    }

    // テストケースID: 5-2, 5-3, 5-4
    // 勤務中、休憩中、退勤済の表示をまとめて回帰確認する。
    public function test_stamp_screen_displays_working_break_and_finished_statuses()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 13:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = $this->stamp($user, 'working');

        $this->actingAs($user)->get('/attendance')
            ->assertSee('出勤中')
            ->assertSee('休憩入')
            ->assertSee('退勤');

        $stamp->update(['status' => 'on_break']);

        $this->actingAs($user)->get('/attendance')
            ->assertSee('休憩中')
            ->assertSee('休憩戻');

        $stamp->update([
            'status' => 'finished',
            'clock_out_at' => '2026-05-22 18:00:00',
        ]);

        $this->actingAs($user)->get('/attendance')
            ->assertSee('退勤済')
            ->assertSee('お疲れ様でした。');
    }

    // テストケースID: 6-1, 7-1, 8-1
    // 出勤、休憩、退勤の打刻処理がDBへ保存されることを確認する。
    public function test_clock_in_break_and_clock_out_store_attendance_and_break_times()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 09:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/attendance/clock-in')
            ->assertRedirect(route('attendance.index'));
        $this->actingAs($user)->post('/attendance/clock-in');

        $stamp = Stamp::first();

        $this->assertDatabaseCount('stamps', 1);
        $this->assertSame('working', $stamp->status);
        $this->assertSame('2026-05-22 09:00:00', $stamp->clock_in_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-05-22 12:00:00'));
        $this->actingAs($user)->post('/attendance/break-in');

        Carbon::setTestNow(Carbon::parse('2026-05-22 13:00:00'));
        $this->actingAs($user)->post('/attendance/break-out');

        $break = StampBreak::first();
        $this->assertSame('2026-05-22 12:00:00', $break->break_start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-22 13:00:00', $break->break_end_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-05-22 18:00:00'));
        $this->actingAs($user)->post('/attendance/clock-out');

        $stamp->refresh();
        $this->assertSame('finished', $stamp->status);
        $this->assertSame('2026-05-22 18:00:00', $stamp->clock_out_at->format('Y-m-d H:i:s'));
    }

    // テストケースID: 6-2 出勤は一日一回のみできることを確認する。
    public function test_off_duty_user_can_clock_in_once_per_day()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 09:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/attendance/clock-in')
            ->assertRedirect(route('attendance.index'));
        $this->actingAs($user)->post('/attendance/clock-in')
            ->assertRedirect(route('attendance.index'));

        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseHas('stamps', [
            'user_id' => $user->id,
            'work_date' => '2026-05-13',
            'status' => 'working',
        ]);
    }

    // テストケースID: 7-3, 7-4
    // 休憩入と休憩戻で状態と休憩時刻が反映されることを確認する。
    public function test_working_user_can_start_and_end_break()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 12:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-13',
            'clock_in_at' => '2026-05-13 09:00:00',
            'status' => 'working',
        ]);

        $this->actingAs($user)->post('/attendance/break-in')
            ->assertRedirect(route('attendance.index'));

        $stamp->refresh();
        $this->assertSame('on_break', $stamp->status);
        $this->assertDatabaseHas('stamp_breaks', [
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-13 12:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-13 13:00:00'));

        $this->actingAs($user)->post('/attendance/break-out')
            ->assertRedirect(route('attendance.index'));

        $stamp->refresh();
        $break = StampBreak::where('stamp_id', $stamp->id)->first();

        $this->assertSame('working', $stamp->status);
        $this->assertSame('2026-05-13 13:00:00', $break->break_end_at->format('Y-m-d H:i:s'));
    }

    // テストケースID: 8-2 退勤処理で退勤済ステータスへ更新されることを確認する。
    public function test_working_user_can_clock_out()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 18:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-13',
            'clock_in_at' => '2026-05-13 09:00:00',
            'status' => 'working',
        ]);

        $this->actingAs($user)->post('/attendance/clock-out')
            ->assertRedirect(route('attendance.index'));

        $stamp->refresh();
        $this->assertSame('finished', $stamp->status);
        $this->assertSame('2026-05-13 18:00:00', $stamp->clock_out_at->format('Y-m-d H:i:s'));
    }

    // テストケースID: 9-1, 9-2
    // 一般ユーザーの勤怠一覧に自分の当月勤怠が表示されることを確認する。
    public function test_user_attendance_list_shows_own_month_records_and_month_navigation()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 09:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'user']);
        $stamp = $this->stamp($user, 'finished');
        $stamp->update(['clock_out_at' => '2026-05-22 18:00:00']);
        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => '2026-05-22 13:00:00',
        ]);
        $this->stamp($other, 'finished')->update([
            'work_date' => '2026-05-21',
            'clock_in_at' => '2026-05-21 08:00:00',
            'clock_out_at' => '2026-05-21 17:00:00',
        ]);

        $this->actingAs($user)->get('/attendance/list')
            ->assertOk()
            ->assertSee('2026/05')
            ->assertSee('前月')
            ->assertSee('翌月')
            ->assertSee('05/22(金)')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('01:00')
            ->assertSee('08:00')
            ->assertDontSee('17:00');
    }

    // テストケースID: 10-1, 10-2, 10-3, 10-4
    // 勤怠詳細に氏名、日付、出退勤、休憩の選択データが表示されることを確認する。
    public function test_user_attendance_detail_shows_selected_attendance_values()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user', 'name' => '勤怠 一郎']);
        $stamp = $this->stamp($user, 'finished');
        $stamp->update([
            'clock_out_at' => '2026-05-22 18:00:00',
            'note' => '通常勤務',
        ]);
        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:10:00',
            'break_end_at' => '2026-05-22 13:10:00',
        ]);

        $this->actingAs($user)->get("/attendance/detail/{$stamp->id}")
            ->assertOk()
            ->assertSee('勤怠 一郎')
            ->assertSee('2026年')
            ->assertSee('5月22日')
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false)
            ->assertSee('value="12:10"', false)
            ->assertSee('value="13:10"', false)
            ->assertSee('通常勤務');
    }

    // 追加ケース: 承認待ちの勤怠修正申請を二重登録できないことを確認する。
    public function test_user_cannot_create_duplicate_pending_correction_request()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-13',
            'clock_in_at' => '2026-05-13 09:00:00',
            'clock_out_at' => '2026-05-13 18:00:00',
            'status' => 'finished',
        ]);

        StampCorrectionRequest::create([
            'stamp_id' => $stamp->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-05-13 09:30:00',
            'requested_clock_out_at' => '2026-05-13 18:00:00',
            'requested_note' => '既存の承認待ち申請',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/attendance/detail/{$stamp->id}", [
            'clock_in_at' => '10:00',
            'clock_out_at' => '19:00',
            'note' => 'POST直叩きの追加申請',
        ])->assertRedirect(route('attendance.show', ['id' => $stamp->id]));

        $this->assertDatabaseCount('stamp_correction_requests', 1);
        $this->assertDatabaseMissing('stamp_correction_requests', [
            'requested_note' => 'POST直叩きの追加申請',
        ]);
    }

    private function stamp(User $user, $status): Stamp
    {
        return Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-22',
            'clock_in_at' => '2026-05-22 09:00:00',
            'clock_out_at' => null,
            'status' => $status,
        ]);
    }
}
