# Complete Integration Guide: EckoX AI Sales Platform

This guide covers the full integration of the Next.js frontend with Laravel backend, Supabase database, and AI services.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Next.js Frontend                         │
│  (React + TypeScript + Tailwind CSS)                        │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ Axios HTTP Client
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                 API Layer (/lib/api)                        │
│  - client.ts (base HTTP client)                            │
│  - auth.api.ts (authentication)                            │
│  - crm.api.ts (leads management)                           │
│  - ai.api.ts (AI operations)                               │
│  - products.api.ts (products & quotes)                     │
│  - conversations.api.ts (messaging)                        │
│  - error-handler.ts (error handling)                       │
│  - middleware.ts (logging, rate limiting, etc.)            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ REST API
                          ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel 11 Backend (/api/v1)                  │
│  - Authentication Controller                               │
│  - CRM/Leads Module                                         │
│  - AI Module (pluggable providers)                          │
│  - Products Module                                          │
│  - Quotes Module                                            │
│  - Conversations Module                                     │
│  - Analytics Module                                         │
└─────────────────────────┬───────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  Supabase    │  │  AI Services │  │   External   │
│  PostgreSQL  │  │  (OpenAI,    │  │   Services   │
│  (with pgv)  │  │   Claude,    │  │              │
│              │  │   Local)     │  │              │
└──────────────┘  └──────────────┘  └──────────────┘
```

## Setup Steps

### 1. Environment Configuration

Create `.env.local`:

```env
# Backend API
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1

# AI Provider Configuration
AI_PROVIDER=mock  # or 'openai', 'claude'
OPENAI_API_KEY=sk-...
CLAUDE_API_KEY=sk-ant-...

# Supabase (for real-time & auth)
NEXT_PUBLIC_SUPABASE_URL=https://xxx.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=eyJhbGc...

# Optional: Error tracking
NEXT_PUBLIC_SENTRY_DSN=https://...@sentry.io/...
```

### 2. Backend Setup

```bash
# 1. Install Laravel
composer create-project laravel/laravel eckox-backend
cd eckox-backend

# 2. Install dependencies
composer require laravel/sanctum laravel/tinker

# 3. Setup Supabase connection
# Update .env with Supabase PostgreSQL credentials

# 4. Run migrations
php artisan migrate

# 5. Start backend
php artisan serve --port=8000
```

### 6. Database Setup

```bash
# Import Supabase schema
psql postgresql://user:password@host:5432/database < lib/database/supabase-schema.sql

# Or using Supabase dashboard:
# 1. Go to SQL Editor
# 2. Run the schema SQL file
# 3. Enable pgvector extension
# 4. Configure Row Level Security
```

## API Integration Examples

### Authentication Flow

```typescript
import { AuthAPI } from '@/lib/api/auth.api';
import { apiClient } from '@/lib/api/client';

// Login
const response = await AuthAPI.login({
  email: 'user@example.com',
  password: 'password',
});

if (response.success && response.data?.token) {
  apiClient.setAuthToken(response.data.token);
  // Store user data in context/store
  setUser(response.data.user);
}
```

### Lead Management

```typescript
import { CRMAPI } from '@/lib/api/crm.api';

// Get leads
const response = await CRMAPI.getLeads({
  status: 'qualified',
  country: 'Nigeria',
  page: 1,
  limit: 20,
});

// Score lead with AI
const scoreResponse = await AIAPI.scoreLead(leadData);

// Update lead status
await CRMAPI.updateLeadStatus(leadId, 'proposal');
```

### AI Chat Integration

```typescript
import { AIAPI } from '@/lib/api/ai.api';
import { useAIStream } from '@/lib/hooks/useRealtimeLeads';
import { ChatMessage } from '@/lib/services/ai/types';

const { streamChat, cancelStream } = useAIStream();

const messages: ChatMessage[] = [
  { role: 'user', content: 'What HPLC systems do you offer?' },
];

// Streaming response
let fullResponse = '';
for await (const chunk of streamChat(messages)) {
  fullResponse += chunk;
  // Update UI in real-time
}
```

### Quote Generation

```typescript
import { QuotesAPI } from '@/lib/api/products.api';

// AI-generate quote
const quote = await QuotesAPI.generateAIQuote(leadId);

// Or manually create
const quote = await QuotesAPI.createQuote({
  leadId,
  items: [
    { productId: 'prod-1', quantity: 1 },
    { productId: 'prod-2', quantity: 2 },
  ],
  currency: 'USD',
});

// Generate PDF
const pdf = await QuotesAPI.downloadPDF(quote.id);
```

### Real-time Features

```typescript
import { useRealtimeLeads, useRealtimeMessages } from '@/lib/hooks/useRealtimeLeads';

// Real-time leads
useRealtimeLeads((lead) => {
  // Lead was updated in real-time
  refetchLeads();
});

// Real-time messages
useRealtimeMessages(conversationId, (message) => {
  // New message received
  addMessageToConversation(message);
});
```

### Error Handling

```typescript
import { APIErrorHandler } from '@/lib/api/error-handler';

try {
  await CRMAPI.createLead(data);
} catch (error) {
  const message = APIErrorHandler.getErrorMessage(error);
  showToast(message, 'error');

  // Retry with exponential backoff
  await APIErrorHandler.retry(
    () => CRMAPI.createLead(data),
    3,
    1000
  );
}
```

## AI Service Architecture

### Switching AI Providers

```typescript
import { getAIServiceManager } from '@/lib/services/ai/ai-service-manager';

// Get manager instance
const manager = getAIServiceManager();

// Switch provider at runtime
manager.switchProvider('claude');

// Check provider status
console.log(manager.getPrimaryProvider()); // 'claude'
console.log(manager.getFallbackProvider()); // 'mock'
```

### Creating Custom AI Service

```typescript
import { BaseAIService } from '@/lib/services/ai/ai-service';

export class CustomAIService extends BaseAIService {
  protected providerName = 'custom';

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    // Your implementation
  }

  async scoreLead(leadData: LeadData): Promise<AIResponse> {
    // Your implementation
  }

  // Implement other methods...
}
```

## RAG (Knowledge Base) Implementation

### Setting up Vector Search

```typescript
// 1. Upload document
const formData = new FormData();
formData.append('file', pdfFile);
await fetch('/api/v1/documents/upload', {
  method: 'POST',
  body: formData,
});

// 2. Backend processes:
//    - Extracts text
//    - Chunks content (500-1000 tokens)
//    - Generates embeddings via OpenAI

// 3. Query knowledge base
const result = await AIAPI.ragQuery('What is CE compliance?');
// Returns: answer + sources + confidence

// Response:
{
  answer: "CE compliance means...",
  sources: [
    { documentId: "doc-001", title: "Compliance Guide", relevance: 0.98 },
  ],
  confidence: 0.92
}
```

## Real-time WebSocket Integration

### Supabase Real-time (Production)

```typescript
import { createClient } from '@supabase/supabase-js';

const supabase = createClient(url, key);

// Subscribe to lead updates
const subscription = supabase
  .from('leads')
  .on('*', (payload) => {
    console.log('Lead updated:', payload.new);
    refetchLeads();
  })
  .subscribe();

// Cleanup
subscription.unsubscribe();
```

### Server-Sent Events (for AI streaming)

```typescript
// Backend sends SSE
response.setHeader('Content-Type', 'text/event-stream');
response.write('data: chunk1\n\n');
response.write('data: chunk2\n\n');

// Frontend receives
const eventSource = new EventSource('/api/v1/ai/stream');
eventSource.onmessage = (event) => {
  console.log('Chunk:', event.data);
};
```

## Deployment Checklist

### Frontend (Vercel)
- [ ] Set environment variables in Vercel dashboard
- [ ] Configure domain & SSL
- [ ] Setup GitHub integration for auto-deploy
- [ ] Configure analytics

### Backend (AWS/DigitalOcean/Heroku)
- [ ] Setup database on Supabase
- [ ] Configure AI provider keys
- [ ] Setup Redis for queues
- [ ] Enable CORS for frontend domain
- [ ] Configure SMTP for emails
- [ ] Setup error tracking (Sentry)

### Database (Supabase)
- [ ] Run migrations
- [ ] Enable pgvector extension
- [ ] Configure RLS policies
- [ ] Setup backups
- [ ] Test real-time subscriptions

### AI Services
- [ ] Test OpenAI API integration
- [ ] Test Claude API integration
- [ ] Setup prompt templates
- [ ] Test RAG with sample documents

## Testing

### API Testing

```typescript
import { describe, it, expect } from '@jest/globals';

describe('CRMAPI', () => {
  it('should fetch leads', async () => {
    const response = await CRMAPI.getLeads();
    expect(response.success).toBe(true);
    expect(Array.isArray(response.data?.leads)).toBe(true);
  });
});
```

### AI Service Testing

```typescript
describe('AIServiceManager', () => {
  it('should score lead', async () => {
    const manager = getAIServiceManager();
    const response = await manager.scoreLead({
      name: 'Test Company',
      company: 'ABC Inc',
      email: 'test@example.com',
      country: 'Nigeria',
      score: 75,
      status: 'qualified',
    });

    expect(response.success).toBe(true);
    expect(response.data.message).toBeDefined();
  });
});
```

## Performance Optimization

### Caching Strategy

```typescript
// Cache API responses
import { useMemo } from 'react';

const CachedLeads = () => {
  const [leads, setLeads] = useState([]);
  const cacheKey = 'leads-list';

  useMemo(async () => {
    const cached = localStorage.getItem(cacheKey);
    if (cached && isStillValid(cached)) {
      setLeads(JSON.parse(cached));
    } else {
      const response = await CRMAPI.getLeads();
      setLeads(response.data?.leads || []);
      localStorage.setItem(cacheKey, JSON.stringify(response.data?.leads));
    }
  }, []);
};
```

### Request Deduplication

Already configured in `middleware.ts` - identical GET requests within 5 seconds will be deduplicated.

## Monitoring & Logging

```typescript
// View API logs
console.log(getAIServiceManager().getRequestLog());

// Monitor performance
setupTimeoutWarning(apiClient);

// Error tracking (Sentry)
if (window.__errorTracking) {
  window.__errorTracking.captureException(error);
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 401 Unauthorized | Check token is stored, verify token validity |
| 422 Validation Error | Check error response for field-specific errors |
| Slow API calls | Use caching, check network, enable rate limiting |
| AI not responding | Check API key, verify provider status, check quota |
| Real-time not updating | Verify Supabase connection, check RLS policies |

## Support & Resources

- Backend: `/docs/BACKEND_STRUCTURE.md`
- AI Services: `/lib/services/ai/README.md`
- API Reference: Check individual API files in `/lib/api/`
