<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\TeamMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamMemberController extends Controller
{
    public function store(AddTeamMemberRequest $request, Team $team, TeamMemberService $service): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $membership = $team->members()->where('user_id', $authUser->id)->first();
        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Você não tem permissão para realizar esta ação.'], 403);
        }

        $data = $request->validated();

        if ($team->members()->where('phone', $data['phone'])->exists()) {
            return response()->json(['message' => 'Member with this phone already exists.'], 422);
        }

        $member = $service->createMember($team, $data);

        return response()->json([
            'member' => new TeamMemberResource($member),
        ], 201);
    }

    public function destroy(Team $team, TeamMember $member): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $membership = $team->members()->where('user_id', $authUser->id)->first();
        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Você não tem permissão para realizar esta ação.'], 403);
        }

        if ($member->team_id !== $team->id) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        if ($member->user_id === $team->owner_id) {
            return response()->json(['message' => 'Cannot remove the team owner.'], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Member removed.']);
    }
}
