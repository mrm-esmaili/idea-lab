<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * فقط کاربران دارای مجوز users.manage (مدیر کل) به این بخش دسترسی دارند.
 * هیچ کاربری نقش خودش را تغییر نمی‌دهد؛ بنابراین همیشه دست‌کم یک
 * مدیر کل باقی می‌ماند. حذف کاربر وجود ندارد؛ برای قطع دسترسی، نقش
 * برداشته می‌شود تا سوابق (مثل تاریخچه وضعیت ایده) حفظ شوند.
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->orderByRaw('role IS NULL')
            ->orderBy('name')
            ->paginate(30);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create', ['user' => new User()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(AdminRole::class)],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = User::create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "کاربر «{$user->name}» ایجاد شد.");
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['nullable', Rule::enum(AdminRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);

        $isSelf = $request->user()->is($user);
        $newRole = filled($data['role'] ?? null)
            ? AdminRole::from($data['role'])
            : null;

        if ($isSelf && $newRole !== $user->role) {
            throw ValidationException::withMessages([
                'role' => 'نقش حساب خودتان را نمی‌توانید تغییر دهید.',
            ]);
        }

        $passwordChanged = filled($data['password'] ?? null);
        $accessRemoved = $user->role !== null && $newRole === null;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $newRole,
        ]);

        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->save();

        if (! $isSelf && ($passwordChanged || $accessRemoved)) {
            $this->logoutEverywhere($user);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "اطلاعات «{$user->name}» به‌روزرسانی شد.");
    }

    private function logoutEverywhere(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        $user->forceFill(['remember_token' => null])->save();
    }
}
