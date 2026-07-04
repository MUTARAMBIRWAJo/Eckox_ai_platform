"use client";

import Link from "next/link";
import { ShieldAlert, Home, ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function AccessDeniedPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-md text-center space-y-6">
        {/* Shield Icon with glowing outline */}
        <div className="relative inline-flex items-center justify-center">
          <div className="absolute inset-0 bg-destructive/20 blur-xl rounded-full" />
          <div className="relative flex h-20 w-20 items-center justify-center rounded-2xl border border-destructive/30 bg-destructive/10 text-destructive">
            <ShieldAlert className="h-10 w-10" />
          </div>
        </div>

        <div className="space-y-2">
          <h1 className="text-3xl font-bold tracking-tight text-foreground">Access Denied</h1>
          <p className="text-muted-foreground text-sm max-w-sm mx-auto">
            You do not have the required permissions to access this resource. Please contact your administrator if you believe this is an error.
          </p>
        </div>

        <div className="flex flex-col sm:flex-row gap-3 justify-center pt-4">
          <Button
            variant="outline"
            className="flex items-center gap-2"
            onClick={() => window.history.back()}
          >
            <ArrowLeft className="w-4 h-4" />
            Go Back
          </Button>

          <Link href="/dashboard" passHref legacyBehavior>
            <Button className="btn-primary flex items-center gap-2">
              <Home className="w-4 h-4" />
              Return Dashboard
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
}
