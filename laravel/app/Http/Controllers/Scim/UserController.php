<?php

namespace App\Http\Controllers\Scim;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private const SCIM_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';
    private const SCIM_LIST_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    private const CONTENT_TYPE = 'application/scim+json';

    // ── Index ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        $filter = $request->query('filter', '');

        if ($filter !== '' && $filter !== null) {
            $userName = $this->parseUserNameFilter((string) $filter);

            if ($userName !== null) {
                $query->where('email', $userName);
            }
        }

        $users = $query->get();

        $resources = $users->map(fn (User $user) => $this->scimUser($user))->values();

        return response()->json([
            'schemas'      => [self::SCIM_LIST_SCHEMA],
            'totalResults' => $resources->count(),
            'startIndex'   => 1,
            'itemsPerPage' => $resources->count(),
            'Resources'    => $resources,
        ])->header('Content-Type', self::CONTENT_TYPE);
    }

    // ── Store ─────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        $userName = $data['userName'] ?? null;

        if (empty($userName)) {
            return $this->scimError('userName is required.', 400);
        }

        if (User::where('email', $userName)->exists()) {
            return $this->scimError('User already exists.', 409);
        }

        $name = $this->extractDisplayName($data);
        $externalId = $data['externalId'] ?? null;
        $active = isset($data['active']) ? (bool) $data['active'] : true;

        $user = User::create([
            'name'        => $name,
            'email'       => $userName,
            'password'    => Hash::make(Str::random(64)),
            'external_id' => $externalId,
            'is_active'   => $active,
        ]);

        return response()->json($this->scimUser($user), 201)
            ->header('Content-Type', self::CONTENT_TYPE)
            ->header('Location', url('/scim/v2/Users/'.$user->id));
    }

    // ── Show ──────────────────────────────────────────────────────────────────────

    public function show(User $user): JsonResponse
    {
        return response()->json($this->scimUser($user))
            ->header('Content-Type', self::CONTENT_TYPE);
    }

    // ── Update (PUT) ──────────────────────────────────────────────────────────────

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->json()->all();

        $userName = $data['userName'] ?? null;

        if (empty($userName)) {
            return $this->scimError('userName is required.', 400);
        }

        if ($userName !== $user->email && User::where('email', $userName)->where('id', '!=', $user->id)->exists()) {
            return $this->scimError('userName is already taken.', 409);
        }

        $name = $this->extractDisplayName($data);
        $externalId = $data['externalId'] ?? $user->external_id;
        $active = isset($data['active']) ? (bool) $data['active'] : $user->is_active;

        $user->update([
            'name'        => $name,
            'email'       => $userName,
            'external_id' => $externalId,
            'is_active'   => $active,
        ]);

        return response()->json($this->scimUser($user))
            ->header('Content-Type', self::CONTENT_TYPE);
    }

    // ── Patch ─────────────────────────────────────────────────────────────────────

    public function patch(Request $request, User $user): JsonResponse
    {
        $data = $request->json()->all();
        $operations = $data['Operations'] ?? [];

        if (empty($operations)) {
            return $this->scimError('Operations array is required.', 400);
        }

        foreach ($operations as $op) {
            $opType = strtolower($op['op'] ?? '');
            $path   = $op['path'] ?? null;
            $value  = $op['value'] ?? null;

            if ($opType === 'replace') {
                if ($path === 'active' || $path === 'Active') {
                    $user->is_active = (bool) $value;
                } elseif ($path === 'userName') {
                    if (User::where('email', $value)->where('id', '!=', $user->id)->exists()) {
                        return $this->scimError('userName is already taken.', 409);
                    }
                    $user->email = $value;
                } elseif ($path === null && is_array($value)) {
                    if (array_key_exists('active', $value)) {
                        $user->is_active = (bool) $value['active'];
                    }
                    if (isset($value['userName'])) {
                        if (User::where('email', $value['userName'])->where('id', '!=', $user->id)->exists()) {
                            return $this->scimError('userName is already taken.', 409);
                        }
                        $user->email = $value['userName'];
                    }
                    if (isset($value['externalId'])) {
                        $user->external_id = $value['externalId'];
                    }
                    if (isset($value['displayName'])) {
                        $user->name = $value['displayName'];
                    }
                }
            } elseif ($opType === 'add') {
                if ($path === null && is_array($value)) {
                    if (array_key_exists('active', $value)) {
                        $user->is_active = (bool) $value['active'];
                    }
                }
            }
        }

        $user->save();

        return response()->json($this->scimUser($user))
            ->header('Content-Type', self::CONTENT_TYPE);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────────

    public function destroy(User $user): \Illuminate\Http\Response
    {
        $user->delete();

        return response()->noContent();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function scimUser(User $user): array
    {
        $nameParts = explode(' ', $user->name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? '';

        return [
            'schemas'    => [self::SCIM_SCHEMA],
            'id'         => (string) $user->id,
            'externalId' => $user->external_id,
            'userName'   => $user->email,
            'displayName' => $user->name,
            'name'       => [
                'formatted'  => $user->name,
                'givenName'  => $firstName,
                'familyName' => $lastName,
            ],
            'emails' => [
                [
                    'value'   => $user->email,
                    'primary' => true,
                    'type'    => 'work',
                ],
            ],
            'active' => $user->is_active ?? true,
            'meta'   => [
                'resourceType' => 'User',
                'created'      => $user->created_at?->toIso8601String(),
                'lastModified' => $user->updated_at?->toIso8601String(),
                'location'     => url('/scim/v2/Users/'.$user->id),
            ],
        ];
    }

    private function scimError(string $detail, int $status): JsonResponse
    {
        return response()->json([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'detail'  => $detail,
            'status'  => $status,
        ], $status)->header('Content-Type', self::CONTENT_TYPE);
    }

    /**
     * Parse Azure AD filter: userName eq "user@domain.com"
     */
    private function parseUserNameFilter(string $filter): ?string
    {
        if (preg_match('/^userName\s+eq\s+"([^"]+)"/i', trim($filter), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractDisplayName(array $data): string
    {
        if (! empty($data['displayName'])) {
            return (string) $data['displayName'];
        }

        $given  = $data['name']['givenName'] ?? '';
        $family = $data['name']['familyName'] ?? '';
        $full   = trim($given.' '.$family);

        if ($full !== '') {
            return $full;
        }

        return $data['userName'] ?? 'Unknown';
    }
}
