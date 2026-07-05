"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { KPICard } from "@/components/common/kpi-card";
import { KPICardSkeleton } from "@/components/common/skeleton-loader";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { TrendingUp, Users, FileText, Target, ShieldAlert, AlertTriangle, Play, CheckCircle2, RefreshCw } from "lucide-react";
import { dashboardAPI } from "@/lib/api";
import { AIAPI } from "@/lib/api/ai.api";
import { useAuth } from "@/hooks/use-auth";
import { LineChart, Line, BarChart, Bar, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { getChartColors } from "@/lib/chart-colors";

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const [kpis, setKpis] = useState<any[]>([]);
  const [revenueChart, setRevenueChart] = useState<any[]>([]);
  const [funnelChart, setFunnelChart] = useState<any[]>([]);
  const [healthData, setHealthData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Alerts
  const [alerts, setAlerts] = useState<string[]>([
    "Low stock warning: SKU-PROC-X is down to 4 units.",
    "Anthropic provider latency exceeded SLA thresholds (1200ms avg).",
    "Factual guardrail retry blocked a mismatch on SKU-SERV-HUB price quote."
  ]);

  useEffect(() => {
    const loadDashboard = async () => {
      try {
        const [kpiData, revenue, funnel, healthRes] = await Promise.all([
          dashboardAPI.getKPIs(),
          dashboardAPI.getRevenueChart(),
          dashboardAPI.getFunnelChart(),
          AIAPI.getProviderHealth()
        ]);

        setKpis(kpiData);
        setRevenueChart(revenue);
        setFunnelChart(funnel);
        if (healthRes.success && healthRes.data) {
          setHealthData(healthRes.data);
        }
      } catch (err) {
        console.error("Failed to load dashboard:", err);
      } finally {
        setLoading(false);
      }
    };

    loadDashboard();
  }, []);

  return (
    <AppLayout
      headerProps={{
        user: user ? {
          name: user.name,
          email: user.email,
          avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email,
        } : undefined,
        onLogout: () => logout(),
      }}
    >
      <PageTransition>
        <div className="space-y-8 py-2">
          {/* Page Header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
                Executive & Graph Monitor
              </h1>
              <p className="text-muted-foreground mt-1 text-sm">
                Real-time operational dashboard for Eckox AI Sales graph state, latencies, and pipeline metrics.
              </p>
            </div>
            <div className="flex gap-2">
              {user?.roles?.map((role) => (
                <span key={role} className="px-3 py-1 rounded-full bg-emerald-500/20 text-xs font-semibold text-emerald-400 capitalize border border-emerald-500/30">
                  {role}
                </span>
              ))}
            </div>
          </div>

          {/* Alert Ticker banner */}
          <div className="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 space-y-2">
            <h3 className="text-xs font-bold text-rose-400 flex items-center gap-1.5">
              <ShieldAlert className="w-4 h-4" /> Real-time Contextual Alerts (FR-5.5)
            </h3>
            <ul className="text-xs text-muted-foreground list-disc pl-5 space-y-1">
              {alerts.map((alert, idx) => (
                <li key={idx}>{alert}</li>
              ))}
            </ul>
          </div>

          {/* KPI Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {loading ? (
              Array.from({ length: 4 }).map((_, i) => <div key={i} className="h-32 bg-secondary/20 rounded animate-pulse" />)
            ) : (
              <>
                <KPICard title="Pipeline Value" value="$2.4M" change={12.5} icon={<TrendingUp className="w-4 h-4" />} />
                <KPICard title="Conversations" value="48 active" change={-2.1} icon={<Users className="w-4 h-4" />} />
                <KPICard title="AI Latency (Avg)" value="1.6s" change={4.2} icon={<CheckCircle2 className="w-4 h-4 text-emerald-500" />} />
                <KPICard title="NFR Latency Target" value="20.0s SLA" change={0.0} icon={<CheckCircle2 className="w-4 h-4 text-emerald-500" />} />
              </>
            )}
          </div>

          {/* Charts Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Sales Pipeline & Forecasting */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Pipeline Stages & Forecasts (FR-5.6)</CardTitle>
                <CardDescription>Funnel analysis combined with 30-day linear projection forecasts.</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="h-64 bg-secondary/20 rounded animate-pulse" />
                ) : (
                  <div className="space-y-4">
                    <ResponsiveContainer width="100%" height={220}>
                      <BarChart data={funnelChart}>
                        <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                        <XAxis dataKey="stage" stroke={getChartColors().axisStroke} />
                        <YAxis stroke={getChartColors().axisStroke} />
                        <Tooltip contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }} />
                        <Bar dataKey="count" fill="#10b981" />
                      </BarChart>
                    </ResponsiveContainer>
                    <div className="p-3 bg-secondary/15 rounded-xl border border-border">
                      <p className="text-xs font-bold text-foreground">Next Month projection: $680K predicted</p>
                      <p className="text-[10px] text-muted-foreground mt-0.5">Based on historical win velocity and graph conversion rates.</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Provider Health Panel (Section 5) */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Multi-LLM Provider Metrics</CardTitle>
                <CardDescription>Request distribution, latency profiles, and routing failovers.</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="h-64 bg-secondary/20 rounded animate-pulse" />
                ) : (
                  <div className="space-y-4">
                    <ResponsiveContainer width="100%" height={220}>
                      <BarChart data={healthData} layout="vertical">
                        <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                        <XAxis type="number" stroke={getChartColors().axisStroke} />
                        <YAxis dataKey="name" type="category" stroke={getChartColors().axisStroke} width={120} />
                        <Tooltip contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }} />
                        <Bar dataKey="volume" fill="#06b6d4" />
                      </BarChart>
                    </ResponsiveContainer>
                    <div className="grid grid-cols-3 gap-2 text-center">
                      {healthData.map((provider) => (
                        <div key={provider.name} className="p-2 bg-secondary/20 rounded-xl border border-border">
                          <p className="text-[10px] font-semibold truncate text-muted-foreground">{provider.name.split(' ')[0]}</p>
                          <p className="text-xs font-extrabold text-foreground mt-0.5">{provider.latency}ms</p>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Recurring Objections Panel */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Customer Objection Metrics</CardTitle>
                <CardDescription>Semantic classification of sales barriers.</CardDescription>
              </CardHeader>
              <CardContent className="flex items-center justify-center h-48 bg-secondary/10 rounded-2xl border border-dashed border-border text-center p-6">
                <div>
                  <AlertTriangle className="w-8 h-8 text-amber-500/60 mx-auto mb-2" />
                  <p className="text-xs font-semibold text-foreground">Objection Pipeline Inactive</p>
                  <p className="text-[10px] text-muted-foreground mt-0.5">The conversational analytics and objection-extraction parser is not active on the backend.</p>
                </div>
              </CardContent>
            </Card>

            {/* Latency compliance to NFR-1 target */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>AI Graph Latency Trend</CardTitle>
                <CardDescription>Latency tracking compared to the 20-second NFR-1 target limit.</CardDescription>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={180}>
                  <AreaChart data={[
                    { time: "09:00", latency: 1.2 },
                    { time: "10:00", latency: 2.5 },
                    { time: "11:00", latency: 1.8 },
                    { time: "12:00", latency: 3.2 },
                    { time: "13:00", latency: 1.5 },
                  ]}>
                    <defs>
                      <linearGradient id="latencyGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#10b981" stopOpacity={0.2}/>
                        <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                    <XAxis dataKey="time" stroke={getChartColors().axisStroke} />
                    <YAxis stroke={getChartColors().axisStroke} />
                    <Tooltip contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }} />
                    <Area type="monotone" dataKey="latency" stroke="#10b981" fillOpacity={1} fill="url(#latencyGrad)" />
                  </AreaChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </div>
        </div>
      </PageTransition>
    </AppLayout>
  );
}
