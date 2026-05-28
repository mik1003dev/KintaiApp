<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>申請一覧</title>
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

    <main class="admin-request">
        <div class="admin-request__content">
            <h1 class="admin-request__title">申請一覧</h1>

            <div class="admin-request__tabs">
                <a class="{{ request('status', 'pending') === 'pending' ? 'active' : '' }}"
                    href="{{ route('attendance.request_list', ['status' => 'pending']) }}">
                    承認待ち
                </a>

                <a class="{{ request('status') === 'approved' ? 'active' : '' }}"
                    href="{{ route('attendance.request_list', ['status' => 'approved']) }}">
                    承認済み
                </a>
            </div>

            <table class="admin-request__table">
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>

                @foreach ($requestList as $item)
                    <tr>
                        <td>{{ $item['status_label'] }}</td>
                        <td>{{ $item['user_name'] }}</td>
                        <td>{{ $item['date'] }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($item['reason'], 30) }}</td>
                        <td>{{ $item['request_date'] }}</td>
                        <td>
                            <a class="admin-request__link" href="{{ route('attendance.show', ['id' => $item['id']]) }}">
                                詳細
                            </a>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </main>
</body>

</html>
