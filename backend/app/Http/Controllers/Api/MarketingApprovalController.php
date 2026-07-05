<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingApproval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketingApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketingApproval::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        return response()->json($query->get());
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $approval = MarketingApproval::findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'error' => "Approval is already '{$approval->status}' and cannot be changed.",
            ], 422);
        }

        $approval->update(['status' => 'approved']);

        return response()->json(['success' => true, 'status' => 'approved']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $approval = MarketingApproval::findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'error' => "Approval is already '{$approval->status}' and cannot be changed.",
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $approval->update(['status' => 'rejected']);

        return response()->json(['success' => true, 'status' => 'rejected']);
    }
}
