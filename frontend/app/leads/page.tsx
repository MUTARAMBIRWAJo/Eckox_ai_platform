"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { DataTable, Column } from "@/components/common/data-table";
import { DataTableSkeleton } from "@/components/common/skeleton-loader";
import { EmptyList } from "@/components/common/empty-state";
import { KanbanBoard } from "@/components/crm/kanban-board";
import { LeadDetailPanel } from "@/components/crm/lead-detail-panel";
import { ViewToggle } from "@/components/crm/view-toggle";
import { CRMAPI } from "@/lib/api/crm.api";
import { useAuth } from "@/hooks/use-auth";
import { LEADS, Lead, LeadStatus } from "@/lib/data/leads";
import { Plus, Search, Filter } from "lucide-react";

const STATUS_COLORS: Record<LeadStatus, string> = {
  "new": "bg-blue-500/10 text-blue-400",
  "contacted": "bg-purple-500/10 text-purple-400",
  "qualified": "bg-yellow-500/10 text-yellow-400",
  "proposal": "bg-cyan-500/10 text-cyan-400",
  "negotiation": "bg-orange-500/10 text-orange-400",
  "closed-won": "bg-primary/10 text-primary",
  "closed-lost": "bg-destructive/10 text-destructive",
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
  const [countryFilter, setCountryFilter] = useState<string>("all");

  useEffect(() => {
    const loadData = async () => {
      try {
        const response = await CRMAPI.getLeads();
        if (response.success && response.data && Array.isArray(response.data.leads)) {
          setLeads(response.data.leads);
          setFilteredLeads(response.data.leads);
        } else if (process.env.NODE_ENV !== "production") {
          setLeads(LEADS);
          setFilteredLeads(LEADS);
        } else {
          setLeads([]);
          setFilteredLeads([]);
        }
      } catch (err) {
        console.error("Failed to load leads from API:", err);
        if (process.env.NODE_ENV !== "production") {
          setLeads(LEADS);
          setFilteredLeads(LEADS);
        } else {
          setLeads([]);
          setFilteredLeads([]);
        }
      } finally {
        setLoading(false);
      }
    };
    loadData();
  }, []);

  useEffect(() => {
    const results = leads.filter((lead) => {
      const matchesSearch =
        lead.name.toLowerCase().includes(search.toLowerCase()) ||
        lead.company.toLowerCase().includes(search.toLowerCase()) ||
        lead.email.toLowerCase().includes(search.toLowerCase());

      const matchesStatus = statusFilter === "all" || lead.status?.toLowerCase() === statusFilter.toLowerCase();
      const matchesCountry = countryFilter === "all" || lead.country === countryFilter;

      return matchesSearch && matchesStatus && matchesCountry;
    });
    setFilteredLeads(results);
  }, [search, leads, statusFilter, countryFilter]);

  const handleSelectLead = (lead: Lead) => {
    setSelectedLead(lead);
    setDetailPanelOpen(true);
  };

  const handleUpdateLeadStatus = async (leadId: string, status: string) => {
    const previousLeads = [...leads];
    setLeads((prev) =>
      prev.map((lead) =>
        lead.id === leadId
          ? { ...lead, status: status as LeadStatus }
          : lead
      )
    );

    try {
      const response = await CRMAPI.updateLeadStatus(leadId, status as any);
      if (!response.success) {
        setLeads(previousLeads);
      }
    } catch (err) {
      console.error("Failed to update status:", err);
      setLeads(previousLeads);
    }
  };

  const handleUpdateLead = async (leadId: string, updatedFields: Partial<Lead>) => {
    const previousLeads = [...leads];
    setLeads((prev) =>
      prev.map((lead) =>
        lead.id === leadId ? { ...lead, ...updatedFields } : lead
      )
    );
    if (selectedLead && selectedLead.id === leadId) {
      setSelectedLead((prev) => (prev ? { ...prev, ...updatedFields } : null));
    }

    try {
      const response = await CRMAPI.updateLead(leadId, updatedFields as any);
      if (!response.success) {
        setLeads(previousLeads);
        if (selectedLead && selectedLead.id === leadId) {
          setSelectedLead(previousLeads.find((l) => l.id === leadId) || null);
        }
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
        <div
          onClick={() => handleSelectLead(row)}
          className="cursor-pointer hover:text-primary"
        >
          <div className="font-medium">{value}</div>
          <div className="text-xs text-muted-foreground">{row.email}</div>
        </div>
      ),
    },
    {
      key: "company",
      label: "Company",
      sortable: true,
    },
    {
      key: "status",
      label: "Status",
      render: (value: LeadStatus) => (
        <Badge className={`${STATUS_COLORS[value]}`}>
          {value}
        </Badge>
      ),
    },
    {
      key: "country",
      label: "Country",
      sortable: true,
    },
    {
      key: "score",
      label: "Score",
      render: (value: number) => (
        <div className="flex items-center gap-2">
          <div className="h-2 flex-1 bg-secondary rounded-full overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-primary to-accent"
              style={{ width: `${value}%` }}
            />
          </div>
          <span className="text-sm font-medium">{value}%</span>
        </div>
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
            <Button className="btn-primary">
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
                <option value="proposal">Proposal</option>
                <option value="won">Won</option>
                <option value="lost">Lost</option>
              </select>
              <select
                value={countryFilter}
                onChange={(e) => setCountryFilter(e.target.value)}
                className="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-1 text-foreground"
              >
                <option value="all">All Countries</option>
                {Array.from(new Set(leads.map((l) => l.country))).filter(Boolean).map((country) => (
                  <option key={country} value={country}>{country}</option>
                ))}
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
    </AppLayout>
  );
}
