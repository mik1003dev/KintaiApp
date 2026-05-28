<?php

namespace App\Http\Requests;

use App\Models\Stamp;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AttendanceCorrectionRequest extends FormRequest
{
    /**
     * 認可
     */
    public function authorize()
    {
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules()
    {
        if (!$this->correctionTargetIsFinished()) {
            return [];
        }

        return [
            'clock_in_at' => ['required', 'date_format:H:i'],
            'clock_out_at' => ['required', 'date_format:H:i'],
            'note' => ['required', 'max:30'],
            'break_start_at.*' => ['nullable'],
            'break_end_at.*' => ['nullable'],
        ];
    }

    /**
     * エラーメッセージ
     */
    public function messages()
    {
        return [
            'clock_in_at.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_in_at.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'note.required' => '備考を記入してください',
            'note.max' => '備考は30文字以内で入力してください',
        ];
    }

    /**
     * 追加バリデーション
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->correctionTargetIsFinished()) {
                return;
            }

            $clockInAt = $this->input('clock_in_at');
            $clockOutAt = $this->input('clock_out_at');
            $breakStartAtList = $this->input('break_start_at', []);
            $breakEndAtList = $this->input('break_end_at', []);

            // 出勤・退勤の前後チェック
            if (
                $clockInAt &&
                $clockOutAt &&
                !$validator->errors()->has('clock_in_at') &&
                !$validator->errors()->has('clock_out_at')
            ) {
                $stamp = $this->targetStamp();
                $clockIn = Carbon::createFromFormat('H:i', $clockInAt);
                $clockOut = Carbon::createFromFormat('H:i', $clockOutAt);

                if ($clockIn->gt($clockOut)) {
                    $validator->errors()->add(
                        'clock_in_at',
                        '出勤時間もしくは退勤時間が不適切な値です'
                    );
                }

                if (
                    $stamp &&
                    $this->dateTimeForStamp($stamp, $clockInAt)->gt(now())
                ) {
                    $validator->errors()->add(
                        'clock_in_at',
                        '出勤時間もしくは退勤時間が不適切な値です'
                    );
                }

                if (
                    $stamp &&
                    $this->dateTimeForStamp($stamp, $clockOutAt)->gt(now())
                ) {
                    $validator->errors()->add(
                        'clock_out_at',
                        '出勤時間もしくは退勤時間が不適切な値です'
                    );
                }
            }

            // 休憩の前後関係チェック
            foreach ($breakStartAtList as $index => $breakStartAt) {
                $breakEndAt = $breakEndAtList[$index] ?? null;

                if (($breakStartAt && !$breakEndAt) || (!$breakStartAt && $breakEndAt)) {
                    $validator->errors()->add(
                        'break_start_at.' . $index,
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                if (
                    ($breakStartAt && !$this->isTimeFormat($breakStartAt)) ||
                    ($breakEndAt && !$this->isTimeFormat($breakEndAt))
                ) {
                    $validator->errors()->add(
                        'break_start_at.' . $index,
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                // 休憩開始が入力されている場合
                if (
                    $breakStartAt &&
                    $clockInAt &&
                    $clockOutAt &&
                    !$validator->errors()->has('clock_in_at') &&
                    !$validator->errors()->has('clock_out_at') &&
                    !$validator->errors()->has('break_start_at.' . $index) &&
                    !$validator->errors()->has('break_end_at.' . $index)
                ) {
                    $clockIn = Carbon::createFromFormat('H:i', $clockInAt);
                    $clockOut = Carbon::createFromFormat('H:i', $clockOutAt);
                    $breakStart = Carbon::createFromFormat('H:i', $breakStartAt);

                    if ($breakStart->lt($clockIn) || $breakStart->gt($clockOut)) {
                        $validator->errors()->add(
                            'break_start_at.' . $index,
                            '休憩時間が不適切な値です'
                        );
                    }
                }

                // 休憩終了が退勤時間より後の場合
                if (
                    $breakEndAt &&
                    $clockOutAt &&
                    !$validator->errors()->has('clock_out_at') &&
                    !$validator->errors()->has('break_start_at.' . $index) &&
                    !$validator->errors()->has('break_end_at.' . $index)
                ) {
                    $clockOut = Carbon::createFromFormat('H:i', $clockOutAt);
                    $breakEnd = Carbon::createFromFormat('H:i', $breakEndAt);

                    if ($breakEnd->gt($clockOut)) {
                        $validator->errors()->add(
                            'break_end_at.' . $index,
                            '休憩時間もしくは退勤時間が不適切な値です'
                        );
                    }
                }

                // 休憩開始 > 休憩終了 の場合も追加で弾く
                if (
                    $breakStartAt &&
                    $breakEndAt &&
                    !$validator->errors()->has('break_start_at.' . $index) &&
                    !$validator->errors()->has('break_end_at.' . $index)
                ) {
                    $breakStart = Carbon::createFromFormat('H:i', $breakStartAt);
                    $breakEnd = Carbon::createFromFormat('H:i', $breakEndAt);

                    if ($breakStart->gt($breakEnd)) {
                        $validator->errors()->add(
                            'break_start_at.' . $index,
                            '休憩時間が不適切な値です'
                        );
                    }
                }
            }
        });
    }

    private function isTimeFormat($value)
    {
        if (!is_string($value) || !preg_match('/^\d{2}:\d{2}$/', $value)) {
            return false;
        }

        $time = Carbon::createFromFormat('H:i', $value);

        return $time && $time->format('H:i') === $value;
    }

    private function correctionTargetIsFinished()
    {
        $stamp = $this->targetStamp();

        return $stamp && $stamp->status === 'finished' && $stamp->clock_out_at;
    }

    private function targetStamp()
    {
        return Stamp::where('id', $this->route('id'))
            ->where('user_id', Auth::id())
            ->first();
    }

    private function dateTimeForStamp(Stamp $stamp, $time)
    {
        return Carbon::parse($stamp->work_date)->setTimeFromTimeString($time . ':00');
    }
}
