"use client";

import { motion } from "framer-motion";
import { Button } from "@/components/ui/button";
import { Sparkles, TrendingUp, Users, FileText } from "lucide-react";

interface SuggestedPromptsProps {
  onSelectPrompt: (prompt: string) => void;
}

const SUGGESTED_PROMPTS = [
  {
    icon: Sparkles,
    title: "Find HPLC Systems",
    description: "Search for HPLC equipment suitable for Kenya market",
    prompt: "Show me HPLC systems available for Kenya with compliance certificates",
  },
  {
    icon: TrendingUp,
    title: "Quote Analysis",
    description: "Analyze recent quote trends and conversion rates",
    prompt: "What are the top performing product categories this month?",
  },
  {
    icon: Users,
    title: "Lead Suggestions",
    description: "Get AI recommendations for qualified leads",
    prompt: "Which leads should I prioritize today based on deal probability?",
  },
  {
    icon: FileText,
    title: "Generate Proposal",
    description: "Create a professional sales proposal quickly",
    prompt: "Help me draft a proposal for a laboratory equipment package",
  },
];

export function SuggestedPrompts({ onSelectPrompt }: SuggestedPromptsProps) {
  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground text-center">
        Try asking about products, leads, or quotations
      </p>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        {SUGGESTED_PROMPTS.map((item, idx) => {
          const Icon = item.icon;
          return (
            <motion.button
              key={idx}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: idx * 0.1 }}
              onClick={() => onSelectPrompt(item.prompt)}
              className="p-4 rounded-lg border border-border bg-card/50 hover:bg-card hover:border-primary/20 transition-all text-left group"
            >
              <div className="flex items-start gap-3">
                <Icon className="w-4 h-4 text-primary mt-0.5 flex-shrink-0" />
                <div className="min-w-0">
                  <p className="font-medium text-sm group-hover:text-primary transition-colors">
                    {item.title}
                  </p>
                  <p className="text-xs text-muted-foreground truncate">
                    {item.description}
                  </p>
                </div>
              </div>
            </motion.button>
          );
        })}
      </div>
    </div>
  );
}
