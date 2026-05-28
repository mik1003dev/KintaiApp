<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationFeatureTest extends TestCase
{
    use RefreshDatabase;

    // 追加ケース: 会員登録バリデーションを一括で回帰確認する。
    public function test_registration_validates_required_fields_and_password_confirmation()
    {
        $this->from('/register')->post('/register', [])
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'name' => 'お名前を入力してください',
                'email' => 'メールアドレスを入力してください',
                'password' => 'パスワードを入力してください',
            ]);

        $this->from('/register')->post('/register', [
            'name' => '一般 太郎',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスはメール形式で入力してください',
                'password' => 'パスワードは8文字以上で入力してください',
                'password_confirmation' => 'パスワードと一致しません',
            ]);
    }

    // テストケースID: 1-6 正しい会員情報がユーザーテーブルへ保存されることを確認する。
    public function test_registration_saves_a_new_general_user()
    {
        $this->post('/register', [
            'name' => '一般 太郎',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/attendance');

        $this->assertDatabaseHas('users', [
            'name' => '一般 太郎',
            'email' => 'new-user@example.com',
            'role' => 'user',
        ]);
    }

    // 追加ケース: 一般ユーザーログインの入力エラーを一括で回帰確認する。
    public function test_general_login_validates_required_fields_and_credentials()
    {
        $this->from('/login')->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
                'password' => 'パスワードを入力してください',
            ]);

        $this->from('/login')->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);
    }

    // 追加ケース: ログイン成功とメール未認証ユーザーの認証画面誘導を確認する。
    public function test_general_user_can_login_and_unverified_user_is_sent_to_verification()
    {
        /** @var User $verifiedUser */
        $verifiedUser = User::factory()->create([
            'email' => 'verified@example.com',
            'role' => 'user',
        ]);

        $this->post('/login', [
            'email' => $verifiedUser->email,
            'password' => 'password',
        ])->assertRedirect('/attendance');

        $this->post('/logout');

        /** @var User $unverifiedUser */
        $unverifiedUser = User::factory()->unverified()->create(['role' => 'user']);

        $this->actingAs($unverifiedUser)->get('/attendance')
            ->assertRedirect('/email/verify');
    }

    // 追加ケース: 管理者ログインの入力エラーを一括で回帰確認する。
    public function test_admin_login_validates_required_fields_and_credentials()
    {
        $this->from('/admin/login')->post('/login', [
            'login_type' => 'admin',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
                'password' => 'パスワードを入力してください',
            ]);

        $this->from('/admin/login')->post('/login', [
            'login_type' => 'admin',
            'email' => 'missing-admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);
    }

    // 追加ケース: 管理者専用ログイン画面から管理画面へ遷移できることを確認する。
    public function test_admin_can_login_from_admin_login_screen()
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'email' => 'admin-login@example.com',
            'role' => 'admin',
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'login_type' => 'admin',
        ])->assertRedirect('/admin/attendance/list');
    }

    // 追加ケース: 会員登録後に認証メールが送信されることを確認する。
    public function test_registration_sends_verification_email_for_application_case()
    {
        Notification::fake();

        $this->post('/register', [
            'name' => '認証 ユーザー',
            'email' => 'verification@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Notification::assertSentTo(User::where('email', 'verification@example.com')->first(), VerifyEmail::class);
    }

    // 追加ケース: メール認証案内画面からMailHogを開けることを確認する。
    public function test_verification_screen_links_to_mailhog_for_application_case()
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create(['role' => 'user']);

        $this->actingAs($user)->get('/email/verify')
            ->assertOk()
            ->assertSee('認証はこちらから')
            ->assertSee('http://localhost:8025', false);
    }

    // 追加ケース: 署名付き認証リンクで認証が完了し勤怠画面へ遷移することを確認する。
    public function test_signed_verification_link_verifies_user_and_redirects_for_application_case()
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create(['role' => 'user']);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect('/attendance?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
