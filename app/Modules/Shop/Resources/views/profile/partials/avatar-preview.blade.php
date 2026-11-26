@php
    $avatarSize = $size ?? '2.25rem';
    $initial = mb_substr((string) ($user->name ?? $user->username ?? '?'), 0, 1);
@endphp
<div class="user-avatar" style="--avatar-size: {{ $avatarSize }};" aria-hidden="true">
    @if (! empty($user->avatar))
        <img src="{{ route('shop.profile.avatar.image', ['v' => optional($user->updated_at)->timestamp]) }}"
             alt="{{ $user->name }} 的头像">
    @else
        <span class="user-avatar-initial">{{ $initial }}</span>
    @endif
</div>
