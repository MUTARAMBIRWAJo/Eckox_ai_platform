"use client";

import { useState, FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { CRMAPI, Lead, CreateLeadRequest } from "@/lib/api/crm.api";
import { UserPlus, Loader2 } from "lucide-react";

interface CreateLeadDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (lead: Lead) => void;
}

interface FieldError {
  name?: string;
  email?: string;
  phone?: string;
  status?: string;
}

const STATUS_OPTIONS: { value: Lead["status"]; label: string }[] = [
  { value: "new", label: "New" },
  { value: "contacted", label: "Contacted" },
  { value: "qualified", label: "Qualified" },
  { value: "lost", label: "Lost" },
];

export function CreateLeadDialog({ open, onOpenChange, onCreated }: CreateLeadDialogProps) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [status, setStatus] = useState<Lead["status"]>("new");
  const [errors, setErrors] = useState<FieldError>({});
  const [serverError, setServerError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const reset = () => {
    setName("");
    setEmail("");
    setPhone("");
    setStatus("new");
    setErrors({});
    setServerError("");
  };

  const validate = (): boolean => {
    const newErrors: FieldError = {};

    if (!name.trim()) {
      newErrors.name = "Name is required";
    } else if (name.length > 255) {
      newErrors.name = "Name must be 255 characters or less";
    }

    if (!email.trim()) {
      newErrors.email = "Email is required";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = "Please enter a valid email address";
    } else if (email.length > 255) {
      newErrors.email = "Email must be 255 characters or less";
    }

    if (phone && phone.length > 20) {
      newErrors.phone = "Phone number must be 20 characters or less";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setServerError("");

    if (!validate()) return;

    setSubmitting(true);
    try {
      const data: CreateLeadRequest = {
        name: name.trim(),
        email: email.trim(),
        ...(phone.trim() ? { phone: phone.trim() } : {}),
        status,
      };

      const response = await CRMAPI.createLead(data);

      if (response.success && response.data) {
        const created = response.data.lead ?? (response.data as any);
        onCreated(created);
        reset();
        onOpenChange(false);
      } else {
        // Backend returned 422 validation errors
        const backendData = response.data as any;
        if (backendData?.errors) {
          const fieldErrors: FieldError = {};
          for (const [field, messages] of Object.entries(backendData.errors)) {
            (fieldErrors as any)[field] = Array.isArray(messages) ? messages[0] : messages;
          }
          setErrors(fieldErrors);
        } else {
          setServerError(response.error || "Failed to create lead. Please try again.");
        }
      }
    } catch (err: any) {
      setServerError(err.message || "An unexpected error occurred.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(isOpen) => { if (!isOpen) reset(); onOpenChange(isOpen); }}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <UserPlus className="w-5 h-5 text-primary" />
            Create New Lead
          </DialogTitle>
          <DialogDescription>
            Add a new sales lead to your CRM pipeline.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 pt-2" noValidate>
          {/* Name */}
          <div className="space-y-1.5">
            <Label htmlFor="lead-name">
              Full Name <span className="text-destructive">*</span>
            </Label>
            <Input
              id="lead-name"
              value={name}
              onChange={(e) => { setName(e.target.value); setErrors((p) => ({ ...p, name: undefined })); }}
              placeholder="e.g. Marie Dubois"
              aria-invalid={!!errors.name}
              aria-describedby={errors.name ? "lead-name-error" : undefined}
            />
            {errors.name && (
              <p id="lead-name-error" className="text-xs text-destructive">{errors.name}</p>
            )}
          </div>

          {/* Email */}
          <div className="space-y-1.5">
            <Label htmlFor="lead-email">
              Email <span className="text-destructive">*</span>
            </Label>
            <Input
              id="lead-email"
              type="email"
              value={email}
              onChange={(e) => { setEmail(e.target.value); setErrors((p) => ({ ...p, email: undefined })); }}
              placeholder="e.g. marie@company.com"
              aria-invalid={!!errors.email}
              aria-describedby={errors.email ? "lead-email-error" : undefined}
            />
            {errors.email && (
              <p id="lead-email-error" className="text-xs text-destructive">{errors.email}</p>
            )}
          </div>

          {/* Phone */}
          <div className="space-y-1.5">
            <Label htmlFor="lead-phone">Phone <span className="text-muted-foreground text-xs">(optional)</span></Label>
            <Input
              id="lead-phone"
              type="tel"
              value={phone}
              onChange={(e) => { setPhone(e.target.value); setErrors((p) => ({ ...p, phone: undefined })); }}
              placeholder="e.g. +33 1 23 45 67 89"
              aria-invalid={!!errors.phone}
              aria-describedby={errors.phone ? "lead-phone-error" : undefined}
            />
            {errors.phone && (
              <p id="lead-phone-error" className="text-xs text-destructive">{errors.phone}</p>
            )}
          </div>

          {/* Status */}
          <div className="space-y-1.5">
            <Label htmlFor="lead-status">Status</Label>
            <select
              id="lead-status"
              value={status}
              onChange={(e) => setStatus(e.target.value as Lead["status"])}
              className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-1 text-foreground"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>

          {/* Server error */}
          {serverError && (
            <p className="text-sm text-destructive bg-destructive/10 rounded-md px-3 py-2">{serverError}</p>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => { reset(); onOpenChange(false); }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={submitting} className="btn-primary min-w-[100px]">
              {submitting ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Creating…
                </>
              ) : (
                <>
                  <UserPlus className="w-4 h-4 mr-2" />
                  Create Lead
                </>
              )}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
