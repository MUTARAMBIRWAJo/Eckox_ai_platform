"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { PageTransition } from "@/components/layout/page-transition";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { useAuth } from "@/hooks/use-auth";
import { apiClient, APIResponse } from "@/lib/api/client";
import {
  Plus, Edit3, Save, History, ClipboardList, Trash2, Loader2, RefreshCw, X, AlertTriangle,
} from "lucide-react";

// ── Types matching the backend Product model ───────────────────────────────────
interface BackendProduct {
  id: number;
  name: string;
  sku: string;
  price_eur: number | string;
  price_usd: number | string;
  stock_level: number;
  spec_processor?: string;
  spec_ram?: string;
  spec_storage?: string;
  created_at?: string;
  updated_at?: string;
}

interface BackendAuditLog {
  id: string;
  product_sku: string;
  user_name: string;
  action: string;
  old_value: string;
  new_value: string;
  created_at: string;
}

interface EditForm {
  price_eur: string;
  price_usd: string;
  stock_level: string;
  spec_processor: string;
  spec_ram: string;
  spec_storage: string;
}

interface CreateForm extends EditForm {
  name: string;
  sku: string;
}

const emptyCreate = (): CreateForm => ({
  name: "", sku: "", price_eur: "", price_usd: "",
  stock_level: "0", spec_processor: "", spec_ram: "", spec_storage: "",
});

const toEdit = (p: BackendProduct): EditForm => ({
  price_eur: String(p.price_eur),
  price_usd: String(p.price_usd),
  stock_level: String(p.stock_level),
  spec_processor: p.spec_processor ?? "",
  spec_ram: p.spec_ram ?? "",
  spec_storage: p.spec_storage ?? "",
});

export default function ProductsPage() {
  const { user, logout } = useAuth();
  const [products, setProducts] = useState<BackendProduct[]>([]);
  const [auditLogs, setAuditLogs] = useState<BackendAuditLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"catalog" | "audit">("catalog");
  const [selectedCurrency, setSelectedCurrency] = useState<"USD" | "EUR">("EUR");
  const [error, setError] = useState<string | null>(null);

  // Edit state
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<EditForm | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Delete state
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  // Create state
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(emptyCreate());
  const [createErrors, setCreateErrors] = useState<Partial<CreateForm>>({});
  const [isCreating, setIsCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  const loadProducts = async () => {
    setLoading(true);
    setError(null);
    try {
      const [prodRes, auditRes] = await Promise.all([
        apiClient.get<BackendProduct[]>("/products"),
        apiClient.get<BackendAuditLog[]>("/products/audit-logs"),
      ]);

      if (prodRes.success && prodRes.data) {
        setProducts(Array.isArray(prodRes.data) ? prodRes.data : []);
      } else {
        setError(prodRes.error ?? "Failed to load products.");
      }

      if (auditRes.success && auditRes.data) {
        setAuditLogs(Array.isArray(auditRes.data) ? auditRes.data : []);
      }
    } catch (err: any) {
      setError("Could not reach the backend.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadProducts(); }, []);

  // ── Edit ────────────────────────────────────────────────────────────────────

  const startEdit = (product: BackendProduct) => {
    setEditingId(product.id);
    setEditForm(toEdit(product));
    setSaveError(null);
  };

  const cancelEdit = () => { setEditingId(null); setEditForm(null); setSaveError(null); };

  const saveEdit = async (product: BackendProduct) => {
    if (!editForm) return;
    setIsSaving(true);
    setSaveError(null);
    try {
      const payload = {
        price_eur: parseFloat(editForm.price_eur),
        price_usd: parseFloat(editForm.price_usd),
        stock_level: parseInt(editForm.stock_level, 10),
        spec_processor: editForm.spec_processor || null,
        spec_ram: editForm.spec_ram || null,
        spec_storage: editForm.spec_storage || null,
      };
      const res = await apiClient.put<BackendProduct>(`/products/${product.id}`, payload);
      if (res.success) {
        await loadProducts();
        cancelEdit();
      } else {
        setSaveError(res.error ?? "Save failed.");
      }
    } catch (err: any) {
      setSaveError(err.message ?? "An error occurred.");
    } finally {
      setIsSaving(false);
    }
  };

  // ── Delete ──────────────────────────────────────────────────────────────────

  const confirmDelete = async () => {
    if (!deletingId) return;
    setIsDeleting(true);
    try {
      const res = await apiClient.delete(`/products/${deletingId}`);
      if (res.success) {
        setProducts((prev) => prev.filter((p) => p.id !== deletingId));
        await loadProducts();
      }
    } finally {
      setIsDeleting(false);
      setDeletingId(null);
    }
  };

  // ── Create ──────────────────────────────────────────────────────────────────

  const validateCreate = (): boolean => {
    const errs: Partial<CreateForm> = {};
    if (!createForm.name.trim()) errs.name = "Name is required";
    if (!createForm.sku.trim()) errs.sku = "SKU is required";
    if (!createForm.price_eur || isNaN(parseFloat(createForm.price_eur))) errs.price_eur = "Valid EUR price required";
    if (!createForm.price_usd || isNaN(parseFloat(createForm.price_usd))) errs.price_usd = "Valid USD price required";
    setCreateErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleCreate = async () => {
    if (!validateCreate()) return;
    setIsCreating(true);
    setCreateError(null);
    try {
      const payload = {
        name: createForm.name.trim(),
        sku: createForm.sku.trim(),
        price_eur: parseFloat(createForm.price_eur),
        price_usd: parseFloat(createForm.price_usd),
        stock_level: parseInt(createForm.stock_level || "0", 10),
        spec_processor: createForm.spec_processor || undefined,
        spec_ram: createForm.spec_ram || undefined,
        spec_storage: createForm.spec_storage || undefined,
      };
      const res = await apiClient.post<BackendProduct>("/products", payload);
      if (res.success) {
        setCreateOpen(false);
        setCreateForm(emptyCreate());
        await loadProducts();
      } else {
        // Surface 422 field errors
        const data = res.data as any;
        if (data?.errors) {
          const errs: Partial<CreateForm> = {};
          for (const [field, msgs] of Object.entries(data.errors)) {
            (errs as any)[field] = Array.isArray(msgs) ? msgs[0] : msgs;
          }
          setCreateErrors(errs);
        } else {
          setCreateError(res.error ?? "Failed to create product.");
        }
      }
    } catch (err: any) {
      setCreateError(err.message);
    } finally {
      setIsCreating(false);
    }
  };

  const price = (product: BackendProduct) =>
    selectedCurrency === "EUR"
      ? `€${Number(product.price_eur).toFixed(2)}`
      : `$${Number(product.price_usd).toFixed(2)}`;

  return (
    <AppLayout
      headerProps={{
        user: user
          ? { name: user.name, email: user.email, avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + user.email }
          : undefined,
        onLogout: () => logout(),
      }}
    >
      <PageTransition>
        <div className="space-y-6">
          {/* Header */}
          <div className="page-header">
            <div>
              <h1 className="page-title">Product Catalog</h1>
              <p className="text-muted-foreground text-sm mt-1">
                Manage Eckox hardware products, pricing, and stock.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={loadProducts} disabled={loading}>
                <RefreshCw className={`w-4 h-4 mr-1.5 ${loading ? "animate-spin" : ""}`} />
                Refresh
              </Button>
              <Button className="btn-primary" onClick={() => { setCreateForm(emptyCreate()); setCreateErrors({}); setCreateError(null); setCreateOpen(true); }}>
                <Plus className="w-4 h-4 mr-2" />
                New Product
              </Button>
            </div>
          </div>

          {/* Tab toggles */}
          <div className="flex gap-2 border-b border-border pb-2">
            <Button variant={activeTab === "catalog" ? "default" : "ghost"} size="sm" onClick={() => setActiveTab("catalog")}>
              <ClipboardList className="w-4 h-4 mr-2" />
              Product Catalog
            </Button>
            <Button variant={activeTab === "audit" ? "default" : "ghost"} size="sm" onClick={() => setActiveTab("audit")}>
              <History className="w-4 h-4 mr-2" />
              Audit Log
            </Button>
            <div className="ml-auto flex items-center gap-2">
              <span className="text-xs text-muted-foreground">Currency:</span>
              {(["EUR", "USD"] as const).map((c) => (
                <Button key={c} variant={selectedCurrency === c ? "default" : "outline"} size="sm"
                  className="text-xs px-2 h-7" onClick={() => setSelectedCurrency(c)}>
                  {c}
                </Button>
              ))}
            </div>
          </div>

          {/* Error */}
          {error && (
            <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-4 text-sm text-destructive flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 shrink-0" />
              {error}
            </div>
          )}

          {/* Catalog Tab */}
          {activeTab === "catalog" && (
            loading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {Array.from({ length: 6 }).map((_, i) => (
                  <div key={i} className="h-48 bg-secondary/20 rounded-2xl animate-pulse" />
                ))}
              </div>
            ) : products.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-48 text-center">
                <ClipboardList className="w-10 h-10 text-muted-foreground/40 mb-3" />
                <p className="text-sm font-semibold text-foreground">No products found</p>
                <p className="text-xs text-muted-foreground mt-1">Create your first product to get started.</p>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {products.map((product) => (
                  <Card key={product.id} className="border-border bg-card/60 backdrop-blur-md">
                    <CardHeader className="pb-2">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <CardTitle className="text-base">{product.name}</CardTitle>
                          <Badge variant="outline" className="mt-1 text-[10px]">{product.sku}</Badge>
                        </div>
                        <div className="flex gap-1">
                          {editingId !== product.id && (
                            <>
                              <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => startEdit(product)}>
                                <Edit3 className="w-3.5 h-3.5" />
                              </Button>
                              <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => setDeletingId(product.id)}>
                                <Trash2 className="w-3.5 h-3.5" />
                              </Button>
                            </>
                          )}
                        </div>
                      </div>
                    </CardHeader>

                    <CardContent className="space-y-3">
                      {editingId === product.id && editForm ? (
                        /* Edit Form */
                        <div className="space-y-2">
                          {saveError && <p className="text-xs text-destructive">{saveError}</p>}
                          {(["price_eur", "price_usd", "stock_level", "spec_processor", "spec_ram", "spec_storage"] as (keyof EditForm)[]).map((field) => (
                            <div key={field} className="space-y-1">
                              <Label className="text-[10px] capitalize">{field.replace(/_/g, " ")}</Label>
                              <Input
                                value={editForm[field]}
                                onChange={(e) => setEditForm((p) => p ? { ...p, [field]: e.target.value } : p)}
                                className="h-7 text-xs"
                                type={field.includes("price") || field === "stock_level" ? "number" : "text"}
                                min="0"
                                step={field.includes("price") ? "0.01" : "1"}
                              />
                            </div>
                          ))}
                          <div className="flex gap-2 pt-1">
                            <Button size="sm" className="flex-1 h-7 text-xs" onClick={() => saveEdit(product)} disabled={isSaving}>
                              {isSaving ? <Loader2 className="w-3 h-3 animate-spin mr-1" /> : <Save className="w-3 h-3 mr-1" />}
                              Save
                            </Button>
                            <Button size="sm" variant="outline" className="h-7 text-xs" onClick={cancelEdit} disabled={isSaving}>
                              Cancel
                            </Button>
                          </div>
                        </div>
                      ) : (
                        /* Display */
                        <>
                          <div className="flex justify-between items-center">
                            <span className="text-xs text-muted-foreground">Price</span>
                            <span className="text-base font-bold text-primary">{price(product)}</span>
                          </div>
                          <div className="flex justify-between items-center">
                            <span className="text-xs text-muted-foreground">Stock</span>
                            <Badge className={product.stock_level > 10 ? "bg-emerald-500/20 text-emerald-400" : "bg-rose-500/20 text-rose-400"}>
                              {product.stock_level} units
                            </Badge>
                          </div>
                          {product.spec_processor && (
                            <div className="text-[10px] text-muted-foreground border-t border-border pt-2 space-y-0.5">
                              <p>CPU: {product.spec_processor}</p>
                              {product.spec_ram && <p>RAM: {product.spec_ram}</p>}
                              {product.spec_storage && <p>Storage: {product.spec_storage}</p>}
                            </div>
                          )}
                        </>
                      )}
                    </CardContent>
                  </Card>
                ))}
              </div>
            )
          )}

          {/* Audit Log Tab */}
          {activeTab === "audit" && (
            loading ? (
              <div className="space-y-2">
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} className="h-12 bg-secondary/20 rounded-xl animate-pulse" />
                ))}
              </div>
            ) : auditLogs.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-48 text-center">
                <History className="w-10 h-10 text-muted-foreground/40 mb-3" />
                <p className="text-sm font-semibold text-foreground">No audit logs</p>
                <p className="text-xs text-muted-foreground mt-1">Price and stock changes will appear here.</p>
              </div>
            ) : (
              <div className="overflow-x-auto rounded-2xl border border-border">
                <table className="w-full text-sm">
                  <thead className="bg-secondary/20 text-xs text-muted-foreground uppercase">
                    <tr>
                      {["SKU", "User", "Action", "Old Value", "New Value", "When"].map((h) => (
                        <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {auditLogs.map((log) => (
                      <tr key={log.id} className="hover:bg-secondary/10 transition-colors">
                        <td className="px-4 py-2.5 font-mono text-xs">{log.product_sku}</td>
                        <td className="px-4 py-2.5">{log.user_name}</td>
                        <td className="px-4 py-2.5">{log.action}</td>
                        <td className="px-4 py-2.5 text-muted-foreground">{log.old_value}</td>
                        <td className="px-4 py-2.5 text-primary font-medium">{log.new_value}</td>
                        <td className="px-4 py-2.5 text-muted-foreground text-xs">
                          {new Date(log.created_at).toLocaleString()}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          )}
        </div>
      </PageTransition>

      {/* Delete Confirm Dialog */}
      <Dialog open={deletingId !== null} onOpenChange={(o) => { if (!o) setDeletingId(null); }}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-destructive">
              <AlertTriangle className="w-5 h-5" /> Confirm Deletion
            </DialogTitle>
            <DialogDescription>
              This will permanently delete the product and create an audit log entry. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => setDeletingId(null)} disabled={isDeleting}>Cancel</Button>
            <Button variant="destructive" onClick={confirmDelete} disabled={isDeleting}>
              {isDeleting ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Trash2 className="w-4 h-4 mr-2" />}
              Delete
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Create Product Dialog */}
      <Dialog open={createOpen} onOpenChange={(o) => { if (!o) { setCreateForm(emptyCreate()); setCreateErrors({}); setCreateError(null); } setCreateOpen(o); }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Plus className="w-5 h-5 text-primary" /> New Product
            </DialogTitle>
            <DialogDescription>Add a new product to the Eckox catalog.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3 pt-2">
            {createError && <p className="text-sm text-destructive bg-destructive/10 rounded px-3 py-2">{createError}</p>}
            {(["name", "sku", "price_eur", "price_usd", "stock_level", "spec_processor", "spec_ram", "spec_storage"] as (keyof CreateForm)[]).map((field) => (
              <div key={field} className="space-y-1">
                <Label htmlFor={`create-${field}`} className="text-xs capitalize">
                  {field.replace(/_/g, " ")}
                  {["name", "sku", "price_eur", "price_usd"].includes(field) && (
                    <span className="text-destructive ml-1">*</span>
                  )}
                </Label>
                <Input
                  id={`create-${field}`}
                  value={createForm[field]}
                  onChange={(e) => {
                    setCreateForm((p) => ({ ...p, [field]: e.target.value }));
                    setCreateErrors((p) => ({ ...p, [field]: undefined }));
                  }}
                  className={`h-8 text-sm ${createErrors[field] ? "border-destructive" : ""}`}
                  type={field.includes("price") || field === "stock_level" ? "number" : "text"}
                  min="0"
                  step={field.includes("price") ? "0.01" : "1"}
                  placeholder={field.includes("price") ? "0.00" : field === "stock_level" ? "0" : ""}
                />
                {createErrors[field] && (
                  <p className="text-[10px] text-destructive">{createErrors[field]}</p>
                )}
              </div>
            ))}
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="outline" onClick={() => { setCreateForm(emptyCreate()); setCreateOpen(false); }} disabled={isCreating}>
                Cancel
              </Button>
              <Button className="btn-primary" onClick={handleCreate} disabled={isCreating}>
                {isCreating ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Plus className="w-4 h-4 mr-2" />}
                Create
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
