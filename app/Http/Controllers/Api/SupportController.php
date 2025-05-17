<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function feedback(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:10',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $feedback = Feedback::create([
            'member_id' => $request->user()->id,
            'message' => $validated['message'],
            'rating' => $validated['rating'],
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'data' => $feedback
        ]);
    }
}
