WELCOME TO SMART NSTP

Hello {{ $recipientName }},

Your {{ strtolower($roleLabel) }} account is ready.

LOGIN CREDENTIALS
Email address: {{ $accountEmail }}
Temporary password: {{ $temporaryPassword }}

Sign in: {{ $loginUrl }}

@if ($requiresStudentDocuments)
REQUIRED BEFORE PORTAL ACCESS
After signing in, upload your Certificate of Registration (COR) and formal photo before the student portal becomes available.

@endif
For your security, you will be asked to create a new password after your first sign-in. Never share your password with anyone.

This automated message was sent by Snapie for the Smart NSTP Management and AI-Integrated Platform.
