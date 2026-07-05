"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { AIAPI, MarketingApproval } from "@/lib/api/ai.api";
import { authAPI, AuthUser } from "@/lib/api";
import { Check, X, Edit, Send, MessageSquare } from "lucide-react";

export default function MarketingApprovalsPage() {
  const [approvals, setApprovals] = useState<MarketingApproval[]>([]);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, appRes] = await Promise.all([
          authAPI.getCurrentUser(),
          AIAPI.getMarketingApprovals()
        ]);
        setUser(currentUser);
        if (appRes.success && appRes.data) {
          setApprovals(appRes.data);
        }
      } catch (err) {
        console.error("Failed to load marketing approvals:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const handleApprove = async (id: string) => {
    const res = await AIAPI.approveMarketingApproval(id);
    if (res.success) {
      setApprovals((prev) => prev.filter((item) => item.id !== id));
    }
  };

  const handleReject = async (id: string) => {
    const res = await AIAPI.rejectMarketingApproval(id);
    if (res.success) {
      setApprovals((prev) => prev.filter((item) => item.id !== id));
    }
  };

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
            Marketing Content Approvals
          </h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Human-in-the-loop validation queue for AI-drafted promotional and social posts (FR-3.3).
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {loading ? (
            <div className="h-48 bg-secondary/20 rounded animate-pulse col-span-2" />
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
                      <CardTitle className="text-base">{item.campaignName}</CardTitle>
                      <CardDescription className="text-xs">Channels: {item.channel.toUpperCase()}</CardDescription>
                    </div>
                    <Badge variant="outline" className="capitalize text-[10px]">
                      {item.status}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-sm bg-background/50 p-4 rounded-xl border border-border/80 whitespace-pre-wrap leading-relaxed">
                    {item.content}
                  </p>
                  <div className="flex justify-end gap-2 pt-2 border-t border-border/40">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleReject(item.id)}
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
    </AppLayout>
  );
}
