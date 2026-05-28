<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メール認証</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="auth-header">
        <div class="auth-header__inner">
            <img class="auth-header__logo" src="{{ asset('images/COACHTECHヘッダーロゴ.png') }}" alt="COACHTECH">
        </div>
    </header>

    <main class="auth-page auth-page--verify">
        <div class="auth-page__content">
            <div class="verify-email">
                <p class="verify-email__text">
                    登録していただいたメールアドレスに認証メールを送付しました。<br>
                    メール認証を完了してください。
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="verify-email__status">
                        認証メールを再送信しました。
                    </p>
                @endif

                <a class="verify-email__button" href="http://localhost:8025" target="_blank" rel="noopener">
                    認証はこちらから
                </a>

                <form class="verify-email__resend-form" method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="verify-email__resend" type="submit">認証メールを再送する</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
