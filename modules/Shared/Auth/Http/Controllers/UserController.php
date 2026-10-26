<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Auth\Actions\CreateUser;
use Modules\Shared\Auth\Actions\UpdateUser;
use Modules\Shared\Auth\Data\CreateUserData;
use Modules\Shared\Auth\Data\UpdateUserData;
use Modules\Shared\Auth\Http\Resources\UserResource;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Queries\ListUsers;

final class UserController
{
    public function __construct(
        private readonly ListUsers $listUsers,
        private readonly CreateUser $createUser,
        private readonly UpdateUser $updateUser,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->listUsers->get($request));
    }

    public function store(CreateUserData $data): UserResource
    {
        return UserResource::make($this->createUser->handle($data));
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function update(Request $request, User $user, UpdateUserData $data): UserResource
    {
        return UserResource::make($this->updateUser->handle($request->user(), $user, $data));
    }
}
