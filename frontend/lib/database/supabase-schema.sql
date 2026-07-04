-- Supabase PostgreSQL Schema for EckoX AI Sales Platform
-- This schema enables vector embeddings for RAG (Retrieval Augmented Generation)

-- Enable necessary extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "vector";

-- Users table
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email VARCHAR(255) UNIQUE NOT NULL,
  name VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'sales', -- admin, sales, manager
  company_id UUID,
  active BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leads table
CREATE TABLE leads (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id),
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(20),
  company VARCHAR(255),
  industry VARCHAR(100),
  country VARCHAR(100),
  score INTEGER DEFAULT 0,
  status VARCHAR(50) DEFAULT 'new', -- new, contacted, qualified, proposal, won, lost
  budget DECIMAL(12, 2),
  timeline VARCHAR(50),
  source VARCHAR(100),
  last_interaction TIMESTAMP,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE customers (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id),
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  company VARCHAR(255),
  country VARCHAR(100),
  total_spent DECIMAL(12, 2) DEFAULT 0,
  contact_count INTEGER DEFAULT 0,
  last_purchase TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100),
  price DECIMAL(12, 2),
  currency VARCHAR(3) DEFAULT 'USD',
  description TEXT,
  specifications JSONB,
  compliance JSONB, -- { ce: true, iso9001: true, nafdac: true }
  region_availability JSONB, -- array of regions
  stock INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Conversations table
CREATE TABLE conversations (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id),
  lead_id UUID REFERENCES leads(id),
  type VARCHAR(50) DEFAULT 'chat', -- chat, whatsapp, email, call
  channel VARCHAR(100),
  status VARCHAR(50) DEFAULT 'active', -- active, closed, archived
  last_message_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE messages (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  conversation_id UUID NOT NULL REFERENCES conversations(id),
  sender VARCHAR(50), -- user, assistant, lead
  sender_name VARCHAR(255),
  content TEXT NOT NULL,
  attachments JSONB,
  ai_generated BOOLEAN DEFAULT false,
  metadata JSONB,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quotes table
CREATE TABLE quotes (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id),
  lead_id UUID REFERENCES leads(id),
  items JSONB NOT NULL, -- array of { productId, quantity, price }
  subtotal DECIMAL(12, 2),
  tax DECIMAL(12, 2),
  total DECIMAL(12, 2),
  currency VARCHAR(3) DEFAULT 'USD',
  status VARCHAR(50) DEFAULT 'draft', -- draft, sent, viewed, accepted, rejected, expired
  valid_until TIMESTAMP,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Knowledge base documents (for RAG)
CREATE TABLE knowledge_documents (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100),
  file_path VARCHAR(255),
  file_type VARCHAR(50),
  content TEXT,
  metadata JSONB, -- { source, author, date }
  indexed BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Document chunks (for vector embedding)
CREATE TABLE document_chunks (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  document_id UUID NOT NULL REFERENCES knowledge_documents(id) ON DELETE CASCADE,
  chunk_text TEXT NOT NULL,
  chunk_index INTEGER,
  embedding vector(1536), -- OpenAI embeddings are 1536 dimensions
  tokens INTEGER, -- Token count for the chunk
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Embeddings log (track RAG queries)
CREATE TABLE embeddings_log (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(id),
  query TEXT NOT NULL,
  response TEXT,
  similarity_scores JSONB, -- array of { chunkId, score }
  metadata JSONB,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activities table
CREATE TABLE activities (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  lead_id UUID NOT NULL REFERENCES leads(id),
  user_id UUID NOT NULL REFERENCES users(id),
  type VARCHAR(50), -- call, email, meeting, note, status_change
  description TEXT,
  due_date TIMESTAMP,
  completed BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Analytics cache (for dashboard performance)
CREATE TABLE analytics_cache (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  metric_type VARCHAR(100),
  period VARCHAR(50),
  data JSONB,
  cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_leads_user_id ON leads(user_id);
CREATE INDEX idx_leads_status ON leads(status);
CREATE INDEX idx_leads_country ON leads(country);
CREATE INDEX idx_leads_score ON leads(score);
CREATE INDEX idx_conversations_user_id ON conversations(user_id);
CREATE INDEX idx_conversations_lead_id ON conversations(lead_id);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id);
CREATE INDEX idx_quotes_user_id ON quotes(user_id);
CREATE INDEX idx_quotes_status ON quotes(status);
CREATE INDEX idx_document_chunks_document_id ON document_chunks(document_id);

-- Vector index for embeddings (for similarity search)
CREATE INDEX idx_document_chunks_embedding ON document_chunks USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- Row Level Security (RLS) - Users can only see their own data
ALTER TABLE leads ENABLE ROW LEVEL SECURITY;
ALTER TABLE conversations ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE quotes ENABLE ROW LEVEL SECURITY;
ALTER TABLE activities ENABLE ROW LEVEL SECURITY;

-- RLS Policy for leads
CREATE POLICY lead_isolation ON leads USING (user_id = current_user_id());

-- RLS Policy for conversations
CREATE POLICY conversation_isolation ON conversations USING (user_id = current_user_id());

-- RLS Policy for quotes
CREATE POLICY quote_isolation ON quotes USING (user_id = current_user_id());

-- View for lead metrics
CREATE VIEW lead_metrics AS
SELECT
  l.country,
  l.status,
  COUNT(*) as count,
  AVG(l.score) as avg_score,
  MAX(l.budget) as max_budget
FROM leads l
GROUP BY l.country, l.status;

-- View for revenue metrics
CREATE VIEW revenue_metrics AS
SELECT
  q.currency,
  SUM(q.total) as total_revenue,
  COUNT(q.id) as quote_count,
  AVG(q.total) as avg_quote_value
FROM quotes q
WHERE q.status IN ('accepted', 'sent', 'viewed')
GROUP BY q.currency;
