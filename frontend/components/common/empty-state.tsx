"use client";

import { Button } from "@/components/ui/button";
import { InboxIcon, Search } from "lucide-react";
import { cn } from "@/lib/utils";

export interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
  className?: string;
}

export function EmptyState({
  icon = <InboxIcon className="w-12 h-12 text-muted-foreground" />,
  title,
  description,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div className={cn(
      "flex flex-col items-center justify-center rounded-lg border border-border bg-card/50 py-12 px-6 text-center",
      className
    )}>
      <div className="mb-4">{icon}</div>
      <h3 className="text-lg font-semibold mb-2">{title}</h3>
      {description && <p className="text-muted-foreground mb-6 max-w-md">{description}</p>}
      {action && (
        <Button onClick={action.onClick} className="mt-4">
          {action.label}
        </Button>
      )}
    </div>
  );
}

export function EmptySearchResults({ query }: { query: string }) {
  return (
    <EmptyState
      icon={<Search className="w-12 h-12 text-muted-foreground" />}
      title="No results found"
      description={`No results for "${query}". Try adjusting your search terms.`}
    />
  );
}

export function EmptyList({ 
  title = "No items yet",
  description = "Get started by creating your first item."
}: { 
  title?: string; 
  description?: string;
}) {
  return (
    <EmptyState
      title={title}
      description={description}
    />
  );
}
