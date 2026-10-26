<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\LoginData;
use Modules\Shared\Auth\Data\UserProfile;
use Modules\Shared\Auth\Exceptions\AccountInactive;
use Modules\Shared\Auth\Exceptions\AccountLocked;
use Modules\Shared\Auth\Exceptions\BranchAccessDenied;
use Modules\Shared\Auth\Exceptions\InvalidCredentials;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\UserProfileBuilder;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Services\SessionCurrentBranch;
use Modules\Shared\Rbac\Models\UserBranchPermission;

/**
 * Signs a user into one branch. Order matters: lock, then password, then status, then branch access,
 * so account details are only revealed to someone who knows the password.
 */
final class LoginUser
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Request $request,
        private readonly Hasher $hasher,
        private readonly Config $config,
        private readonly AuditLogger $auditLogger,
        private readonly UserProfileBuilder $profileBuilder,
    ) {}

    /**
     * @throws InvalidCredentials|AccountLocked|AccountInactive|BranchAccessDenied|ValidationException
     */
    public function handle(LoginData $data): UserProfile
    {
        $user = User::query()->where('username', $data->username)->first();

        if ($user === null) {
            $this->hasher->check($data->password, $this->dummyHash());
            $this->auditLogger->record(AuditAction::LoginFailed, newValues: ['username' => $data->username]);

            throw new InvalidCredentials;
        }

        if ($user->isLocked()) {
            throw new AccountLocked($user->locked_until);
        }

        if (! $this->hasher->check($data->password, $user->password)) {
            $this->recordFailedAttempt($user);
        }

        if (! $user->is_active) {
            throw new AccountInactive;
        }

        $branch = $this->branchFor($user, $data->branchId);

        return DB::transaction(function () use ($user, $branch): UserProfile {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ])->save();

            $this->auth->guard('web')->login($user);
            $this->request->session()->regenerate();
            $this->request->session()->put(SessionCurrentBranch::SESSION_KEY, $branch->getKey());

            $this->auditLogger->record(AuditAction::Login, $user, newValues: ['branch_id' => $branch->getKey()], actor: $user);

            return $this->profileBuilder->build($user, $branch);
        });
    }

    /**
     * @throws InvalidCredentials|AccountLocked
     */
    private function recordFailedAttempt(User $user): never
    {
        $maxAttempts = (int) $this->config->get('auth.lockout.max_attempts', 5);

        $lockedUntil = DB::transaction(function () use ($user, $maxAttempts) {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $attempts = $user->failed_login_attempts + 1;
            $lockedUntil = $attempts >= $maxAttempts
                ? now()->addMinutes((int) $this->config->get('auth.lockout.minutes', 15))
                : null;

            $user->forceFill([
                'failed_login_attempts' => $lockedUntil === null ? $attempts : 0,
                'locked_until' => $lockedUntil,
            ])->save();

            $this->auditLogger->record(AuditAction::LoginFailed, $user, newValues: ['attempts' => $attempts], actor: $user);

            if ($lockedUntil !== null) {
                $this->auditLogger->record(AuditAction::AccountLocked, $user, newValues: ['locked_until' => $lockedUntil->toIso8601String()], actor: $user);
            }

            return $lockedUntil;
        });

        throw $lockedUntil === null ? new InvalidCredentials : new AccountLocked($lockedUntil);
    }

    /**
     * @throws ValidationException|BranchAccessDenied
     */
    private function branchFor(User $user, int $branchId): Branch
    {
        $branch = Branch::query()->active()->find($branchId);

        if ($branch === null) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch is invalid.']);
        }

        $hasAccess = $user->is_super_admin || UserBranchPermission::query()
            ->where('user_id', $user->getKey())
            ->where('branch_id', $branch->getKey())
            ->exists();

        if (! $hasAccess) {
            throw new BranchAccessDenied;
        }

        return $branch;
    }

    /**
     * A real hash to check against when the username is unknown, so response time does not reveal it.
     */
    private function dummyHash(): string
    {
        return self::$dummyHash ??= $this->hasher->make(Str::random(32));
    }
}
