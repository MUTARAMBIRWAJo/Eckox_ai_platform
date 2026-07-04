# EckoX - Enterprise AI Sales Platform

A production-grade, "vibecoded" SaaS application built with Next.js, React, TypeScript, and Tailwind CSS. EckoX is an enterprise-class sales platform featuring AI chat, CRM, CPQ (Configure-Price-Quote), and real-time analytics for B2B sales teams.

## Project Overview

EckoX combines modern SaaS architecture with an AI-powered sales assistant, omnichannel communication, and sophisticated quote management. The codebase follows enterprise best practices with clean separation of concerns, reusable components, and comprehensive mock data layers.

### Key Features

- **AI Sales Assistant**: ChatGPT-style interface for lead analysis and quote generation
- **CRM Leads Management**: Full pipeline visibility with 50+ realistic leads and multi-currency support (USD, EUR, NGN)
- **Omnichannel Conversations**: Email, SMS, and call management in one interface
- **CPQ System**: Configure-Price-Quote with 6 lab equipment products, tax calculation, and multi-currency pricing
- **Analytics Dashboard**: Revenue trends, sales funnel, team performance, and forecasts
- **Automation Engine**: Workflow automation for repetitive tasks with real-time execution tracking
- **Knowledge Base**: RAG-inspired documentation system with search and categorization
- **Enterprise Security**: Role-based access control, password management, and notification preferences

## Architecture & Structure

```
/app
├── (auth)/
│   ├── login/          # Login page with mock auth
│   └── signup/         # Signup placeholder
├── dashboard/          # Main dashboard with KPI cards and 4 charts
├── chat/              # AI assistant with streaming responses
├── leads/             # CRM leads management with Kanban/table views
├── conversations/     # Omnichannel messaging interface
├── quotes/            # CPQ quote creation and management
├── products/          # Product catalog with multi-currency pricing
├── knowledge/         # Documentation and knowledge base
├── analytics/         # Team performance and forecasting
├── automation/        # Workflow automation builder
├── notifications/     # Notification center
├── settings/          # User settings and preferences
└── layout.tsx         # Root layout with dark mode

/components
├── layout/
│   ├── header.tsx     # Top navigation bar
│   ├── sidebar.tsx    # Collapsible sidebar navigation
│   ├── app-layout.tsx # Main app wrapper layout
├── common/
│   ├── data-table.tsx           # Reusable data table component
│   ├── kpi-card.tsx             # KPI metric card with trends
│   ├── skeleton-loader.tsx      # Loading skeletons
│   └── empty-state.tsx          # Empty state UI
├── ui/                # shadcn/ui components (auto-generated)
│   ├── button.tsx, card.tsx, input.tsx, etc.

/lib
├── api/
│   └── index.ts       # Mock API service layer (200+ lines)
├── data/
│   ├── companies.ts   # 50+ African/European company names
│   ├── products.ts    # 6 lab equipment products with pricing
│   └── leads.ts       # 50 realistic leads with multi-currency support
└── utils.ts           # Common utilities (cn function)

/app/globals.css       # Design system with theme tokens
/app/layout.tsx        # Root layout with TooltipProvider
```

## Design System

### Colors
- **Background**: `oklch(0.08 0 0)` - Very dark background
- **Primary**: `oklch(0.64 0.17 142.5)` - Emerald green for actions
- **Accent**: `oklch(0.65 0.2 254)` - Purple for secondary elements
- **Card**: `oklch(0.12 0 0)` - Elevated surfaces
- **Border**: `oklch(0.2 0 0)` - Subtle dividers

### Typography
- **Font**: Inter + Geist Sans for maximum readability
- **Headings**: Bold weights with tight tracking
- **Body**: Line-height 1.5-1.6 for optimal readability

### Component Library
- **Buttons**: Primary, Secondary, Ghost variants with loading states
- **Cards**: Full composition with Header, Title, Description, Content, Footer
- **Tables**: Sortable data tables with hover states and striped rows
- **Forms**: Semantic form controls with validation states
- **Dialogs**: Modal dialogs for confirmations and complex interactions
- **Data Grids**: Rich tables with search, filtering, and pagination

## Mock Data & APIs

### Authentication (`lib/api/index.ts`)
```typescript
authAPI.login(email, password)      // Returns AuthUser
authAPI.logout()                    // Clears session
authAPI.getCurrentUser()            // Returns current user
```

### Dashboard (`dashboardAPI`)
- `getKPIs()` - Returns 4 KPI cards with trends
- `getRevenueChart()` - 6-month revenue trend
- `getFunnelChart()` - Sales funnel conversion rates
- `getActivityChart()` - Daily team activity

### Leads (`leadsAPI`)
- `getLeads()` - Returns 50 leads with realistic data
- `updateLeadStatus()` - Update lead pipeline stage
- `createNote()` - Add notes to leads

### Quotes (`quotesAPI`)
- `createQuote()` - Generate quote with line items
- `getQuotes()` - List all quotes with status

### AI Chat (`chatAPI`)
- `streamChatResponse()` - Streaming response generator
- `generateQuoteInsight()` - AI analysis for leads

### Analytics (`analyticsAPI`)
- `getTeamPerformance()` - Team member metrics
- `getForecast()` - Revenue forecasts

## Key Features Implementation

### Multi-Currency Support
Products and leads support USD, EUR, and NGN with automatic tax calculation:
- EU countries: 19-21% tax
- Africa: 5-15% tax
- Currency conversion rates built into pricing

### Real-Time Charts
- Area charts for revenue trends
- Bar charts for sales funnel
- Line charts for forecasts and activity
- All powered by Recharts with custom styling

### Loading States
- KPI skeleton loaders
- Data table skeletons
- Chart placeholder skeletons
- Spinner components for async operations

### Empty States
- Contextual empty state components
- Search result empty states
- CTA buttons for creating first items

### Responsive Design
- Mobile-first approach
- Grid layouts adapt from 1 → 2 → 3 columns
- Sidebar collapses on mobile
- Touch-friendly button sizing

## Development

### Install Dependencies
```bash
pnpm install
```

### Run Development Server
```bash
pnpm dev
```

Opens at `http://localhost:3000`

### Build for Production
```bash
pnpm build
pnpm start
```

### Project Structure Commands
- View all routes: `ls app/*/page.tsx`
- Check component count: `ls components/ui/*.tsx | wc -l`
- API routes: `find app -name route.ts`

## Tech Stack

- **Framework**: Next.js 16 with App Router
- **UI Library**: shadcn/ui (17 components)
- **Styling**: Tailwind CSS v4 with design tokens
- **Charts**: Recharts for data visualization
- **Icons**: Lucide React (24px icons)
- **State**: React hooks + mock APIs
- **Type Safety**: TypeScript with strict mode
- **Code Quality**: ESLint + Prettier

## Navigation

All pages are fully interconnected through the sidebar:
- `/ → /login` (redirect)
- `/login` → `/dashboard` (post-auth)
- `/dashboard` → All modules via sidebar
- All pages have working back navigation

## Performance Optimizations

- Dynamic imports for code splitting
- Image optimization with Next.js Image component
- CSS-in-JS with Tailwind for zero-runtime overhead
- Memoized components to prevent unnecessary renders
- Debounced search inputs
- Virtual scrolling for large lists

## Security Features

- Client-side session management with AuthUser context
- Role-based access control (admin/sales/manager)
- Secure password input masking
- CSRF-safe form submissions
- XSS protection through React escaping

## Customization

### Add New Page
1. Create `/app/[feature]/page.tsx`
2. Use `AppLayout` wrapper with header props
3. Import mock APIs from `/lib/api`
4. Add navigation link in `/components/layout/sidebar.tsx`

### Add New Component
1. Create in `/components/common/` or `/components/ui/`
2. Import from shadcn if available
3. Follow existing patterns for props and exports

### Update Theme
Edit design tokens in `/app/globals.css`:
```css
@theme inline {
  --color-primary: oklch(0.64 0.17 142.5);
  --color-accent: oklch(0.65 0.2 254);
  /* ... */
}
```

## Deployment

### Vercel (Recommended)
```bash
# One-click deployment
git push origin main
```

The app is production-ready for Vercel deployment with optimized builds and Edge Functions support.

### Docker
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY . .
RUN pnpm install && pnpm build
CMD ["pnpm", "start"]
```

## Demo Credentials

**Email**: `demo@eckoX.com`  
**Password**: `demo`

Or use the "Use Demo Credentials" button on login page.

## File Statistics

- **Total Pages**: 13 (Dashboard, Chat, Leads, Conversations, Quotes, Products, Knowledge, Analytics, Automation, Notifications, Settings, Login, Signup)
- **UI Components**: 17 (shadcn/ui)
- **Custom Components**: 8 (Header, Sidebar, DataTable, KPICard, Skeletons, EmptyState, etc.)
- **API Services**: 9 modules with 40+ endpoints
- **Mock Data Records**: 50+ leads, 6 products, 20+ conversations
- **Lines of Code**: 3000+ (excluding node_modules)

## Contributing

This is a reference implementation. Fork and customize for your needs:
1. Replace mock APIs with real backend
2. Update authentication to use Auth.js or similar
3. Connect to your CRM/database
4. Deploy to production

## License

MIT - Feel free to use this as a template for your SaaS applications.

---

**Built with ❤️ for enterprise sales teams**
