@props(['user'])

@if ($user->profile_photo_path)
    <img {{ $attributes->class(['avatar', 'avatar-photo']) }} src="{{ route('profile.photo', ['v' => $user->updated_at?->timestamp]) }}" alt="{{ $user->name }} profile photo">
@else
    <span {{ $attributes->class(['avatar']) }}>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
@endif
