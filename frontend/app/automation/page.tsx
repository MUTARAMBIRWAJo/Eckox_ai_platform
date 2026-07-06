"use client";

import { AppLayout } from "@/components/layout/app-layout";
import { Card, CardContent } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import { Workflow, Info } from "lucide-react";

export default function AutomationPage() {
  const { user, logout } = useAuth();

  return (
    <AppLayout
      headerProps={{
        user: user
          ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email }
          : undefined,
        onLogout: () => logout(),
      }}
    >
      <div className="space-y-6 max-w-2xl">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
            Workflow Automation
          </h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Create rules and actions to automate CRM lead processes.
          </p>
        </div>

        {/* Automation Inactive State */}
        <Card className="border-border bg-card/60 backdrop-blur-md">
          <CardContent className="pt-12 pb-12 flex flex-col items-center justify-center text-center p-6">
            <Workflow className="w-12 h-12 text-muted-foreground/30 mb-3 animate-pulse" />
            <h3 className="text-base font-semibold text-foreground">Automation Rules Inactive</h3>
            <p className="text-xs text-muted-foreground mt-1.5 max-w-sm">
              The workflow automation and orchestration system is managed directly via the AI Sales Agent's LangGraph routing logic.
            </p>
            <div className="mt-6 flex items-start gap-2 bg-secondary/20 p-3 rounded-xl border border-border text-[10px] text-muted-foreground text-left max-w-sm">
              <Info className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <span>To build custom routing flows or automate lead notifications, configure the reasoning pathways inside the AI Sales Agent config file.</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
