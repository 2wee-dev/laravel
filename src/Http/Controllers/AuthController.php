<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use TwoWee\Laravel\Auth\TokenGuard;

class AuthController extends Controller
{
    public function entryPoint(Request $request): JsonResponse
    {
        $menuUrl = \TwoWee\Laravel\TwoWee::baseUrl() . '/menu/main';

        if (! config('twowee.auth.enabled', true)) {
            return response()->json([
                'redirect' => $menuUrl,
            ]);
        }

        if (Auth::guard('twowee')->check()) {
            return response()->json([
                'redirect' => $menuUrl,
            ]);
        }

        return $this->loginForm();
    }

    public function loginForm(): JsonResponse
    {
        $usernameField = config('twowee.auth.username_field', 'email');
        $authAction = \TwoWee\Laravel\TwoWee::baseUrl() . '/auth/login';

        return response()->json([
            'layout' => 'Card',
            'screen_id' => '',
            'title' => 'Login',
            'sections' => [
                [
                    'id' => 'credentials',
                    'label' => 'Credentials',
                    'column' => 0,
                    'row_group' => 0,
                    'fields' => [
                        [
                            'id' => $usernameField,
                            'label' => $usernameField === 'email' ? 'E-Mail' : 'Username',
                            'type' => $usernameField === 'email' ? 'Email' : 'Text',
                            'value' => '',
                            'editable' => true,
                        ],
                        [
                            'id' => 'password',
                            'label' => 'Password',
                            'type' => 'Password',
                            'value' => '',
                            'editable' => true,
                        ],
                    ],
                ],
            ],
            'auth_action' => $authAction,
        ]);
    }

    public function loginSubmit(Request $request): JsonResponse
    {
        $usernameField = config('twowee.auth.username_field', 'email');

        $username = $request->input('fields.' . $usernameField, '');
        $password = $request->input('fields.password', '');

        $provider = config('auth.providers.users.model');
        $user = $provider::where($usernameField, $username)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'token' => null,
                'error' => 'Invalid credentials.',
                'screen' => null,
            ], 401);
        }

        $token = TokenGuard::createToken($user);

        // Build the menu screen to include in the auth response
        $menuScreen = app(\TwoWee\Laravel\Http\Controllers\MenuController::class)->buildMenuData($user);

        return response()->json([
            'success' => true,
            'token' => $token,
            'error' => null,
            'screen' => $menuScreen,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::guard('twowee')->user();

        if ($user !== null) {
            TokenGuard::revokeTokens($user);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
