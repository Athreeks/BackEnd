<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Fungsi untuk Registrasi User Baru
     * 💖 KODE INI SUDAH DIPERBARUI
     */
    public function register(Request $request)
    {
        // 1. Validasi disesuaikan dengan form (termasuk 'telepon')
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'telepon' => 'required|string|max:20|unique:users', // 👈 Diubah dari phone_number
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'telepon' => $validatedData['telepon'], // 👈 Ditambahkan
            'password' => Hash::make($validatedData['password']),
            // Role akan otomatis 'customer' (sesuai migrasi)
        ]);

        // 2. Kembalikan respons 201 (Created)
        return response()->json([
            'message' => 'Registrasi berhasil!',
            'user' => $user,
        ], 201);
    }

    /**
     * Fungsi untuk Login (Tetap sama, menggunakan username)
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Username atau password salah'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Fungsi untuk Logout (Tetap sama)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil!']);
    }
}
