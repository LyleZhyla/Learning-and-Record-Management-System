<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use Tests\TestCase;

class AccountCreatedMailTest extends TestCase
{
    public function test_account_created_email_renders_branded_html_and_plain_text(): void
    {
        $mail = new AccountCreatedMail(
            recipientName: 'Juan Dela Cruz',
            accountEmail: 'juan@example.test',
            temporaryPassword: 'Temp!Password2026',
            roleLabel: 'Student',
            requiresStudentDocuments: true,
        );

        $html = $mail->render();
        $text = view('emails.account-created-text', [
            'recipientName' => $mail->recipientName,
            'accountEmail' => $mail->accountEmail,
            'temporaryPassword' => $mail->temporaryPassword,
            'roleLabel' => $mail->roleLabel,
            'requiresStudentDocuments' => $mail->requiresStudentDocuments,
            'loginUrl' => route('login'),
        ])->render();

        $this->assertStringContainsString('SNAPIE', $html);
        $this->assertStringContainsString('Snapie profile head', $html);
        $this->assertStringContainsString('Snapie waving hello', $html);
        $this->assertStringContainsString('Welcome to Smart NSTP, Juan Dela Cruz!', $html);
        $this->assertStringContainsString('juan@example.test', $html);
        $this->assertStringContainsString('Temp!Password2026', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString('upload your COR and formal photo', $html);
        $this->assertStringContainsString('LOGIN CREDENTIALS', $text);
        $this->assertStringContainsString('REQUIRED BEFORE PORTAL ACCESS', $text);
        $this->assertStringContainsString('Never share your password', $text);
    }
}
