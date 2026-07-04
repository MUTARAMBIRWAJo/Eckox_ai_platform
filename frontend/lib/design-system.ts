// Design System - Unified spacing, sizing, and styling constants
export const SPACING = {
  xs: "2px",
  sm: "4px",
  md: "8px",
  lg: "16px",
  xl: "24px",
  "2xl": "32px",
  "3xl": "48px",
  "4xl": "64px",
} as const;

export const BORDER_RADIUS = {
  sm: "0.375rem", // 6px
  md: "0.5rem",   // 8px
  lg: "0.75rem",  // 12px
  xl: "1rem",     // 16px
} as const;

export const SHADOWS = {
  none: "none",
  sm: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
  md: "0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)",
  lg: "0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)",
} as const;

// Tailwind class utilities
export const LAYOUT = {
  // Page container - max width with centered content
  pageContainer: "mx-auto max-w-7xl px-6 lg:px-8",
  
  // Page header - title + description
  pageHeader: "mb-8 flex flex-col gap-2",
  pageTitle: "text-3xl font-bold tracking-tight text-foreground",
  pageSubtitle: "text-muted-foreground",
  
  // Section header
  sectionHeader: "flex items-center justify-between mb-6",
  sectionTitle: "text-xl font-semibold tracking-tight text-foreground",
  
  // Content grid
  cardGrid: "grid gap-6 md:grid-cols-2 lg:grid-cols-3",
  cardGridWide: "grid gap-6 md:grid-cols-2",
  cardGrid4: "grid gap-6 md:grid-cols-2 lg:grid-cols-4",
  
  // Flexbox helpers
  flexBetween: "flex items-center justify-between",
  flexCenter: "flex items-center justify-center",
  flexStart: "flex items-start justify-between",
} as const;

export const CARD = {
  // Base card
  base: "rounded-lg border border-border bg-card shadow-sm transition-all duration-200",
  interactive: "rounded-lg border border-border bg-card shadow-sm transition-all duration-200 hover:shadow-md hover:border-border/50 cursor-pointer",
  
  // Card internals
  header: "flex items-center justify-between border-b border-border px-6 py-4 gap-4",
  title: "text-lg font-semibold text-foreground",
  body: "px-6 py-4",
  bodyDense: "px-4 py-3",
  footer: "border-t border-border px-6 py-4",
  
  // Padding variants
  padded: "p-6",
  paddedDense: "p-4",
  paddedCompact: "p-3",
} as const;

export const TABLE = {
  // Table styles
  container: "w-full overflow-auto",
  table: "w-full border-collapse",
  
  // Header
  header: "border-b border-border bg-secondary/20",
  headerCell: "px-4 py-3 text-left text-sm font-semibold text-foreground",
  
  // Body
  row: "border-b border-border transition-colors hover:bg-secondary/30",
  cell: "px-4 py-3 text-sm text-foreground",
  cellMuted: "px-4 py-3 text-sm text-muted-foreground",
} as const;

export const BUTTON = {
  // Size variants
  sm: "h-8 px-3 text-xs",
  md: "h-10 px-4 text-sm",
  lg: "h-12 px-6 text-base",
  
  // Base button class
  base: "inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 rounded-lg",
} as const;

export const FORM = {
  container: "space-y-5",
  group: "space-y-2",
  label: "text-sm font-medium text-foreground",
  input: "h-10 rounded-lg border border-border bg-input px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary",
  error: "text-xs font-medium text-destructive mt-1",
} as const;

export const BADGE = {
  base: "inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold transition-colors",
  success: "bg-primary/10 text-primary",
  warning: "bg-yellow-500/10 text-yellow-400",
  error: "bg-destructive/10 text-destructive",
  info: "bg-blue-500/10 text-blue-400",
  neutral: "bg-secondary text-foreground",
} as const;

export const STATUS_COLORS = {
  success: { badge: "bg-primary/10 text-primary", text: "text-primary" },
  warning: { badge: "bg-yellow-500/10 text-yellow-400", text: "text-yellow-400" },
  error: { badge: "bg-destructive/10 text-destructive", text: "text-destructive" },
  info: { badge: "bg-blue-500/10 text-blue-400", text: "text-blue-400" },
  neutral: { badge: "bg-secondary text-foreground", text: "text-muted-foreground" },
} as const;

// Responsive breakpoints
export const BREAKPOINTS = {
  sm: "640px",
  md: "768px",
  lg: "1024px",
  xl: "1280px",
  "2xl": "1536px",
} as const;
