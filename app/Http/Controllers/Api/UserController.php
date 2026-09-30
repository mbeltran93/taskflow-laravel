<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * List every user.
     */
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->orderBy('id')->paginate(15)
        );
    }

    /**
     * Show a single user.
     */
    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }
}
