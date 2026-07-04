"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { MoreHorizontal, Plus } from "lucide-react";
import { Lead } from "@/lib/data/leads";

interface KanbanColumn {
  id: string;
  title: string;
  color: string;
}

const COLUMNS: KanbanColumn[] = [
  { id: "new", title: "New", color: "border-l-blue-500 dark:border-l-blue-400" },
  { id: "contacted", title: "Contacted", color: "border-l-purple-500 dark:border-l-purple-400" },
  { id: "qualified", title: "Qualified", color: "border-l-yellow-500 dark:border-l-yellow-400" },
  { id: "proposal", title: "Proposal", color: "border-l-orange-500 dark:border-l-orange-400" },
  { id: "won", title: "Won", color: "border-l-green-500 dark:border-l-green-400" },
  { id: "lost", title: "Lost", color: "border-l-red-500 dark:border-l-red-400" },
];

interface KanbanBoardProps {
  leads: Lead[];
  onSelectLead?: (lead: Lead) => void;
  onUpdateLeadStatus?: (leadId: string, status: string) => void;
}

export function KanbanBoard({ leads, onSelectLead, onUpdateLeadStatus }: KanbanBoardProps) {
  const [draggedLead, setDraggedLead] = useState<Lead | null>(null);

  const ledsByStatus = COLUMNS.reduce(
    (acc, col) => {
      acc[col.id] = leads.filter((lead) => lead.status?.toLowerCase() === col.id);
      return acc;
    },
    {} as Record<string, Lead[]>
  );

  const handleDragStart = (lead: Lead) => {
    setDraggedLead(lead);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = (columnId: string) => {
    if (draggedLead) {
      onUpdateLeadStatus?.(draggedLead.id, columnId);
      setDraggedLead(null);
    }
  };

  return (
    <div className="overflow-x-auto pb-4">
      <div className="flex gap-6 min-w-min">
        {COLUMNS.map((column) => (
          <div
            key={column.id}
            className="flex-shrink-0 w-80 flex flex-col"
          >
            {/* Column Header */}
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <h3 className="font-semibold">{column.title}</h3>
                <Badge variant="secondary" className="bg-muted">
                  {ledsByStatus[column.id].length}
                </Badge>
              </div>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <Plus className="h-4 w-4" />
              </Button>
            </div>

            {/* Column Cards Area */}
            <motion.div
              onDragOver={handleDragOver}
              onDrop={() => handleDrop(column.id)}
              className="flex-1 space-y-3 p-3 rounded-lg border-2 border-dashed border-border hover:border-primary/50 transition-colors bg-muted/10 min-h-96"
            >
              {ledsByStatus[column.id].map((lead, idx) => (
                <motion.div
                  key={lead.id}
                  draggable
                  onDragStart={() => handleDragStart(lead)}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: idx * 0.05 }}
                  className="cursor-move"
                  onClick={() => onSelectLead?.(lead)}
                >
                  <div
                    className={`p-3 rounded-lg border-l-4 hover:shadow-md transition-all cursor-pointer bg-card ${column.color} hover:scale-105 transform`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm truncate">
                          {lead.name}
                        </p>
                        <p className="text-xs text-muted-foreground truncate">
                          {lead.company}
                        </p>
                        <div className="flex gap-1 mt-2">
                          <Badge
                            variant="outline"
                            className="text-xs bg-primary/10 text-primary border-0"
                          >
                            {lead.score}% score
                          </Badge>
                          <Badge
                            variant="outline"
                            className="text-xs"
                          >
                            {lead.country}
                          </Badge>
                        </div>
                      </div>
                      <button
                        className="h-6 w-6 flex-shrink-0 rounded hover:bg-secondary transition-colors flex items-center justify-center"
                        onClick={(e) => e.stopPropagation()}
                      >
                        <MoreHorizontal className="h-3 w-3" />
                      </button>
                    </div>
                  </div>
                </motion.div>
              ))}

              {ledsByStatus[column.id].length === 0 && (
                <div className="flex items-center justify-center h-96 text-muted-foreground text-sm">
                  No leads yet
                </div>
              )}
            </motion.div>
          </div>
        ))}
      </div>
    </div>
  );
}
