<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadActivityRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Services\CRM\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    protected LeadService $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['admin', 'manager', 'sales-agent', 'super-admin'])) {
            return response()->json(['message' => 'Unauthorized role access.'], 403);
        }

        $leads = $this->leadService->getLeads($user, $request->only(['status', 'assigned_to', 'per_page']));

        return response()->json($leads);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['admin', 'manager', 'sales-agent', 'super-admin'])) {
            return response()->json(['message' => 'Unauthorized role access.'], 403);
        }

        $lead = $this->leadService->createLead($user, $request->validated());

        \Illuminate\Support\Facades\Log::info('CRM Lead created successfully', [
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'status' => $lead->status,
        ]);

        return response()->json([
            'message' => 'Lead created successfully',
            'lead' => $lead,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lead = $this->leadService->getLeadForUser($user, $id);

        return response()->json($lead);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lead = $this->leadService->getLeadForUser($user, $id);

        $updatedLead = $this->leadService->updateLead($user, $lead, $request->validated());

        \Illuminate\Support\Facades\Log::info('CRM Lead updated successfully', [
            'user_id' => $user->id,
            'lead_id' => $updatedLead->id,
            'status' => $updatedLead->status,
        ]);

        return response()->json([
            'message' => 'Lead updated successfully',
            'lead' => $updatedLead,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lead = $this->leadService->getLeadForUser($user, $id);

        $this->leadService->deleteLead($user, $lead);

        \Illuminate\Support\Facades\Log::info('CRM Lead deleted successfully', [
            'user_id' => $user->id,
            'lead_id' => $id,
        ]);

        return response()->json([
            'message' => 'Lead deleted successfully',
        ]);
    }

    /**
     * Log an activity for a lead.
     */
    public function logActivity(StoreLeadActivityRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lead = $this->leadService->getLeadForUser($user, $id);

        $activity = $this->leadService->addActivity(
            $user,
            $lead,
            $request->validated()['type'],
            $request->validated()['description']
        );

        \Illuminate\Support\Facades\Log::info('CRM Lead activity recorded successfully', [
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'activity_id' => $activity->id,
            'activity_type' => $activity->type,
        ]);

        return response()->json([
            'message' => 'Lead activity recorded successfully',
            'activity' => $activity,
        ], 201);
    }
}
