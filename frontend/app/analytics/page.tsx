"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { authAPI, AuthUser, analyticsAPI } from "@/lib/api";
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, Table as ChartTable, TableBody, TableCell, TableHead, TableRow } from "recharts";

interface TeamMember {
  name: string;
  closed: number;
  pipeline: number;
  winRate: number;
}

export default function AnalyticsPage() {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [teamPerf, setTeamPerf] = useState<TeamMember[]>([]);
  const [forecast, setForecast] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, perf, forecData] = await Promise.all([
          authAPI.getCurrentUser(),
          analyticsAPI.getTeamPerformance(),
          analyticsAPI.getForecast(3),
        ]);
        setUser(currentUser);
        setTeamPerf(perf);
        setForecast(forecData);
      } catch (err) {
        console.error("Failed to load analytics:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

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
          <h1 className="text-3xl font-bold">Analytics</h1>
          <p className="text-muted-foreground mt-1">Sales performance and team metrics</p>
        </div>

        {/* Team Performance */}
        <Card className="card">
          <CardHeader>
            <CardTitle>Team Performance</CardTitle>
            <CardDescription>Individual sales metrics</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 font-semibold">Sales Rep</th>
                    <th className="text-right py-3 px-4 font-semibold">Closed Deals</th>
                    <th className="text-right py-3 px-4 font-semibold">Pipeline</th>
                    <th className="text-right py-3 px-4 font-semibold">Win Rate</th>
                  </tr>
                </thead>
                <tbody>
                  {teamPerf.map((member) => (
                    <tr key={member.name} className="border-b border-border hover:bg-secondary/50">
                      <td className="py-3 px-4 font-medium">{member.name}</td>
                      <td className="text-right py-3 px-4">{member.closed}</td>
                      <td className="text-right py-3 px-4">${member.pipeline.toLocaleString()}</td>
                      <td className="text-right py-3 px-4">
                        <span className="font-semibold text-primary">{(member.winRate * 100).toFixed(0)}%</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        {/* Sales Forecast */}
        <Card className="card">
          <CardHeader>
            <CardTitle>3-Month Forecast</CardTitle>
            <CardDescription>Projected revenue based on pipeline</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-64 bg-secondary/20 rounded animate-pulse" />
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={forecast}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2a" />
                  <XAxis dataKey="month" stroke="#7a7a7a" />
                  <YAxis stroke="#7a7a7a" />
                  <Tooltip 
                    contentStyle={{ backgroundColor: "#1a1a1a", border: "1px solid #2a2a2a" }}
                    formatter={(value) => `$${value.toLocaleString()}`}
                  />
                  <Legend />
                  <Line
                    type="monotone"
                    dataKey="forecast"
                    stroke="#10b981"
                    strokeWidth={2}
                    name="Forecast"
                    dot={{ fill: "#10b981", r: 4 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card className="card">
            <CardHeader>
              <CardTitle className="text-sm">Avg Deal Size</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">$24.5K</div>
              <p className="text-xs text-muted-foreground mt-2">Up 12% from last month</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardHeader>
              <CardTitle className="text-sm">Sales Cycle</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">34 days</div>
              <p className="text-xs text-muted-foreground mt-2">Average time to close</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardHeader>
              <CardTitle className="text-sm">Conversion Rate</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">24%</div>
              <p className="text-xs text-muted-foreground mt-2">Lead to deal ratio</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
