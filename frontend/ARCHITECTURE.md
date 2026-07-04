# EckoX Architecture Guide

## System Overview

EckoX is a modern SaaS platform built on Next.js 16 with a decoupled architecture separating UI layers, business logic, and data services. The application uses client-side mock APIs to simulate backend operations while maintaining production-ready code structure.

## Layer Architecture

```
┌─────────────────────────────────────────┐
│        UI Components (React)             │
│  (Pages, Layouts, Reusable Components)  │
├─────────────────────────────────────────┤
│     Business Logic & State               │
│    (Hooks, Server Actions, Client)       │
├─────────────────────────────────────────┤
│      API Service Layer                   │
│    (Mock implementations with types)     │
├─────────────────────────────────────────┤
│    Data & Configuration Layer            │
│    (Mock data, constants, schemas)       │
└─────────────────────────────────────────┘
```

## File Structure Details

### `/app` - Next.js App Router

```
app/
├── page.tsx                  # Root → redirects to /login
├── layout.tsx               # Root layout with providers
├── globals.css              # Design tokens & theme
├── (auth)/
│   ├── login/page.tsx       # Login with mock auth
│   └── signup/page.tsx      # Signup placeholder
├── dashboard/page.tsx       # Main dashboard (4 charts)
├── chat/page.tsx            # AI assistant interface
├── leads/page.tsx           # CRM with Kanban/table view
├── conversations/page.tsx   # Omnichannel messaging
├── quotes/page.tsx          # CPQ system
├── products/page.tsx        # Product catalog
├── knowledge/page.tsx       # Knowledge base
├── analytics/page.tsx       # Team analytics
├── automation/page.tsx      # Workflow builder
├── notifications/page.tsx   # Notification center
└── settings/page.tsx        # User settings
```

### `/components` - Reusable UI

```
components/
├── layout/
│   ├── header.tsx           # Top navigation
│   ├── sidebar.tsx          # Left navigation
│   └── app-layout.tsx       # Main wrapper
├── common/
│   ├── data-table.tsx       # Generic data table
│   ├── kpi-card.tsx         # Metric display
│   ├── skeleton-loader.tsx  # Loading states
│   └── empty-state.tsx      # No data UI
└── ui/                      # shadcn/ui (auto-generated)
    ├── button.tsx
    ├── card.tsx
    ├── input.tsx
    ├── switch.tsx
    ├── dialog.tsx
    ├── avatar.tsx
    ├── badge.tsx
    └── ... (17 total)
```

### `/lib` - Business Logic & Data

```
lib/
├── api/
│   └── index.ts             # All API services (283 lines)
│       - authAPI            # Authentication
│       - dashboardAPI       # Dashboard metrics
│       - leadsAPI           # CRM operations
│       - chatAPI            # AI chat
│       - quotesAPI          # CPQ
│       - productsAPI        # Catalog
│       - analyticsAPI       # Analytics
│       - automationAPI      # Workflows
│       - notificationsAPI   # Alerts
├── data/
│   ├── companies.ts         # 50 company names
│   ├── products.ts          # 6 lab equipment items
│   └── leads.ts             # 50 realistic leads
└── utils.ts                 # Utilities (cn function)
```

## Core Services

### Authentication Service
```typescript
authAPI.login(email, password)       // Returns user with role
authAPI.logout()                     // Clears session
authAPI.getCurrentUser()             // Returns current user
```

**Session Storage**: Mock implementation stores in memory
**Demo Credentials**: `demo@eckoX.com` / `demo`

### Dashboard Service
```typescript
dashboardAPI.getKPIs()               // 4 KPI cards
dashboardAPI.getRevenueChart()       // 6-month trend
dashboardAPI.getFunnelChart()        // Pipeline conversion
dashboardAPI.getActivityChart()      // Team activity
```

### CRM Service (Leads)
```typescript
leadsAPI.getLeads(limit)             // 50 leads with pagination
leadsAPI.updateLeadStatus(id, status) // Update pipeline stage
leadsAPI.createNote(leadId, note)    // Add lead notes
leadsAPI.bulkUpdate(leadIds, changes) // Batch operations
```

### AI Chat Service
```typescript
chatAPI.streamChatResponse(message)  // Returns streaming response
chatAPI.generateQuoteInsight(leadId) // AI analysis
chatAPI.getSuggestions(leadId)       // Smart suggestions
```

### CPQ Service (Quotes)
```typescript
quotesAPI.createQuote(leadId, items) // Build quote
quotesAPI.getQuotes(limit)           // List quotes
quotesAPI.updateQuote(id, updates)   // Modify quote
quotesAPI.calculateTax(items, country) // Tax calculation
```

### Products Service
```typescript
productsAPI.getProducts()            // All 6 products
productsAPI.getProductsByCategory(cat) // Filtered
productsAPI.getPricing(currency)     // Multi-currency
```

### Analytics Service
```typescript
analyticsAPI.getTeamPerformance()    // Sales metrics
analyticsAPI.getForecast(months)     // Revenue forecast
analyticsAPI.getMetrics()            // KPI aggregates
```

## Data Flow Patterns

### Page to API to UI
```
Page Component
    ↓
useEffect hook
    ↓
API Service call (mock)
    ↓
setState with data
    ↓
Render UI with data
```

### Real-world Conversion
When replacing mocks with real backend:

1. **Keep API layer identical**: Only change implementation inside `/lib/api`
2. **Update endpoints**: Replace mock data with fetch calls
3. **Authentication**: Integrate with Auth.js or similar
4. **Database**: Connect to Neon, Supabase, or other storage
5. **No UI changes needed**: All components work with real data

## Design System

### Theme Tokens (oklch color space)
- `--color-background`: `oklch(0.08 0 0)` - Very dark
- `--color-primary`: `oklch(0.64 0.17 142.5)` - Emerald green
- `--color-accent`: `oklch(0.65 0.2 254)` - Purple
- `--color-card`: `oklch(0.12 0 0)` - Elevated surface

### Typography
- Headings: **Inter/Geist Sans** - Bold weights
- Body: **Geist Sans** - Regular/Light - line-height 1.5-1.6

### Component Variants
- **Buttons**: `primary`, `secondary`, `outline`, `ghost`
- **Cards**: Composable with Header, Title, Content, Footer
- **Inputs**: Text, email, password, select, textarea
- **Icons**: Lucide React 24px

## Multi-Currency Support

All prices support three currencies with automatic tax calculation:

```typescript
// Product pricing
{
  name: "Lab Analyzer",
  priceUSD: 15000,      // US price
  priceEUR: 14200,      // EU price
  priceNGN: 6850000,    // Nigeria price
  taxRate: 0.19         // EU (19%) 
}

// Currency display
getPrice(product, "USD") → "$15,000"
getPrice(product, "EUR") → "€14,200"
getPrice(product, "NGN") → "₦6,850,000"
```

## State Management

**Current**: React hooks + prop drilling
**Ready for**: Redux, Zustand, or Jotai

```typescript
// Example page state pattern
const [data, setData] = useState(null);
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);

useEffect(() => {
  loadData().then(setData).finally(() => setLoading(false));
}, []);
```

## Performance Optimizations

1. **Code Splitting**: Dynamic imports for large pages
2. **Image Optimization**: Next.js Image component
3. **CSS**: Tailwind v4 zero-runtime overhead
4. **Memoization**: React.memo for expensive components
5. **Data Fetching**: Debounced search, pagination
6. **Bundle Size**: Tree-shaking unused exports

## Testing Strategy

### Unit Testing (Jest recommended)
- API service functions
- Utility functions
- Custom hooks

### Integration Testing (Playwright/Cypress recommended)
- User flows (login → dashboard → create quote)
- Data table interactions
- Form submissions

### E2E Testing (Vercel deployments)
- Full user journeys
- Performance metrics
- Mobile responsiveness

## Deployment

### Vercel (One-click)
```bash
git push origin main  # Auto-deploys to Vercel
```

### Self-hosted (Docker)
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY . .
RUN pnpm install && pnpm build
CMD ["pnpm", "start"]
```

### Environment Variables
```
# Add to .env.local
NEXT_PUBLIC_API_BASE=https://api.example.com
DATABASE_URL=postgres://...
AUTH_SECRET=$(openssl rand -base64 32)
```

## Migration Path

### Stage 1: Backend Integration
```
Mock APIs → Real backend endpoints
Keep all UI/components unchanged
```

### Stage 2: Authentication
```
Mock auth → Auth.js or third-party
Verify session management still works
```

### Stage 3: Database
```
Hardcoded leads → Database queries
Implement pagination & filtering
```

### Stage 4: Advanced Features
```
Add real-time updates with WebSockets
Implement file uploads to storage
Add payment processing
```

## Common Tasks

### Add New Page
1. Create `/app/[feature]/page.tsx`
2. Use `AppLayout` component
3. Import mock APIs
4. Update sidebar navigation

### Add New Component
1. Create in `/components/common/`
2. Import from shadcn if available
3. Use design tokens for colors
4. Export with TypeScript types

### Update Styling
1. Edit `/app/globals.css` theme tokens
2. Use Tailwind classes in JSX
3. Semantic colors over raw hex values

### Connect Real Data
1. Update `/lib/api/index.ts` implementation
2. Replace mock return with fetch call
3. Add error handling & types
4. No UI changes required

---

**Built for scalability and maintainability** — This architecture supports 10x growth with minimal refactoring.
