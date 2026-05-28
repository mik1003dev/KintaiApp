<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフ一覧</title>
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
            <h1 class="admin-staff__title">スタッフ一覧</h1>

            <table class="admin-staff__table">
                <tr>
                    <th>名前</th>
                    <th>メールアドレス</th>
                    <th>月次勤怠</th>
                </tr>

                @foreach ($staffs as $staff)
                    <tr>
                        <td>{{ $staff->name }}</td>
                        <td>{{ $staff->email }}</td>
                        <td>
                            <a class="admin-staff__link" href="/admin/attendance/staff/{{ $staff->id }}">詳細</a>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </main>
</body>

</html>
