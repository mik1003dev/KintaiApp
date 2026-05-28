<?php

namespace App\Http\Requests;

use App\Models\Stamp;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'clock_in_at' => ['required', 'date_format:H:i'],
            'clock_out_at' => ['required', 'date_format:H:i'],
            'note' => ['required', 'max:30'],
            'breaks.*.break_start_at' => ['nullable'],
            'breaks.*.break_end_at' => ['nullable'],
        ];
    }

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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $clockInAt = $this->input('clock_in_at');
            $clockOutAt = $this->input('clock_out_at');
            $breaks = $this->input('breaks', []);
            $workDate = '2000-01-01';
            $stamp = Stamp::find($this->route('id'));
            $clockInDateTime = null;
            $clockOutDateTime = null;

            if (
                !$validator->errors()->has('clock_in_at') &&
                !$validator->errors()->has('clock_out_at') &&
                $clockInAt &&
                $clockOutAt
            ) {
                $clockInDateTime = Carbon::parse($workDate . ' ' . $clockInAt . ':00');
                $clockOutDateTime = Carbon::parse($workDate . ' ' . $clockOutAt . ':00');

                if ($clockInDateTime->gt($clockOutDateTime)) {
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

            $validatedBreaks = [];

            foreach ($breaks as $index => $break) {
                $breakStartAt = $break['break_start_at'] ?? null;
                $breakEndAt = $break['break_end_at'] ?? null;

                if (!$breakStartAt && !$breakEndAt) {
                    continue;
                }

                if (($breakStartAt && !$breakEndAt) || (!$breakStartAt && $breakEndAt)) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                if (!$this->isTimeFormat($breakStartAt) || !$this->isTimeFormat($breakEndAt)) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                $breakStartDateTime = Carbon::parse($workDate . ' ' . $breakStartAt . ':00');
                $breakEndDateTime = Carbon::parse($workDate . ' ' . $breakEndAt . ':00');

                if ($breakStartDateTime->gt($breakEndDateTime)) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                foreach ($validatedBreaks as $validatedBreak) {
                    if (
                        $breakStartDateTime->lt($validatedBreak['end']) &&
                        $breakEndDateTime->gt($validatedBreak['start'])
                    ) {
                        $validator->errors()->add(
                            "breaks.$index.break_start_at",
                            '休憩時間が不適切な値です'
                        );
                        continue 2;
                    }
                }

                if ($clockInDateTime && $clockOutDateTime) {
                    if (
                        $breakStartDateTime->lt($clockInDateTime) ||
                        $breakStartDateTime->gt($clockOutDateTime)
                    ) {
                        $validator->errors()->add(
                            "breaks.$index.break_start_at",
                            '休憩時間が不適切な値です'
                        );
                        continue;
                    }

                    if ($breakEndDateTime->gt($clockOutDateTime)) {
                        $validator->errors()->add(
                            "breaks.$index.break_end_at",
                            '休憩時間もしくは退勤時間が不適切な値です'
                        );
                        continue;
                    }
                }

                $validatedBreaks[] = [
                    'start' => $breakStartDateTime,
                    'end' => $breakEndDateTime,
                ];
            }
        });
    }

    private function isTimeFormat($value)
    {
        if (!$value) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('H:i', $value);
        } catch (\Exception $e) {
            return false;
        }

        return $date && $date->format('H:i') === $value;
    }

    private function dateTimeForStamp(Stamp $stamp, $time)
    {
        return Carbon::parse($stamp->work_date)->setTimeFromTimeString($time . ':00');
    }
}
