<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        // Ambil user yang sedang login
        $user = $request->user();

        // Validasi input
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            // 1. Tambahkan validasi untuk password lama
            'old_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        // Perbarui nama jika ada di request
        if ($request->has('name')) {
            $user->name = $validatedData['name'];
        }

        // Perbarui password jika ada di request
        if ($request->has('password')) {
            // 2. Verifikasi password lama sebelum mengubahnya
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json(['message' => 'Password lama tidak sesuai.'], 422);
            }

            $user->password = Hash::make($validatedData['password']);
        }

        // Simpan perubahan
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui!',
            'user' => $user
        ]);
    }
}
