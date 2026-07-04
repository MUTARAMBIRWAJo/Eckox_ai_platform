# Laravel 11 Backend Architecture for EckoX AI Sales Platform

This document outlines the complete backend structure, modules, and API endpoints required for the EckoX AI Sales Platform.

## Directory Structure

```
laravel-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── LeadController.php
│   │   │   ├── AIController.php
│   │   │   ├── QuoteController.php
│   │   │   ├── ProductController.php
│   │   │   ├── ConversationController.php
│   │   │   └── AnalyticsController.php
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Lead.php
│   │   ├── Product.php
│   │   ├── Quote.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   └── Activity.php
│   ├── Services/
│   │   ├── AIService.php
│   │   ├── LeadScoringService.php
│   │   ├── QuoteGeneratorService.php
│   │   └── RAGService.php
│   ├── Repositories/
│   │   ├── LeadRepository.php
│   │   ├── QuoteRepository.php
│   │   └── ProductRepository.php
│   └── Events/
├── routes/
│   ├── api.php
│   └── web.php
├── database/
│   ├── migrations/
│   └── seeders/
├── storage/
│   ├── documents/
│   └── embeddings/
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

## API Endpoints

### Authentication Module
```
POST   /api/v1/auth/login           - User login
POST   /api/v1/auth/register        - User registration
POST   /api/v1/auth/logout          - User logout
GET    /api/v1/auth/me              - Get current user
POST   /api/v1/auth/refresh         - Refresh token
PUT    /api/v1/auth/profile         - Update profile
POST   /api/v1/auth/change-password - Change password
POST   /api/v1/auth/forgot-password - Request password reset
POST   /api/v1/auth/reset-password  - Reset password
```

### CRM/Leads Module
```
GET    /api/v1/leads                - List all leads
POST   /api/v1/leads                - Create lead
GET    /api/v1/leads/{id}           - Get lead details
PUT    /api/v1/leads/{id}           - Update lead
DELETE /api/v1/leads/{id}           - Delete lead
PATCH  /api/v1/leads/{id}/status    - Update lead status
PATCH  /api/v1/leads/{id}/score     - Update lead score
GET    /api/v1/leads/search         - Search leads
POST   /api/v1/leads/bulk/status    - Bulk update status
POST   /api/v1/leads/bulk/delete    - Bulk delete
POST   /api/v1/leads/import         - Import from CSV/Excel
GET    /api/v1/leads/export         - Export leads

GET    /api/v1/customers            - List customers
GET    /api/v1/customers/{id}       - Get customer details

GET    /api/v1/pipeline/stats       - Pipeline statistics
GET    /api/v1/pipeline/{status}    - Get leads by status
```

### AI Module
```
POST   /api/v1/ai/chat              - Chat completion
POST   /api/v1/ai/stream            - Streaming chat (SSE)
POST   /api/v1/ai/summarize         - Summarize text
POST   /api/v1/ai/summarize-lead/{id} - Summarize lead notes
POST   /api/v1/ai/lead-score        - Score lead (AI analysis)
POST   /api/v1/ai/generate-quote    - Generate quote (AI)
POST   /api/v1/ai/rag-query         - RAG query
GET    /api/v1/ai/knowledge-search  - Search knowledge base
GET    /api/v1/ai/dashboard-insights - Get AI insights
GET    /api/v1/ai/provider-status   - Check AI provider status
GET    /api/v1/ai/health            - AI service health check
```

### Products Module
```
GET    /api/v1/products             - List products
POST   /api/v1/products             - Create product
GET    /api/v1/products/{id}        - Get product details
PUT    /api/v1/products/{id}        - Update product
DELETE /api/v1/products/{id}        - Delete product
GET    /api/v1/products/category/{cat} - Products by category
GET    /api/v1/products/search      - Search products
```

### Quotes Module
```
GET    /api/v1/quotes               - List quotes
POST   /api/v1/quotes               - Create quote
GET    /api/v1/quotes/{id}          - Get quote details
PUT    /api/v1/quotes/{id}          - Update quote
DELETE /api/v1/quotes/{id}          - Delete quote
POST   /api/v1/quotes/{id}/send     - Send quote
POST   /api/v1/quotes/{id}/viewed   - Mark as viewed
POST   /api/v1/quotes/{id}/accept   - Accept quote
POST   /api/v1/quotes/{id}/reject   - Reject quote
GET    /api/v1/quotes/{id}/pdf      - Generate PDF
POST   /api/v1/quotes/calculate-price - Calculate price
POST   /api/v1/quotes/ai-generate/{leadId} - AI generate quote
```

### Conversations Module
```
GET    /api/v1/conversations        - List conversations
POST   /api/v1/conversations        - Create conversation
GET    /api/v1/conversations/{id}   - Get conversation
GET    /api/v1/conversations/{id}/messages - Get messages
POST   /api/v1/conversations/{id}/messages - Send message
POST   /api/v1/conversations/{id}/ai-message - AI response
POST   /api/v1/conversations/{id}/ai-stream - AI stream response
POST   /api/v1/conversations/{id}/close - Close conversation
POST   /api/v1/conversations/{id}/archive - Archive conversation

GET    /api/v1/leads/{id}/activities - Get activities
POST   /api/v1/leads/{id}/activities - Create activity
PATCH  /api/v1/activities/{id}/complete - Complete activity
```

### Analytics Module
```
GET    /api/v1/analytics/dashboard  - Dashboard analytics
GET    /api/v1/analytics/revenue    - Revenue metrics
GET    /api/v1/analytics/conversion-metrics - Conversion metrics
GET    /api/v1/analytics/by-country - Metrics by country
GET    /api/v1/analytics/by-industry - Metrics by industry
GET    /api/v1/analytics/team-performance - Team performance
GET    /api/v1/analytics/pipeline-metrics - Pipeline metrics
GET    /api/v1/analytics/report     - Generate report
```

## Response Format

### Success Response
```json
{
  "success": true,
  "data": {},
  "message": "Operation completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error description",
  "code": 400
}
```

## Key Features

### 1. Authentication (Sanctum or JWT)
- Token-based authentication
- Role-based access control (admin, sales, manager)
- Token refresh mechanism
- Automatic logout on 401

### 2. AI Service Architecture
- Pluggable provider system (OpenAI, Claude, Local)
- Fallback mechanism if primary provider fails
- Streaming support for real-time responses
- Prompt templates for different business use cases

### 3. Lead Management
- CRUD operations
- Scoring engine (AI-powered)
- Status pipeline management
- Bulk operations
- Import/Export functionality

### 4. Quote Generation
- AI-powered quote creation
- Regional pricing engine (EU VAT, African tax rules)
- PDF generation
- Email integration

### 5. Knowledge Base (RAG)
- Document upload and indexing
- Vector embeddings (Supabase pgvector)
- Similarity search
- Context-aware AI responses

### 6. Real-time Features
- WebSocket support (Laravel Echo)
- Server-sent events (SSE) for AI streaming
- Supabase real-time subscriptions

### 7. Analytics
- Dashboard metrics
- Revenue tracking
- Conversion analysis
- Team performance metrics
- Regional breakdown

## Database Schema

See `/database/supabase-schema.sql` for complete PostgreSQL schema.

Key tables:
- `users` - Application users
- `leads` - Sales leads
- `products` - Lab equipment catalog
- `quotes` - Sales quotes
- `conversations` - Chat/messaging
- `messages` - Message history
- `knowledge_documents` - Knowledge base docs
- `document_chunks` - Vector embeddings for RAG
- `activities` - CRM activities

## Installation & Setup

### Prerequisites
- PHP 8.2+
- Laravel 11
- PostgreSQL 14+
- Redis (for queues)
- Docker & Docker Compose

### Steps

1. Clone repository
```bash
git clone <repo> && cd backend
```

2. Install dependencies
```bash
composer install
```

3. Set up environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Set up database
```bash
php artisan migrate --seed
```

5. Configure Supabase
Update `.env` with Supabase credentials:
```
DB_CONNECTION=pgsql
DB_HOST=<supabase-host>
DB_DATABASE=<supabase-db>
DB_USERNAME=<supabase-user>
DB_PASSWORD=<supabase-password>
```

6. Configure AI Services
```
AI_PROVIDER=mock  # or 'openai', 'claude'
OPENAI_API_KEY=<your-key>
CLAUDE_API_KEY=<your-key>
```

7. Start with Docker
```bash
docker-compose up -d
```

## Development

### Running Tests
```bash
php artisan test
```

### Queue Jobs
```bash
php artisan queue:work
```

### Seeding Data
```bash
php artisan db:seed
```

## Deployment

1. Use Laravel Forge or similar platform
2. Configure Supabase as primary database
3. Set up CI/CD with GitHub Actions
4. Deploy to AWS, DigitalOcean, or Heroku

## API Authentication

All endpoints (except login/register) require Bearer token:

```
Authorization: Bearer <token>
```

## Middleware

- `api` - API rate limiting
- `auth:sanctum` - Authentication check
- `role:admin` - Role-based access
- `throttle:api` - Rate limiting

## Error Handling

All errors follow standard format with appropriate HTTP codes:
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Server Error

## Support

For implementation details, refer to specific module documentation in `/docs/modules/`
