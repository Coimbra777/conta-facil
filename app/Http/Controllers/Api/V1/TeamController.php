<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTeamRequest;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Charge;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function store(StoreTeamRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $team = Team::create([
            'name' => $request->validated()['name'],
            'owner_id' => $user->id,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone ?? '',
            'email' => $user->email,
            'role' => 'admin',
        ]);

        return response()->json([
            'team' => new TeamResource($team->loadCount('members')),
        ], 201);
    }

    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $teamIds = TeamMember::where('user_id', $user->id)->pluck('team_id');
        $teams = Team::whereIn('id', $teamIds)->withCount('members')->get();

        return response()->json([
            'teams' => TeamResource::collection($teams),
        ]);
    }

    public function show(Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Você não tem permissão para realizar esta ação.'], 403);
        }

        $team->load('owner', 'members');

        return response()->json([
            'team' => new TeamResource($team),
            'members' => TeamMemberResource::collection($team->members),
        ]);
    }

    public function dashboard(Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Você não tem permissão para realizar esta ação.'], 403);
        }

        $expenseIds = $team->expenses()->pluck('id');
        $charges = Charge::whereIn('expense_id', $expenseIds)->get();

        $paidStatuses = ['validated'];

        $totalOpen = $charges->reject(fn ($c) => in_array($c->status, $paidStatuses))->sum('amount');
        $totalPaid = $charges->filter(fn ($c) => in_array($c->status, $paidStatuses))->sum('amount');
        $membersPaid = $charges->filter(fn ($c) => in_array($c->status, $paidStatuses))->unique('team_member_id')->count();
        $membersPending = $charges->reject(fn ($c) => in_array($c->status, $paidStatuses))->unique('team_member_id')->count();

        return response()->json([
            'total_expenses' => $team->expenses()->count(),
            'total_open' => round($totalOpen, 2),
            'total_paid' => round($totalPaid, 2),
            'members_paid' => $membersPaid,
            'members_pending' => $membersPending,
        ]);
    }
}
