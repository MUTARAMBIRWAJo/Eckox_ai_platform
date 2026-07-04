"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { KPICard } from "@/components/common/kpi-card";
import { KPICardSkeleton } from "@/components/common/skeleton-loader";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { TrendingUp, Users, FileText, Target } from "lucide-react";
import { dashboardAPI } from "@/lib/api";
import { useAuth } from "@/hooks/use-auth";
import { LineChart, Line, BarChart, Bar, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { ChartContainer, ChartTooltip, ChartLegend } from "@/components/ui/chart";
import { getChartColors } from "@/lib/chart-colors";

interface DashboardKPI {
  title: string;
  value: string;
  change: number;
  isPositive: boolean;
  icon: string;
}

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const [kpis, setKpis] = useState<DashboardKPI[]>([]);
  const [revenueChart, setRevenueChart] = useState<any[]>([]);
  const [funnelChart, setFunnelChart] = useState<any[]>([]);
  const [activityChart, setActivityChart] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadDashboard = async () => {
      try {
        const [kpiData, revenue, funnel, activity] = await Promise.all([
          dashboardAPI.getKPIs(),
          dashboardAPI.getRevenueChart(),
          dashboardAPI.getFunnelChart(),
          dashboardAPI.getActivityChart(),
        ]);

        setKpis(kpiData);
        setRevenueChart(revenue);
        setFunnelChart(funnel);
        setActivityChart(activity);
      } catch (err) {
        console.error("Failed to load dashboard:", err);
      } finally {
        setLoading(false);
      }
    };

    loadDashboard();
  }, []);

  const getIconForKPI = (icon: string) => {
    const iconMap: Record<string, React.ReactNode> = {
      TrendingUp: <TrendingUp className="w-4 h-4" />,
      CheckCircle: <div className="w-4 h-4 rounded-full bg-primary/30" />,
      Users: <Users className="w-4 h-4" />,
      Target: <Target className="w-4 h-4" />,
    };
    return iconMap[icon];
  };

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
          <div className="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="page-title">Dashboard</h1>
              <p className="text-muted-foreground mt-2 text-sm">Welcome back, {user?.name}. Here&apos;s your sales performance.</p>
            </div>
            <div className="flex gap-2">
              {user?.roles?.map(role => (
                <span key={role} className="px-3 py-1 rounded-full bg-primary/20 text-xs font-semibold text-primary capitalize">
                  {role}
                </span>
              ))}
            </div>
          </div>

          {/* KPI Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {loading ? (
            Array.from({ length: 4 }).map((_, i) => <KPICardSkeleton key={i} />)
          ) : (
            kpis.map((kpi) => (
              <KPICard
                key={kpi.title}
                title={kpi.title}
                value={kpi.value}
                change={kpi.change}
                icon={getIconForKPI(kpi.icon)}
              />
            ))
          )}
        </div>

        {/* Charts Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Revenue Trend */}
          <Card className="card">
            <CardHeader>
              <CardTitle>Revenue Trend</CardTitle>
              <CardDescription>Monthly revenue over the last 6 months</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="h-64 bg-secondary/20 rounded animate-pulse" />
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <AreaChart data={revenueChart}>
                    <defs>
                      <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor={getChartColors().primary} stopOpacity={0.3}/>
                        <stop offset="95%" stopColor={getChartColors().primary} stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                    <XAxis dataKey="month" stroke={getChartColors().axisStroke} />
                    <YAxis stroke={getChartColors().axisStroke} />
                    <Tooltip 
                      contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }}
                      formatter={(value) => `$${value.toLocaleString()}`}
                    />
                    <Area
                      type="monotone"
                      dataKey="value"
                      stroke={getChartColors().primary}
                      fillOpacity={1}
                      fill="url(#colorValue)"
                    />
                  </AreaChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          {/* Sales Funnel */}
          <Card className="card">
            <CardHeader>
              <CardTitle>Sales Funnel</CardTitle>
              <CardDescription>Lead conversion by stage</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="h-64 bg-secondary/20 rounded animate-pulse" />
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={funnelChart} layout="vertical">
                    <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                    <XAxis type="number" stroke={getChartColors().axisStroke} />
                    <YAxis dataKey="stage" type="category" stroke={getChartColors().axisStroke} width={80} />
                    <Tooltip 
                      contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }}
                      formatter={(value) => [value, "Count"]}
                    />
                    <Bar dataKey="count" fill={getChartColors().accent} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          {/* Activity */}
          <Card className="card">
            <CardHeader>
              <CardTitle>Team Activity</CardTitle>
              <CardDescription>Activities per day this week</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="h-64 bg-secondary/20 rounded animate-pulse" />
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={activityChart}>
                    <CartesianGrid strokeDasharray="3 3" stroke={getChartColors().gridStroke} />
                    <XAxis dataKey="day" stroke={getChartColors().axisStroke} />
                    <YAxis stroke={getChartColors().axisStroke} />
                    <Tooltip 
                      contentStyle={{ backgroundColor: getChartColors().tooltipBg, border: `1px solid ${getChartColors().tooltipBorder}` }}
                      formatter={(value) => [value, "Activities"]}
                    />
                    <Line
                      type="monotone"
                      dataKey="count"
                      stroke={getChartColors().warning}
                      strokeWidth={2}
                      dot={{ fill: getChartColors().warning, r: 4 }}
                    />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          {/* Quick Actions */}
          <Card className="card">
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
              <CardDescription>Common tasks</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button className="w-full btn-primary justify-start">
                <FileText className="w-4 h-4 mr-2" />
                Create New Quote
              </Button>
              <Button className="w-full btn-secondary justify-start">
                <Users className="w-4 h-4 mr-2" />
                Add New Lead
              </Button>
              <Button className="w-full btn-secondary justify-start">
                <TrendingUp className="w-4 h-4 mr-2" />
                View Analytics
              </Button>
              <Button className="w-full btn-secondary justify-start">
                <Target className="w-4 h-4 mr-2" />
                Chat with AI
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
      </PageTransition>
    </AppLayout>
  );
}
