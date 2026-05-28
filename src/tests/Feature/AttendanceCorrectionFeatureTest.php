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

class AttendanceCorrectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // テストケースID: 11-1, 11-2, 11-3, 11-4
    // 一般ユーザーの勤怠修正時刻と備考のバリデーションを確認する。
    public function test_user_correction_request_validates_times_breaks_and_note()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = $this->stamp($user);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '19:00',
                'clock_out_at' => '18:00',
                'note' => '退勤より遅い出勤',
            ])->assertRedirect("/attendance/detail/{$stamp->id}")
            ->assertSessionHasErrors([
                'clock_in_at' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'break_start_at' => ['19:00'],
                'break_end_at' => ['19:30'],
                'note' => '休憩開始が不正',
            ])->assertSessionHasErrors([
                'break_start_at.0' => '休憩時間が不適切な値です',
            ]);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'break_start_at' => ['17:30'],
                'break_end_at' => ['18:30'],
                'note' => '休憩終了が不正',
            ])->assertSessionHasErrors([
                'break_end_at.0' => '休憩時間もしくは退勤時間が不適切な値です',
            ]);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
            ])->assertSessionHasErrors([
                'note' => '備考を記入してください',
            ]);
    }

    // テストケースID: 11-5, 11-6
    // 修正申請保存と承認待ち詳細画面の編集不可表示を確認する。
    public function test_user_correction_request_is_saved_and_pending_detail_is_locked()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = $this->stamp($user);

        $this->actingAs($user)->post("/attendance/detail/{$stamp->id}", [
            'clock_in_at' => '09:30',
            'clock_out_at' => '18:30',
            'break_start_at' => ['12:30'],
            'break_end_at' => ['13:30'],
            'note' => '電車遅延のため修正',
        ])->assertRedirect(route('attendance.show', ['id' => $stamp->id]));

        $requestData = StampCorrectionRequest::first();

        $this->assertSame('pending', $requestData->status);
        $this->assertDatabaseHas('stamp_correction_requests', [
            'stamp_id' => $stamp->id,
            'user_id' => $user->id,
            'requested_note' => '電車遅延のため修正',
        ]);
        $this->assertDatabaseHas('stamp_request_breaks', [
            'stamp_correction_request_id' => $requestData->id,
        ]);

        $this->actingAs($user)->get("/attendance/detail/{$stamp->id}")
            ->assertOk()
            ->assertSee('承認待ちのため修正はできません。')
            ->assertSee('value="09:30"', false)
            ->assertSee('電車遅延のため修正');
    }

    // 追加ケース: 退勤前の勤怠詳細では修正申請できないことを確認する。
    public function test_user_cannot_request_correction_before_clock_out()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-22',
            'clock_in_at' => '2026-05-22 09:00:00',
            'clock_out_at' => null,
            'status' => 'working',
            'note' => null,
        ]);

        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => '2026-05-22 12:00:00',
            'break_end_at' => null,
        ]);

        $this->actingAs($user)->get("/attendance/detail/{$stamp->id}")
            ->assertOk()
            ->assertSee('退勤後に修正申請できます。')
            ->assertSee('disabled', false)
            ->assertDontSee('class="attendance-detail__button"', false);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '',
                'break_start_at' => ['12:00'],
                'break_end_at' => [''],
                'note' => '',
            ])->assertRedirect(route('attendance.show', ['id' => $stamp->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stamp_correction_requests', 0);
        $this->assertDatabaseCount('stamp_request_breaks', 0);
    }

    // 追加ケース: 当日の未来時刻では勤怠修正申請できないことを確認する。
    public function test_user_cannot_request_correction_with_future_time_for_today()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 10:00:00'));
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => '2026-05-22',
            'clock_in_at' => '2026-05-22 09:00:00',
            'clock_out_at' => '2026-05-22 09:30:00',
            'status' => 'finished',
            'note' => '修正前',
        ]);

        $this->from("/attendance/detail/{$stamp->id}")
            ->actingAs($user)
            ->post("/attendance/detail/{$stamp->id}", [
                'clock_in_at' => '09:00',
                'clock_out_at' => '10:30',
                'note' => '未来時刻の申請',
            ])->assertRedirect("/attendance/detail/{$stamp->id}")
            ->assertSessionHasErrors([
                'clock_out_at' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);

        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    // テストケースID: 11-7, 11-8
    // 一般ユーザー申請一覧の承認待ちと承認済み表示を確認する。
    public function test_user_request_list_shows_only_own_pending_and_approved_requests()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user', 'name' => '申請 一郎']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'user', 'name' => '申請 他人']);
        $pendingStamp = $this->stamp($user);
        $approvedStamp = $this->stamp($user, '2026-05-21');
        $otherStamp = $this->stamp($other, '2026-05-20');

        $this->request($pendingStamp, $user, 'pending', '承認待ち理由');
        $this->request($approvedStamp, $user, 'approved', '承認済み理由');
        $this->request($otherStamp, $other, 'pending', '他人の理由');

        $this->actingAs($user)->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('承認待ち理由')
            ->assertDontSee('承認済み理由')
            ->assertDontSee('他人の理由');

        $this->actingAs($user)->get('/stamp_correction_request/list?status=approved')
            ->assertOk()
            ->assertSee('承認済み理由')
            ->assertDontSee('承認待ち理由');
    }

    private function stamp(User $user, $date = '2026-05-22'): Stamp
    {
        $stamp = Stamp::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $date . ' 09:00:00',
            'clock_out_at' => $date . ' 18:00:00',
            'status' => 'finished',
            'note' => '修正前',
        ]);

        StampBreak::create([
            'stamp_id' => $stamp->id,
            'break_start_at' => $date . ' 12:00:00',
            'break_end_at' => $date . ' 13:00:00',
        ]);

        return $stamp;
    }

    private function request(Stamp $stamp, User $user, $status, $note): StampCorrectionRequest
    {
        $request = StampCorrectionRequest::create([
            'stamp_id' => $stamp->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => $stamp->work_date->format('Y-m-d') . ' 09:15:00',
            'requested_clock_out_at' => $stamp->work_date->format('Y-m-d') . ' 18:15:00',
            'requested_note' => $note,
            'status' => $status,
        ]);

        StampRequestBreak::create([
            'stamp_correction_request_id' => $request->id,
            'break_start_at' => $stamp->work_date->format('Y-m-d') . ' 12:15:00',
            'break_end_at' => $stamp->work_date->format('Y-m-d') . ' 13:15:00',
        ]);

        return $request;
    }
}
