"use client";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { TrendingUp, TrendingDown } from "lucide-react";
import { cn } from "@/lib/utils";

export interface KPICardProps {
  title: string;
  value: string;
  description?: string;
  change?: number;
  icon?: React.ReactNode;
  loading?: boolean;
}

export function KPICard({
  title,
  value,
  description,
  change,
  icon,
  loading,
}: KPICardProps) {
  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="skeleton h-6 w-24" />
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            <div className="skeleton h-10 w-16" />
            <div className="skeleton h-4 w-12" />
          </div>
        </CardContent>
      </Card>
    );
  }

  const isPositive = change ? change > 0 : false;

  return (
    <Card className="relative group overflow-hidden transition-all duration-300 hover:shadow-lg hover:border-primary/20 hover:-translate-y-0.5">
      <div className="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3 relative z-10">
        <CardTitle className="text-sm font-semibold text-muted-foreground">{title}</CardTitle>
        {icon && <div className="text-muted-foreground group-hover:text-primary transition-colors group-hover:scale-110 duration-300">{icon}</div>}
      </CardHeader>
      <CardContent className="relative z-10">
        <div className="space-y-4">
          <div className="text-4xl font-bold tracking-tighter text-foreground">{value}</div>
          {change !== undefined && (
            <div className="flex items-center gap-2 text-xs font-semibold">
              {isPositive ? (
                <>
                  <div className="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-primary/10">
                    <TrendingUp className="w-3 h-3 text-primary" />
                    <span className="text-primary">+{change.toFixed(1)}%</span>
                  </div>
                </>
              ) : (
                <>
                  <div className="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-destructive/10">
                    <TrendingDown className="w-3 h-3 text-destructive" />
                    <span className="text-destructive">{change.toFixed(1)}%</span>
                  </div>
                </>
              )}
              <span className="text-muted-foreground text-xs font-normal">vs last month</span>
            </div>
          )}
          {description && (
            <CardDescription className="text-xs leading-relaxed">{description}</CardDescription>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
