"use client";

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ChevronDown, ChevronUp } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export interface Column<T> {
  key: keyof T;
  label: string;
  sortable?: boolean;
  render?: (value: T[keyof T], row: T) => React.ReactNode;
  width?: string;
}

export interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  sortBy?: keyof T;
  sortDirection?: "asc" | "desc";
  onSort?: (key: keyof T, direction: "asc" | "desc") => void;
  hoverable?: boolean;
  striped?: boolean;
  dense?: boolean;
}

export function DataTable<T extends Record<string, any>>({
  columns,
  data,
  sortBy,
  sortDirection = "asc",
  onSort,
  hoverable = true,
  striped = false,
  dense = false,
}: DataTableProps<T>) {
  const [localSort, setLocalSort] = useState({ by: sortBy, direction: sortDirection });

  const handleSort = (key: keyof T) => {
    let newDirection: "asc" | "desc" = "asc";
    if (localSort.by === key && localSort.direction === "asc") {
      newDirection = "desc";
    }

    setLocalSort({ by: key, direction: newDirection });
    onSort?.(key, newDirection);
  };

  if (data.length === 0) {
    return (
      <div className="card">
        <div className="flex items-center justify-center py-12">
          <div className="text-center">
            <p className="text-muted-foreground">No data available</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="card overflow-hidden">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow className="border-border hover:bg-transparent">
              {columns.map((column) => (
                <TableHead
                  key={String(column.key)}
                  style={{ width: column.width }}
                  className="bg-secondary/30 font-semibold"
                >
                  {column.sortable ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleSort(column.key)}
                      className="h-auto p-0 font-semibold hover:bg-transparent"
                    >
                      <span className="flex items-center gap-1">
                        {column.label}
                        {localSort.by === column.key && (
                          localSort.direction === "asc" ? (
                            <ChevronUp className="w-4 h-4" />
                          ) : (
                            <ChevronDown className="w-4 h-4" />
                          )
                        )}
                      </span>
                    </Button>
                  ) : (
                    column.label
                  )}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
      <TableBody>
        {data.map((row, rowIdx) => (
          <TableRow
            key={rowIdx}
            className={cn(
              "transition-all duration-200",
              striped && rowIdx % 2 === 0 && "bg-secondary/20",
              hoverable && "hover:bg-secondary/50 hover:shadow-sm cursor-pointer border-l-2 border-l-transparent hover:border-l-primary"
            )}
          >
                {columns.map((column) => (
                  <TableCell
                    key={String(column.key)}
                    className={cn(
                      "text-foreground",
                      dense && "py-2"
                    )}
                  >
                    {column.render
                      ? column.render(row[column.key], row)
                      : row[column.key]}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
