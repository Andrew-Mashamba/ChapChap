<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $member = $request->user();
        return response()->json(['data' => $member]);
    }

    public function update(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:300',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('members')->ignore($member->id)],
            'gender' => 'sometimes|string|max:20',
            'date_of_birth' => 'sometimes|date',
            'country' => 'sometimes|string|max:100',
            'region' => 'sometimes|string|max:100',
            'district' => 'sometimes|string|max:100',
            'ward' => 'sometimes|string|max:100',
            'street' => 'sometimes|string',
            'postal_code' => 'sometimes|string|max:20',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'shop_name' => 'sometimes|string|max:255',
            'shop_description' => 'sometimes|string',
            'bank_name' => 'sometimes|string|max:100',
            'bank_account_name' => 'sometimes|string|max:255',
            'bank_account_number' => 'sometimes|string|max:100',
            'mobile_money_number' => 'sometimes|string|max:20',
        ]);

        // Handle photo upload if present
        if ($request->hasFile('photo')) {
            if ($member->photo_path) {
                Storage::delete($member->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('member-photos', 'public');
        }

        // Handle ID document upload if present
        if ($request->hasFile('id_document')) {
            if ($member->id_document_path) {
                Storage::delete($member->id_document_path);
            }
            $validated['id_document_path'] = $request->file('id_document')->store('member-documents', 'public');
        }

        $member->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $member
        ]);
    }

    public function destroy(Request $request)
    {
        $member = $request->user();

        // Delete associated files
        if ($member->photo_path) {
            Storage::delete($member->photo_path);
        }
        if ($member->id_document_path) {
            Storage::delete($member->id_document_path);
        }
        if ($member->shop_logo) {
            Storage::delete($member->shop_logo);
        }

        // Delete the member
        $member->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
