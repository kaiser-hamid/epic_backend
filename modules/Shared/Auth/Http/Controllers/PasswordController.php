<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shared\Auth\Actions\ChangePassword;
use Modules\Shared\Auth\Data\ChangePasswordData;

final class PasswordController
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    public function update(Request $request, ChangePasswordData $data): Response
    {
        $this->changePassword->handle($request->user(), $data);

        return response()->noContent();
    }
}
