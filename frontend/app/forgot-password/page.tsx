"use client";

import { useState, FormEvent } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { ArrowLeft, ArrowRight, Zap, CheckCircle2 } from "lucide-react";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      // Simulate password reset request API call
      await new Promise((resolve) => setTimeout(resolve, 1500));
      setSubmitted(true);
    } catch (err) {
      setError("An unexpected error occurred. Please try again.");
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
                <h3 className="font-semibold text-foreground">Secure Recovery</h3>
                <p className="text-sm text-muted-foreground">Self-service password recovery options</p>
              </div>
            </div>
          </div>

          <div className="border-t border-border pt-8">
            <p className="text-xs text-muted-foreground">EckoX uses industry-standard security protocols to protect your account access.</p>
          </div>
        </div>
      </div>

      {/* Right Side - Form */}
      <div className="flex-1 flex flex-col items-center justify-center px-6 py-12">
        <div className="w-full max-w-sm space-y-8">
          {/* Mobile Header */}
          <div className="md:hidden flex items-center gap-3 mb-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">
              E
            </div>
            <span className="text-xl font-bold">EckoX</span>
          </div>

          {!submitted ? (
            <>
              {/* Form Header */}
              <div className="space-y-2">
                <h1 className="text-3xl font-bold tracking-tight">Forgot Password?</h1>
                <p className="text-muted-foreground">Enter your email and we will send you a reset link</p>
              </div>

              {/* Error Message */}
              {error && (
                <div className="p-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-sm font-medium">
                  {error}
                </div>
              )}

              {/* Request Form */}
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

                <Button
                  type="submit"
                  disabled={loading}
                  className="w-full h-11 btn-primary text-base font-semibold"
                >
                  {loading ? (
                    <>
                      <Spinner className="w-4 h-4" />
                      <span>Sending reset link...</span>
                    </>
                  ) : (
                    <>
                      Send Reset Link
                      <ArrowRight className="w-4 h-4 ml-1" />
                    </>
                  )}
                </Button>
              </form>
            </>
          ) : (
            <div className="space-y-6 text-center">
              <div className="flex justify-center">
                <CheckCircle2 className="h-16 w-16 text-emerald-500" />
              </div>
              <div className="space-y-2">
                <h1 className="text-2xl font-bold">Check Your Email</h1>
                <p className="text-muted-foreground">
                  We have sent password recovery instructions to <strong className="text-foreground">{email}</strong>.
                </p>
              </div>
            </div>
          )}

          {/* Footer Back Link */}
          <div className="text-center border-t border-border pt-6">
            <Link
              href="/login"
              className="inline-flex items-center text-sm font-medium text-primary hover:text-primary/80 transition-colors"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Sign In
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
