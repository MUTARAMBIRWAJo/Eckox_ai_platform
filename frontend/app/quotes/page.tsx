"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { DataTable, Column } from "@/components/common/data-table";
import { DataTableSkeleton } from "@/components/common/skeleton-loader";
import { quotesAPI, authAPI, AuthUser } from "@/lib/api";
import { Plus, Download, Send } from "lucide-react";

interface Quote {
  id: string;
  leadId: string;
  total: number;
  createdAt: string;
  status: string;
}

export default function QuotesPage() {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [currentUser, quotesData] = await Promise.all([
          authAPI.getCurrentUser(),
          quotesAPI.getQuotes(20),
        ]);
        setUser(currentUser);
        setQuotes(quotesData.quotes);
      } catch (err) {
        console.error("Failed to load quotes:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const columns: Column<Quote>[] = [
    {
      key: "id",
      label: "Quote ID",
      sortable: true,
      render: (value) => (
        <code className="text-xs bg-secondary px-2 py-1 rounded">{value}</code>
      ),
    },
    {
      key: "leadId",
      label: "Lead",
      sortable: true,
    },
    {
      key: "total",
      label: "Amount",
      render: (value: number) => (
        <span className="font-medium">${value.toLocaleString()}</span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (value: string) => {
        const statusColors: Record<string, string> = {
          draft: "bg-slate-500/10 text-slate-400",
          sent: "bg-blue-500/10 text-blue-400",
          accepted: "bg-primary/10 text-primary",
          rejected: "bg-destructive/10 text-destructive",
        };
        return (
          <Badge className={statusColors[value] || "bg-secondary"}>
            {value}
          </Badge>
        );
      },
    },
    {
      key: "createdAt",
      label: "Created",
      render: (value: string) => (
        <span className="text-sm text-muted-foreground">
          {new Date(value).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: "id",
      label: "Actions",
      render: () => (
        <div className="flex gap-2">
          <Button size="sm" variant="ghost" title="Download">
            <Download className="w-4 h-4" />
          </Button>
          <Button size="sm" variant="ghost" title="Send">
            <Send className="w-4 h-4" />
          </Button>
        </div>
      ),
    },
  ];

  const stats = {
    total: quotes.length,
    sent: quotes.filter((q) => q.status === "sent").length,
    accepted: quotes.filter((q) => q.status === "accepted").length,
    value: quotes.reduce((sum, q) => sum + q.total, 0),
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
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Quotes</h1>
            <p className="text-muted-foreground mt-1">Create and manage sales quotes with CPQ</p>
          </div>
          <Button className="btn-primary gap-2">
            <Plus className="w-4 h-4" />
            New Quote
          </Button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{stats.total}</div>
              <p className="text-xs text-muted-foreground">Total Quotes</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{stats.sent}</div>
              <p className="text-xs text-muted-foreground">Sent</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{stats.accepted}</div>
              <p className="text-xs text-muted-foreground">Accepted</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">${(stats.value / 1000).toFixed(0)}K</div>
              <p className="text-xs text-muted-foreground">Total Value</p>
            </CardContent>
          </Card>
        </div>

        {/* Quotes Table */}
        {loading ? (
          <DataTableSkeleton />
        ) : (
          <DataTable columns={columns} data={quotes} hoverable striped />
        )}
      </div>
    </AppLayout>
  );
}
