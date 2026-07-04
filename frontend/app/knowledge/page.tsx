"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { authAPI, AuthUser } from "@/lib/api";
import { Plus, Search, BookOpen, FileText, Lightbulb } from "lucide-react";

const KNOWLEDGE_ARTICLES = [
  {
    id: 1,
    title: "Getting Started with Sales Automation",
    category: "Setup",
    views: 1240,
    helpful: 89,
  },
  {
    id: 2,
    title: "CPQ Pricing Rules Best Practices",
    category: "CPQ",
    views: 856,
    helpful: 92,
  },
  {
    id: 3,
    title: "Lead Scoring and Qualification",
    category: "Sales",
    views: 1502,
    helpful: 87,
  },
  {
    id: 4,
    title: "Integrating CRM with External Tools",
    category: "Integration",
    views: 673,
    helpful: 85,
  },
  {
    id: 5,
    title: "AI Assistant Advanced Prompts",
    category: "AI",
    views: 945,
    helpful: 94,
  },
  {
    id: 6,
    title: "Analytics and Reporting Guide",
    category: "Analytics",
    views: 1123,
    helpful: 91,
  },
];

export default function KnowledgeBasePage() {
  const [articles, setArticles] = useState(KNOWLEDGE_ARTICLES);
  const [search, setSearch] = useState("");
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadUser = async () => {
      try {
        const currentUser = await authAPI.getCurrentUser();
        setUser(currentUser);
      } catch (err) {
        console.error("Failed to load user:", err);
      } finally {
        setLoading(false);
      }
    };

    loadUser();
  }, []);

  const filteredArticles = articles.filter((article) =>
    article.title.toLowerCase().includes(search.toLowerCase()) ||
    article.category.toLowerCase().includes(search.toLowerCase())
  );

  const categories = Array.from(new Set(articles.map((a) => a.category)));

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6 max-w-4xl">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Knowledge Base</h1>
            <p className="text-muted-foreground mt-1">Documentation, guides, and best practices</p>
          </div>
          <Button className="btn-primary gap-2">
            <Plus className="w-4 h-4" />
            New Article
          </Button>
        </div>

        {/* Search */}
        <Card className="card">
          <CardContent className="pt-6">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
              <Input
                placeholder="Search articles..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-10 bg-background"
              />
            </div>
          </CardContent>
        </Card>

        {/* Featured Articles */}
        <div>
          <h2 className="text-lg font-semibold mb-4">Featured</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {filteredArticles.slice(0, 2).map((article) => (
              <Card key={article.id} className="card hover:shadow-lg transition-shadow cursor-pointer">
                <CardHeader>
                  <div className="flex items-start justify-between gap-2">
                    <Lightbulb className="w-5 h-5 text-primary flex-shrink-0 mt-1" />
                    <Badge className="bg-primary/10 text-primary">{article.category}</Badge>
                  </div>
                  <CardTitle className="text-base mt-2">{article.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{article.views} views</span>
                    <span>{article.helpful}% found helpful</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>

        {/* Categories */}
        <div>
          <h2 className="text-lg font-semibold mb-4">Browse by Category</h2>
          <div className="flex flex-wrap gap-2 mb-6">
            {categories.map((category) => (
              <Button key={category} variant="outline" size="sm">
                {category}
              </Button>
            ))}
          </div>
        </div>

        {/* All Articles */}
        <div>
          <h2 className="text-lg font-semibold mb-4">All Articles</h2>
          <div className="space-y-3">
            {filteredArticles.map((article) => (
              <Card key={article.id} className="card hover:shadow-md transition-shadow cursor-pointer">
                <CardContent className="pt-6 flex items-center justify-between">
                  <div className="flex items-start gap-3 flex-1">
                    <FileText className="w-5 h-5 text-muted-foreground flex-shrink-0 mt-0.5" />
                    <div className="flex-1 min-w-0">
                      <h3 className="font-medium line-clamp-2">{article.title}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {article.views} views • {article.helpful}% helpful
                      </p>
                    </div>
                  </div>
                  <Badge variant="outline" className="ml-2 flex-shrink-0">
                    {article.category}
                  </Badge>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
