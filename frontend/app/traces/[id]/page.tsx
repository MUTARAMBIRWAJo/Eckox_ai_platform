"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { AIAPI, TraceLog } from "@/lib/api/ai.api";
import { authAPI, AuthUser } from "@/lib/api";
import { Activity, ArrowRight, CheckCircle2, AlertTriangle, Cpu, Terminal, Shield, ArrowLeft, Clock } from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";

export default function TraceViewerPage() {
  const params = useParams();
  const traceId = params?.id as string;

  const [trace, setTrace] = useState<TraceLog | null>(null);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, traceRes] = await Promise.all([
          authAPI.getCurrentUser(),
          AIAPI.getTrace(traceId),
        ]);
        setUser(currentUser);
        if (traceRes.success && traceRes.data) {
          setTrace(traceRes.data);
        }
      } catch (err) {
        console.error("Failed to load trace:", err);
      } finally {
        setLoading(false);
      }
    };

    if (traceId) {
      loadData();
    }
  }, [traceId]);

  if (loading) {
    return (
      <AppLayout headerProps={{ user, onLogout: () => {} }}>
        <div className="flex items-center justify-center h-[60vh]">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500" />
        </div>
      </AppLayout>
    );
  }

  if (!trace) {
    return (
      <AppLayout headerProps={{ user, onLogout: () => {} }}>
        <div className="text-center p-12">
          <AlertTriangle className="w-16 h-16 mx-auto text-amber-500 mb-4" />
          <h2 className="text-2xl font-bold">Trace Not Found</h2>
          <p className="text-muted-foreground mt-2">Could not find trace logs matching ID "{traceId}".</p>
          <Link href="/conversations" className="mt-4 inline-block">
            <Button variant="outline">Back to Escalations</Button>
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6">
        {/* Back Link */}
        <div className="flex items-center gap-3">
          <Link href="/conversations">
            <Button variant="ghost" size="icon" className="rounded-full">
              <ArrowLeft className="w-5 h-5" />
            </Button>
          </Link>
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              Trace Telemetry Viewer
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Node-by-node execution details for Trace: <span className="font-mono text-foreground font-semibold">{traceId}</span>
            </p>
          </div>
        </div>

        {/* Highlight Banner if Failover occurred */}
        {(trace.hasFailover || trace.hasRetryCycle) && (
          <div className="bg-amber-500/10 border border-amber-500/20 text-amber-200 p-4 rounded-2xl flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
            <div>
              <h4 className="font-bold text-sm">Actionable Event Logs Detected</h4>
              <p className="text-xs mt-1 text-muted-foreground">
                This execution required an active LLM provider failover or guardrail retry/regrounding sequence to pass safety validations.
              </p>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Node Flow Visualization */}
          <Card className="col-span-1 lg:col-span-2 border-border bg-card/60 backdrop-blur-md">
            <CardHeader>
              <CardTitle>Agent Execution Path</CardTitle>
              <CardDescription>Order and duration of executed state graph nodes</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="relative border-l-2 border-border pl-6 ml-4 space-y-6">
                {trace.nodePath.map((node, idx) => {
                  const latency = trace.latencyMs[node] || 0;
                  return (
                    <div key={idx} className="relative">
                      {/* Node Bullet */}
                      <span className="absolute -left-[31px] top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-border border-2 border-background">
                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                      </span>
                      <div className="flex items-center justify-between gap-4 p-3 bg-secondary/20 rounded-xl border border-border hover:bg-secondary/40 transition-colors">
                        <div>
                          <p className="text-sm font-semibold capitalize text-foreground">
                            {node.replace("_", " ")}
                          </p>
                          <span className="text-[10px] text-muted-foreground">Step {idx + 1}</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Clock className="w-3.5 h-3.5 text-muted-foreground" />
                          <span className="text-xs font-mono">{latency}ms</span>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>

          {/* Details Sidebar */}
          <div className="col-span-1 space-y-6">
            {/* LLM & Execution Context */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle className="text-lg">Decision Details</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex justify-between items-center py-2 border-b border-border">
                  <span className="text-xs text-muted-foreground">Lead Name</span>
                  <span className="text-sm font-semibold">{trace.leadName}</span>
                </div>
                <div className="flex justify-between items-center py-2 border-b border-border">
                  <span className="text-xs text-muted-foreground">LLM Provider</span>
                  <Badge className="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    <Cpu className="w-3.5 h-3.5 mr-1" />
                    {trace.llmProvider.toUpperCase()}
                  </Badge>
                </div>
                <div className="flex justify-between items-center py-2 border-b border-border">
                  <span className="text-xs text-muted-foreground">Decision Type</span>
                  <Badge variant="outline" className="capitalize">
                    {trace.decisionType}
                  </Badge>
                </div>
                {trace.actionExecuted && (
                  <div className="flex justify-between items-center py-2 border-b border-border">
                    <span className="text-xs text-muted-foreground">Channel Status</span>
                    <span className="text-xs text-emerald-500 flex items-center gap-1 font-semibold">
                      <CheckCircle2 className="w-3.5 h-3.5" />
                      {trace.actionExecuted.channel.toUpperCase()} ({trace.actionExecuted.status})
                    </span>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Tool Calls */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle className="text-lg">Database Tool Calls</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {trace.toolCalls.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No tool calls executed in this trace.</p>
                ) : (
                  trace.toolCalls.map((tool, idx) => (
                    <div key={idx} className="bg-secondary/15 p-3 rounded-xl border border-border space-y-2">
                      <div className="flex items-center gap-2">
                        <Terminal className="w-4 h-4 text-emerald-500" />
                        <span className="text-xs font-mono font-bold text-foreground">{tool.name}</span>
                      </div>
                      <div className="text-[10px] space-y-1">
                        <p className="text-muted-foreground">
                          Inputs: <code className="bg-muted px-1 rounded">{JSON.stringify(tool.inputs)}</code>
                        </p>
                        <p className="text-muted-foreground">
                          Output: <code className="bg-muted px-1 rounded">{JSON.stringify(tool.output)}</code>
                        </p>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>

            {/* Guardrails */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle className="text-lg">Response Guardrail</CardTitle>
              </CardHeader>
              <CardContent>
                {trace.guardrailVerdict ? (
                  <div className="flex items-start gap-2.5">
                    {trace.guardrailVerdict.success ? (
                      <>
                        <Shield className="w-5 h-5 text-emerald-500 mt-0.5" />
                        <div>
                          <p className="text-xs font-semibold text-emerald-500">Guardrail Passed</p>
                          <p className="text-[10px] text-muted-foreground mt-0.5">Response facts matched citation metadata in context.</p>
                        </div>
                      </>
                    ) : (
                      <>
                        <Shield className="w-5 h-5 text-rose-500 mt-0.5" />
                        <div>
                          <p className="text-xs font-semibold text-rose-500">Guardrail Violated</p>
                          <div className="mt-1 space-y-1">
                            {trace.guardrailVerdict.errors.map((err, i) => (
                              <p key={i} className="text-[10px] text-muted-foreground">• {err}</p>
                            ))}
                          </div>
                        </div>
                      </>
                    )}
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">Guardrail execution did not run or was skipped.</p>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
