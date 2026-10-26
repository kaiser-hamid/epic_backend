<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shared\Auth\Actions\ActivateUser;
use Modules\Shared\Auth\Actions\DeactivateUser;
use Modules\Shared\Auth\Actions\ForceLogoutUser;
use Modules\Shared\Auth\Actions\ResetUserPassword;
use Modules\Shared\Auth\Actions\UnlockUser;
use Modules\Shared\Auth\Data\ResetPasswordData;
use Modules\Shared\Auth\Http\Resources\UserResource;
use Modules\Shared\Auth\Models\User;

/**
 * Admin account controls: status, password reset, unlock and force logout.
 */
final class UserAccountController
{
    public function __construct(
        private readonly ActivateUser $activateUser,
        private readonly DeactivateUser $deactivateUser,
        private readonly ResetUserPassword $resetUserPassword,
        private readonly UnlockUser $unlockUser,
        private readonly ForceLogoutUser $forceLogoutUser,
    ) {}

    public function activate(Request $request, User $user): UserResource
    {
        return UserResource::make($this->activateUser->handle($request->user(), $user));
    }

    public function deactivate(Request $request, User $user): UserResource
    {
        return UserResource::make($this->deactivateUser->handle($request->user(), $user));
    }

    public function resetPassword(Request $request, User $user, ResetPasswordData $data): Response
    {
        $this->resetUserPassword->handle($request->user(), $user, $data);

        return response()->noContent();
    }

    public function unlock(Request $request, User $user): UserResource
    {
        return UserResource::make($this->unlockUser->handle($request->user(), $user));
    }

    public function forceLogout(Request $request, User $user): JsonResponse
    {
        return response()->json(['data' => ['revoked' => $this->forceLogoutUser->handle($request->user(), $user)]]);
    }
}
