"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { DataTable, Column } from "@/components/common/data-table";
import { DataTableSkeleton } from "@/components/common/skeleton-loader";
import { EmptyList } from "@/components/common/empty-state";
import { KanbanBoard } from "@/components/crm/kanban-board";
import { LeadDetailPanel } from "@/components/crm/lead-detail-panel";
import { CreateLeadDialog } from "@/components/crm/create-lead-dialog";
import { ViewToggle } from "@/components/crm/view-toggle";
import { CRMAPI, Lead } from "@/lib/api/crm.api";
import { useAuth } from "@/hooks/use-auth";
import { Plus, Search, Trash2, AlertTriangle, Loader2 } from "lucide-react";

type LeadStatus = Lead["status"];

const STATUS_COLORS: Record<LeadStatus, string> = {
  "new": "bg-blue-500/10 text-blue-400",
  "contacted": "bg-purple-500/10 text-purple-400",
  "qualified": "bg-yellow-500/10 text-yellow-400",
  "lost": "bg-destructive/10 text-destructive",
};

export default function LeadsPage() {
  const { user, logout } = useAuth();
  const [leads, setLeads] = useState<Lead[]>([]);
  const [filteredLeads, setFilteredLeads] = useState<Lead[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState<"table" | "kanban">("table");
  const [selectedLead, setSelectedLead] = useState<Lead | null>(null);
  const [detailPanelOpen, setDetailPanelOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [createOpen, setCreateOpen] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const loadLeads = async () => {
    setLoading(true);
    try {
      const response = await CRMAPI.getLeads();
      if (response.success && response.data) {
        const list = Array.isArray(response.data) ? response.data : response.data.leads ?? [];
        setLeads(list);
        setFilteredLeads(list);
      } else {
        setLeads([]);
        setFilteredLeads([]);
      }
    } catch (err) {
      console.error("Failed to load leads from API:", err);
      setLeads([]);
      setFilteredLeads([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadLeads(); }, []);

  useEffect(() => {
    const results = leads.filter((lead) => {
      const matchesSearch =
        lead.name.toLowerCase().includes(search.toLowerCase()) ||
        (lead.email ?? "").toLowerCase().includes(search.toLowerCase());
      const matchesStatus = statusFilter === "all" || lead.status?.toLowerCase() === statusFilter.toLowerCase();
      return matchesSearch && matchesStatus;
    });
    setFilteredLeads(results);
  }, [search, leads, statusFilter]);

  const handleSelectLead = (lead: Lead) => {
    setSelectedLead(lead);
    setDetailPanelOpen(true);
  };

  const handleUpdateLeadStatus = async (leadId: string, status: string) => {
    const previousLeads = [...leads];
    setLeads((prev) => prev.map((l) => l.id === leadId ? { ...l, status: status as LeadStatus } : l));
    try {
      const response = await CRMAPI.updateLeadStatus(leadId, status as LeadStatus);
      if (!response.success) setLeads(previousLeads);
    } catch (err) {
      console.error("Failed to update status:", err);
      setLeads(previousLeads);
    }
  };

  const handleDeleteLead = async () => {
    if (!deletingId) return;
    setIsDeleting(true);
    try {
      const res = await CRMAPI.deleteLead(deletingId);
      if (res.success) {
        setLeads((prev) => prev.filter((l) => l.id !== deletingId));
      }
    } catch (err) {
      console.error("Delete failed:", err);
    } finally {
      setIsDeleting(false);
      setDeletingId(null);
    }
  };

  const handleUpdateLead = async (leadId: string, updatedFields: Partial<Lead>) => {
    const previousLeads = [...leads];
    setLeads((prev) => prev.map((l) => l.id === leadId ? { ...l, ...updatedFields } : l));
    if (selectedLead?.id === leadId) setSelectedLead((prev) => prev ? { ...prev, ...updatedFields } : null);
    try {
      const response = await CRMAPI.updateLead(leadId, updatedFields);
      if (!response.success) {
        setLeads(previousLeads);
        if (selectedLead?.id === leadId) setSelectedLead(previousLeads.find((l) => l.id === leadId) || null);
      }
    } catch (err) {
      console.error("Failed to update lead:", err);
      setLeads(previousLeads);
    }
  };

  const columns: Column<Lead>[] = [
    {
      key: "name",
      label: "Contact",
      sortable: true,
      render: (value, row) => (
        <div onClick={() => handleSelectLead(row)} className="cursor-pointer hover:text-primary">
          <div className="font-medium">{value}</div>
          <div className="text-xs text-muted-foreground">{row.email}</div>
        </div>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (value: string) => (
        <Badge className={STATUS_COLORS[value] ?? "bg-secondary/30 text-foreground"}>{value}</Badge>
      ),
    },
    {
      key: "phone",
      label: "Phone",
      render: (value: string) => value ? <span className="text-sm">{value}</span> : <span className="text-muted-foreground text-xs">—</span>,
    },
    {
      key: "id",
      label: "",
      render: (_value, row) => (
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7 text-destructive hover:text-destructive opacity-60 hover:opacity-100"
          onClick={(e) => { e.stopPropagation(); setDeletingId(row.id); }}
        >
          <Trash2 className="w-3.5 h-3.5" />
        </Button>
      ),
    },
  ];

  if (loading) return (
    <AppLayout headerProps={{
      user: user ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email } : undefined,
      onLogout: () => logout()
    }}>
      <DataTableSkeleton rows={5} />
    </AppLayout>
  );

  return (
    <AppLayout
      headerProps={{
        user: user ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email } : undefined,
        onLogout: () => logout()
      }}
    >
      <PageTransition>
        <div className="space-y-6">
          {/* Page Header */}
          <div className="page-header">
            <div>
              <h1 className="page-title">CRM Leads</h1>
              <p className="text-muted-foreground text-sm mt-1">
                Manage and track sales leads across your pipeline
              </p>
            </div>
            <Button className="btn-primary" onClick={() => setCreateOpen(true)}>
              <Plus className="h-4 w-4 mr-2" />
              New Lead
            </Button>
          </div>

          {/* Controls */}
          <div className="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div className="flex-1 relative max-w-md">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search leads..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-10"
              />
            </div>
            <div className="flex gap-2 items-center flex-wrap">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-1 text-foreground"
              >
                <option value="all">All Statuses</option>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="qualified">Qualified</option>
                <option value="lost">Lost</option>
              </select>
              <ViewToggle view={view} onViewChange={setView} />
            </div>
          </div>

          {/* Content */}
          {filteredLeads.length === 0 ? (
            <EmptyList title="No leads found" />
          ) : view === "table" ? (
            <DataTable
              data={filteredLeads}
              columns={columns}
              striped
              hoverable
            />
          ) : (
            <KanbanBoard
              leads={filteredLeads}
              onSelectLead={handleSelectLead}
              onUpdateLeadStatus={handleUpdateLeadStatus}
            />
          )}
        </div>

        {/* Lead Detail Panel */}
        <LeadDetailPanel
          lead={selectedLead}
          open={detailPanelOpen}
          onOpenChange={setDetailPanelOpen}
          onUpdateLead={handleUpdateLead}
        />
      </PageTransition>

      {/* Create Lead Dialog */}
      <CreateLeadDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        onCreated={(newLead) => setLeads((prev) => [newLead, ...prev])}
      />

      {/* Delete Confirm Dialog */}
      <Dialog open={deletingId !== null} onOpenChange={(o) => { if (!o) setDeletingId(null); }}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-destructive">
              <AlertTriangle className="w-5 h-5" /> Delete Lead?
            </DialogTitle>
            <DialogDescription>
              This will permanently remove the lead and all associated data. This cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => setDeletingId(null)} disabled={isDeleting}>Cancel</Button>
            <Button variant="destructive" onClick={handleDeleteLead} disabled={isDeleting}>
              {isDeleting ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Trash2 className="w-4 h-4 mr-2" />}
              Delete
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
