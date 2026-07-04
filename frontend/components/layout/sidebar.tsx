"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  MessageSquare,
  Users,
  FileText,
  Package,
  BookOpen,
  BarChart3,
  Zap,
  Bell,
  Settings,
  Menu,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { useAuth } from "@/hooks/use-auth";
import { canAccess } from "@/lib/rbac";

interface SidebarItem {
  label: string;
  href: string;
  icon: React.ReactNode;
  badge?: number;
}

const SIDEBAR_ITEMS: SidebarItem[] = [
  { label: "Dashboard", href: "/dashboard", icon: <LayoutDashboard className="w-4 h-4" /> },
  { label: "AI Chat", href: "/chat", icon: <MessageSquare className="w-4 h-4" /> },
  { label: "CRM Leads", href: "/leads", icon: <Users className="w-4 h-4" /> },
  { label: "Conversations", href: "/conversations", icon: <MessageSquare className="w-4 h-4" /> },
  { label: "Quotes", href: "/quotes", icon: <FileText className="w-4 h-4" /> },
  { label: "Products", href: "/products", icon: <Package className="w-4 h-4" /> },
  { label: "Knowledge Base", href: "/knowledge", icon: <BookOpen className="w-4 h-4" /> },
  { label: "Analytics", href: "/analytics", icon: <BarChart3 className="w-4 h-4" /> },
  { label: "Automation", href: "/automation", icon: <Zap className="w-4 h-4" /> },
  { label: "Notifications", href: "/notifications", icon: <Bell className="w-4 h-4" /> },
  { label: "Settings", href: "/settings", icon: <Settings className="w-4 h-4" /> },
];

export function Sidebar() {
  const pathname = usePathname();
  const [isOpen, setIsOpen] = useState(true);
  const { user } = useAuth();

  const visibleItems = SIDEBAR_ITEMS.filter((item) => canAccess(user, item.href));

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 z-30 hidden h-screen border-r border-border bg-card transition-all duration-300 lg:block",
        isOpen ? "w-64" : "w-20"
      )}
    >
      <div className="flex h-full flex-col">
        {/* Sidebar Header */}
        <div className="flex items-center justify-between border-b border-border px-4 py-5 gap-2">
          {isOpen && (
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm">
                E
              </div>
              <span className="font-semibold text-sm">EckoX</span>
            </div>
          )}
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setIsOpen(!isOpen)}
            className="ml-auto h-8 w-8"
          >
            {isOpen ? <X className="w-4 h-4" /> : <Menu className="w-4 h-4" />}
          </Button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
          {visibleItems.map((item) => {
            const isActive = pathname === item.href || pathname.startsWith(item.href + "/");
            return (
              <Link key={item.href} href={item.href}>
                <Button
                  variant={isActive ? "default" : "ghost"}
                  className={cn(
                    "w-full justify-start gap-3 text-sm transition-all duration-200",
                    isActive && "shadow-md",
                    !isOpen && "justify-center"
                  )}
                  title={!isOpen ? item.label : ""}
                >
                  <span className="flex-shrink-0">{item.icon}</span>
                  {isOpen && (
                    <div className="flex flex-1 items-center justify-between min-w-0">
                      <span className="truncate">{item.label}</span>
                      {item.badge && (
                        <span className="ml-auto flex-shrink-0 rounded-full bg-primary/20 px-2 py-0.5 text-xs font-semibold text-primary">
                          {item.badge}
                        </span>
                      )}
                    </div>
                  )}
                </Button>
              </Link>
            );
          })}
        </nav>

        {/* Sidebar Footer */}
        <div className={cn(
          "border-t border-border bg-card/50 p-3 transition-all duration-300",
          !isOpen && "px-2"
        )}>
          <div className={cn(
            "text-xs text-muted-foreground transition-all duration-300",
            isOpen ? "text-center" : "hidden"
          )}>
            <p className="font-semibold">EckoX</p>
            <p>v1.0.0</p>
          </div>
        </div>
      </div>
    </aside>
  );
}
