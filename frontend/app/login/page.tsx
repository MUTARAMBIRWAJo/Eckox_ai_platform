"use client";

import { useState, FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/hooks/use-auth";
import { ArrowRight, Zap } from "lucide-react";

export default function LoginPage() {
  const router = useRouter();
  const { login, error: authError, clearError } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [localError, setLocalError] = useState("");

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLocalError("");
    clearError();
    setLoading(true);

    try {
      const success = await login({ email, password });
      if (success) {
        router.push("/dashboard");
      }
    } catch (err) {
      setLocalError("An unexpected error occurred");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen bg-background">
      {/* Left Side - Branding */}
      <div className="hidden md:flex md:w-1/2 flex-col justify-between bg-gradient-to-br from-primary/5 via-background to-accent/5 p-12 border-r border-border">
        <div>
          <div className="flex items-center gap-3 mb-4">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">
              E
            </div>
            <span className="text-xl font-bold">EckoX</span>
          </div>
          <p className="text-muted-foreground text-sm">Enterprise AI Sales Platform</p>
        </div>

        <div className="space-y-8">
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <Zap className="h-5 w-5 text-primary" />
              </div>
              <div>
                <h3 className="font-semibold text-foreground">AI-Powered Sales</h3>
                <p className="text-sm text-muted-foreground">Intelligent lead scoring and insights</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-accent/10">
                <Zap className="h-5 w-5 text-accent" />
              </div>
              <div>
                <h3 className="font-semibold text-foreground">Unified CRM</h3>
                <p className="text-sm text-muted-foreground">Manage leads, deals, and customers</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <Zap className="h-5 w-5 text-primary" />
              </div>
              <div>
                <h3 className="font-semibold text-foreground">Real-Time Analytics</h3>
                <p className="text-sm text-muted-foreground">Track performance with advanced dashboards</p>
              </div>
            </div>
          </div>

          <div className="border-t border-border pt-8">
            <p className="text-xs text-muted-foreground mb-4">Enterprise features trusted by leading companies</p>
            <div className="flex flex-wrap gap-2">
              {["AI Chat", "Advanced CRM", "CPQ System", "Analytics", "Automation"].map((feature) => (
                <span key={feature} className="px-3 py-1 rounded-full bg-secondary text-xs font-medium text-foreground">
                  {feature}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Right Side - Login Form */}
      <div className="flex-1 flex flex-col items-center justify-center px-6 py-12">
        <div className="w-full max-w-sm space-y-8">
          {/* Mobile Header */}
          <div className="md:hidden flex items-center gap-3 mb-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">
              E
            </div>
            <span className="text-xl font-bold">EckoX</span>
          </div>

          {/* Form Header */}
          <div className="space-y-2">
            <h1 className="text-3xl font-bold tracking-tight">Welcome Back</h1>
            <p className="text-muted-foreground">Sign in to your sales platform</p>
          </div>

          {/* Error Message */}
          {(localError || authError) && (
            <div className="p-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-sm font-medium">
              {localError || authError}
            </div>
          )}

          {/* Login Form */}
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <label htmlFor="email" className="label-base">
                Email
              </label>
              <Input
                id="email"
                type="email"
                placeholder="you@company.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={loading}
                required
                className="h-11"
              />
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label htmlFor="password" className="label-base">
                  Password
                </label>
                <Link href="/forgot-password" className="text-sm font-medium text-primary hover:text-primary/80 transition-colors">
                  Forgot password?
                </Link>
              </div>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={loading}
                required
                className="h-11"
              />
            </div>

            <Button
              type="submit"
              disabled={loading}
              className="w-full h-11 btn-primary text-base font-semibold"
            >
              {loading ? (
                <>
                  <Spinner className="w-4 h-4" />
                  <span>Signing in...</span>
                </>
              ) : (
                <>
                  Sign In
                  <ArrowRight className="w-4 h-4 ml-1" />
                </>
              )}
            </Button>
          </form>

          {/* Divider */}
          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-border" />
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="bg-background px-2 text-muted-foreground text-xs font-medium">or try demo</span>
            </div>
          </div>

          {/* Demo Button */}
          <Button
            type="button"
            variant="outline"
            className="w-full h-11 font-medium"
            onClick={() => {
              setEmail("demo@eckoX.com");
              setPassword("demo");
            }}
          >
            Use Demo Credentials
          </Button>

          {/* Footer */}
          <div className="text-center text-sm text-muted-foreground space-y-1 border-t border-border pt-6">
            <p>
              Don&apos;t have an account?{" "}
              <Link href="/register" className="text-primary hover:text-primary/80 font-medium transition-colors">
                Create one
              </Link>
            </p>
            <p className="text-xs mt-4">By signing in, you agree to our Terms of Service</p>
          </div>
        </div>
      </div>
    </div>
  );
}
