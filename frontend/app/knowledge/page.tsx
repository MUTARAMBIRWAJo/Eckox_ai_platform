"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { AIAPI, KBEntry } from "@/lib/api/ai.api";
import { useAuth } from "@/hooks/use-auth";
import { Plus, Search, BookOpen, Trash2, Eye, ShieldAlert, Sparkles, HelpCircle, Save } from "lucide-react";

export default function KnowledgeBasePage() {
  const [entries, setEntries] = useState<KBEntry[]>([]);
  const { user, logout } = useAuth();
  const [loading, setLoading] = useState(true);

  // Form State
  const [editingEntry, setEditingEntry] = useState<Partial<KBEntry> | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Test Query State
  const [testQuery, setTestQuery] = useState("");
  const [testResults, setTestResults] = useState<{ score: number; content: string }[] | null>(null);
  const [testingQuery, setTestingQuery] = useState(false);

  useEffect(() => {
    const loadData = async () => {
      try {
        const kbRes = await AIAPI.getKBEntries();
        if (kbRes.success && kbRes.data) {
          setEntries(kbRes.data);
        }
      } catch (err) {
        console.error("Failed to load knowledge base:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const handleSave = async () => {
    if (!editingEntry) return;
    setIsSaving(true);
    try {
      if (editingEntry.id) {
        // Update
        const res = await AIAPI.updateKBEntry(editingEntry.id, editingEntry);
        if (res.success && res.data) {
          setEntries((prev) => prev.map((e) => (e.id === res.data.id ? res.data : e)));
        }
      } else {
        // Create
        const res = await AIAPI.createKBEntry(editingEntry as Omit<KBEntry, 'id'>);
        if (res.success && res.data) {
          setEntries((prev) => [...prev, res.data]);
        }
      }
      setEditingEntry(null);
    } catch (e) {
      console.error(e);
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this knowledge base entry?")) return;
    const res = await AIAPI.deleteKBEntry(id);
    if (res.success) {
      setEntries((prev) => prev.filter((e) => e.id !== id));
    }
  };

  const handleTestQuery = async () => {
    if (!testQuery.trim()) return;
    setTestingQuery(true);
    try {
      const res = await AIAPI.testKBQuery(testQuery);
      if (res.success && res.data) {
        setTestResults(res.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setTestingQuery(false);
    }
  };

  return (
    <AppLayout
      headerProps={{
        user: user
          ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email }
          : undefined,
        onLogout: () => logout(),
      }}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              Knowledge Base Grounding
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Manage the source materials that guide and constrain LLM response generation.
            </p>
          </div>
          <Button onClick={() => setEditingEntry({
            region: "europe",
            docType: "compliance",
            productCategory: "hardware",
            content: "",
            effectiveDate: new Date().toISOString().split('T')[0],
            isActive: true,
          })} className="bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl gap-2 shadow-lg shadow-emerald-500/20">
            <Plus className="w-4 h-4" /> Add Document Entry
          </Button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main List */}
          <div className="col-span-1 lg:col-span-2 space-y-4">
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle className="text-lg">Grounding Directory</CardTitle>
                <CardDescription>CE marks, SLA documents, and localized specifications.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {entries.map((entry) => (
                  <div key={entry.id} className="p-4 bg-secondary/15 rounded-2xl border border-border space-y-3">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                      <div className="flex items-center gap-2">
                        <Badge className="bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 uppercase">
                          {entry.region}
                        </Badge>
                        <Badge variant="outline" className="capitalize">
                          {entry.docType}
                        </Badge>
                        {entry.productCategory && (
                          <Badge variant="secondary" className="text-xs">
                            {entry.productCategory}
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-2">
                        {entry.embeddingStatus === 'in_progress' ? (
                          <span className="text-[10px] text-amber-400 animate-pulse font-semibold">
                            Embedding Generation in Progress...
                          </span>
                        ) : (
                          <span className="text-[10px] text-emerald-500 font-semibold">Active & Embedded</span>
                        )}
                        <button
                          onClick={() => setEditingEntry(entry)}
                          className="p-1 hover:text-foreground text-muted-foreground transition-colors"
                          aria-label="Edit entry"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(entry.id)}
                          className="p-1 hover:text-rose-500 text-muted-foreground transition-colors"
                          aria-label="Delete entry"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                    <p className="text-sm text-foreground/80 line-clamp-3 bg-background/40 p-3 rounded-xl border border-border/40 whitespace-pre-wrap">
                      {entry.content}
                    </p>
                    <div className="text-[10px] text-muted-foreground flex justify-between">
                      <span>Effective: {entry.effectiveDate}</span>
                      <span>ID: {entry.id}</span>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>

          {/* Test & Config Sidebar */}
          <div className="col-span-1 space-y-6">
            {/* Semantic pgvector search tester */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader>
                <CardTitle className="text-md flex items-center gap-1.5">
                  <Sparkles className="w-5 h-5 text-emerald-400" />
                  Semantic Retrieval Tester
                </CardTitle>
                <CardDescription>Test which knowledge base entries get retrieved for customer questions.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="Type client query (e.g. ce cert)..."
                    value={testQuery}
                    onChange={(e) => setTestQuery(e.target.value)}
                    className="flex-1 input-base rounded-xl px-3 py-1.5 bg-background border border-border focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                  />
                  <Button
                    onClick={handleTestQuery}
                    disabled={testingQuery}
                    className="bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs"
                  >
                    {testingQuery ? "Testing..." : "Query"}
                  </Button>
                </div>

                {testResults && (
                  <div className="space-y-2 mt-2">
                    <p className="text-xs font-semibold">Retrieved chunks (pgvector cosine similarity):</p>
                    {testResults.map((res, i) => (
                      <div key={i} className="p-2.5 bg-secondary/20 rounded-xl border border-border space-y-1.5">
                        <div className="flex justify-between items-center text-[10px]">
                          <span className="font-mono text-emerald-500 font-bold">Similarity: {Math.round(res.score * 100)}%</span>
                          <span>Rank #{i + 1}</span>
                        </div>
                        <p className="text-[11px] text-muted-foreground line-clamp-2">"{res.content}"</p>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Entry Form Modal Drawer overlay */}
            {editingEntry && (
              <Card className="border-emerald-500/40 bg-card shadow-2xl">
                <CardHeader>
                  <CardTitle className="text-md">{editingEntry.id ? "Edit Document Entry" : "Create Document Entry"}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 text-xs">
                  <div>
                    <label className="block text-muted-foreground mb-1">Content</label>
                    <textarea
                      rows={5}
                      value={editingEntry.content || ""}
                      onChange={(e) => setEditingEntry({ ...editingEntry, content: e.target.value })}
                      placeholder="Input ce guidelines or delivery SLAs..."
                      className="w-full input-base bg-background border border-border rounded-xl p-3 focus:outline-none"
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-muted-foreground mb-1">Region</label>
                      <select
                        value={editingEntry.region || "europe"}
                        onChange={(e) => setEditingEntry({ ...editingEntry, region: e.target.value as any })}
                        className="w-full bg-background border border-border rounded-xl p-2"
                      >
                        <option value="europe">Europe</option>
                        <option value="africa">Africa</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-muted-foreground mb-1">Doc Type</label>
                      <select
                        value={editingEntry.docType || "compliance"}
                        onChange={(e) => setEditingEntry({ ...editingEntry, docType: e.target.value as any })}
                        className="w-full bg-background border border-border rounded-xl p-2"
                      >
                        <option value="compliance">Compliance</option>
                        <option value="sla">SLA</option>
                        <option value="faq">FAQ</option>
                        <option value="brochure">Brochure</option>
                      </select>
                    </div>
                  </div>
                  <div className="flex gap-2 justify-end pt-2">
                    <Button variant="outline" size="sm" onClick={() => setEditingEntry(null)}>
                      Cancel
                    </Button>
                    <Button
                      onClick={handleSave}
                      disabled={isSaving}
                      className="bg-emerald-500 hover:bg-emerald-600 text-white gap-1.5"
                    >
                      <Save className="w-3.5 h-3.5" />
                      {isSaving ? "Saving..." : "Save Entry"}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
