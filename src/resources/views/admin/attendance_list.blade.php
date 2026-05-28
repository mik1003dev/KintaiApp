<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者 日次勤怠一覧</title>
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

    <main class="admin-daily">
        <div class="admin-daily__content">
            <h1 class="admin-daily__title">
                {{ \Carbon\Carbon::parse($date)->format('Y年n月j日') }}の勤怠
            </h1>

            <div class="admin-daily__date-nav">
                <a href="{{ route('admin.attendance_list', ['date' => $prevDate]) }}">
                    ← 前日
                </a>

                <span>
                    🗓️ {{ \Carbon\Carbon::parse($date)->format('Y年n月j日') }}
                </span>

                <a href="{{ route('admin.attendance_list', ['date' => $nextDate]) }}">
                    翌日 →
                </a>
            </div>

            <table class="admin-daily__table">
                <tr>
                    <th>名前</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>合計</th>
                    <th>詳細</th>
                </tr>

                @forelse ($attendanceList as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['clock_in_at'] }}</td>
                        <td>{{ $item['clock_out_at'] }}</td>
                        <td>{{ $item['break_time'] ?? '' }}</td>
                        <td>{{ $item['work_time'] ?? '' }}</td>
                        <td>
                            <a class="admin-daily__link" href="{{ $item['detail_url'] }}">
                                詳細
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">データがありません</td>
                    </tr>
                @endforelse
            </table>
        </div>
    </main>
</body>

</html>
