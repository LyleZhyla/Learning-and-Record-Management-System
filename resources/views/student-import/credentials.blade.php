@extends($layout)

@section('title', 'Imported Student Credentials')
@section('page-title', 'Imported Student Credentials')

@section('content')
    <div class="import-credentials-page">
        <section class="page-actions import-credential-heading">
            <div><span class="eyebrow">Import successful</span><h2>{{ number_format($studentCount) }} student account{{ $studentCount === 1 ? '' : 's' }} created</h2><p>Copy or print these credentials before leaving this page.</p></div>
            <div class="page-action-buttons import-credential-actions"><button class="secondary-outline-button" type="button" onclick="window.print()">Print credentials</button><a class="primary-button compact" href="{{ route($backRoute) }}">Done</a></div>
        </section>

        <div class="alert warning import-credential-warning" role="alert"><strong>Passwords are shown only on this result page.</strong> Send each credential securely. Every student must change the temporary password after signing in.</div>

        <section class="card user-table-card import-credential-table-card">
            <div class="table-wrap">
                <table class="data-table import-credential-table">
                    <thead><tr><th>Student</th><th>Login email</th><th>Temporary password</th><th>Attendance QR</th></tr></thead>
                    <tbody>
                        @foreach($credentials as $index => $credential)
                            <tr>
                                <td><strong>{{ $credential['name'] }}</strong></td>
                                <td><span class="credential-email">{{ $credential['email'] }}</span></td>
                                <td><div class="credential-password"><code data-import-password="{{ $index }}">{{ $credential['temporary_password'] }}</code><button type="button" data-copy-import-password="{{ $index }}">Copy</button></div></td>
                                <td><img class="import-credential-qr" src="{{ $credential['qr_data_uri'] }}" alt="Attendance QR for {{ $credential['name'] }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-copy-import-password]').forEach((button) => {
            button.addEventListener('click', async () => {
                const password = document.querySelector(`[data-import-password="${button.dataset.copyImportPassword}"]`);
                if (!password) return;

                try {
                    await navigator.clipboard.writeText(password.textContent.trim());
                    button.textContent = 'Copied';
                } catch {
                    const range = document.createRange();
                    range.selectNodeContents(password);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    button.textContent = 'Select & copy';
                }
            });
        });
    </script>
@endsection
