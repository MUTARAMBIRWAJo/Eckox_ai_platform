"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { KPICard } from "@/components/common/kpi-card";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { TrendingUp, Users, Target, CheckCircle2, ShieldAlert, RefreshCw } from "lucide-react";
import { DashboardAPI, DashboardStats, ProviderHealth } from "@/lib/api/dashboard.api";
import { useAuth } from "@/hooks/use-auth";
import {
  BarChart, Bar, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer,
} from "recharts";
import { getChartColors } from "@/lib/chart-colors";

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [healthData, setHealthData] = useState<ProviderHealth[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadDashboard = async () => {
    setLoading(true);
    setError(null);
    try {
      const [statsRes, healthRes] = await Promise.all([
        DashboardAPI.getStats(),
        DashboardAPI.getProviderHealth(),
      ]);

      if (statsRes.success && statsRes.data) {
        setStats(statsRes.data);
      } else {
        setError(statsRes.error || "Failed to load dashboard stats.");
      }

      if (healthRes.success && healthRes.data) {
        setHealthData(healthRes.data);
      }
    } catch (err: any) {
      setError("Could not reach the backend. Please check your connection.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadDashboard(); }, []);

  const chartColors = getChartColors();

  // Build KPI values from real backend data
  const totalLeads = stats?.pipeline?.reduce((sum, s) => sum + s.count, 0) ?? 0;
  const conversionRate = stats?.conversionRate ?? 0;
  const avgLatencyS = stats ? (stats.avgLatencyMs / 1000).toFixed(1) : "—";
  const totalDecisions = stats?.totalDecisions ?? 0;

  return (
    <AppLayout
      headerProps={{
        user: user
          ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email }
          : undefined,
        onLogout: () => logout(),
      }}
    >
      <PageTransition>
        <div className="space-y-8 py-2">
          {/* Page Header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
                Executive Monitor
              </h1>
              <p className="text-muted-foreground mt-1 text-sm">
                Live pipeline metrics and AI system health from your backend.
              </p>
            </div>
            <div className="flex items-center gap-3">
              {user?.roles?.map((role) => (
                <span key={role} className="px-3 py-1 rounded-full bg-emerald-500/20 text-xs font-semibold text-emerald-400 capitalize border border-emerald-500/30">
                  {role}
                </span>
              ))}
              <Button variant="outline" size="sm" onClick={loadDashboard} disabled={loading}>
                <RefreshCw className={`w-4 h-4 mr-1.5 ${loading ? "animate-spin" : ""}`} />
                Refresh
              </Button>
            </div>
          </div>

          {/* Error banner */}
          {error && !loading && (
            <div className="bg-destructive/10 border border-destructive/20 rounded-2xl p-4 text-sm text-destructive">
              <ShieldAlert className="inline w-4 h-4 mr-2" />
              {error}
            </div>
          )}

          {/* KPI Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {loading ? (
              Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="h-32 bg-secondary/20 rounded-2xl animate-pulse" />
              ))
            ) : (
              <>
                <KPICard
                  title="Total Pipeline Leads"
                  value={String(totalLeads)}
                  change={0}
                  icon={<Users className="w-4 h-4" />}
                />
                <KPICard
                  title="AI Conversion Rate"
                  value={`${conversionRate}%`}
                  change={0}
                  icon={<Target className="w-4 h-4" />}
                />
                <KPICard
                  title="Avg AI Latency"
                  value={`${avgLatencyS}s`}
                  change={0}
                  icon={<CheckCircle2 className="w-4 h-4 text-emerald-500" />}
                />
                <KPICard
                  title="Total AI Decisions"
                  value={String(totalDecisions)}
                  change={0}
                  icon={<TrendingUp className="w-4 h-4" />}
                />
              </>
            )}
          </div>

          {/* Charts Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Pipeline Stages */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Pipeline Stages</CardTitle>
                <CardDescription>Lead counts by status from your backend CRM.</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="h-56 bg-secondary/20 rounded animate-pulse" />
                ) : stats?.pipeline?.length ? (
                  <ResponsiveContainer width="100%" height={220}>
                    <BarChart data={stats.pipeline}>
                      <CartesianGrid strokeDasharray="3 3" stroke={chartColors.gridStroke} />
                      <XAxis dataKey="stage" stroke={chartColors.axisStroke} tick={{ fontSize: 11 }} />
                      <YAxis stroke={chartColors.axisStroke} />
                      <Tooltip contentStyle={{ backgroundColor: chartColors.tooltipBg, border: `1px solid ${chartColors.tooltipBorder}` }} />
                      <Bar dataKey="count" fill="#10b981" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="h-56 flex items-center justify-center text-sm text-muted-foreground">
                    No pipeline data available yet.
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Provider Health */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Multi-LLM Provider Health</CardTitle>
                <CardDescription>Request volume, failovers, and latency per provider.</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="h-56 bg-secondary/20 rounded animate-pulse" />
                ) : healthData.length ? (
                  <div className="space-y-4">
                    <ResponsiveContainer width="100%" height={180}>
                      <BarChart data={healthData} layout="vertical">
                        <CartesianGrid strokeDasharray="3 3" stroke={chartColors.gridStroke} />
                        <XAxis type="number" stroke={chartColors.axisStroke} />
                        <YAxis dataKey="name" type="category" stroke={chartColors.axisStroke} width={130} tick={{ fontSize: 10 }} />
                        <Tooltip contentStyle={{ backgroundColor: chartColors.tooltipBg, border: `1px solid ${chartColors.tooltipBorder}` }} />
                        <Bar dataKey="volume" fill="#06b6d4" radius={[0, 4, 4, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                    <div className="grid grid-cols-3 gap-2 text-center">
                      {healthData.map((p) => (
                        <div key={p.name} className="p-2 bg-secondary/20 rounded-xl border border-border">
                          <p className="text-[10px] font-semibold truncate text-muted-foreground">{p.name.split(" ")[0]}</p>
                          <p className="text-xs font-extrabold text-foreground mt-0.5">{p.latency}ms</p>
                          <p className="text-[9px] text-muted-foreground">{p.failovers} failovers</p>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="h-56 flex items-center justify-center text-sm text-muted-foreground">
                    No provider health data available yet.
                  </div>
                )}
              </CardContent>
            </Card>

            {/* AI Latency Trend — uses real avgLatencyMs */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>AI Graph Latency</CardTitle>
                <CardDescription>Average LLM reasoning latency vs 20s NFR-1 target.</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="h-48 bg-secondary/20 rounded animate-pulse" />
                ) : (
                  <ResponsiveContainer width="100%" height={180}>
                    <AreaChart
                      data={[
                        { label: "Current Avg", latency: stats ? stats.avgLatencyMs / 1000 : 0 },
                        { label: "NFR-1 SLA", latency: 20 },
                      ]}
                    >
                      <defs>
                        <linearGradient id="latencyGrad" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#10b981" stopOpacity={0.2} />
                          <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke={chartColors.gridStroke} />
                      <XAxis dataKey="label" stroke={chartColors.axisStroke} />
                      <YAxis stroke={chartColors.axisStroke} />
                      <Tooltip contentStyle={{ backgroundColor: chartColors.tooltipBg, border: `1px solid ${chartColors.tooltipBorder}` }} />
                      <Area type="monotone" dataKey="latency" stroke="#10b981" fillOpacity={1} fill="url(#latencyGrad)" />
                    </AreaChart>
                  </ResponsiveContainer>
                )}
              </CardContent>
            </Card>

            {/* Objection metrics — no backend endpoint */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Customer Objection Metrics</CardTitle>
                <CardDescription>Semantic classification of sales barriers.</CardDescription>
              </CardHeader>
              <CardContent className="flex items-center justify-center h-48 bg-secondary/10 rounded-2xl border border-dashed border-border text-center p-6">
                <div>
                  <ShieldAlert className="w-8 h-8 text-amber-500/60 mx-auto mb-2" />
                  <p className="text-xs font-semibold text-foreground">No Backend Endpoint Available</p>
                  <p className="text-[10px] text-muted-foreground mt-0.5">
                    Conversational objection analytics require a dedicated backend endpoint not yet implemented.
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </PageTransition>
    </AppLayout>
  );
}
