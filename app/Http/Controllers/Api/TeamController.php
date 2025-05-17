<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function members(Request $request)
    {
        $member = $request->user();

        // Get direct downlines
        $downlines = Member::where('upline_id', $member->seller_id)
            ->select([
                'id',
                'first_name',
                'last_name',
                'phone_number',
                'seller_level',
                'total_sales_volume',
                'total_downlines',
                'created_at'
            ])
            ->get();

        return response()->json(['data' => $downlines]);
    }

    public function performance(Request $request)
    {
        $member = $request->user();

        // Get team performance metrics
        $teamStats = [
            'total_members' => Member::where('upline_id', $member->seller_id)->count(),
            'total_sales' => Member::where('upline_id', $member->seller_id)
                ->sum('total_sales_volume'),
            'total_commission' => Member::where('upline_id', $member->seller_id)
                ->sum('commission_balance'),
            'active_members' => Member::where('upline_id', $member->seller_id)
                ->where('account_status', 'active')
                ->count(),
            'new_members_this_month' => Member::where('upline_id', $member->seller_id)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        return response()->json(['data' => $teamStats]);
    }
}
