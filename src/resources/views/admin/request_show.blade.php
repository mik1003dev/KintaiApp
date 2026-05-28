<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者 申請詳細画面</title>
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

            <div class="admin-detail__card">
                <div class="admin-detail__row">
                    <div class="admin-detail__label">名前</div>
                    <div class="admin-detail__value">{{ $detail['user_name'] }}</div>
                </div>

                <div class="admin-detail__row">
                    <div class="admin-detail__label">日付</div>
                    <div class="admin-detail__value admin-detail__date">
                        <span>{{ $detail['date_year'] ?? $detail['date'] }}</span>
                        <span>{{ $detail['date_month_day'] ?? '' }}</span>
                    </div>
                </div>

                <div class="admin-detail__row">
                    <div class="admin-detail__label">出勤・退勤</div>
                    <div class="admin-detail__value admin-detail__time admin-detail__time--readonly">
                        <span>{{ $detail['clock_in_at'] }}</span>
                        <span>〜</span>
                        <span>{{ $detail['clock_out_at'] }}</span>
                    </div>
                </div>

                @foreach ($detail['breaks'] as $index => $break)
                    <div class="admin-detail__row">
                        <div class="admin-detail__label">
                            {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                        </div>
                        <div class="admin-detail__value admin-detail__time admin-detail__time--readonly">
                            <span>{{ $break['start'] }}</span>
                            <span>〜</span>
                            <span>{{ $break['end'] }}</span>
                        </div>
                    </div>
                @endforeach

                <div class="admin-detail__row admin-detail__row--textarea">
                    <div class="admin-detail__label">備考</div>
                    <div class="admin-detail__value admin-detail__note-readonly">
                        {{ $detail['note'] }}
                    </div>
                </div>
            </div>

            @if ($detail['status'] === 'pending')
                <form class="admin-detail__button-area" action="{{ route('admin.request_approve', ['attendance_correct_request_id' => $detail['id']]) }}" method="post">
                    @csrf
                    <button class="admin-detail__button" type="submit">承認</button>
                </form>
            @else
                <div class="admin-detail__button-area">
                    <span class="admin-detail__button admin-detail__button--approved">承認済み</span>
                </div>
            @endif
        </div>
    </main>
</body>

</html>
