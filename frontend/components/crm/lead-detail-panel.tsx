"use client";

import { useEffect, useState } from "react";
import { Lead } from "@/lib/data/leads";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Mail, Phone, MessageSquare, FileText, TrendingUp, Edit2, Check, X } from "lucide-react";
import { motion } from "framer-motion";

interface LeadDetailPanelProps {
  lead: Lead | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUpdateLead?: (leadId: string, data: Partial<Lead>) => Promise<void>;
}

export function LeadDetailPanel({
  lead,
  open,
  onOpenChange,
  onUpdateLead,
}: LeadDetailPanelProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState("");
  const [company, setCompany] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (lead) {
      setName(lead.name || "");
      setCompany(lead.company || "");
      setEmail(lead.email || "");
      setPhone(lead.phone || "");
      setIsEditing(false);
    }
  }, [lead, open]);

  if (!lead) return null;

  const handleSave = async () => {
    if (!name || !email) return;
    setLoading(true);
    try {
      if (onUpdateLead) {
        await onUpdateLead(lead.id, { name, company, email, phone });
      }
      setIsEditing(false);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:w-96 p-0 overflow-y-auto">
        <SheetHeader className="border-b border-border px-6 py-4 flex flex-row items-center justify-between">
          <div className="flex-1 min-w-0 pr-4">
            {isEditing ? (
              <div className="space-y-2 mt-2">
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Contact Name"
                  className="h-8 font-semibold text-base"
                />
                <Input
                  value={company}
                  onChange={(e) => setCompany(e.target.value)}
                  placeholder="Company"
                  className="h-8 text-sm"
                />
              </div>
            ) : (
              <>
                <SheetTitle className="truncate">{lead.name}</SheetTitle>
                <SheetDescription className="truncate">{lead.company}</SheetDescription>
              </>
            )}
          </div>
          <div className="flex items-center gap-2">
            {isEditing ? (
              <>
                <Button size="icon" variant="ghost" className="h-8 w-8 text-emerald-500" onClick={handleSave} disabled={loading}>
                  <Check className="h-4 w-4" />
                </Button>
                <Button size="icon" variant="ghost" className="h-8 w-8 text-destructive" onClick={() => setIsEditing(false)} disabled={loading}>
                  <X className="h-4 w-4" />
                </Button>
              </>
            ) : (
              <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => setIsEditing(true)}>
                <Edit2 className="h-4 w-4" />
              </Button>
            )}
          </div>
        </SheetHeader>

        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="space-y-6 px-6 py-4"
        >
          {/* Lead Score */}
          <Card className="p-4 bg-gradient-to-r from-primary/10 to-accent/10 border-0">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Lead Score</p>
                <p className="text-3xl font-bold text-primary">{lead.score}%</p>
              </div>
              <TrendingUp className="h-8 w-8 text-primary/30" />
            </div>
          </Card>

          {/* Contact Information */}
          <div className="space-y-3">
            <h4 className="font-semibold text-sm">Contact Information</h4>
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <Mail className="h-4 w-4 text-muted-foreground" />
                {isEditing ? (
                  <Input
                    value={email}
                    type="email"
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email"
                    className="h-8 text-xs flex-1"
                  />
                ) : (
                  <a
                    href={`mailto:${lead.email}`}
                    className="text-sm hover:text-primary truncate max-w-[200px]"
                  >
                    {lead.email}
                  </a>
                )}
              </div>
              <div className="flex items-center gap-3">
                <Phone className="h-4 w-4 text-muted-foreground" />
                {isEditing ? (
                  <Input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="Phone"
                    className="h-8 text-xs flex-1"
                  />
                ) : (
                  <span className="text-sm">{lead.phone || "Not provided"}</span>
                )}
              </div>
            </div>
          </div>

          {/* Status & Details */}
          <div className="space-y-3">
            <h4 className="font-semibold text-sm">Details</h4>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <Badge className="mt-1 capitalize">{lead.status}</Badge>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Country</p>
                <p className="text-sm font-medium mt-1">{lead.country}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Industry</p>
                <p className="text-sm font-medium mt-1">{lead.industry || "Lab"}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Probability</p>
                <p className="text-sm font-medium mt-1">{lead.probability || "65"}%</p>
              </div>
            </div>
          </div>

          {/* AI Summary */}
          <div className="space-y-3">
            <h4 className="font-semibold text-sm">AI Summary</h4>
            <p className="text-sm text-muted-foreground leading-relaxed">
              {name || lead.name} from {company || lead.company} is a high-quality lead interested in laboratory equipment. Based on interaction history, they have a 75% likelihood to close in the next 30 days. Recommend scheduling a product demo.
            </p>
          </div>

          {/* Recommended Actions */}
          <div className="space-y-3">
            <h4 className="font-semibold text-sm">Suggested Next Steps</h4>
            <ul className="text-sm space-y-2 text-muted-foreground">
              <li className="flex gap-2">
                <span className="text-primary">•</span>
                <span>Send product catalog</span>
              </li>
              <li className="flex gap-2">
                <span className="text-primary">•</span>
                <span>Schedule product demo</span>
              </li>
              <li className="flex gap-2">
                <span className="text-primary">•</span>
                <span>Get compliance requirements</span>
              </li>
            </ul>
          </div>

          {/* Action Buttons */}
          <div className="space-y-2 pt-4 border-t border-border">
            <Button className="btn-primary w-full">
              <MessageSquare className="h-4 w-4 mr-2" />
              Send Message
            </Button>
            <Button variant="secondary" className="w-full">
              <FileText className="h-4 w-4 mr-2" />
              Generate Quote
            </Button>
          </div>
        </motion.div>
      </SheetContent>
    </Sheet>
  );
}
