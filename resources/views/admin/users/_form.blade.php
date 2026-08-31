<div class="form-grid">
    <label class="field-group full">
        <span>Full name</span>
        <input type="text" name="name" value="{{ old('name', $user?->name) }}" maxlength="100" autocomplete="name" required>
        @error('name') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group full">
        <span>Email address</span>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" maxlength="255" autocomplete="email" required>
        @error('email') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group">
        <span>Account role</span>
        <select name="role" required>
            @foreach (\App\Models\User::ROLE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user?->role ?? ($initialRole ?? 'facilitator')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group">
        <span>Account status</span>
        <select name="status" required>
            @foreach (\App\Models\User::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $user?->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group full">
        <span>Staff component</span>
        <select name="nstp_component_id">
            <option value="">Not applicable</option>
            @foreach ($components as $component)
                <option value="{{ $component->id }}" @selected((int) old('nstp_component_id', $user?->nstp_component_id) === $component->id)>{{ $component->code }} — {{ $component->name }}</option>
            @endforeach
        </select>
        <small class="form-help">Required for Coordinators and optional for Facilitators. Coordinator access is restricted to the selected component.</small>
        @error('nstp_component_id') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    @if (! $user)
        <label class="field-group">
            <span>Temporary password</span>
            <input type="password" name="password" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}" required>
            @error('password') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Confirm temporary password</span>
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>
        <x-password-requirements />
        <p class="form-help full">The user must change this temporary password at the next login.</p>
    @endif
</div>
