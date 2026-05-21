<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Paginated list of all users.
     */
    public function index(Request $request): JsonResponse
    {
        // Defense-in-depth: token ability checked first, then role.
        // The 'admin' middleware already enforces the role at route level,
        // but this guards against future mis-routing or middleware removal.
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminUsers->value), 403);

        $users = User::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('email', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        return response()->json([
            'data' => $users->getCollection()->map(fn ($u) => $this->formatUser($u)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Update a user's role or disabled status.
     *
     * Allowed fields: role (user|admin), disabled (bool).
     * Super Admin role cannot be assigned via API — use the CLI.
     */
    public function update(Request $request, User $user, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminUsers->value), 403);

        // Cannot demote/modify other super admins or self
        if ($user->isSuperAdmin() || $user->id === $request->user()->id) {
            abort(403, 'Cannot modify a Super Admin or your own account via API.');
        }

        $data = $request->validate([
            'role'     => 'sometimes|in:user,admin',
            'disabled' => 'sometimes|boolean',
        ]);

        $before = ['role' => $user->role->value];

        if (isset($data['role'])) {
            $user->role = Role::from($data['role']);
        }

        if (isset($data['disabled'])) {
            // Store as email_verified_at = null when disabled (simple approach)
            $user->email_verified_at = $data['disabled'] ? null : now();
        }

        $user->save();

        $auditLogger->log(AuditEvent::ApiAdminUserUpdated, null, [
            'user_id' => $user->id,
            'before'  => $before,
            'after'   => ['role' => $user->role->value],
            'via'     => 'api',
        ]);

        return response()->json(['data' => $this->formatUser($user)]);
    }

    /** @return array<string, mixed> */
    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role->value,
            'verified'   => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
