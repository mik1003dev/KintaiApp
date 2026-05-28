<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠詳細画面</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body>
    <header class="user-header">
        <div class="user-header__inner">
            <img class="user-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">

            <nav class="user-header__nav">
                <a href="{{ route('attendance.index') }}">勤怠</a>
                <a href="{{ route('attendance.list') }}">勤怠一覧</a>
                <a href="{{ route('attendance.request_list') }}">申請</a>
                <form action="/logout" method="post">
                    @csrf
                    <button type="submit">ログアウト</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="attendance-detail">
        <div class="attendance-detail__content">
            <h1 class="attendance-detail__title">勤怠詳細</h1>

            @php
                $isLocked = $isPending || !$canRequestCorrection;
            @endphp

            <form action="{{ route('attendance.update_request', ['id' => $attendanceDetail['id']]) }}" method="post">
                @csrf

                <div class="attendance-detail__card">
                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">名前</div>
                        <div class="attendance-detail__value">{{ $attendanceDetail['name'] }}</div>
                    </div>

                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">日付</div>
                        <div class="attendance-detail__value attendance-detail__date">
                            <span>{{ $attendanceDetail['date_year'] ?? $attendanceDetail['date'] }}</span>
                            <span>{{ $attendanceDetail['date_month_day'] ?? '' }}</span>
                        </div>
                    </div>

                    <div class="attendance-detail__row">
                        <div class="attendance-detail__label">出勤・退勤</div>
                        <div class="attendance-detail__value attendance-detail__field">
                            <div class="attendance-detail__time">
                                <input type="text" name="clock_in_at" value="{{ old('clock_in_at', $attendanceDetail['clock_in_at']) }}" @if($isLocked) disabled @endif>
                                <span>〜</span>
                                <input type="text" name="clock_out_at" value="{{ old('clock_out_at', $attendanceDetail['clock_out_at']) }}" @if($isLocked) disabled @endif>
                            </div>
                            @error('clock_in_at')
                                <p class="attendance-detail__error">{{ $message }}</p>
                            @else
                                @error('clock_out_at')
                                    <p class="attendance-detail__error">{{ $message }}</p>
                                @enderror
                            @enderror
                        </div>
                    </div>

                    @php
                        $breakRows = $attendanceDetail['breaks']->values();
                        if ($isPending) {
                            $breakRows = $breakRows->filter(function ($break) {
                                return !empty($break['break_start_at']) || !empty($break['break_end_at']);
                            })->values();
                        } elseif ($breakRows->isEmpty()) {
                            $breakRows = collect([['break_start_at' => '', 'break_end_at' => '']]);
                        } else {
                            $breakRows->push(['break_start_at' => '', 'break_end_at' => '']);
                        }
                    @endphp

                    @foreach ($breakRows as $index => $break)
                        <div class="attendance-detail__row">
                            <div class="attendance-detail__label">
                                {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                            </div>
                            <div class="attendance-detail__value attendance-detail__field">
                                <div class="attendance-detail__time">
                                    <input type="text" name="break_start_at[]" value="{{ old('break_start_at.' . $index, $break['break_start_at']) }}" @if($isLocked) disabled @endif>
                                    <span>〜</span>
                                    <input type="text" name="break_end_at[]" value="{{ old('break_end_at.' . $index, $break['break_end_at']) }}" @if($isLocked) disabled @endif>
                                </div>
                                @error('break_start_at.' . $index)
                                    <p class="attendance-detail__error">{{ $message }}</p>
                                @else
                                    @error('break_end_at.' . $index)
                                        <p class="attendance-detail__error">{{ $message }}</p>
                                    @enderror
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="attendance-detail__row attendance-detail__row--textarea">
                        <div class="attendance-detail__label">備考</div>
                        <div class="attendance-detail__value attendance-detail__field">
                            <textarea name="note" @if($isLocked) disabled @endif>{{ old('note', $attendanceDetail['note']) }}</textarea>
                            @error('note')
                                <p class="attendance-detail__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                @if ($isPending)
                    <p class="attendance-detail__pending">* 承認待ちのため修正はできません。</p>
                @elseif (!$canRequestCorrection)
                    <p class="attendance-detail__pending">* 退勤後に修正申請できます。</p>
                @endif

                @if (!$isLocked)
                    <div class="attendance-detail__button-area">
                        <button class="attendance-detail__button" type="submit">修正</button>
                    </div>
                @endif
            </form>
        </div>
    </main>
</body>

</html>
