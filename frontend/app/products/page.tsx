"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { authAPI, AuthUser } from "@/lib/api";
import { Plus, Edit3, Save, History, ClipboardList } from "lucide-react";

interface Product {
  id: number;
  name: string;
  sku: string;
  priceEur: number;
  priceUsd: number;
  stockLevel: number;
  specProcessor?: string;
  specRam?: string;
  specStorage?: string;
}

interface AuditLog {
  id: string;
  productSku: string;
  userName: string;
  action: string;
  oldValue: string;
  newValue: string;
  timestamp: string;
}

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>([]);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedCurrency, setSelectedCurrency] = useState<"USD" | "EUR">("USD");

  // Edit State
  const [editingProd, setEditingProd] = useState<Partial<Product> | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    const loadData = async () => {
      try {
        const currentUser = await authAPI.getCurrentUser();
        setUser(currentUser);

        // Mock database products matching backend migration schema
        const mockProducts: Product[] = [
          {
            id: 1,
            name: "Eckox Processor X",
            sku: "SKU-PROC-X",
            priceEur: 800.0,
            priceUsd: 870.0,
            stockLevel: 45,
            specProcessor: "64-Core Armv9",
            specRam: "128GB LPDDR5X",
            specStorage: "2TB NVMe PCIe 5.0",
          },
          {
            id: 2,
            name: "Eckox Server Hub Prime",
            sku: "SKU-SERV-HUB",
            priceEur: 2400.0,
            priceUsd: 2600.0,
            stockLevel: 14,
            specProcessor: "128-Core Armv9 Enterprise",
            specRam: "512GB ECC DDR5",
            specStorage: "8TB RAID-10 NVMe",
          }
        ];
        setProducts(mockProducts);

        // Prepopulate audit logs
        setAuditLogs([
          {
            id: "1",
            productSku: "SKU-PROC-X",
            userName: "John Doe",
            action: "Price updated (EUR)",
            oldValue: "€750.00",
            newValue: "€800.00",
            timestamp: "2026-07-04T18:00:00Z"
          },
          {
            id: "2",
            productSku: "SKU-SERV-HUB",
            userName: "Sarah Smith",
            action: "Stock received",
            oldValue: "10",
            newValue: "14",
            timestamp: "2026-07-04T12:00:00Z"
          }
        ]);

      } catch (err) {
        console.error("Failed to load products:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const handleSaveProduct = async () => {
    if (!editingProd || !user) return;
    setIsSaving(true);
    // Find original product to calculate log diff
    const original = products.find((p) => p.id === editingProd.id);
    if (original) {
      const logsToAppend: AuditLog[] = [];
      if (editingProd.priceEur !== undefined && editingProd.priceEur !== original.priceEur) {
        logsToAppend.push({
          id: String(Date.now()),
          productSku: original.sku,
          userName: user.name,
          action: "Price updated (EUR)",
          oldValue: `€${original.priceEur.toFixed(2)}`,
          newValue: `€${editingProd.priceEur.toFixed(2)}`,
          timestamp: new Date().toISOString()
        });
      }
      if (editingProd.priceUsd !== undefined && editingProd.priceUsd !== original.priceUsd) {
        logsToAppend.push({
          id: String(Date.now() + 1),
          productSku: original.sku,
          userName: user.name,
          action: "Price updated (USD)",
          oldValue: `$${original.priceUsd.toFixed(2)}`,
          newValue: `$${editingProd.priceUsd.toFixed(2)}`,
          timestamp: new Date().toISOString()
        });
      }
      if (editingProd.stockLevel !== undefined && editingProd.stockLevel !== original.stockLevel) {
        logsToAppend.push({
          id: String(Date.now() + 2),
          productSku: original.sku,
          userName: user.name,
          action: "Stock level adjusted",
          oldValue: String(original.stockLevel),
          newValue: String(editingProd.stockLevel),
          timestamp: new Date().toISOString()
        });
      }

      setAuditLogs((prev) => [...logsToAppend, ...prev]);
      setProducts((prev) => prev.map((p) => (p.id === editingProd.id ? { ...p, ...editingProd } as Product : p)));
    }
    setIsSaving(false);
    setEditingProd(null);
  };

  return (
    <AppLayout
      headerProps={{
        user,
        onLogout: () => window.location.href = "/login",
      }}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-500 bg-clip-text text-transparent">
              CPQ Product Catalog
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Manage product pricing, hardware specifications, and inventory levels.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground font-semibold">Base Currency:</span>
            {(["USD", "EUR"] as const).map((curr) => (
              <Button
                key={curr}
                variant={selectedCurrency === curr ? "default" : "outline"}
                size="sm"
                onClick={() => setSelectedCurrency(curr)}
                className={selectedCurrency === curr ? "bg-emerald-500 text-white border-emerald-500" : ""}
              >
                {curr}
              </Button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Products List Grid */}
          <div className="col-span-1 lg:col-span-2 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {products.map((prod) => (
                <Card key={prod.id} className="border-border bg-card/60 backdrop-blur-md">
                  <CardHeader className="pb-2">
                    <div className="flex justify-between items-start">
                      <div>
                        <CardTitle className="text-md">{prod.name}</CardTitle>
                        <CardDescription className="font-mono text-xs">{prod.sku}</CardDescription>
                      </div>
                      <Button variant="ghost" size="icon" onClick={() => setEditingProd(prod)}>
                        <Edit3 className="w-4 h-4 text-emerald-400" />
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-4 text-xs">
                    <div className="flex justify-between py-1 border-b border-border">
                      <span className="text-muted-foreground">Price</span>
                      <span className="font-bold text-foreground">
                        {selectedCurrency === "USD" ? `$${prod.priceUsd}` : `€${prod.priceEur}`}
                      </span>
                    </div>
                    <div className="flex justify-between py-1 border-b border-border">
                      <span className="text-muted-foreground">Stock Level</span>
                      <span className={`font-bold ${prod.stockLevel < 15 ? 'text-amber-400' : 'text-emerald-500'}`}>
                        {prod.stockLevel} units
                      </span>
                    </div>
                    <div className="space-y-1 pt-1">
                      <span className="text-muted-foreground font-bold">Specs:</span>
                      <p className="text-[11px] text-muted-foreground">CPU: {prod.specProcessor}</p>
                      <p className="text-[11px] text-muted-foreground">RAM: {prod.specRam}</p>
                      <p className="text-[11px] text-muted-foreground">Storage: {prod.specStorage}</p>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>

          {/* Audit Logs & Editor Sidebar */}
          <div className="col-span-1 space-y-6">
            {/* Editor Drawer */}
            {editingProd && (
              <Card className="border-emerald-500/40 bg-card shadow-xl">
                <CardHeader>
                  <CardTitle className="text-sm">Edit Product Catalog Entry</CardTitle>
                  <CardDescription className="text-xs font-mono">{editingProd.sku}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3 text-xs">
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-muted-foreground mb-1">Price (EUR)</label>
                      <input
                        type="number"
                        value={editingProd.priceEur || 0}
                        onChange={(e) => setEditingProd({ ...editingProd, priceEur: parseFloat(e.target.value) })}
                        className="w-full input-base bg-background border border-border rounded-xl p-2 focus:outline-none"
                      />
                    </div>
                    <div>
                      <label className="block text-muted-foreground mb-1">Price (USD)</label>
                      <input
                        type="number"
                        value={editingProd.priceUsd || 0}
                        onChange={(e) => setEditingProd({ ...editingProd, priceUsd: parseFloat(e.target.value) })}
                        className="w-full input-base bg-background border border-border rounded-xl p-2 focus:outline-none"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="block text-muted-foreground mb-1">Stock Level</label>
                    <input
                      type="number"
                      value={editingProd.stockLevel || 0}
                      onChange={(e) => setEditingProd({ ...editingProd, stockLevel: parseInt(e.target.value) })}
                      className="w-full input-base bg-background border border-border rounded-xl p-2 focus:outline-none"
                    />
                  </div>
                  <div className="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" onClick={() => setEditingProd(null)}>
                      Cancel
                    </Button>
                    <Button
                      onClick={handleSaveProduct}
                      disabled={isSaving}
                      className="bg-emerald-500 hover:bg-emerald-600 text-white gap-1.5"
                    >
                      <Save className="w-3.5 h-3.5" />
                      Save Product
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Audit Trail */}
            <Card className="border-border bg-card/60 backdrop-blur-md">
              <CardHeader className="pb-2">
                <CardTitle className="text-md flex items-center gap-1.5">
                  <History className="w-5 h-5 text-emerald-400" />
                  Product Change Audit Log
                </CardTitle>
                <CardDescription>Live tracking of price, stock, and specification edits.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {auditLogs.map((log) => (
                  <div key={log.id} className="p-3 bg-secondary/15 rounded-xl border border-border space-y-1">
                    <div className="flex justify-between items-center text-[10px]">
                      <span className="font-semibold text-emerald-500">{log.productSku}</span>
                      <span className="text-muted-foreground">{new Date(log.timestamp).toLocaleTimeString()}</span>
                    </div>
                    <p className="text-xs text-foreground font-bold">{log.action}</p>
                    <p className="text-[10px] text-muted-foreground">
                      Changed by: <span className="text-foreground">{log.userName}</span>
                    </p>
                    <p className="text-[10px] text-muted-foreground font-mono">
                      {log.oldValue} → {log.newValue}
                    </p>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
