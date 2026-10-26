<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shared\Auth\Actions\LoginUser;
use Modules\Shared\Auth\Actions\LogoutUser;
use Modules\Shared\Auth\Data\LoginData;
use Modules\Shared\Auth\Http\Resources\UserProfileResource;
use Modules\Shared\Auth\Queries\ShowCurrentUser;

final class AuthenticatedSessionController
{
    public function __construct(
        private readonly LoginUser $loginUser,
        private readonly LogoutUser $logoutUser,
        private readonly ShowCurrentUser $showCurrentUser,
    ) {}

    public function store(LoginData $data): UserProfileResource
    {
        return UserProfileResource::make($this->loginUser->handle($data));
    }

    public function show(Request $request): UserProfileResource
    {
        return UserProfileResource::make($this->showCurrentUser->get($request->user()));
    }

    public function destroy(Request $request): Response
    {
        $this->logoutUser->handle($request->user());

        return response()->noContent();
    }
}
