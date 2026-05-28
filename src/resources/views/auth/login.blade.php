<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="auth-header">
        <div class="auth-header__inner">
            <img class="auth-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">
        </div>
    </header>

    <main class="auth-page">
        <div class="auth-page__content">
            <h1 class="auth-page__title">ログイン</h1>

            <form class="auth-form" method="POST" action="/login" autocomplete="off">
                @csrf

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

                <button class="auth-form__button" type="submit">ログインする</button>
            </form>

            <a class="auth-page__link" href="/register">会員登録はこちら</a>
        </div>
    </main>
</body>
</html>
