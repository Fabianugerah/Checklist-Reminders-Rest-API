<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;

class UserController extends Controller
{
    // REGISTER
    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Users"},
     *     summary="Register user baru",
     *     description="Digunakan untuk registrasi user atau admin (admin membutuhkan secret_code).",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role"},
     *             @OA\Property(property="name", type="string", example="Fabianugerah"),
     *             @OA\Property(property="email", type="string", example="fabian@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"admin", "user"}, example="user"),
     *             @OA\Property(property="secret_code", type="string", example="my_super_secret_admin_code")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registrasi berhasil"),
     *     @OA\Response(response=403, description="Secret code salah"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        // Secret code untuk admin
        if ($validated['role'] === 'admin') {
            if ($request->secret_code !== env('ADMIN_SECRET_CODE')) {
                return response()->json(['message' => 'Invalid secret code for admin'], 403);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }

    // LOGIN
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Users"},
     *     summary="Login user",
     *     description="User login dan mendapatkan token JWT yang disimpan di database.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", example="fabian@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login berhasil dan token dikembalikan"),
     *     @OA\Response(response=401, description="Kredensial salah")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        // Simpan token ke database (untuk studi kasus)
        $user->token = $token;
        $loggedUser = User::find($user->id);
        if ($loggedUser) {
            $loggedUser->token = $token;
            $loggedUser->save();
        }

        return response()->json([
            'message' => 'Login success',
            'user' => $user
        ]);
    }


    // ME (Get User Info)
    /**
     * @OA\Get(
     *     path="/api/user",
     *     tags={"Users"},
     *     summary="Ambil data user berdasarkan token JWT",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Berhasil ambil data user"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function user()
    {
        return response()->json(auth()->user());
    }

    // LOGOUT
    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Users"},
     *     summary="Logout user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout berhasil"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            // Hapus token dari kolom users.token
            $user = User::find(auth()->id());
            if ($user) {
                $user->token = null;
                $user->save();
            }

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to logout', 'error' => $e->getMessage()], 500);
        }
    }
}
