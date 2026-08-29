@props(['user'])

<div class="profile-photo-editor">
    <div class="profile-photo-preview" data-profile-photo-preview>
        @if ($user->profile_photo_path)
            <img src="{{ route('profile.photo', ['v' => $user->updated_at?->timestamp]) }}" alt="Current profile photo">
        @else
            <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
        @endif
    </div>
    <div class="profile-photo-controls">
        <span class="eyebrow">Profile picture</span>
        <strong>Choose a photo</strong>
        <p>JPG, PNG, or WebP · Maximum 5 MB. A square image works best.</p>
        <label class="secondary-outline-button profile-photo-button" for="profile_photo">Upload picture</label>
        <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" data-profile-photo-input>
        <small data-profile-photo-name>{{ $user->profile_photo_path ? 'Current photo will remain unless you choose a new one.' : 'No photo selected.' }}</small>
        @error('profile_photo')<small class="field-error">{{ $message }}</small>@enderror
    </div>
</div>
<script src="{{ asset('js/profile-photo.js') }}"></script>
