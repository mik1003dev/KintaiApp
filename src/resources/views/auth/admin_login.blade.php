<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="auth-header">
        <div class="auth-header__inner">
            <img class="auth-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">
        </div>
    </header>

    <main class="auth-page auth-page--admin-login">
        <div class="auth-page__content">
            <h1 class="auth-page__title">管理者ログイン</h1>

            <form class="auth-form auth-form--admin-login" method="POST" action="/login" autocomplete="off">
                @csrf
                <input type="hidden" name="login_type" value="admin">

                <div class="auth-form__group">
                    <label class="auth-form__label">メールアドレス</label>
                    <input class="auth-form__input" type="text" name="email" value="{{ old('email') }}">
                    @error('email')
                        <p class="auth-form__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="auth-form__group">
                    <label class="auth-form__label">パスワード</label>
                    <input class="auth-form__input" type="password" name="password" value="" autocomplete="new-password">
                    @error('password')
                        <p class="auth-form__error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="auth-form__button" type="submit">管理者ログインする</button>
            </form>
        </div>
    </main>
</body>
</html>
