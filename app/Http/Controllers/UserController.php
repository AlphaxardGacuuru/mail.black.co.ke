<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Services\UserService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(protected UserService $service)
    {
        //
    }

    // Password reset is now handled via update()

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = $this->service->index($request);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): UserResource
    {
        [$status, $message, $user] = $this->service->store($request);

        return (new UserResource($user))->additional([
            'status' => $status,
            'message' => $message,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int|string $id): UserResource
    {
        abort_unless(
            $request->user()->id === $id || $request->user()->email === config('admin.email'),
            403
        );

        $user = $this->service->show($id);

        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int|string $id): Response
    {
        [$saved, $message, $user] = $this->service->update($request, $id);

        return response([
            'status' => $saved,
            'message' => $message,
            'data' => $user,
        ], 200);
    }

    /**
     * Give or take away a user's verified badge (admin only).
     */
    public function verify(Request $request, int|string $id): Response
    {
        $request->validate(['verified' => ['required', 'boolean']]);

        [$saved, $message, $user] = $this->service->verify($id, $request->boolean('verified'));

        return response([
            'status' => $saved,
            'message' => $message,
            'data' => new UserResource($user),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message] = $this->service->destroy($id);

        return response([
            'status' => $deleted,
            'message' => $message,
        ], 200);
    }

    /*
     * Get the Auth User
     */
    public function auth(): UserResource|Response
    {
        return $this->service->auth();
    }
}
