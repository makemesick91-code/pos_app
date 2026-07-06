<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Issues a Sanctum token. Rejects inactive users and tenant users whose
     * tenant is not active (suspended/inactive).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with(['tenant', 'store'])
            ->where('email', $request->input('email'))
            ->first();

        if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        // Tenant users cannot log in while their tenant is not active.
        if (! $user->isSaasAdmin()) {
            if ($user->tenant === null || $user->tenant->status !== Tenant::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'email' => ['This tenant is not active.'],
                ]);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user->tenant),
            'store' => $this->storePayload($user->store),
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['tenant', 'store']);

        return response()->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user->tenant),
            'store' => $this->storePayload($user->store),
            'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'store_id' => $user->store_id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tenantPayload(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function storePayload(?Store $store): ?array
    {
        if ($store === null) {
            return null;
        }

        return [
            'id' => $store->id,
            'name' => $store->name,
            'code' => $store->code,
        ];
    }
}
