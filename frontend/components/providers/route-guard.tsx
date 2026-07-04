"use client";

import { useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useAuth } from "@/hooks/use-auth";
import { canAccess } from "@/lib/rbac";
import { Spinner } from "@/components/ui/spinner";

const PUBLIC_ROUTES = ["/login", "/register", "/signup"];

export function RouteGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isAuthenticated, isLoading, checkAuth } = useAuth();

  // Run auth check on initialization and keep session fresh silently
  useEffect(() => {
    checkAuth();
    
    // Silent auto-refresh session every 5 minutes
    const interval = setInterval(() => {
      checkAuth();
    }, 5 * 60 * 1000);

    return () => clearInterval(interval);
  }, [checkAuth]);

  // Handle route protection
  useEffect(() => {
    if (isLoading) return;

    const isPublic = PUBLIC_ROUTES.includes(pathname);

    if (isAuthenticated) {
      // If user is authenticated and trying to access login/register, send to dashboard
      if (isPublic) {
        router.replace("/dashboard");
        return;
      }

      // Check role based authorization
      if (!canAccess(user, pathname)) {
        router.replace("/403");
      }
    } else {
      // If not authenticated and trying to access protected route, send to login
      if (!isPublic) {
        router.replace("/login");
      }
    }
  }, [isAuthenticated, isLoading, pathname, router, user]);

  // Show a loading screen while resolving authentication status on protected pages
  const isPublicRoute = PUBLIC_ROUTES.includes(pathname);
  if (isLoading && !isPublicRoute) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <div className="flex flex-col items-center gap-4">
          <Spinner className="w-10 h-10 text-primary animate-spin" />
          <p className="text-sm text-muted-foreground animate-pulse">Initializing session...</p>
        </div>
      </div>
    );
  }

  // If unauthorized for a protected page and not loading, we block rendering until redirect completes
  if (!isAuthenticated && !isPublicRoute && !isLoading) {
    return null;
  }

  if (isAuthenticated && !isPublicRoute && !isLoading && !canAccess(user, pathname)) {
    return null;
  }

  return <>{children}</>;
}
