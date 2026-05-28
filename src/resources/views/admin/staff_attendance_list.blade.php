<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>スタッフ別勤怠一覧</title>
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

    <main class="admin-staff">
        <div class="admin-staff__content">
            <h1 class="admin-staff__title">{{ $staff->name }}さんの勤怠</h1>

        <div class="admin-staff__month">
            <a href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m')]) }}">
                ← 前月
            </a>

            <span class="admin-staff__month-current">
                🗓️ {{ \Carbon\Carbon::parse($month . '-01')->format('Y年m月') }}
            </span>

            <a href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m')]) }}">
                翌月 →
            </a>
        </div>

            <table class="admin-staff__table">
                <tr>
                    <th>日付</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>合計</th>
                    <th>詳細</th>
                </tr>

                @foreach ($stamps as $stamp)
                    <tr>
                        <td>
                            {{ $stamp->display_date->locale('ja')->isoFormat('MM/DD(ddd)') }}
                        </td>
                        <td>{{ $stamp->clock_in_at ? \Carbon\Carbon::parse($stamp->clock_in_at)->format('H:i') : '' }}</td>
                        <td>{{ $stamp->clock_out_at ? \Carbon\Carbon::parse($stamp->clock_out_at)->format('H:i') : '' }}</td>
                        <td>{{ $stamp->break_time }}</td>
                        <td>{{ $stamp->work_time }}</td>
                        <td>
                            <a class="admin-staff__link" href="{{ $stamp->detail_url }}">詳細</a>
                        </td>
                    </tr>
                @endforeach
            </table>

            <div class="admin-staff__csv">
                <a href="{{ route('admin.staff.attendance.csv', ['id' => $staff->id, 'month' => $month]) }}">
                    CSV出力
                </a>
            </div>
        </div>
    </main>
</body>

</html>
