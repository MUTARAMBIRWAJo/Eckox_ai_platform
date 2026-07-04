"use client";

import { useState, FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/hooks/use-auth";
import { ArrowRight, Zap } from "lucide-react";

export default function RegisterPage() {
  const router = useRouter();
  const { register, error: authError, clearError } = useAuth();
  
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [role, setRole] = useState<'admin' | 'manager' | 'sales-agent' | 'super-admin'>("sales-agent");
  const [loading, setLoading] = useState(false);
  const [localError, setLocalError] = useState("");

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLocalError("");
    clearError();

    if (password !== passwordConfirmation) {
      setLocalError("Passwords do not match");
      return;
    }

    if (password.length < 8) {
      setLocalError("Password must be at least 8 characters");
      return;
    }

    setLoading(true);

    try {
      const success = await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        role,
      });

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
          </div>

          <div className="border-t border-border pt-8">
            <p className="text-xs text-muted-foreground mb-4">Enterprise features trusted by leading companies</p>
            <div className="flex flex-wrap gap-2">
              {["AI Chat", "Advanced CRM", "CPQ System", "Analytics"].map((feature) => (
                <span key={feature} className="px-3 py-1 rounded-full bg-secondary text-xs font-medium text-foreground">
                  {feature}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Right Side - Register Form */}
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
            <h1 className="text-3xl font-bold tracking-tight">Create Account</h1>
            <p className="text-muted-foreground">Get started with EckoX Sales Platform</p>
          </div>

          {/* Error Message */}
          {(localError || authError) && (
            <div className="p-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-sm font-medium">
              {localError || authError}
            </div>
          )}

          {/* Register Form */}
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <label htmlFor="name" className="label-base text-sm font-medium">
                Full Name
              </label>
              <Input
                id="name"
                type="text"
                placeholder="John Doe"
                value={name}
                onChange={(e) => setName(e.target.value)}
                disabled={loading}
                required
                className="h-10"
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="email" className="label-base text-sm font-medium">
                Email Address
              </label>
              <Input
                id="email"
                type="email"
                placeholder="you@company.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={loading}
                required
                className="h-10"
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="role" className="label-base text-sm font-medium">
                Role (Optional)
              </label>
              <select
                id="role"
                value={role}
                onChange={(e: any) => setRole(e.target.value)}
                disabled={loading}
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <option value="sales-agent">Sales Agent</option>
                <option value="manager">Manager</option>
                <option value="admin">Admin</option>
                <option value="super-admin">Super Admin</option>
              </select>
            </div>

            <div className="space-y-2">
              <label htmlFor="password" className="label-base text-sm font-medium">
                Password
              </label>
              <Input
                id="password"
                type="password"
                placeholder="Min 8 characters"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={loading}
                required
                className="h-10"
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="passwordConfirmation" className="label-base text-sm font-medium">
                Confirm Password
              </label>
              <Input
                id="passwordConfirmation"
                type="password"
                placeholder="Confirm password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                disabled={loading}
                required
                className="h-10"
              />
            </div>

            <Button
              type="submit"
              disabled={loading}
              className="w-full h-11 btn-primary text-base font-semibold mt-4"
            >
              {loading ? (
                <>
                  <Spinner className="w-4 h-4 mr-2" />
                  <span>Registering...</span>
                </>
              ) : (
                <>
                  Create Account
                  <ArrowRight className="w-4 h-4 ml-1" />
                </>
              )}
            </Button>
          </form>

          {/* Footer */}
          <div className="text-center text-sm text-muted-foreground space-y-1 border-t border-border pt-6">
            <p>
              Already have an account?{" "}
              <Link href="/login" className="text-primary hover:text-primary/80 font-medium transition-colors">
                Sign In
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
