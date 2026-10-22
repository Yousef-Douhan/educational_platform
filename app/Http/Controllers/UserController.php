<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            $query = User::query();

            if ($search = $request->input('search')) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $users = $query->latest()->paginate($request->input('per_page', 10));
            return response()->json($users);
        }

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'trashed' => User::onlyTrashed()->count(),
        ];

        return view('cms.users.index', compact('stats'));
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => __('users.created_successfully'),
            'data' => $user
        ]);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function edit(User $user)
    {
        return response()->json($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => __('users.updated_successfully'),
            'data' => $user
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('users.deleted_successfully')
        ]);
    }

    public function trashed(Request $request)
    {
        if ($request->expectsJson()) {
            $users = User::onlyTrashed()->latest()->paginate(10);
            return response()->json($users);
        }

        return view('cms.users.softdelete');
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'success' => true,
            'message' => __('users.restored_successfully')
        ]);
    }

    public function forceDelete($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => __('users.force_deleted_successfully')
        ]);
    }
}
