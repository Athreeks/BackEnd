<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Menampilkan data user yang sedang login.
     */
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Memperbarui data user (nama, email, telepon, dan foto).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // 2. Validasi (hapus 'alamat')
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'telepon' => 'nullable|string|max:20',
            // 'alamat' => 'nullable|string', 👈 DIHAPUS DARI SINI
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // 3. Handle file upload (tetap sama)
        if ($request->hasFile('image')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $path = $request->file('image')->store('profile_photos', 'public');
            $user->profile_photo_path = $path;
        }

        // 4. Update data user (hapus 'alamat')
        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->telepon = $validatedData['telepon'];
        // $user->alamat = $validatedData['alamat']; 👈 DIHAPUS DARI SINI

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui!',
            'user' => $user
        ]);
    }
}
