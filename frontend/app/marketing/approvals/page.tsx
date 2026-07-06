"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AIAPI, MarketingApproval } from "@/lib/api/ai.api";
import { useAuth } from "@/hooks/use-auth";
import { Check, X, MessageSquare, RefreshCw, AlertTriangle, Loader2 } from "lucide-react";

export default function MarketingApprovalsPage() {
  const [approvals, setApprovals] = useState<MarketingApproval[]>([]);
  const { user, logout } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Reject Dialog state
  const [rejectingId, setRejectingId] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [isRejecting, setIsRejecting] = useState(false);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const appRes = await AIAPI.getMarketingApprovals();
      if (appRes.success && appRes.data) {
        setApprovals(appRes.data);
      } else {
        setError(appRes.error || "Failed to load marketing approvals");
      }
    } catch (err: any) {
      setError(err.message || "Could not fetch marketing approvals from the backend");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleApprove = async (id: string) => {
    try {
      const res = await AIAPI.approveMarketingApproval(id);
      if (res.success) {
        setApprovals((prev) => prev.filter((item) => item.id !== id));
      } else {
        alert(res.error || "Approval failed");
      }
    } catch (err: any) {
      alert(err.message || "An error occurred");
    }
  };

  const handleRejectSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectingId) return;
    setIsRejecting(true);
    try {
      const res = await AIAPI.rejectMarketingApproval(rejectingId, rejectReason.trim() || undefined);
      if (res.success) {
        setApprovals((prev) => prev.filter((item) => item.id !== rejectingId));
        setRejectingId(null);
        setRejectReason("");
      } else {
        alert(res.error || "Rejection failed");
      }
    } catch (err: any) {
      alert(err.message || "An error occurred");
    } finally {
      setIsRejecting(false);
    }
  };

  return (
    <AppLayout
      headerProps={{
        user: user
          ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email }
          : undefined,
        onLogout: () => logout(),
      }}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              Marketing Content Approvals
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Human-in-the-loop validation queue for AI-drafted promotional and social posts (FR-3.3).
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={loadData} disabled={loading} className="self-start">
            <RefreshCw className={`w-4 h-4 mr-1.5 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-4 text-sm text-destructive flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0" />
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {loading ? (
            <div className="h-48 bg-secondary/20 rounded-2xl animate-pulse col-span-2" />
          ) : approvals.length === 0 ? (
            <Card className="col-span-2 border-border bg-card/60 backdrop-blur-md flex items-center justify-center p-12">
              <div className="text-center text-muted-foreground">
                <MessageSquare className="w-16 h-16 mx-auto mb-4 text-emerald-500/40" />
                <h3 className="text-lg font-semibold text-foreground">Approvals Queue Clear</h3>
                <p className="text-sm mt-1">No pending social content templates require human approval.</p>
              </div>
            </Card>
          ) : (
            approvals.map((item) => (
              <Card key={item.id} className="border-border bg-card/60 backdrop-blur-md flex flex-col justify-between">
                <CardHeader>
                  <div className="flex justify-between items-start gap-2">
                    <div>
                      <CardTitle className="text-base">{item.campaignName || "Campaign Content"}</CardTitle>
                      <CardDescription className="text-xs">Channel: <span className="font-mono text-primary font-bold uppercase">{item.channel}</span></CardDescription>
                    </div>
                    <Badge variant="outline" className="capitalize text-[10px] bg-amber-500/10 text-amber-500 border-amber-500/20">
                      {item.status}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-sm bg-background/50 p-4 rounded-xl border border-border/80 whitespace-pre-wrap leading-relaxed text-foreground">
                    {item.content}
                  </p>
                  <div className="flex justify-end gap-2 pt-2 border-t border-border/40">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setRejectingId(item.id)}
                      className="text-rose-400 border-rose-500/20 hover:bg-rose-500/10 hover:text-rose-500 rounded-xl"
                    >
                      <X className="w-4 h-4 mr-1.5" /> Reject
                    </Button>
                    <Button
                      size="sm"
                      onClick={() => handleApprove(item.id)}
                      className="bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl"
                    >
                      <Check className="w-4 h-4 mr-1.5" /> Approve & Publish
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>

      {/* Reject dialog with reason input */}
      <Dialog open={rejectingId !== null} onOpenChange={(o) => { if (!o) setRejectingId(null); }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-destructive">
              <AlertTriangle className="w-5 h-5" /> Reject Marketing Content
            </DialogTitle>
            <DialogDescription>
              Please provide a brief reason for rejecting this content. This will help refine future AI generations.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleRejectSubmit} className="space-y-3 pt-2">
            <div className="space-y-1">
              <Label htmlFor="reject-reason" className="text-xs">Reason (optional)</Label>
              <Input
                id="reject-reason"
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="e.g. Price mismatch, wrong tone"
                maxLength={500}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setRejectingId(null)} disabled={isRejecting}>
                Cancel
              </Button>
              <Button type="submit" variant="destructive" disabled={isRejecting}>
                {isRejecting ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <X className="w-4 h-4 mr-2" />}
                Reject Content
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
