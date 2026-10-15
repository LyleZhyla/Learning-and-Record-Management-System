@php($systemColors = \App\Models\SystemSetting::systemColors())
<style>
    :root {
        --brand-primary: {{ $systemColors['primary_color'] }};
        --brand-secondary: {{ $systemColors['secondary_color'] }};
        --brand-accent: {{ $systemColors['accent_color'] }};
        --blue: var(--brand-primary);
        --navy: var(--brand-secondary);
        --navy-2: color-mix(in srgb, var(--brand-secondary) 86%, white);
        --green: var(--brand-accent);
        --blue-soft: color-mix(in srgb, var(--brand-primary) 10%, white);
        --green-950: color-mix(in srgb, var(--brand-secondary) 86%, black);
        --green-900: var(--brand-secondary);
        --green-800: color-mix(in srgb, var(--brand-primary) 72%, var(--brand-secondary));
        --green-700: var(--brand-primary);
        --gold: var(--brand-accent);
        --gold-light: color-mix(in srgb, var(--brand-accent) 45%, white);
    }
    .sidebar { background: linear-gradient(180deg, var(--brand-secondary), color-mix(in srgb, var(--brand-secondary) 82%, black)); }
    .primary-button { background: linear-gradient(135deg, var(--brand-primary), color-mix(in srgb, var(--brand-primary) 72%, black)); }
    .brand-mark, .large-avatar { background: linear-gradient(145deg, var(--brand-primary), color-mix(in srgb, var(--brand-secondary) 82%, black)); }
    .nav-link.active { background: color-mix(in srgb, var(--brand-primary) 30%, transparent); }
    .nav-link.active::after, .topbar-status span, .online-dot { background: var(--brand-accent); }
    .text-link, .table-action, .settings-summary-card > strong { color: var(--brand-primary); }
    .welcome-banner, .auth-visual { background: linear-gradient(135deg, var(--brand-secondary), color-mix(in srgb, var(--brand-primary) 72%, var(--brand-secondary))); }
</style>
