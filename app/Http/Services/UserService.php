<?php

namespace App\Http\Services;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserService extends Service
{
	public function __construct(protected AccountDeletionService $accountDeletion)
	{
		parent::__construct();
	}

	public function index(Request $request): LengthAwarePaginator|Collection
	{
		if ($request->filled('idAndName')) {
			return User::query()
				->select('id', 'name')
				->orderBy('id', 'DESC')
				->get();
		}

		$query = $this->search(User::query(), $request);

		return $query
			->orderBy('id', 'DESC')
			->paginate($request->integer('per_page', 15));
	}

	public function show(int|string $id): User
	{
		return User::query()->findOrFail($id);
	}

	public function store(Request $request): array
	{
		$user = User::query()->create([
			'name' => $request->string('name')->toString(),
			'email' => $request->string('email')->toString(),
			'password' => Hash::make($request->string('password')->toString()),
			'phone' => $request->input('phone'),
			'settings' => $request->input('settings'),
		]);

		if ($request->filled('userRoles')) {
			$user->syncRoles($request->input('userRoles'));
		}

		return [true, 'Account Created', $user->fresh()];
	}

	public function update(Request $request, int|string $id): array
	{
		$user = User::query()->findOrFail($id);

		if ($request->filled('name')) {
			$user->name = $request->string('name')->toString();
		}

		if ($request->filled('email')) {
			$user->email = $request->string('email')->toString();
		}

		if ($request->filled('phone')) {
			$user->phone = $request->input('phone');
		}

		if ($request->filled('password')) {
			$user->password = Hash::make($request->string('password')->toString());
		}

		if ($request->exists('settings')) {
			$user->settings = $request->input('settings');
		}

		$saved = $user->save();

		// Users can update their own account, but only the admin sets roles.
		if ($request->filled('userRoles') && $request->user()?->email === config('admin.email')) {
			$user->syncRoles($request->input('userRoles'));
		}

		return [$saved, 'Account Updated', $user->fresh()];
	}

	public function verify(int|string $id, bool $verified): array
	{
		$user = User::query()->findOrFail($id);
		$user->verified = $verified;
		$saved = $user->save();

		return [$saved, $verified ? 'User Verified' : 'Verification Removed', $user->fresh()];
	}

	public function destroy(int|string $id): array
	{
		$user = User::query()->findOrFail($id);

		if ($user->id === $this->id) {
			throw ValidationException::withMessages(['user' => ['Delete your own account from your profile settings.']]);
		}

		$this->accountDeletion->delete($user);

		return [true, 'Account Deleted'];
	}

	public function auth(): UserResource|Response
	{
		if (! auth('sanctum')->check()) {
			return response(['message' => 'Not Authenticated'], 401);
		}

		return new UserResource(auth('sanctum')->user());
	}

	protected function search($query, Request $request)
	{
		if ($request->filled('roleId')) {
			$roleName = Role::query()->findOrFail($request->input('roleId'))->name;
			$query = $query->role($roleName);
		}

		if ($request->filled('name')) {
			$query = $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
		}

		return $query;
	}
}
