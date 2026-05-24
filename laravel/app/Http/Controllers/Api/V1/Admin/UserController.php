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
use Illuminate\Validation\Rule;
use Dedoc\Scramble\Attributes\Response;
use App\Support\PaginationMeta; // used by documentation

/**
 * @tags Admin
 */
class UserController extends Controller
{
    /**
     * Paginated list of all users.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 1000). Example: 100
     * @queryParam search string Filter users by email or name.
     */
    #[Response(200, 'Paginated users list',
        type: 'array{
            data: array<int, array{
                id: int,
                name: string|null,
                email: string,
                role: Role,
                verified: bool,
                created_at: string
            }>,
            meta: array{
                current_page: int,
                last_page: int,
                per_page: int,
                total: int,
                count: int,
                from: int|null,
                to: int|null,
                has_more_pages: bool
            }
        }'
    )]
    #[Response(403, 'Unauthorized or missing token ability',
        type: 'array{
            message: string
        }'
    )]
    #[Response(422, 'Validation error',
        type: 'array{
            message: string,
            errors: array<string, array<int, string>>
        }'
    )]
    public function index(Request $request): JsonResponse
    {
        // Defense-in-depth: token ability checked first, then role.
        // The 'admin' middleware already enforces the role at route level,
        // but this guards against future mis-routing or middleware removal.
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminUsers->value), 403);

        $data = $request->validate([
            ...PaginationMeta::$validationRules,
            'search' => 'nullable|string|max:255',
        ]);

        $users = User::query()
            ->when(
                $data['search'] ?? null,
                fn ($q, $s) => $q
                    ->where('email', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate($data['per_page'] ?? 100)
            ->withQueryString();

        return response()->json([
            'data' => $users->getCollection()->map(fn ($u) => $this->formatUser($u)),
            'meta' => PaginationMeta::from($users),
        ]);
    }

    /**
     * Update a user's role or disabled status.
     *
     * Super Admin role cannot be assigned via API, you must use the CLI.
     *
     * @bodyParam role Role The user role. Example: admin
     * @bodyParam disabled boolean Whether the user should be disabled.
     */
    #[Response(200, 'User updated successfully',
        type: 'array{
            data: array{
                id: int,
                name: string|null,
                email: string,
                role: Role,
                verified: bool,
                created_at: string
            }
        }'
    )]
    #[Response(403, 'Unauthorized or forbidden action',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'User not found',
        type: 'array{
            message: string
        }'
    )]
    #[Response(422, 'Validation error',
        type: 'array{
            message: string,
            errors: array<string, array<int, string>>
        }'
    )]
    public function update(Request $request, User $user, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminUsers->value), 403);

        // Cannot demote/modify other super admins or self
        if ($user->isSuperAdmin() || $user->id === $request->user()->id) {
            abort(403, 'Cannot modify a Super Admin or your own account via API.');
        }

        $data = $request->validate([
            'role' => ['sometimes', 'string', Rule::in([Role::User->value, Role::Admin->value])],
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
