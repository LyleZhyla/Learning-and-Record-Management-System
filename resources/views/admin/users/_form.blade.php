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
        <select name="role" required data-account-role>
            @foreach (($roleOptions ?? \App\Models\User::ROLE_LABELS) as $value => $label)
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

    <label class="field-group full" data-staff-component-field>
        <span>Staff component</span>
        <select name="nstp_component_id" data-staff-component-select>
            <option value="">Not applicable</option>
            @foreach ($components as $component)
                <option value="{{ $component->id }}" @selected((int) old('nstp_component_id', $user?->nstp_component_id) === $component->id)>{{ $component->code }} — {{ $component->name }}</option>
            @endforeach
        </select>
        <small class="form-help" data-staff-component-help>Required for Coordinators and optional for Facilitators. Coordinator access is restricted to the selected component.</small>
        @error('nstp_component_id') <small class="field-error">{{ $message }}</small> @enderror
    </label>

    @php($facilitatorProfile = $user?->facilitatorProfile)
    <section class="facilitator-record-fields full" data-facilitator-record-fields>
        <div class="facilitator-record-heading full">
            <div><span class="eyebrow">Facilitator record</span><h3>Employment and contact information</h3></div>
            <p>Official employment fields are maintained by the Super Admin. Facilitators can update only their contact number, specialization, and professional summary from their profile.</p>
        </div>

        <label class="field-group">
            <span>Employee number</span>
            <input type="text" name="employee_number" value="{{ old('employee_number', $facilitatorProfile?->employee_number) }}" maxlength="50" data-facilitator-record-input data-facilitator-required>
            @error('employee_number') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Department / unit</span>
            <input type="text" name="department" value="{{ old('department', $facilitatorProfile?->department) }}" maxlength="150" data-facilitator-record-input data-facilitator-required>
            @error('department') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Designation</span>
            <input type="text" name="designation" value="{{ old('designation', $facilitatorProfile?->designation) }}" maxlength="120" data-facilitator-record-input data-facilitator-required>
            @error('designation') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Employment status</span>
            <select name="employment_status" data-facilitator-record-input data-facilitator-required>
                <option value="">Select employment status</option>
                @foreach (\App\Models\FacilitatorProfile::EMPLOYMENT_STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('employment_status', $facilitatorProfile?->employment_status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('employment_status') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Contact number</span>
            <input type="tel" name="contact_number" value="{{ old('contact_number', $facilitatorProfile?->contact_number) }}" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" data-facilitator-record-input>
            @error('contact_number') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group">
            <span>Specialization</span>
            <input type="text" name="specialization" value="{{ old('specialization', $facilitatorProfile?->specialization) }}" maxlength="255" placeholder="e.g. Community development" data-facilitator-record-input>
            @error('specialization') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field-group full">
            <span>Professional summary</span>
            <textarea name="professional_summary" rows="4" maxlength="2000" data-facilitator-record-input>{{ old('professional_summary', $facilitatorProfile?->professional_summary) }}</textarea>
            @error('professional_summary') <small class="field-error">{{ $message }}</small> @enderror
        </label>
    </section>

    @if (! $user)
        <div class="generated-password-note full"><span aria-hidden="true">✦</span><div><strong>Automatic temporary password</strong><p>The password will be shown once after account creation. The user must change it at the next login.</p></div></div>
    @endif
</div>
