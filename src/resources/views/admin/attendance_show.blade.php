<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理者 勤怠詳細画面</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body>
    <header class="admin-header">
        <div class="admin-header__inner">
            <img class="admin-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">

            <nav class="admin-header__nav">
                <a href="/admin/attendance/list">勤怠一覧</a>
                <a href="/admin/staff/list">スタッフ一覧</a>
                <a href="/stamp_correction_request/list">申請一覧</a>
                <form action="/logout" method="post">
                    @csrf
                    <input type="hidden" name="logout_destination" value="admin">
                    <button type="submit">ログアウト</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="admin-detail">
        <div class="admin-detail__content">
            <h1 class="admin-detail__title">勤怠詳細</h1>

            <form action="{{ route('admin.attendance.update', ['id' => $attendanceDetail['id']]) }}" method="post">
                @csrf

                <div class="admin-detail__card">
                    <div class="admin-detail__row">
                        <div class="admin-detail__label">名前</div>
                        <div class="admin-detail__value">{{ $attendanceDetail['name'] }}</div>
                    </div>

                    <div class="admin-detail__row">
                        <div class="admin-detail__label">日付</div>
                        <div class="admin-detail__value admin-detail__date">
                            <span>{{ $attendanceDetail['date_year'] ?? $attendanceDetail['date'] }}</span>
                            <span>{{ $attendanceDetail['date_month_day'] ?? '' }}</span>
                        </div>
                    </div>

                    <div class="admin-detail__row">
                        <div class="admin-detail__label">出勤・退勤</div>
                        <div class="admin-detail__value admin-detail__field">
                            <div class="admin-detail__time">
                                <input type="text" name="clock_in_at" value="{{ old('clock_in_at', $attendanceDetail['clock_in_at']) }}">
                                <span>〜</span>
                                <input type="text" name="clock_out_at" value="{{ old('clock_out_at', $attendanceDetail['clock_out_at']) }}">
                            </div>
                            @error('clock_in_at')
                                <p class="admin-detail__error">{{ $message }}</p>
                            @else
                                @error('clock_out_at')
                                    <p class="admin-detail__error">{{ $message }}</p>
                                @enderror
                            @enderror
                        </div>
                    </div>

                    @php
                        $breakRows = $attendanceDetail['breaks']->values();
                        if ($breakRows->isEmpty()) {
                            $breakRows = collect([['break_start_at' => '', 'break_end_at' => '']]);
                        } else {
                            $breakRows->push(['break_start_at' => '', 'break_end_at' => '']);
                        }
                    @endphp

                    @foreach ($breakRows as $index => $break)
                        <div class="admin-detail__row">
                            <div class="admin-detail__label">
                                {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                            </div>
                            <div class="admin-detail__value admin-detail__field">
                                <div class="admin-detail__time">
                                    <input type="text" name="breaks[{{ $index }}][break_start_at]"
                                        value="{{ old('breaks.' . $index . '.break_start_at', $break['break_start_at']) }}">
                                    <span>〜</span>
                                    <input type="text" name="breaks[{{ $index }}][break_end_at]"
                                        value="{{ old('breaks.' . $index . '.break_end_at', $break['break_end_at']) }}">
                                </div>
                                @error('breaks.' . $index . '.break_start_at')
                                    <p class="admin-detail__error">{{ $message }}</p>
                                @else
                                    @error('breaks.' . $index . '.break_end_at')
                                        <p class="admin-detail__error">{{ $message }}</p>
                                    @enderror
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="admin-detail__row admin-detail__row--textarea">
                        <div class="admin-detail__label">備考</div>
                        <div class="admin-detail__value admin-detail__field">
                            <textarea name="note">{{ old('note', $attendanceDetail['note']) }}</textarea>
                            @error('note')
                                <p class="admin-detail__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="admin-detail__button-area">
                    <input class="admin-detail__button" type="submit" value="修正">
                </div>
            </form>
        </div>
    </main>
</body>

</html>
