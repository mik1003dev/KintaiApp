<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員登録</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="auth-header">
        <div class="auth-header__inner">
            <img class="auth-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">
        </div>
    </header>

    <main class="auth-page auth-page--register">
        <div class="auth-page__content">
            <h1 class="auth-page__title">会員登録</h1>

            <form class="auth-form auth-form--register" method="POST" action="/register">
                @csrf

                <div class="auth-form__group">
                    <label class="auth-form__label">名前</label>
                    <input class="auth-form__input" type="text" name="name" value="{{ old('name') }}">
                    @error('name')
                        <p class="auth-form__error">{{ $message }}</p>
                    @enderror
                </div>

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

                <div class="auth-form__group">
                    <label class="auth-form__label">パスワード確認</label>
                    <input class="auth-form__input" type="password" name="password_confirmation" value="" autocomplete="new-password">
                    @error('password_confirmation')
                        <p class="auth-form__error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="auth-form__button" type="submit">登録する</button>
            </form>

            <a class="auth-page__link" href="/login">ログインはこちら</a>
        </div>
    </main>
</body>
</html>
