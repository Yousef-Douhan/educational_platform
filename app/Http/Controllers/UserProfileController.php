<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class UserProfileController extends Controller
{
    private const SORTABLE = ['id', 'created_at', 'updated_at', 'phone'];

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $profiles = UserProfile::query()
            ->with('user:id,name,email')
            ->search($request->get('search'))
            ->filterByUser($request->get('user_id'))
            ->createdBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->paginate((int) $request->get('per_page', 10))
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.user-profiles.partials.table', compact('profiles'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.user-profiles.index', [
            'profiles' => $profiles,
            'stats'    => $this->stats(),
            'users'    => User::select('id', 'name', 'email')->orderBy('name')->get(),
        ]);
    }

    public function create(): JsonResponse
    {
        return response()->json([
            'users' => $this->availableUsers(),
        ]);
    }

    public function store(StoreUserProfileRequest $request): JsonResponse
    {
        try {
            $profile = DB::transaction(function () use ($request) {
                $data = $request->safe()->except('avatar');

                if ($request->hasFile('avatar')) {
                    $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
                }

                return UserProfile::create($data);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('user-profiles.messages.error')], 500);
        }

        return response()->json([
            'message' => __('user-profiles.messages.created'),
            'data'    => $profile->load('user:id,name,email'),
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(UserProfile $userProfile): JsonResponse
    {
        return response()->json([
            'data' => $userProfile->load('user:id,name,email'),
        ]);
    }

    public function edit(UserProfile $userProfile): JsonResponse
    {
        return response()->json([
            'data'  => $userProfile,
            'users' => User::select('id', 'name', 'email')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserProfileRequest $request, UserProfile $userProfile): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $userProfile) {
                $data = $request->safe()->except(['avatar', 'remove_avatar']);

                if ($request->hasFile('avatar')) {
                    $this->deleteAvatar($userProfile->avatar);
                    $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
                } elseif ($request->boolean('remove_avatar')) {
                    $this->deleteAvatar($userProfile->avatar);
                    $data['avatar'] = null;
                }

                $userProfile->update($data);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('user-profiles.messages.error')], 500);
        }

        return response()->json([
            'message' => __('user-profiles.messages.updated'),
            'data'    => $userProfile->fresh('user:id,name,email'),
            'stats'   => $this->stats(),
        ]);
    }

    /** حذف مؤقت (Soft Delete) */
    public function destroy(UserProfile $userProfile): JsonResponse
    {
        $userProfile->delete();

        return response()->json([
            'message' => __('user-profiles.messages.deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    /** قائمة المحذوفات المؤقتة */
    public function trashed(Request $request): JsonResponse
    {
        $profiles = UserProfile::onlyTrashed()
            ->with('user:id,name,email')
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate((int) $request->get('per_page', 10))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.user-profiles.partials.trash-table', compact('profiles'))->render(),
            'stats' => $this->stats(),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $profile = UserProfile::onlyTrashed()->findOrFail($id);
        $profile->restore();

        return response()->json([
            'message' => __('user-profiles.messages.restored'),
            'stats'   => $this->stats(),
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $profile = UserProfile::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($profile) {
            $this->deleteAvatar($profile->avatar);
            $profile->forceDelete();
        });

        return response()->json([
            'message' => __('user-profiles.messages.force_deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        $row = UserProfile::withTrashed()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(deleted_at IS NULL) as active')
            ->selectRaw('SUM(deleted_at IS NOT NULL) as trashed')
            ->selectRaw('SUM(deleted_at IS NULL AND avatar IS NOT NULL) as with_avatar')
            ->first();

        return [
            'total'       => (int) $row->total,
            'active'      => (int) $row->active,
            'trashed'     => (int) $row->trashed,
            'with_avatar' => (int) $row->with_avatar,
        ];
    }

    /** المستخدمون الذين ليس لديهم ملف شخصي (بما فيه المحذوف مؤقتًا) */
    private function availableUsers()
    {
        return User::select('id', 'name', 'email')
            ->whereDoesntHave('profile', fn($q) => $q->withTrashed())
            ->orderBy('name')
            ->get();
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
