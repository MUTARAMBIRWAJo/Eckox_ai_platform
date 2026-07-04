"use client";

import { useEffect, useState } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { authAPI, AuthUser } from "@/lib/api";
import { PRODUCTS, Product } from "@/lib/data/products";
import { Plus, ShoppingCart } from "lucide-react";

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedCurrency, setSelectedCurrency] = useState<"USD" | "EUR" | "NGN">("USD");

  useEffect(() => {
    const loadData = async () => {
      try {
        const currentUser = await authAPI.getCurrentUser();
        setUser(currentUser);
        setProducts(PRODUCTS);
      } catch (err) {
        console.error("Failed to load products:", err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
      "Analyzer": "bg-blue-500/10 text-blue-400",
      "Centrifuge": "bg-purple-500/10 text-purple-400",
      "Incubator": "bg-green-500/10 text-green-400",
      "Microscope": "bg-cyan-500/10 text-cyan-400",
      "Spectrophotometer": "bg-yellow-500/10 text-yellow-400",
      "PCR": "bg-orange-500/10 text-orange-400",
    };
    return colors[category] || "bg-secondary";
  };

  const getPrice = (product: Product) => {
    if (selectedCurrency === "USD") {
      return `$${product.priceUSD.toLocaleString()}`;
    } else if (selectedCurrency === "EUR") {
      return `€${product.priceEUR.toLocaleString()}`;
    }
    return `₦${product.priceNGN.toLocaleString()}`;
  };

  const getCurrencySymbol = (currency: "USD" | "EUR" | "NGN") => {
    const symbols = { USD: "$", EUR: "€", NGN: "₦" };
    return symbols[currency];
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
            <h1 className="text-3xl font-bold">Products</h1>
            <p className="text-muted-foreground mt-1">Lab equipment catalog for CPQ</p>
          </div>
          <Button className="btn-primary gap-2">
            <Plus className="w-4 h-4" />
            Add Product
          </Button>
        </div>

        {/* Currency Selector */}
        <Card className="card">
          <CardContent className="pt-6">
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium">Display Currency:</span>
              {(["USD", "EUR", "NGN"] as const).map((currency) => (
                <Button
                  key={currency}
                  variant={selectedCurrency === currency ? "default" : "outline"}
                  size="sm"
                  onClick={() => setSelectedCurrency(currency)}
                >
                  {getCurrencySymbol(currency)} {currency}
                </Button>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Products Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {loading ? (
            Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="card p-6 space-y-4 animate-pulse">
                <div className="h-8 bg-secondary rounded w-3/4" />
                <div className="h-4 bg-secondary rounded" />
                <div className="h-20 bg-secondary rounded" />
              </div>
            ))
          ) : (
            products.map((product) => (
              <Card key={product.id} className="card hover:shadow-lg transition-shadow overflow-hidden">
                <CardHeader>
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                      <CardTitle className="text-base line-clamp-2">{product.name}</CardTitle>
                      <Badge className={`mt-2 ${getCategoryColor(product.category)}`}>
                        {product.category}
                      </Badge>
                    </div>
                    <ShoppingCart className="w-4 h-4 text-muted-foreground flex-shrink-0" />
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-sm text-muted-foreground line-clamp-3">
                    {product.description}
                  </p>
                  
                  {/* Price */}
                  <div className="pt-4 border-t border-border">
                    <div className="text-2xl font-bold">{getPrice(product)}</div>
                    <p className="text-xs text-muted-foreground mt-1">
                      Tax: {(product.taxRate * 100).toFixed(0)}%
                    </p>
                  </div>

                  {/* Actions */}
                  <div className="flex gap-2 pt-2">
                    <Button className="flex-1 btn-primary">Add to Quote</Button>
                    <Button variant="outline" className="btn-secondary" size="sm">
                      Details
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>
    </AppLayout>
  );
}
