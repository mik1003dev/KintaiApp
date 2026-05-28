<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠一覧画面</title>
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

    <main class="attendance-list">
        <div class="attendance-list__content">
            <h1 class="attendance-list__title">勤怠一覧</h1>

            <div class="attendance-list__month">
                <a href="{{ route('attendance.list', ['month' => $previousMonth]) }}">← 前月</a>

                <form class="attendance-list__month-form" method="get" action="{{ route('attendance.list') }}">
                    <label class="attendance-list__month-picker">
                        <span class="attendance-list__month-label">📅 {{ $currentMonth->format('Y/m') }}</span>
                        <input type="month" name="month" value="{{ $currentMonth->format('Y-m') }}" onchange="this.form.submit()">
                    </label>
                </form>

                <a href="{{ route('attendance.list', ['month' => $nextMonth]) }}">翌月 →</a>
            </div>

            <table class="attendance-list__table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attendanceList as $attendance)
                        <tr>
                            <td>{{ $attendance['work_date'] }}</td>
                            <td>{{ $attendance['clock_in_at'] }}</td>
                            <td>{{ $attendance['clock_out_at'] }}</td>
                            <td>{{ $attendance['break_time'] }}</td>
                            <td>{{ $attendance['work_time'] }}</td>
                            <td>
                                <a class="attendance-list__detail" href="{{ $attendance['detail_url'] }}">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">勤怠データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>
