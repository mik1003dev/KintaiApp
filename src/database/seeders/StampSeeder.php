<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stamp;
use App\Models\User;
use Carbon\Carbon;

class StampSeeder extends Seeder
{
    public function run()
    {
        $today = Carbon::today();
        $seedUsers = User::whereIn('email', [
            'user@example.com',
            'taro@example.com',
            'hanako@example.com',
            'ichiro@example.com',
        ])->pluck('id');

        Stamp::whereIn('user_id', $seedUsers)
            ->whereBetween('work_date', [
                $today->copy()->startOfMonth()->subMonths(2)->toDateString(),
                $today->toDateString(),
            ])
            ->delete();

        $this->seedMonthlyStamps(
            User::where('email', 'user@example.com')->firstOrFail(),
            $today
        );

        $this->createStamp('taro@example.com', $today, '09:00', null, 'working', null);
        $this->createStamp('hanako@example.com', $today, '08:45', null, 'on_break', null);
        $this->createStamp('ichiro@example.com', $today, '09:15', '18:10', 'finished', '通常勤務');
    }

    private function seedMonthlyStamps(User $user, Carbon $today)
    {
        $weekdayOff = $this->nthWeekdayOfMonth(
            $today->copy()->subMonth(),
            Carbon::MONDAY,
            2
        );
        $holidayWork = $this->nthWeekdayOfMonth(
            $today->copy()->subMonth(),
            Carbon::SATURDAY,
            2
        );

        for ($monthOffset = 2; $monthOffset >= 0; $monthOffset--) {
            $month = $today->copy()->startOfMonth()->subMonths($monthOffset);
            $lastDay = $monthOffset === 0
                ? $today->day
                : $month->copy()->endOfMonth()->day;

            for ($day = 1; $day <= $lastDay; $day++) {
                $date = $month->copy()->day($day);

                if ($weekdayOff && $date->isSameDay($weekdayOff)) {
                    continue;
                }

                if ($date->isWeekend() && (!$holidayWork || !$date->isSameDay($holidayWork))) {
                    continue;
                }

                $clockIn = $date->copy()->setTime(9, 0);
                $clockOut = $date->copy()->setTime(18, 0);
                $note = null;

                if ($holidayWork && $date->isSameDay($holidayWork)) {
                    $note = '休日出勤';
                } elseif (str_contains((string) $day, '5')) {
                    $clockOut = $date->copy()->setTime(16, 0);
                    $note = '早退';
                } elseif ($day % 10 === 0) {
                    $note = '追加休憩あり';
                } elseif (str_contains((string) $day, '8')) {
                    $clockOut = $date->copy()->setTime(19, 0);
                    $note = '残業';
                }

                Stamp::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'work_date' => $date->toDateString(),
                    ],
                    [
                        'clock_in_at' => $clockIn,
                        'clock_out_at' => $clockOut,
                        'status' => 'finished',
                        'note' => $note,
                    ]
                );
            }
        }
    }

    private function nthWeekdayOfMonth(Carbon $month, int $weekday, int $nth)
    {
        $date = $month->copy()->startOfMonth();
        $count = 0;

        while ($date->month === $month->month) {
            if ($date->dayOfWeek === $weekday) {
                $count++;

                if ($count === $nth) {
                    return $date->copy();
                }
            }

            $date->addDay();
        }

        return null;
    }

    private function createStamp($email, Carbon $date, $clockIn, $clockOut, $status, $note)
    {
        $user = User::where('email', $email)->firstOrFail();

        Stamp::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_date' => $date->toDateString(),
            ],
            [
                'clock_in_at' => $clockIn ? $date->copy()->setTimeFromTimeString($clockIn) : null,
                'clock_out_at' => $clockOut ? $date->copy()->setTimeFromTimeString($clockOut) : null,
                'status' => $status,
                'note' => $note,
            ]
        );
    }
}
