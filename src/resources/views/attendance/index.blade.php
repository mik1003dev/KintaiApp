<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠打刻画面</title>
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

    @php
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $now = now();
        $statusLabels = [
            'off_duty' => '勤務外',
            'working' => '出勤中',
            'on_break' => '休憩中',
            'finished' => '退勤済',
        ];
    @endphp

    <main class="attendance-stamp">
        <div class="attendance-stamp__content">
            <p class="attendance-stamp__status">
                {{ $statusLabels[$status] ?? '勤務外' }}
            </p>

            <p class="attendance-stamp__date">
                {{ $now->format('Y年n月j日') }}({{ $weekdays[$now->dayOfWeek] }})
            </p>

            <p class="attendance-stamp__time">
                {{ $now->format('H:i') }}
            </p>

            <div class="attendance-stamp__actions">
                @if ($status === 'off_duty')
                    <form action="{{ route('attendance.clock_in') }}" method="post">
                        @csrf
                        <button class="attendance-stamp__button" type="submit">出勤</button>
                    </form>
                @endif

                @if ($status === 'working')
                    <form action="{{ route('attendance.clock_out') }}" method="post">
                        @csrf
                        <button class="attendance-stamp__button" type="submit">退勤</button>
                    </form>

                    <form action="{{ route('attendance.break_in') }}" method="post">
                        @csrf
                        <button class="attendance-stamp__button attendance-stamp__button--light" type="submit">休憩入</button>
                    </form>
                @endif

                @if ($status === 'on_break')
                    <form action="{{ route('attendance.break_out') }}" method="post">
                        @csrf
                        <button class="attendance-stamp__button attendance-stamp__button--light" type="submit">休憩戻</button>
                    </form>
                @endif

                @if ($status === 'finished')
                    <p class="attendance-stamp__message">お疲れ様でした。</p>
                @endif
            </div>
        </div>
    </main>
</body>

</html>
