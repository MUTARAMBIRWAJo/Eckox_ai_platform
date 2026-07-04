"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { authAPI, AuthUser } from "@/lib/api";
import { Plus, Workflow, Zap, Trash2 } from "lucide-react";

const AUTOMATIONS = [
  {
    id: 1,
    name: "Auto-qualify high-value leads",
    description: "Automatically qualify leads with deal value > $50K",
    status: "active",
    trigger: "Lead Value > $50K",
    action: "Mark as Qualified",
    executions: 127,
  },
  {
    id: 2,
    name: "Send follow-up emails",
    description: "Send email reminder 3 days after quote sent",
    status: "active",
    trigger: "Quote Sent",
    action: "Send Email",
    executions: 312,
  },
  {
    id: 3,
    name: "Slack notifications for closed deals",
    description: "Notify team on Slack when deal is closed",
    status: "active",
    trigger: "Deal Closed",
    action: "Send Slack Message",
    executions: 89,
  },
  {
    id: 4,
    name: "Archive inactive leads",
    description: "Archive leads with no activity for 60 days",
    status: "inactive",
    trigger: "No Activity 60 Days",
    action: "Archive Lead",
    executions: 0,
  },
  {
    id: 5,
    name: "Update CRM on product purchase",
    description: "Automatically update CRM when product is purchased",
    status: "active",
    trigger: "Product Purchased",
    action: "Update CRM Field",
    executions: 54,
  },
];

export default function AutomationPage() {
  const [automations, setAutomations] = useState(AUTOMATIONS);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadUser = async () => {
      try {
        const currentUser = await authAPI.getCurrentUser();
        setUser(currentUser);
      } catch (err) {
        console.error("Failed to load user:", err);
      } finally {
        setLoading(false);
      }
    };

    loadUser();
  }, []);

  const toggleAutomation = (id: number) => {
    setAutomations((prevs) =>
      prevs.map((auto) =>
        auto.id === id
          ? { ...auto, status: auto.status === "active" ? "inactive" : "active" }
          : auto
      )
    );
  };

  const deleteAutomation = (id: number) => {
    setAutomations((prevs) => prevs.filter((auto) => auto.id !== id));
  };

  const activeCount = automations.filter((a) => a.status === "active").length;
  const totalExecutions = automations.reduce((sum, a) => sum + a.executions, 0);

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
            <h1 className="text-3xl font-bold">Automation</h1>
            <p className="text-muted-foreground mt-1">Create workflows to automate repetitive tasks</p>
          </div>
          <Button className="btn-primary gap-2">
            <Plus className="w-4 h-4" />
            New Automation
          </Button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{activeCount}</div>
              <p className="text-xs text-muted-foreground">Active Automations</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{automations.length}</div>
              <p className="text-xs text-muted-foreground">Total</p>
            </CardContent>
          </Card>
          <Card className="card">
            <CardContent className="pt-6">
              <div className="text-2xl font-bold">{totalExecutions}</div>
              <p className="text-xs text-muted-foreground">Total Executions</p>
            </CardContent>
          </Card>
        </div>

        {/* Automations List */}
        <div className="space-y-3">
          {automations.map((automation) => (
            <Card key={automation.id} className="card hover:shadow-md transition-shadow">
              <CardContent className="pt-6">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-4 flex-1">
                    <div className="mt-1">
                      <Workflow className="w-5 h-5 text-muted-foreground" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-semibold">{automation.name}</h3>
                        <Badge 
                          className={automation.status === "active" 
                            ? "bg-primary/10 text-primary" 
                            : "bg-secondary"}
                        >
                          {automation.status}
                        </Badge>
                      </div>
                      <p className="text-sm text-muted-foreground mb-3">{automation.description}</p>
                      <div className="flex gap-4 text-xs text-muted-foreground">
                        <div className="flex items-center gap-1">
                          <span className="font-medium">Trigger:</span>
                          <span>{automation.trigger}</span>
                        </div>
                        <div className="flex items-center gap-1">
                          <span className="font-medium">Action:</span>
                          <span>{automation.action}</span>
                        </div>
                        <div className="flex items-center gap-1">
                          <span className="font-medium">Runs:</span>
                          <span>{automation.executions}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 flex-shrink-0">
                    <Switch
                      checked={automation.status === "active"}
                      onCheckedChange={() => toggleAutomation(automation.id)}
                    />
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => deleteAutomation(automation.id)}
                    >
                      <Trash2 className="w-4 h-4 text-destructive" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </AppLayout>
  );
}
