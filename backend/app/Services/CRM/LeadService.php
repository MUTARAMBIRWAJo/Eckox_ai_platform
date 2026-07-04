<?php

namespace App\Services\CRM;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadService
{
    /**
     * Get list of leads.
     */
    public function getLeads(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Lead::with('assignedToUser')->forUser($user);

        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->assignedTo((int)$filters['assigned_to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a lead.
     */
    public function createLead(User $user, array $data): Lead
    {
        if (empty($data['assigned_to'])) {
            $data['assigned_to'] = $user->id;
        }

        $lead = Lead::create($data);

        $this->logActivity($lead, $user, 'system', 'Lead created in CRM.');

        return $lead;
    }

    /**
     * Get a lead by ID with validation.
     */
    public function getLeadForUser(User $user, int $id): Lead
    {
        $lead = Lead::with(['assignedToUser', 'activities.user'])->findOrFail($id);

        if (!$this->canUserAccessLead($user, $lead)) {
            abort(403, 'Unauthorized access to this lead.');
        }

        return $lead;
    }

    /**
     * Update a lead.
     */
    public function updateLead(User $user, Lead $lead, array $data): Lead
    {
        if (!$this->canUserAccessLead($user, $lead)) {
            abort(403, 'Unauthorized access to this lead.');
        }

        if ($user->hasRole('sales-agent') && isset($data['assigned_to']) && $data['assigned_to'] != $user->id) {
            abort(403, 'Sales agents cannot reassign leads to other users.');
        }

        if (isset($data['status'])) {
            $currentStatus = $lead->status;
            $newStatus = $data['status'];

            if ($currentStatus !== $newStatus) {
                $allowed = false;
                if ($currentStatus === 'new' && $newStatus === 'contacted') {
                    $allowed = true;
                } elseif ($currentStatus === 'contacted' && in_array($newStatus, ['qualified', 'lost'])) {
                    $allowed = true;
                } elseif ($currentStatus === 'qualified' && $newStatus === 'lost') {
                    $allowed = true;
                }

                if (!$allowed) {
                    abort(422, "Invalid status transition from {$currentStatus} to {$newStatus}.");
                }

                $this->logActivity($lead, $user, 'system', "Lead status changed from {$currentStatus} to {$newStatus}.");
            }
        }

        $lead->update($data);

        $this->logActivity($lead, $user, 'note', 'Lead details updated.');

        return $lead;
    }

    /**
     * Delete a lead.
     */
    public function deleteLead(User $user, Lead $lead): void
    {
        if (!$this->canUserAccessLead($user, $lead)) {
            abort(403, 'Unauthorized access to this lead.');
        }

        if ($user->hasRole('sales-agent')) {
            abort(403, 'Sales agents cannot delete leads.');
        }

        if ($user->hasRole('manager')) {
            $assignedUser = $lead->assignedToUser;
            if ($assignedUser && ($assignedUser->hasRole('admin') || $assignedUser->hasRole('super-admin'))) {
                abort(403, 'Managers cannot delete leads assigned to Admins.');
            }
        }

        $lead->delete();
    }

    /**
     * Add activity to a lead.
     */
    public function addActivity(User $user, Lead $lead, string $type, string $description): LeadActivity
    {
        if (!$this->canUserAccessLead($user, $lead)) {
            abort(403, 'Unauthorized access to this lead.');
        }

        return $this->logActivity($lead, $user, $type, $description);
    }

    /**
     * Check if a user can access a lead.
     */
    public function canUserAccessLead(User $user, Lead $lead): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('super-admin')) {
            return true;
        }

        return $lead->assigned_to === $user->id;
    }

    /**
     * Log activity helper.
     */
    private function logActivity(Lead $lead, User $user, string $type, string $description): LeadActivity
    {
        return LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
