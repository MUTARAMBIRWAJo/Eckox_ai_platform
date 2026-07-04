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
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { useState } from "react";
import { useAuth } from "@/hooks/use-auth";
import { canAccess } from "@/lib/rbac";

interface NavItem {
  label: string;
  href: string;
  icon: React.ReactNode;
}

const NAV_ITEMS: NavItem[] = [
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

export function MobileSidebar() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const { user } = useAuth();

  const visibleItems = NAV_ITEMS.filter((item) => canAccess(user, item.href));

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger render={<Button variant="ghost" size="icon" className="lg:hidden" />}>
        <Menu className="w-4 h-4" />
      </SheetTrigger>
      <SheetContent side="left" className="w-64 p-0">
        <SheetHeader className="border-b border-border px-6 py-4 text-left">
          <div className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm">
              E
            </div>
            <span className="font-semibold">EckoX</span>
          </div>
        </SheetHeader>
        <nav className="flex flex-col gap-1 px-3 py-4">
          {visibleItems.map((item) => {
            const isActive = pathname === item.href || pathname.startsWith(item.href + "/");
            return (
              <Link key={item.href} href={item.href} onClick={() => setOpen(false)} className="block">
                <div
                  className={cn(
                    "w-full justify-start gap-3 text-sm rounded-lg border border-transparent px-3 py-2 transition-colors flex items-center",
                    isActive
                      ? "bg-primary text-primary-foreground"
                      : "hover:bg-secondary text-foreground"
                  )}
                >
                  <span className="flex-shrink-0">{item.icon}</span>
                  <span className="truncate">{item.label}</span>
                </div>
              </Link>
            );
          })}
        </nav>
      </SheetContent>
    </Sheet>
  );
}
