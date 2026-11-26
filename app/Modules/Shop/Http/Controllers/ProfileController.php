<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Modules\Shop\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 个人中心：基本资料、头像上传与读取。
 *
 * 头像落本地磁盘 storage/app/avatars，由本控制器读流输出，
 * 不依赖 public/storage 软链接，断网部署同样可用。
 */
class ProfileController extends Controller
{
    private const DISK = 'local';
    private const DIRECTORY = 'avatars';
    private const DEFAULT_DIRECTORY = 'defaults';

    /** 参考项目 public/avatars/defaults 下的 5 张内置头像。 */
    public const DEFAULT_AVATARS = [
        'avatar1.svg',
        'avatar2.svg',
        'avatar3.svg',
        'avatar4.svg',
        'avatar5.svg',
    ];

    public function edit(): View
    {
        return view('shop::profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
        ], [
            'name.required' => '请输入姓名',
            'email.required' => '请输入邮箱',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '该邮箱已被其他账号使用',
        ]);

        $user->fill($data)->save();

        return redirect()->route('shop.profile.edit')->with('status', '基本资料已保存');
    }

    public function avatar(): View
    {
        return view('shop::profile.avatar', [
            'user' => auth()->user(),
            'defaultAvatars' => self::DEFAULT_AVATARS,
        ]);
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'default_avatar' => [
                'nullable', 'string',
                Rule::in(array_map(
                    fn (string $file): string => self::DEFAULT_DIRECTORY.'/'.$file,
                    self::DEFAULT_AVATARS
                )),
            ],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'avatar.image' => '请选择图片文件',
            'avatar.mimes' => '头像仅支持 JPG / PNG / WebP',
            'avatar.max' => '头像不能超过 2 MB',
            'default_avatar.in' => '请选择有效的默认头像',
        ]);

        if ($request->filled('default_avatar')) {
            $default = (string) $request->input('default_avatar');

            $this->deleteFile($user->avatar);
            $user->forceFill(['avatar' => $default])->save();

            return redirect()->route('shop.profile.avatar')->with('status', '已应用默认头像');
        }

        if ($request->boolean('remove_avatar')) {
            $this->deleteFile($user->avatar);
            $user->forceFill(['avatar' => null])->save();

            return redirect()->route('shop.profile.avatar')->with('status', '头像已移除');
        }

        if (! $request->hasFile('avatar')) {
            return back()->withErrors(['avatar' => '请选择要上传的头像图片']);
        }

        $path = $request->file('avatar')->store(self::DIRECTORY, self::DISK);

        $this->deleteFile($user->avatar);
        $user->forceFill(['avatar' => $path])->save();

        return redirect()->route('shop.profile.avatar')->with('status', '头像已更新');
    }

    /**
     * 输出当前登录用户头像（支持内置默认头像；私有缓存，避免重复读盘）。
     */
    public function avatarImage(Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $this->resolveAvatarFile($user->avatar);

        abort_if($file === null, 404, '头像不存在');

        return response()->file($file, [
            'Content-Type' => mime_content_type($file) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function isDefaultAvatar(?string $path): bool
    {
        if (! is_string($path) || ! str_starts_with($path, self::DEFAULT_DIRECTORY.'/')) {
            return false;
        }

        return in_array(basename($path), self::DEFAULT_AVATARS, true);
    }

    /**
     * 把数据库里的 avatar 值解析成可直接输出的绝对文件路径。
     */
    private function resolveAvatarFile(?string $avatar): ?string
    {
        if (blank($avatar)) {
            return null;
        }

        if (self::isDefaultAvatar($avatar)) {
            $file = public_path('avatars/'.self::DEFAULT_DIRECTORY.'/'.basename($avatar));

            return is_file($file) ? $file : null;
        }

        if (! Storage::disk(self::DISK)->exists($avatar)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($avatar);
    }

    private function deleteFile(?string $path): void
    {
        if (blank($path) || self::isDefaultAvatar($path)) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
