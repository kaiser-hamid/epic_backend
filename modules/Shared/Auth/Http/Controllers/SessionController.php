<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Shared\Auth\Actions\RevokeOtherSessions;
use Modules\Shared\Auth\Actions\RevokeSession;
use Modules\Shared\Auth\Http\Resources\SessionResource;
use Modules\Shared\Auth\Queries\ListUserSessions;

/**
 * The signed-in user's own devices.
 */
final class SessionController
{
    public function __construct(
        private readonly ListUserSessions $listUserSessions,
        private readonly RevokeSession $revokeSession,
        private readonly RevokeOtherSessions $revokeOtherSessions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SessionResource::collection($this->listUserSessions->get($request->user()));
    }

    public function destroy(Request $request, string $session): Response
    {
        $this->revokeSession->handle($request->user(), $session);

        return response()->noContent();
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        return response()->json(['data' => ['revoked' => $this->revokeOtherSessions->handle($request->user())]]);
    }
}
