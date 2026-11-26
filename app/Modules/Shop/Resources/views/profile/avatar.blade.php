@extends('shop::layouts.app')

@section('title', '更新头像')
@section('heading', '更新头像')

@section('content')
    <div class="card" style="max-inline-size:30rem;">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="text-center mb-3">
            @include('shop::profile.partials.avatar-preview', ['user' => $user, 'size' => '8rem'])
            <p class="text-muted small mt-2 mb-0">当前头像</p>
        </div>

        <form method="post" action="{{ route('shop.profile.avatar.update') }}" enctype="multipart/form-data" aria-label="头像上传表单">
            @csrf

            <div class="mb-3">
                <label for="avatar" class="form-label">选择图片</label>
                <input type="file" id="avatar" name="avatar"
                       class="form-control @error('avatar') is-invalid @enderror"
                       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <div class="form-text">支持 JPG / PNG / WebP，单张不超过 2 MB；上传后自动替换旧头像。</div>
                @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if (! empty($user->avatar))
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                    <label class="form-check-label" for="remove_avatar">移除当前头像，改用姓名首字</label>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>上传头像</button>
                <a href="{{ route('shop.profile.edit') }}" class="btn btn-outline-secondary">返回个人资料</a>
            </div>
        </form>
    </div>

    <div class="card mt-3" style="max-inline-size:30rem;">
        <div class="card-header">
            <h3 class="card-title h6 mb-0">默认头像仓库</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">内置 5 款默认头像，点击即可立即替换当前头像。</p>
            <div class="row row-cols-3 g-3">
                @foreach ($defaultAvatars as $file)
                    @php $value = 'defaults/'.$file; @endphp
                    <div class="col text-center">
                        <form method="post" action="{{ route('shop.profile.avatar.update') }}">
                            @csrf
                            <input type="hidden" name="default_avatar" value="{{ $value }}">
                            <button type="submit"
                                    class="default-avatar-option @if ($user->avatar === $value) is-selected @endif"
                                    aria-label="使用默认头像{{ $loop->iteration }}">
                                <img src="{{ asset('assets/shop/avatars/defaults/'.$file) }}"
                                     alt="默认头像{{ $loop->iteration }}" width="72" height="72">
                                <span class="small text-muted d-block mt-1">头像{{ $loop->iteration }}</span>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
