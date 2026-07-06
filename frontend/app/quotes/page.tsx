"use client";

import { AppLayout } from "@/components/layout/app-layout";
import { Card, CardContent } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import { FileText, Sparkles } from "lucide-react";

export default function QuotesPage() {
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
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
            Sales Quotes (CPQ)
          </h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Configure, Price, Quote (CPQ) module.
          </p>
        </div>

        {/* Quotes Empty/Inactive State */}
        <Card className="border-border bg-card/60 backdrop-blur-md">
          <CardContent className="pt-12 pb-12 flex flex-col items-center justify-center text-center p-6">
            <FileText className="w-12 h-12 text-muted-foreground/30 mb-3 animate-pulse" />
            <h3 className="text-base font-semibold text-foreground">Quote Management</h3>
            <p className="text-xs text-muted-foreground mt-1.5 max-w-sm">
              The CRM quote generation and CPQ dashboard is handled dynamically inside the AI Sales Agent chat thread.
            </p>
            <div className="mt-6 flex items-start gap-2 bg-secondary/20 p-3 rounded-xl border border-border text-[10px] text-muted-foreground text-left max-w-sm">
              <Sparkles className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <span>Ask the AI Sales Agent in the Chat view to "generate a quote for Marie" or "pricing calculations". The agent will calculate regional compliance tariffs automatically.</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
