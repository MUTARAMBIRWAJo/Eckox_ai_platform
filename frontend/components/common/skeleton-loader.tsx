"use client";

import { Skeleton } from "@/components/ui/skeleton";

export function KPICardSkeleton() {
  return (
    <div className="card p-6 space-y-4 shadow-sm hover:shadow-md transition-shadow">
      <div className="space-y-2">
        <Skeleton className="skeleton-sm w-24" />
        <Skeleton className="skeleton-lg w-32" />
      </div>
      <Skeleton className="skeleton-sm w-20" />
    </div>
  );
}

export function DataTableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="card overflow-hidden shadow-sm">
      <div className="space-y-0">
        {/* Header */}
        <div className="flex gap-4 border-b border-border bg-secondary/30 px-6 py-4">
          <Skeleton className="skeleton-sm w-24" />
          <Skeleton className="skeleton-sm w-32" />
          <Skeleton className="skeleton-sm w-20" />
          <Skeleton className="skeleton-sm w-16" />
        </div>
        {/* Rows */}
        {Array.from({ length: rows }).map((_, idx) => (
          <div key={idx} className="flex gap-4 border-b border-border px-6 py-4 hover:bg-secondary/20 transition-colors">
            <Skeleton className="skeleton-sm w-24" />
            <Skeleton className="skeleton-sm w-32" />
            <Skeleton className="skeleton-sm w-20" />
            <Skeleton className="skeleton-sm w-16" />
          </div>
        ))}
      </div>
    </div>
  );
}

export function ChatMessageSkeleton() {
  return (
    <div className="flex gap-4 mb-4 px-4 py-3 rounded-lg hover:bg-secondary/30 transition-colors">
      <Skeleton className="w-10 h-10 rounded-full flex-shrink-0" />
      <div className="flex-1 space-y-2 min-w-0">
        <Skeleton className="skeleton-sm w-24" />
        <Skeleton className="skeleton-sm w-full" />
        <Skeleton className="skeleton-sm w-4/5" />
      </div>
    </div>
  );
}

export function CardSkeleton() {
  return (
    <div className="card p-6 space-y-4 shadow-sm hover:shadow-md transition-shadow">
      <div className="space-y-2">
        <Skeleton className="skeleton-md w-32" />
        <Skeleton className="skeleton-sm w-full" />
        <Skeleton className="skeleton-sm w-3/4" />
      </div>
      <div className="space-y-2 pt-4">
        <Skeleton className="skeleton-md w-24" />
      </div>
    </div>
  );
}

export function PageSkeleton() {
  return (
    <div className="space-y-6 animate-pulse">
      <div className="flex items-center justify-between">
        <Skeleton className="skeleton-lg w-48" />
        <Skeleton className="skeleton-md w-32" />
      </div>
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, idx) => (
          <CardSkeleton key={idx} />
        ))}
      </div>
      <DataTableSkeleton rows={4} />
    </div>
  );
}
