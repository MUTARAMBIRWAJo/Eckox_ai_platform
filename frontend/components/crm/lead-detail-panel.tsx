"use client";

import { useEffect, useState } from "react";
import { Lead, CRMAPI } from "@/lib/api/crm.api";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Mail, Phone, MessageSquare, FileText, TrendingUp, Edit2, Check, X,
  Clock, Plus, Loader2, AlertCircle, ClipboardList
} from "lucide-react";
import { motion } from "framer-motion";

interface LeadDetailPanelProps {
  lead: Lead | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUpdateLead?: (leadId: string, data: Partial<Lead>) => Promise<void>;
}

export function LeadDetailPanel({
  lead,
  open,
  onOpenChange,
  onUpdateLead,
}: LeadDetailPanelProps) {
  const [fullLead, setFullLead] = useState<Lead | null>(null);
  const [fetching, setFetching] = useState(false);

  // Tab state
  const [activeTab, setActiveTab] = useState<"details" | "activities">("details");

  // Edit state
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [loading, setLoading] = useState(false);

  // Log activity state
  const [actType, setActType] = useState<"call" | "email" | "meeting" | "note">("note");
  const [actDesc, setActDesc] = useState("");
  const [logging, setLogging] = useState(false);
  const [logError, setLogError] = useState("");

  const fetchFullDetails = async (id: string) => {
    setFetching(true);
    try {
      const res = await CRMAPI.getLead(id);
      if (res.success && res.data) {
        setFullLead(res.data);
      }
    } catch (err) {
      console.error("Failed to fetch full lead details:", err);
    } finally {
      setFetching(false);
    }
  };

  useEffect(() => {
    if (lead && open) {
      setFullLead(lead);
      setName(lead.name || "");
      setEmail(lead.email || "");
      setPhone(lead.phone || "");
      setIsEditing(false);
      setActiveTab("details");
      setActDesc("");
      setLogError("");
      fetchFullDetails(lead.id);
    } else {
      setFullLead(null);
    }
  }, [lead, open]);

  if (!lead) return null;

  const handleSave = async () => {
    if (!name.trim() || !email.trim()) return;
    setLoading(true);
    try {
      if (onUpdateLead) {
        await onUpdateLead(lead.id, { name, email, phone });
      }
      setIsEditing(false);
      // reload
      await fetchFullDetails(lead.id);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleLogActivity = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!actDesc.trim()) return;
    setLogging(true);
    setLogError("");
    try {
      const res = await CRMAPI.logActivity(lead.id, actType, actDesc.trim());
      if (res.success) {
        setActDesc("");
        // Reload lead to get updated activities list
        await fetchFullDetails(lead.id);
      } else {
        setLogError(res.error || "Failed to log activity");
      }
    } catch (err: any) {
      setLogError(err.message || "An unexpected error occurred");
    } finally {
      setLogging(false);
    }
  };

  // Safe checks for activities array
  const activities = (fullLead as any)?.activities || [];

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:w-[450px] p-0 flex flex-col h-full bg-background border-l border-border">
        {/* Header */}
        <SheetHeader className="border-b border-border px-6 py-4 flex flex-row items-center justify-between space-y-0 shrink-0">
          <div className="flex-1 min-w-0 pr-4">
            {isEditing ? (
              <div className="space-y-2 mt-2">
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Contact Name"
                  className="h-8 font-semibold text-base"
                />
              </div>
            ) : (
              <>
                <SheetTitle className="truncate text-xl font-bold">{fullLead?.name || lead.name}</SheetTitle>
                <SheetDescription className="truncate text-xs">
                  Lead status: <span className="font-semibold capitalize text-primary">{fullLead?.status || lead.status}</span>
                </SheetDescription>
              </>
            )}
          </div>
          <div className="flex items-center gap-2">
            {isEditing ? (
              <>
                <Button size="icon" variant="ghost" className="h-8 w-8 text-emerald-500 hover:text-emerald-400" onClick={handleSave} disabled={loading}>
                  <Check className="h-4 w-4" />
                </Button>
                <Button size="icon" variant="ghost" className="h-8 w-8 text-destructive" onClick={() => setIsEditing(false)} disabled={loading}>
                  <X className="h-4 w-4" />
                </Button>
              </>
            ) : (
              <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => setIsEditing(true)}>
                <Edit2 className="h-4 w-4" />
              </Button>
            )}
          </div>
        </SheetHeader>

        {/* Navigation Tabs */}
        <div className="flex border-b border-border px-6 shrink-0 bg-secondary/10">
          <button
            className={`py-2.5 px-4 text-xs font-semibold border-b-2 transition-colors ${
              activeTab === "details"
                ? "border-primary text-primary"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
            onClick={() => setActiveTab("details")}
          >
            Details
          </button>
          <button
            className={`py-2.5 px-4 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === "activities"
                ? "border-primary text-primary"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
            onClick={() => setActiveTab("activities")}
          >
            Activity Log
            {activities.length > 0 && (
              <span className="bg-primary/20 text-primary text-[10px] px-1.5 py-0.2 rounded-full font-bold">
                {activities.length}
              </span>
            )}
          </button>
        </div>

        {/* Scrollable Content Container */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="space-y-6"
          >
            {activeTab === "details" ? (
              <div className="space-y-6">
                {/* Lead Score */}
                {(fullLead?.score !== undefined && fullLead.score !== null) && (
                  <Card className="p-4 bg-gradient-to-r from-primary/10 to-accent/10 border-0">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm text-muted-foreground">Lead Score</p>
                        <p className="text-3xl font-bold text-primary">{fullLead.score}%</p>
                      </div>
                      <TrendingUp className="h-8 w-8 text-primary/30" />
                    </div>
                  </Card>
                )}

                {/* Contact Information */}
                <div className="space-y-3">
                  <h4 className="font-semibold text-sm">Contact Information</h4>
                  <div className="space-y-2.5">
                    <div className="flex items-center gap-3">
                      <Mail className="h-4 w-4 text-muted-foreground" />
                      {isEditing ? (
                        <Input
                          value={email}
                          type="email"
                          onChange={(e) => setEmail(e.target.value)}
                          placeholder="Email"
                          className="h-8 text-xs flex-1"
                        />
                      ) : (
                        <a
                          href={`mailto:${fullLead?.email || lead.email}`}
                          className="text-sm hover:text-primary truncate max-w-[300px]"
                        >
                          {fullLead?.email || lead.email}
                        </a>
                      )}
                    </div>
                    <div className="flex items-center gap-3">
                      <Phone className="h-4 w-4 text-muted-foreground" />
                      {isEditing ? (
                        <Input
                          value={phone}
                          onChange={(e) => setPhone(e.target.value)}
                          placeholder="Phone"
                          className="h-8 text-xs flex-1"
                        />
                      ) : (
                        <span className="text-sm">{fullLead?.phone || lead.phone || "Not provided"}</span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Status & Details */}
                <div className="space-y-3">
                  <h4 className="font-semibold text-sm">Details</h4>
                  <div className="grid grid-cols-2 gap-4 bg-secondary/10 p-3.5 rounded-2xl border border-border">
                    <div>
                      <p className="text-xs text-muted-foreground">Status</p>
                      <Badge className="mt-1 capitalize">{fullLead?.status || lead.status}</Badge>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground">Country</p>
                      <p className="text-sm font-medium mt-1">{fullLead?.country || "—"}</p>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground">Source</p>
                      <p className="text-sm font-medium mt-1 capitalize">{fullLead?.source || "Direct"}</p>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground">Last Interaction</p>
                      <p className="text-sm font-medium mt-1">
                        {fullLead?.lastInteraction
                          ? new Date(fullLead.lastInteraction).toLocaleDateString()
                          : "None"}
                      </p>
                    </div>
                  </div>
                </div>

                {/* AI Summary */}
                <div className="space-y-3">
                  <h4 className="font-semibold text-sm">AI Summary</h4>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    {fullLead?.name || lead.name} is classified with status <span className="font-semibold capitalize text-foreground">{fullLead?.status || lead.status}</span>.
                    {fullLead?.score !== undefined && ` Scoring analytics indicates a ${fullLead.score}% operational readiness level. `}
                    Recommend initiating standard CRM follow-up sequences.
                  </p>
                </div>
              </div>
            ) : (
              /* Activities Tab */
              <div className="space-y-6">
                {/* Form to Log New Activity */}
                <Card className="p-4 border border-border bg-card/40">
                  <h4 className="font-semibold text-sm mb-3">Log Activity</h4>
                  <form onSubmit={handleLogActivity} className="space-y-3">
                    <div className="grid grid-cols-2 gap-2">
                      <div className="space-y-1">
                        <Label htmlFor="act-type" className="text-[10px] uppercase text-muted-foreground">Type</Label>
                        <select
                          id="act-type"
                          value={actType}
                          onChange={(e) => setActType(e.target.value as any)}
                          className="flex h-8 w-full rounded-md border border-input bg-background px-2.5 py-1 text-xs ring-offset-background text-foreground"
                        >
                          <option value="note">Note</option>
                          <option value="call">Call</option>
                          <option value="email">Email</option>
                          <option value="meeting">Meeting</option>
                        </select>
                      </div>
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="act-desc" className="text-[10px] uppercase text-muted-foreground">Description</Label>
                      <textarea
                        id="act-desc"
                        rows={2}
                        value={actDesc}
                        onChange={(e) => setActDesc(e.target.value)}
                        placeholder="Log what happened (e.g. called prospect, discussed processor specs)"
                        className="flex w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-1"
                        required
                      />
                    </div>

                    {logError && (
                      <p className="text-xs text-destructive flex items-center gap-1 bg-destructive/15 px-2.5 py-1.5 rounded-lg">
                        <AlertCircle className="w-3.5 h-3.5" />
                        {logError}
                      </p>
                    )}

                    <div className="flex justify-end">
                      <Button type="submit" size="sm" disabled={logging} className="btn-primary text-xs h-8">
                        {logging ? (
                          <>
                            <Loader2 className="w-3 h-3 animate-spin mr-1.5" />
                            Logging…
                          </>
                        ) : (
                          <>
                            <Plus className="w-3.5 h-3.5 mr-1" />
                            Log Activity
                          </>
                        )}
                      </Button>
                    </div>
                  </form>
                </Card>

                {/* Activities List */}
                <div className="space-y-3">
                  <h4 className="font-semibold text-sm">Activity History</h4>
                  {fetching && activities.length === 0 ? (
                    <div className="flex items-center justify-center py-8">
                      <Loader2 className="w-6 h-6 animate-spin text-primary" />
                    </div>
                  ) : activities.length === 0 ? (
                    <div className="text-center py-8 bg-secondary/5 border border-dashed border-border rounded-xl">
                      <ClipboardList className="w-8 h-8 text-muted-foreground/30 mx-auto mb-2" />
                      <p className="text-xs font-semibold">No activity logged yet</p>
                      <p className="text-[10px] text-muted-foreground">Logs will automatically show up when you interact with this lead.</p>
                    </div>
                  ) : (
                    <div className="relative border-l border-border pl-4 ml-2 space-y-4">
                      {activities.map((act: any) => (
                        <div key={act.id} className="relative">
                          {/* Bullet Icon */}
                          <span className="absolute -left-[22px] top-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-border border border-background">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                          </span>
                          <div className="bg-secondary/10 border border-border p-2.5 rounded-xl text-xs space-y-1">
                            <div className="flex justify-between items-center">
                              <Badge variant="outline" className="text-[9px] uppercase font-bold py-0 h-4 bg-background">
                                {act.type}
                              </Badge>
                              <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                                <Clock className="w-3 h-3" />
                                {new Date(act.created_at || act.createdAt).toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" })}
                              </span>
                            </div>
                            <p className="text-foreground font-medium text-xs leading-relaxed">{act.description}</p>
                            {act.user?.name && (
                              <p className="text-[9px] text-muted-foreground">Recorded by {act.user.name}</p>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </motion.div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
