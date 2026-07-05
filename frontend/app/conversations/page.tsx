"use client";

import { useEffect, useState, useRef } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { ScrollArea } from "@/components/ui/scroll-area";
import { AIAPI, EscalationRecord } from "@/lib/api/ai.api";
import { authAPI, AuthUser } from "@/lib/api";
import { AlertCircle, CheckCircle2, ShieldAlert, Zap, Globe, MessageSquare, Terminal, Eye } from "lucide-react";
import Link from "next/link";

export default function ConversationsPage() {
  const [escalations, setEscalations] = useState<EscalationRecord[]>([]);
  const [selectedEsc, setSelectedEsc] = useState<EscalationRecord | null>(null);
  const [selectedReason, setSelectedReason] = useState<string>("all");
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [replyText, setReplyText] = useState("");
  const [takeoverStatus, setTakeoverStatus] = useState<Record<string, boolean>>({});
  const [wsConnected, setWsConnected] = useState(true); // Reverb connection state
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, escRes] = await Promise.all([
          authAPI.getCurrentUser(),
          AIAPI.getEscalations(),
        ]);
        setUser(currentUser);
        if (escRes.success && escRes.data) {
          setEscalations(escRes.data);
          if (escRes.data.length > 0) {
            setSelectedEsc(escRes.data[0]);
          }
        }
      } catch (err) {
        console.error("Failed to load conversations:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();

    // Laravel Reverb WebSockets simulation
    const interval = setInterval(() => {
      // Simulate real-time arrival of a new escalated conversation
      const newEsc: EscalationRecord = {
        id: `esc_${Date.now()}`,
        traceId: `trace_${Math.floor(Math.random() * 1000)}`,
        leadId: '3',
        leadName: 'Objection Specialist',
        reason: 'low_confidence',
        content: 'Is your processor CE certified in the EU?',
        region: 'europe',
        language: 'en',
        history: [
          { sender: 'lead', content: 'Is your processor CE certified in the EU?', timestamp: new Date().toISOString() }
        ],
        createdAt: new Date().toISOString(),
      };
      setEscalations((prev) => {
        // Only append if it's not already there and limit duplication
        if (prev.length < 5) {
          return [newEsc, ...prev];
        }
        return prev;
      });
    }, 45000);

    return () => clearInterval(interval);
  }, []);

  const handleTakeover = async (traceId: string) => {
    if (!replyText.trim()) return;
    const res = await AIAPI.takeoverConversation(traceId, replyText);
    if (res.success) {
      setTakeoverStatus((prev) => ({ ...prev, [traceId]: true }));
      // Append reply to local state history
      if (selectedEsc) {
        setSelectedEsc({
          ...selectedEsc,
          history: [
            ...selectedEsc.history,
            { sender: 'user', content: replyText, timestamp: new Date().toISOString() }
          ]
        });
      }
      setReplyText("");
    }
  };

  const getReasonBadgeColor = (reason: string) => {
    switch (reason) {
      case "injection_detected":
        return "bg-red-500/20 text-red-500 border border-red-500/30";
      case "legal_risk":
        return "bg-amber-500/20 text-amber-500 border border-amber-500/30";
      case "guardrail_failure":
        return "bg-yellow-500/20 text-yellow-500 border border-yellow-500/30";
      default:
        return "bg-blue-500/20 text-blue-500 border border-blue-500/30";
    }
  };

  const filteredEscalations = escalations.filter((esc) => {
    if (selectedReason === "all") return true;
    return esc.reason === selectedReason;
  });

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6">
        {/* Header with real-time indicator */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              Escalation & Intervention Center
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Act on safety blocks, compliance alerts, and manual agent overrides.
            </p>
          </div>
          <div className="flex items-center gap-2 bg-secondary/50 px-3 py-1.5 rounded-full border border-border self-start">
            <span className={`w-2.5 h-2.5 rounded-full ${wsConnected ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}`} />
            <span className="text-xs font-semibold text-muted-foreground">
              {wsConnected ? 'Reverb Connected' : 'Reverb Offline'}
            </span>
          </div>
        </div>

        {/* Reason Filters */}
        <div className="flex flex-wrap gap-2" role="group" aria-label="Filter escalations by reason">
          {["all", "injection_detected", "legal_risk", "guardrail_failure", "low_confidence", "tool_error"].map((reason) => (
            <button
              key={reason}
              onClick={() => setSelectedReason(reason)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${
                selectedReason === reason
                  ? "bg-emerald-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/20"
                  : "bg-secondary/40 text-muted-foreground border-border hover:bg-secondary/80"
              }`}
            >
              {reason.replace("_", " ")}
            </button>
          ))}
        </div>

        {/* Grid Container */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-280px)]">
          {/* List queue */}
          <Card className="col-span-1 border-border bg-card/60 backdrop-blur-md overflow-hidden flex flex-col">
            <CardHeader className="border-b border-border">
              <CardTitle className="text-lg">Inbound Queue</CardTitle>
              <CardDescription>{filteredEscalations.length} items waiting review</CardDescription>
            </CardHeader>
            <ScrollArea className="flex-1">
              <div className="space-y-2 p-4" ref={listRef} role="listbox" aria-label="Escalated conversations list">
                {filteredEscalations.map((esc, index) => (
                  <button
                    key={esc.id}
                    onClick={() => setSelectedEsc(esc)}
                    role="option"
                    aria-selected={selectedEsc?.id === esc.id}
                    className={`w-full text-left p-4 rounded-xl transition-all border ${
                      selectedEsc?.id === esc.id
                        ? "bg-emerald-500/10 border-emerald-500/40 shadow-sm"
                        : "bg-secondary/15 hover:bg-secondary/40 border-border"
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2 mb-2">
                      <span className="font-semibold text-sm text-foreground">{esc.leadName}</span>
                      <Badge className={getReasonBadgeColor(esc.reason)}>
                        {esc.reason.replace("_", " ")}
                      </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground line-clamp-2 mb-3">
                      "{esc.content}"
                    </p>
                    <div className="flex items-center justify-between text-[10px] text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <Globe className="w-3 h-3" />
                        {esc.region.toUpperCase()} ({esc.language})
                      </span>
                      <span>{new Date(esc.createdAt).toLocaleTimeString()}</span>
                    </div>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </Card>

          {/* Conversation details and reply */}
          {selectedEsc ? (
            <Card className="col-span-1 lg:col-span-2 border-border bg-card/60 backdrop-blur-md overflow-hidden flex flex-col">
              <CardHeader className="border-b border-border flex flex-row items-center justify-between space-y-0">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    {selectedEsc.leadName}
                    <Badge className="ml-2 bg-secondary/80 text-muted-foreground">{selectedEsc.region.toUpperCase()}</Badge>
                  </CardTitle>
                  <CardDescription className="text-xs flex items-center gap-2 mt-1">
                    Trace ID: <code className="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{selectedEsc.traceId}</code>
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Link href={`/traces/${selectedEsc.traceId}`}>
                    <Button variant="outline" size="sm" className="gap-1 text-xs">
                      <Eye className="w-3.5 h-3.5" /> View Trace
                    </Button>
                  </Link>
                </div>
              </CardHeader>

              <ScrollArea className="flex-1 p-4">
                <div className="space-y-4">
                  {selectedEsc.history.map((msg, idx) => (
                    <div
                      key={idx}
                      className={`flex gap-3 ${msg.sender === 'user' || msg.sender === 'assistant' ? "justify-end" : ""}`}
                    >
                      {msg.sender === 'lead' && (
                        <Avatar className="w-8 h-8 flex-shrink-0 border border-border">
                          <AvatarFallback className="bg-emerald-500/20 text-emerald-500 text-xs">L</AvatarFallback>
                        </Avatar>
                      )}
                      <div
                        className={`max-w-md px-4 py-2.5 rounded-2xl text-sm border ${
                          msg.sender === 'user'
                            ? "bg-emerald-500 text-white border-emerald-600 rounded-tr-none"
                            : msg.sender === 'assistant'
                            ? "bg-secondary text-foreground border-border rounded-tr-none"
                            : "bg-card text-foreground border-border rounded-tl-none"
                        }`}
                      >
                        <p className="font-semibold text-[10px] mb-0.5 opacity-80">
                          {msg.sender.toUpperCase()}
                        </p>
                        <p className="whitespace-pre-wrap">{msg.content}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </ScrollArea>

              {/* Takeover Control */}
              <div className="border-t border-border p-4 bg-secondary/10">
                {takeoverStatus[selectedEsc.traceId] ? (
                  <div className="flex items-center gap-2 text-emerald-500 text-sm bg-emerald-500/10 p-3 rounded-xl border border-emerald-500/20">
                    <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
                    <span>Human staff has hijacked this thread. AI routing is disabled.</span>
                  </div>
                ) : (
                  <div className="flex flex-col gap-2">
                    <div className="flex gap-2">
                      <input
                        type="text"
                        placeholder="Write a override message to the client..."
                        value={replyText}
                        onChange={(e) => setReplyText(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') handleTakeover(selectedEsc.traceId);
                        }}
                        className="flex-1 input-base rounded-xl px-4 py-2 bg-background border border-border focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                      />
                      <Button
                        onClick={() => handleTakeover(selectedEsc.traceId)}
                        className="bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl px-4 gap-1.5"
                      >
                        <Zap className="w-4 h-4" /> Take Over & Send
                      </Button>
                    </div>
                    <span className="text-[10px] text-muted-foreground">
                      * Sending a response locks the conversation out of AI automation.
                    </span>
                  </div>
                )}
              </div>
            </Card>
          ) : (
            <Card className="col-span-1 lg:col-span-2 border-border bg-card/60 backdrop-blur-md flex items-center justify-center">
              <div className="text-center text-muted-foreground p-6">
                <ShieldAlert className="w-16 h-16 mx-auto mb-4 text-emerald-500/40 animate-pulse" />
                <h3 className="text-lg font-semibold text-foreground mb-1">Queue Empty</h3>
                <p className="text-sm">There are no escalated conversations awaiting human review.</p>
              </div>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
