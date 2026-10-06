<div class="form-grid">
    <label class="field-group full" data-account-name-field>
        <span>Full name</span>
        <input type="text" name="name" value="{{ old('name', $user?->name) }}" maxlength="100" autocomplete="name" required data-account-name-input>
        @error('name') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group full" data-account-email-field>
        <span>Email address</span>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" maxlength="255" autocomplete="email" required>
        @error('email') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group" data-account-role-field>
        <span>Account role</span>
        <select name="role" required data-account-role>
            @foreach (($roleOptions ?? \App\Models\User::ROLE_LABELS) as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user?->role ?? ($initialRole ?? 'facilitator')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group" data-account-status-field>
        <span>Account status</span>
        <select name="status" required data-account-status-input>
            @foreach (\App\Models\User::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $user?->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    <label class="field-group full" data-staff-component-field>
        <span>Assigned component</span>
        <select name="nstp_component_id" data-staff-component-select>
            <option value="">Not applicable</option>
            @foreach ($components as $component)
                <option value="{{ $component->id }}" @selected((int) old('nstp_component_id', $user?->nstp_component_id) === $component->id)>{{ $component->code }} — {{ $component->name }}</option>
            @endforeach
        </select>
        <small class="form-help" data-staff-component-help>Required for Facilitators and Coordinators.</small>
        @error('nstp_component_id') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    @php($facilitatorProfile = $user?->facilitatorProfile)
    <section class="facilitator-record-fields full compact-facilitator-record" data-facilitator-record-fields>
        <label class="field-group full">
            <span>Contact number</span>
            <input type="tel" name="contact_number" value="{{ old('contact_number', $facilitatorProfile?->contact_number) }}" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" data-facilitator-record-input data-facilitator-required>
            @error('contact_number') <small class="field-error">{{ $message }}</small> @enderror
        </label>
    </section>

    @if (! $user)
        <div class="generated-password-note full"><span aria-hidden="true">✦</span><div><strong>Automatic temporary password</strong><p>The password will be shown once after account creation. The user must change it at the next login.</p></div></div>
    @endif
</div>
