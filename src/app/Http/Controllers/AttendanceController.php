<?php

namespace App\Http\Controllers;

use App\Models\Stamp;                // 勤怠テーブルを操作するために Stamp モデルを読み込む
use App\Models\StampBreak;           // 休憩テーブルを操作するために StampBreak モデルを読み込む
use Carbon\Carbon;                   // 日付・時刻を扱うために Carbon を読み込む
use Illuminate\Http\Request;         // Requestクラス
use App\Models\User;                 // ユーザーテーブルを操作するために User モデルを読み込む
use App\Models\StampCorrectionRequest;
use App\Models\StampRequestBreak;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use Illuminate\Support\Facades\Auth;


class AttendanceController extends Controller
{
    /**
     * 勤怠打刻画面を表示する
     */
    public function index()
    {
        $user = Auth::user();

        // 今日の日付を取得（例: 2026-04-21）
        $today = Carbon::today()->toDateString();

        // 今日の勤怠データを取得
        // 1ユーザー1日1件想定なので first() で1件取得
        $stamp = Stamp::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // デフォルト状態は「勤務外」
        $status = 'off_duty';

        // 今日の勤怠データがある場合は、そのstatusを使う
        if ($stamp) {
            $status = $stamp->status;
        }

        // Bladeへデータを渡して画面表示
        return view('attendance.index', compact('stamp', 'status'));
    }

    /**
     * 出勤処理
     */
    public function clockIn()
    {
        $user = Auth::user();

        // 今日の日付を取得
        $today = Carbon::today()->toDateString();

        // 現在日時を取得
        $now = Carbon::now();

        // すでに今日の勤怠データがあるか確認
        $existingStamp = Stamp::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // まだ勤怠データがない場合だけ出勤レコードを作成
        if (!$existingStamp) {
            Stamp::create([
                'user_id' => $user->id,
                'work_date' => $today,
                'clock_in_at' => $now,
                'clock_out_at' => null,
                'status' => 'working',
                'note' => null,
            ]);
        }

        // 打刻後は勤怠打刻画面に戻る
        return redirect()->route('attendance.index');
    }

    /**
     * 休憩入処理
     */
    public function breakIn()
    {
        $user = Auth::user();

        // 今日の日付を取得
        $today = Carbon::today()->toDateString();

        // 現在日時を取得
        $now = Carbon::now();

        // 今日の勤怠データを取得
        $stamp = Stamp::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // 勤怠データがあり、ステータスが出勤中のときだけ休憩入を実行
        if ($stamp && $stamp->status === 'working') {
            StampBreak::create([
                'stamp_id' => $stamp->id,
                'break_start_at' => $now,
                'break_end_at' => null,
            ]);

            $stamp->update([
                'status' => 'on_break',
            ]);
        }

        // 処理後は勤怠打刻画面に戻る
        return redirect()->route('attendance.index');
    }

    /**
     * 休憩戻処理
     */
    public function breakOut()
    {
        $user = Auth::user();

        // 今日の日付を取得
        $today = Carbon::today()->toDateString();

        // 現在日時を取得
        $now = Carbon::now();

        // 今日の勤怠データを取得
        $stamp = Stamp::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // 勤怠データがあり、ステータスが休憩中のときだけ休憩戻を実行
        if ($stamp && $stamp->status === 'on_break') {

            // まだ休憩終了時刻が入っていない最新の休憩レコードを取得
            $stampBreak = StampBreak::where('stamp_id', $stamp->id)
                ->whereNull('break_end_at')
                ->latest('id')
                ->first();

            // 対象の休憩レコードが見つかったら休憩終了時刻を更新
            if ($stampBreak) {
                $stampBreak->update([
                    'break_end_at' => $now,
                ]);
            }

            // 勤怠ステータスを出勤中へ戻す
            $stamp->update([
                'status' => 'working',
            ]);
        }

        // 処理後は勤怠打刻画面に戻る
        return redirect()->route('attendance.index');
    }

    /**
     * 退勤処理
     */
    public function clockOut()
    {
        $user = Auth::user();

        // 今日の日付を取得
        $today = Carbon::today()->toDateString();

        // 現在日時を取得
        $now = Carbon::now();

        // 今日の勤怠データを取得
        $stamp = Stamp::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // 勤怠データがあり、ステータスが出勤中のときだけ退勤を実行
        if ($stamp && $stamp->status === 'working') {
            $stamp->update([
                'clock_out_at' => $now,
                'status' => 'finished',
            ]);
        }

        // 処理後は勤怠打刻画面に戻る
        return redirect()->route('attendance.index');
    }

    /**
     * 勤怠一覧画面を表示する
     */
    public function list(Request $request)
    {
        $user = Auth::user();

        // クエリパラメータから対象月を取得
        $month = $request->input('month');

        // month指定がない場合は今月を使う
        if ($month) {
            $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } else {
            $currentMonth = Carbon::now()->startOfMonth();
        }

        // 対象月の開始日と終了日を取得
        $startOfMonth = $currentMonth->copy()->startOfMonth()->toDateString();
        $endOfMonth = $currentMonth->copy()->endOfMonth()->toDateString();

        // 前月・翌月の文字列を作成
        $previousMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        // 対象月の勤怠一覧を取得
        $stampRecords = Stamp::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->orderBy('work_date', 'asc')
            ->get()
            ->keyBy(function ($stamp) {
                return Carbon::parse($stamp->work_date)->format('Y-m-d');
            });

        // Bladeで表示しやすい形に整形する
        $attendanceList = collect();
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        for ($date = $currentMonth->copy()->startOfMonth(); $date->lte($currentMonth->copy()->endOfMonth()); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $stamp = $stampRecords->get($dateKey);

            if ($stamp) {
                // 出勤時刻
                $clockIn = $stamp->clock_in_at
                    ? Carbon::parse($stamp->clock_in_at)->format('H:i')
                    : '';

                // 退勤時刻
                $clockOut = $stamp->clock_out_at
                    ? Carbon::parse($stamp->clock_out_at)->format('H:i')
                    : '';

                // 休憩合計分数
                $totalBreakMinutes = 0;

                foreach ($stamp->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at) {
                        $breakStart = Carbon::parse($break->break_start_at)->startOfMinute();
                        $breakEnd = Carbon::parse($break->break_end_at)->startOfMinute();
                        $totalBreakMinutes += $breakStart->diffInMinutes($breakEnd);
                    }
                }

                // 休憩時間表示
                $breakTime = '';
                if ($totalBreakMinutes > 0) {
                    $breakHours = floor($totalBreakMinutes / 60);
                    $breakMinutes = $totalBreakMinutes % 60;
                    $breakTime = sprintf('%02d:%02d', $breakHours, $breakMinutes);
                }

                // 勤務合計時間表示
                $workTime = '';
                if ($stamp->clock_in_at && $stamp->clock_out_at) {
                    $clockInAt = Carbon::parse($stamp->clock_in_at)->startOfMinute();
                    $clockOutAt = Carbon::parse($stamp->clock_out_at)->startOfMinute();
                    $workMinutes = $clockInAt->diffInMinutes($clockOutAt) - $totalBreakMinutes;
                    $workMinutes = max(0, $workMinutes);

                    $workHours = floor($workMinutes / 60);
                    $remainingMinutes = $workMinutes % 60;
                    $workTime = sprintf('%02d:%02d', $workHours, $remainingMinutes);
                }

                $attendanceList->push([
                    'id' => $stamp->id,
                    'work_date' => $date->format('m/d') . '(' . $weekdays[$date->dayOfWeek] . ')',
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'break_time' => $breakTime,
                    'work_time' => $workTime,
                    'detail_url' => route('attendance.show', ['id' => $stamp->id]),
                ]);
            } else {
                $attendanceList->push([
                    'id' => null,
                    'work_date' => $date->format('m/d') . '(' . $weekdays[$date->dayOfWeek] . ')',
                    'clock_in_at' => '',
                    'clock_out_at' => '',
                    'break_time' => '',
                    'work_time' => '',
                    'detail_url' => route('attendance.show_by_date', ['date' => $date->format('Y-m-d')]),
                ]);
            }
        }

        // Bladeへデータを渡す
        return view('attendance.list', compact(
            'attendanceList',
            'currentMonth',
            'previousMonth',
            'nextMonth'
        ));
    }

    /**
     * 勤怠詳細画面を表示する
     */
    public function show($id)
    {
        $user = Auth::user();

        // 対象の勤怠データを取得
        // 自分の勤怠データのみ表示できるように user_id も条件に含める
        $stamp = Stamp::with('breaks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Bladeで表示しやすい形に整形
        $attendanceDetail = [
            'id' => $stamp->id,
            'name' => $user->name,
            'date' => Carbon::parse($stamp->work_date)->format('Y年n月j日'),
            'date_year' => Carbon::parse($stamp->work_date)->format('Y年'),
            'date_month_day' => Carbon::parse($stamp->work_date)->format('n月j日'),
            'clock_in_at' => $stamp->clock_in_at
                ? Carbon::parse($stamp->clock_in_at)->format('H:i')
                : '',
            'clock_out_at' => $stamp->clock_out_at
                ? Carbon::parse($stamp->clock_out_at)->format('H:i')
                : '',
            'note' => $stamp->note ?? '',
            'breaks' => $stamp->breaks->map(function ($break) {
                return [
                    'break_start_at' => $break->break_start_at
                        ? Carbon::parse($break->break_start_at)->format('H:i')
                        : '',
                    'break_end_at' => $break->break_end_at
                        ? Carbon::parse($break->break_end_at)->format('H:i')
                        : '',
                ];
            }),
        ];

        $pendingRequest = StampCorrectionRequest::with('requestBreaks')
            ->where('stamp_id', $stamp->id)
            ->where('status', 'pending')
            ->first();

        $isPending = $pendingRequest ? true : false;
        $canRequestCorrection = $stamp->status === 'finished' && $stamp->clock_out_at;

        if ($pendingRequest) {
            $attendanceDetail['clock_in_at'] = $pendingRequest->requested_clock_in_at
                ? Carbon::parse($pendingRequest->requested_clock_in_at)->format('H:i')
                : '';
            $attendanceDetail['clock_out_at'] = $pendingRequest->requested_clock_out_at
                ? Carbon::parse($pendingRequest->requested_clock_out_at)->format('H:i')
                : '';
            $attendanceDetail['note'] = $pendingRequest->requested_note ?? '';
            $attendanceDetail['breaks'] = $pendingRequest->requestBreaks->map(function ($break) {
                return [
                    'break_start_at' => $break->break_start_at
                        ? Carbon::parse($break->break_start_at)->format('H:i')
                        : '',
                    'break_end_at' => $break->break_end_at
                        ? Carbon::parse($break->break_end_at)->format('H:i')
                        : '',
                ];
            });
        }

        return view('attendance.show', compact(
            'attendanceDetail',
            'isPending',
            'canRequestCorrection'
        ));
    }

    /**
     * 勤怠詳細画面を日付指定で表示する
     */
    public function showByDate($date)
    {
        $user = Auth::user();
        $workDate = Carbon::parse($date)->toDateString();

        $stamp = Stamp::firstOrCreate(
            [
                'user_id' => $user->id,
                'work_date' => $workDate,
            ],
            [
                'clock_in_at' => null,
                'clock_out_at' => null,
                'status' => 'off_duty',
                'note' => null,
            ]
        );

        return redirect()->route('attendance.show', ['id' => $stamp->id]);
    }

    /**
     * 修正申請処理
     */
    public function updateRequest(AttendanceCorrectionRequest $request, $id)
    {
        $user = Auth::user();

        // 勤怠データ取得
        $stamp = Stamp::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($stamp->status !== 'finished' || !$stamp->clock_out_at) {
            return redirect()->route('attendance.show', ['id' => $id]);
        }

        $hasPendingRequest = StampCorrectionRequest::where('stamp_id', $stamp->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            return redirect()->route('attendance.show', ['id' => $id]);
        }

        // 勤務日を YYYY-MM-DD 形式に整える
        $workDate = Carbon::parse($stamp->work_date)->toDateString();

        // 親テーブル保存
        $requestData = StampCorrectionRequest::create([
            'stamp_id' => $stamp->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => $workDate . ' ' . $request->clock_in_at . ':00',
            'requested_clock_out_at' => $workDate . ' ' . $request->clock_out_at . ':00',
            'requested_note' => $request->note ?? '',
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // 休憩情報保存
        if ($request->break_start_at) {
            foreach ($request->break_start_at as $index => $startTime) {
                $endTime = $request->break_end_at[$index] ?? null;

                if (!$startTime && !$endTime) {
                    continue;
                }

                StampRequestBreak::create([
                    'stamp_correction_request_id' => $requestData->id,
                    'break_start_at' => $workDate . ' ' . $startTime . ':00',
                    'break_end_at' => $workDate . ' ' . $endTime . ':00',
                ]);
            }
        }

        return redirect()->route('attendance.show', ['id' => $id]);
    }

    /**
     * 修正申請一覧画面を表示する
     */
    public function requestList(Request $request)
    {
        $user = Auth::user();
                
        // ステータス取得（デフォルトは承認待ち）
        $status = $request->input('status', 'pending');

        // 管理者の場合
        if ($user->role === 'admin') {
            $requests = StampCorrectionRequest::with(['stamp', 'user'])
                ->where('status', $status)
                ->orderBy('created_at', 'desc')
                ->get();

            $requestList = $requests->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user->name,
                    'date' => Carbon::parse($item->stamp->work_date)->format('Y/m/d'),
                    'clock_in_at' => $item->requested_clock_in_at
                        ? Carbon::parse($item->requested_clock_in_at)->format('H:i')
                        : '',
                    'clock_out_at' => $item->requested_clock_out_at
                        ? Carbon::parse($item->requested_clock_out_at)->format('H:i')
                        : '',
                    'status' => $item->status,
                    'status_label' => $item->status === 'approved' ? '承認済み' : '承認待ち',
                    'reason' => $item->requested_note ?? '',
                    'request_date' => Carbon::parse($item->created_at)->format('Y/m/d'),
                ];
            });

            return view('admin.request_list', compact('requestList', 'status'));
        }

        // 一般ユーザーの場合
        $requests = StampCorrectionRequest::with('stamp.user')
            ->where('user_id', $user->id)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();

        $requestList = $requests->map(function ($item) {
            return [
                'id' => $item->stamp->id,
                'user_name' => $item->stamp->user->name ?? Auth::user()->name,
                'date' => Carbon::parse($item->stamp->work_date)->format('Y/m/d'),
                'clock_in_at' => $item->requested_clock_in_at
                    ? Carbon::parse($item->requested_clock_in_at)->format('H:i')
                    : '',
                'clock_out_at' => $item->requested_clock_out_at
                    ? Carbon::parse($item->requested_clock_out_at)->format('H:i')
                    : '',
                'status' => $item->status,
                'status_label' => $item->status === 'approved' ? '承認済み' : '承認待ち',
                'reason' => $item->requested_note ?? '',
                'request_date' => Carbon::parse($item->created_at)->format('Y/m/d'),
            ];
        });

        return view('attendance.request_list', compact('requestList', 'status'));
    }

    /**
     * 管理者側 修正申請詳細画面
     */
    public function adminRequestShow($attendance_correct_request_id)
    {
        // 申請データを取得
        $requestData = StampCorrectionRequest::with([
            'user',
            'stamp',
            'requestBreaks'
        ])->where('id', $attendance_correct_request_id)->firstOrFail();

        // Bladeで表示しやすい形に整形
        $detail = [
            'id' => $requestData->id,
            'user_name' => $requestData->user->name,
            'date' => Carbon::parse($requestData->stamp->work_date)->format('Y年m月d日'),
            'date_year' => Carbon::parse($requestData->stamp->work_date)->format('Y年'),
            'date_month_day' => Carbon::parse($requestData->stamp->work_date)->format('n月j日'),
            'clock_in_at' => $requestData->requested_clock_in_at
                ? Carbon::parse($requestData->requested_clock_in_at)->format('H:i')
                : '',
            'clock_out_at' => $requestData->requested_clock_out_at
                ? Carbon::parse($requestData->requested_clock_out_at)->format('H:i')
                : '',
            'note' => $requestData->requested_note,
            'status' => $requestData->status,
            'breaks' => $requestData->requestBreaks->map(function ($break) {
                return [
                    'start' => $break->break_start_at
                        ? Carbon::parse($break->break_start_at)->format('H:i')
                        : '',
                    'end' => $break->break_end_at
                        ? Carbon::parse($break->break_end_at)->format('H:i')
                        : '',
                ];
            }),
        ];

        return view('admin.request_show', compact('detail'));
    }

    /**
     * 管理者が修正申請を承認する
     */
    public function approveRequest($attendance_correct_request_id)
    {
        $admin = Auth::user();

        // 修正申請データを取得
        $requestData = StampCorrectionRequest::with([
            'stamp',
            'requestBreaks',
        ])->where('id', $attendance_correct_request_id)->firstOrFail();

        if ($requestData->status !== 'pending') {
            return redirect()->route('attendance.request_list');
        }

        // 対象の勤怠データを取得
        $stamp = $requestData->stamp;

        // 勤怠データを修正後の内容で更新
        $stamp->update([
            'clock_in_at' => $requestData->requested_clock_in_at,
            'clock_out_at' => $requestData->requested_clock_out_at,
            'note' => $requestData->requested_note,
        ]);

        // 既存の休憩データを削除
        StampBreak::where('stamp_id', $stamp->id)->delete();

        // 修正申請側の休憩データをもとに再登録
        foreach ($requestData->requestBreaks as $requestBreak) {
            StampBreak::create([
                'stamp_id' => $stamp->id,
                'break_start_at' => $requestBreak->break_start_at,
                'break_end_at' => $requestBreak->break_end_at,
            ]);
        }

        // 修正申請を承認済みに更新
        $requestData->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('admin.request_show', [
            'attendance_correct_request_id' => $attendance_correct_request_id,
        ]);
    }

    /**
     * 管理者 日次勤怠一覧画面
     */
    public function adminAttendanceList(Request $request)
    {
        // 日付取得（指定なければ今日）
        $date = $request->input('date', Carbon::today()->toDateString());
        $workDate = Carbon::parse($date)->toDateString();

        $stamps = Stamp::with(['breaks', 'user'])
            ->whereDate('work_date', $workDate)
            ->whereNotNull('clock_in_at')
            ->orderBy('user_id')
            ->get()
            ->filter(function ($stamp) {
                return optional($stamp->user)->role === 'user';
            })
            ->values();

        $attendanceList = $stamps->map(function ($stamp) {
            $breakMinutes = 0;

            foreach ($stamp->breaks as $break) {
                if ($break->break_start_at && $break->break_end_at) {
                    $breakStart = \Carbon\Carbon::parse($break->break_start_at)->startOfMinute();
                    $breakEnd = \Carbon\Carbon::parse($break->break_end_at)->startOfMinute();

                    $breakMinutes += $breakStart->diffInMinutes($breakEnd);
                }
            }

            $workMinutes = 0;

            if ($stamp->clock_in_at && $stamp->clock_out_at) {
                $clockIn = \Carbon\Carbon::parse($stamp->clock_in_at)->startOfMinute();
                $clockOut = \Carbon\Carbon::parse($stamp->clock_out_at)->startOfMinute();

                $workMinutes = $clockIn->diffInMinutes($clockOut) - $breakMinutes;
                $workMinutes = max(0, $workMinutes);
            }

            return [
                'name' => $stamp->user->name,
                'clock_in_at' => $stamp->clock_in_at
                    ? \Carbon\Carbon::parse($stamp->clock_in_at)->format('H:i')
                    : '',
                'clock_out_at' => $stamp->clock_out_at
                    ? \Carbon\Carbon::parse($stamp->clock_out_at)->format('H:i')
                    : '',
                'break_time' => ($stamp->clock_in_at || $stamp->clock_out_at || $breakMinutes > 0)
                    ? sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60)
                    : '',
                'work_time' => ($stamp->clock_in_at && $stamp->clock_out_at)
                    ? sprintf('%d:%02d', intdiv($workMinutes, 60), $workMinutes % 60)
                    : '',
                'detail_url' => route('admin.attendance.show', ['id' => $stamp->id]),
            ];
        });

        // 前日翌日
        $prevDate = Carbon::parse($date)->subDay()->toDateString();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();

        return view('admin.attendance_list', compact(
            'date',
            'attendanceList',
            'prevDate',
            'nextDate'
        ));
    }

    /**
     * 管理者 勤怠詳細画面
     */
    public function adminAttendanceShow($id)
    {
        // 対象の勤怠データを取得
        $stamp = Stamp::with(['user', 'breaks'])
            ->where('id', $id)
            ->firstOrFail();

        // Bladeで表示しやすい形に整形
        $attendanceDetail = [
            'id' => $stamp->id,
            'name' => $stamp->user->name,
            'date' => Carbon::parse($stamp->work_date)->format('Y年n月j日'),
            'date_year' => Carbon::parse($stamp->work_date)->format('Y年'),
            'date_month_day' => Carbon::parse($stamp->work_date)->format('n月j日'),
            'clock_in_at' => $stamp->clock_in_at
                ? Carbon::parse($stamp->clock_in_at)->format('H:i')
                : '',
            'clock_out_at' => $stamp->clock_out_at
                ? Carbon::parse($stamp->clock_out_at)->format('H:i')
                : '',
            'note' => $stamp->note ?? '',
            'breaks' => $stamp->breaks->map(function ($break) {
                return [
                    'break_start_at' => $break->break_start_at
                        ? Carbon::parse($break->break_start_at)->format('H:i')
                        : '',
                    'break_end_at' => $break->break_end_at
                        ? Carbon::parse($break->break_end_at)->format('H:i')
                        : '',
                ];
            }),
        ];

        return view('admin.attendance_show', compact('attendanceDetail'));
    }

    /**
     * 管理者 勤怠詳細画面を日付指定で表示する（スタッフ別一覧の未出勤日用）
     */
    public function adminAttendanceShowByDate(User $user, $date)
    {
        $workDate = Carbon::parse($date)->toDateString();

        $stamp = Stamp::firstOrCreate(
            [
                'user_id' => $user->id,
                'work_date' => $workDate,
            ],
            [
                'clock_in_at' => null,
                'clock_out_at' => null,
                'status' => 'off_duty',
                'note' => null,
            ]
        );

        return redirect()->route('admin.attendance.show', ['id' => $stamp->id]);
    }

    /**
     * 管理者 勤怠詳細 修正処理
     */
    public function adminAttendanceUpdate(AdminAttendanceUpdateRequest $request, $id)
    {
        $stamp = Stamp::with('breaks')->findOrFail($id);
        $workDate = Carbon::parse($stamp->work_date)->format('Y-m-d');
        $breakRows = [];

        foreach ($request->input('breaks', []) as $index => $breakData) {
            $breakStartAt = $breakData['break_start_at'] ?? null;
            $breakEndAt = $breakData['break_end_at'] ?? null;

            if (!$breakStartAt && !$breakEndAt) {
                continue;
            }

            $breakRows[] = [
                'start' => $breakStartAt,
                'end' => $breakEndAt,
            ];
        }

        // 勤怠本体を更新
        $stamp->clock_in_at = $workDate . ' ' . $request->clock_in_at . ':00';
        $stamp->clock_out_at = $workDate . ' ' . $request->clock_out_at . ':00';
        $stamp->note = $request->note;
        $stamp->status = 'finished';

        $stamp->save();

        // 休憩を更新。追加用の空白行は保存しない。
        StampBreak::where('stamp_id', $stamp->id)->delete();

        foreach ($breakRows as $breakRow) {
            StampBreak::create([
                'stamp_id' => $stamp->id,
                'break_start_at' => $workDate . ' ' . $breakRow['start'] . ':00',
                'break_end_at' => $workDate . ' ' . $breakRow['end'] . ':00',
            ]);
        }

        return redirect()->route('admin.attendance.show', ['id' => $id]);
    }

    public function adminStaffList()
    {
        $staffs = User::where('role', 'user')->get();
        return view('admin.staff_list', compact('staffs'));
    }

    public function adminStaffAttendanceList(Request $request, $id)
    {
        $staff = User::findOrFail($id);

        $month = $request->input('month', now()->format('Y-m'));

        $startOfMonth = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth = \Carbon\Carbon::parse($month . '-01')->endOfMonth();

        $stampRecords = Stamp::with('breaks')
            ->where('user_id', $id)
            ->whereBetween('work_date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(function ($stamp) {
                return \Carbon\Carbon::parse($stamp->work_date)->format('Y-m-d');
            });

        $stamps = collect();

        for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');

            if ($stampRecords->has($dateKey)) {
                $stamp = $stampRecords->get($dateKey);

                $breakMinutes = 0;

                foreach ($stamp->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at) {
                        $breakStart = \Carbon\Carbon::parse($break->break_start_at)->startOfMinute();
                        $breakEnd = \Carbon\Carbon::parse($break->break_end_at)->startOfMinute();

                        $breakMinutes += $breakStart->diffInMinutes($breakEnd);
                    }
                }

                $workMinutes = 0;

                if ($stamp->clock_in_at && $stamp->clock_out_at) {
                    $clockIn = \Carbon\Carbon::parse($stamp->clock_in_at)->startOfMinute();
                    $clockOut = \Carbon\Carbon::parse($stamp->clock_out_at)->startOfMinute();
                    $workMinutes = $clockIn->diffInMinutes($clockOut) - $breakMinutes;
                    $workMinutes = max(0, $workMinutes);
                }

                $stamp->display_date = $date->copy();
                $stamp->break_time = sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60);
                $stamp->work_time = sprintf('%d:%02d', intdiv($workMinutes, 60), $workMinutes % 60);
            } else {
                $stamp = (object) [
                    'id' => null,
                    'work_date' => $dateKey,
                    'display_date' => $date->copy(),
                    'clock_in_at' => null,
                    'clock_out_at' => null,
                    'break_time' => '',
                    'work_time' => '',
                    'detail_url' => route('admin.attendance.show_by_date', [
                        'user' => $staff->id,
                        'date' => $dateKey,
                    ]),
                ];
            }

            if ($stamp->id) {
                $stamp->detail_url = route('admin.attendance.show', ['id' => $stamp->id]);
            }

            $stamps->push($stamp);
        }

        return view('admin.staff_attendance_list', compact(
            'staff',
            'stamps',
            'month'
        ));
    }

    public function adminStaffAttendanceCsv(Request $request, $id)
    {
        $staff = User::findOrFail($id);

        $month = $request->input('month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($month . '-01')->endOfMonth();

        $stamps = Stamp::with('breaks')
            ->where('user_id', $id)
            ->whereBetween('work_date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->orderBy('work_date')
            ->get()
            ->keyBy(function ($stamp) {
                return Carbon::parse($stamp->work_date)->format('Y-m-d');
            });

        $fileName = $staff->name . '_' . $month . '_attendance.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($stamps, $startOfMonth, $endOfMonth) {
            $handle = fopen('php://output', 'w');

            // Excel文字化け対策
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
                $dateKey = $date->format('Y-m-d');
                $stamp = $stamps->get($dateKey);

                if (!$stamp) {
                    fputcsv($handle, [
                        $date->format('Y/m/d'),
                        '',
                        '',
                        '',
                        '',
                    ]);

                    continue;
                }

                $breakMinutes = 0;

                foreach ($stamp->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at) {
                        $breakStart = Carbon::parse($break->break_start_at)->startOfMinute();
                        $breakEnd = Carbon::parse($break->break_end_at)->startOfMinute();

                        $breakMinutes += $breakStart->diffInMinutes($breakEnd);
                    }
                }

                $workMinutes = 0;

                if ($stamp->clock_in_at && $stamp->clock_out_at) {
                    $clockIn = Carbon::parse($stamp->clock_in_at)->startOfMinute();
                    $clockOut = Carbon::parse($stamp->clock_out_at)->startOfMinute();
                    $workMinutes = $clockIn->diffInMinutes($clockOut) - $breakMinutes;
                    $workMinutes = max(0, $workMinutes);
                }

                fputcsv($handle, [
                    $date->format('Y/m/d'),
                    $stamp->clock_in_at ? Carbon::parse($stamp->clock_in_at)->format('H:i') : '',
                    $stamp->clock_out_at ? Carbon::parse($stamp->clock_out_at)->format('H:i') : '',
                    $breakMinutes > 0 ? sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60) : '',
                    ($stamp->clock_in_at && $stamp->clock_out_at) ? sprintf('%d:%02d', intdiv($workMinutes, 60), $workMinutes % 60) : '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
