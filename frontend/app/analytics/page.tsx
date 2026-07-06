"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { DashboardAPI, DashboardStats } from "@/lib/api/dashboard.api";
import { useAuth } from "@/hooks/use-auth";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { getChartColors } from "@/lib/chart-colors";
import { AlertCircle, TrendingUp, Cpu, Calendar, ShieldAlert } from "lucide-react";

export default function AnalyticsPage() {
  const { user, logout } = useAuth();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadData = async () => {
      try {
        const statsRes = await DashboardAPI.getStats();
        if (statsRes.success && statsRes.data) {
          setStats(statsRes.data);
        } else {
          setError(statsRes.error || "Failed to load analytics statistics");
        }
      } catch (err: any) {
        setError("Failed to fetch analytics from the backend");
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const chartColors = getChartColors();

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
        <div className="space-y-6">
          {/* Header */}
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              Analytics & Insights
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Real-time conversion, pipeline counts, and engine performance metrics.
            </p>
          </div>

          {error && (
            <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-4 text-sm text-destructive flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 shrink-0" />
              {error}
            </div>
          )}

          {/* CRM Pipeline Distribution */}
          <Card className="border-border bg-card/60 backdrop-blur-md">
            <CardHeader>
              <CardTitle>CRM Pipeline Stage Distribution</CardTitle>
              <CardDescription>Visualizing lead volumes per pipeline stage from the backend.</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="h-64 bg-secondary/20 rounded-2xl animate-pulse" />
              ) : stats?.pipeline?.length ? (
                <ResponsiveContainer width="100%" height={260}>
                  <BarChart data={stats.pipeline}>
                    <CartesianGrid strokeDasharray="3 3" stroke={chartColors.gridStroke} />
                    <XAxis dataKey="stage" stroke={chartColors.axisStroke} tick={{ fontSize: 11 }} />
                    <YAxis stroke={chartColors.axisStroke} />
                    <Tooltip contentStyle={{ backgroundColor: chartColors.tooltipBg, border: `1px solid ${chartColors.tooltipBorder}` }} />
                    <Bar dataKey="count" fill="#10b981" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-64 flex items-center justify-center text-sm text-muted-foreground">
                  No pipeline stage data found.
                </div>
              )}
            </CardContent>
          </Card>

          {/* Key Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs uppercase text-muted-foreground flex items-center gap-1.5">
                  <TrendingUp className="w-4 h-4 text-emerald-500" />
                  AI Conversion Rate
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-extrabold text-foreground">
                  {loading ? "—" : `${stats?.conversionRate ?? 0}%`}
                </div>
                <p className="text-[10px] text-muted-foreground mt-2">
                  Total leads successfully qualified by the sales agents.
                </p>
              </CardContent>
            </Card>

            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs uppercase text-muted-foreground flex items-center gap-1.5">
                  <Cpu className="w-4 h-4 text-emerald-500" />
                  Avg AI Reasoning Latency
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-extrabold text-foreground">
                  {loading ? "—" : `${(stats ? stats.avgLatencyMs / 1000 : 0).toFixed(2)}s`}
                </div>
                <p className="text-[10px] text-muted-foreground mt-2">
                  Combined multi-provider execution latency.
                </p>
              </CardContent>
            </Card>

            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader className="pb-2">
                <CardTitle className="text-xs uppercase text-muted-foreground flex items-center gap-1.5">
                  <Calendar className="w-4 h-4 text-emerald-500" />
                  Active Decisions
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-extrabold text-foreground">
                  {loading ? "—" : stats?.totalDecisions ?? 0}
                </div>
                <p className="text-[10px] text-muted-foreground mt-2">
                  Total AI reasoning graph path executions.
                </p>
              </CardContent>
            </Card>
          </div>

          {/* Team Performance & Forecast (Honest Empty States) */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>Team Performance</CardTitle>
                <CardDescription>Individual agent sales metrics.</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col items-center justify-center h-48 text-center p-6 border border-dashed border-border rounded-2xl bg-secondary/5">
                <AlertCircle className="w-8 h-8 text-muted-foreground/60 mb-2" />
                <p className="text-xs font-semibold">Team Performance Inactive</p>
                <p className="text-[10px] text-muted-foreground mt-1">
                  Team attribution tracking requires the user permissions and multi-agent module to be activated on the backend.
                </p>
              </CardContent>
            </Card>

            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle>3-Month Revenue Forecast</CardTitle>
                <CardDescription>Projected sales pipeline value.</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col items-center justify-center h-48 text-center p-6 border border-dashed border-border rounded-2xl bg-secondary/5">
                <AlertCircle className="w-8 h-8 text-muted-foreground/60 mb-2" />
                <p className="text-xs font-semibold">Forecast Model Inactive</p>
                <p className="text-[10px] text-muted-foreground mt-1">
                  Statistical forecasting models are not loaded in the current environment context.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </PageTransition>
    </AppLayout>
  );
}
