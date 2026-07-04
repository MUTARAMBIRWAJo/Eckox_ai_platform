"use client";

import { Button } from "@/components/ui/button";
import { LayoutGrid, Table2 } from "lucide-react";
import { cn } from "@/lib/utils";

interface ViewToggleProps {
  view: "table" | "kanban";
  onViewChange: (view: "table" | "kanban") => void;
}

export function ViewToggle({ view, onViewChange }: ViewToggleProps) {
  return (
    <div className="flex gap-1 bg-secondary rounded-lg p-1">
      <Button
        variant={view === "table" ? "default" : "ghost"}
        size="sm"
        onClick={() => onViewChange("table")}
        className={cn(
          "gap-2",
          view === "table" && "shadow-sm"
        )}
      >
        <Table2 className="h-4 w-4" />
        Table
      </Button>
      <Button
        variant={view === "kanban" ? "default" : "ghost"}
        size="sm"
        onClick={() => onViewChange("kanban")}
        className={cn(
          "gap-2",
          view === "kanban" && "shadow-sm"
        )}
      >
        <LayoutGrid className="h-4 w-4" />
        Kanban
      </Button>
    </div>
  );
}
