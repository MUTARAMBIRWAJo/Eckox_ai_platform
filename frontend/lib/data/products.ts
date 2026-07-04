// Lab Equipment Products for CPQ
export interface Product {
  id: string;
  name: string;
  category: "Analyzer" | "Centrifuge" | "Incubator" | "Microscope" | "Spectrophotometer" | "PCR";
  description: string;
  priceUSD: number;
  priceEUR: number;
  priceNGN: number;
  taxRate: number; // EU: 19-21%, Africa: 5-15%
}

export const PRODUCTS: Product[] = [
  {
    id: "prod_001",
    name: "Clinical Chemistry Analyzer X-1000",
    category: "Analyzer",
    description: "High-throughput automated chemistry analyzer with 1000 tests/hour capacity",
    priceUSD: 85000,
    priceEUR: 78000,
    priceNGN: 41250000,
    taxRate: 0.19, // EU
  },
  {
    id: "prod_002",
    name: "Micro Centrifuge Pro Max",
    category: "Centrifuge",
    description: "Benchtop microcentrifuge with 16,000 RPM and temperature control",
    priceUSD: 12500,
    priceEUR: 11500,
    priceNGN: 6062500,
    taxRate: 0.19,
  },
  {
    id: "prod_003",
    name: "Incubator Precision Series",
    category: "Incubator",
    description: "Digital incubator with precise temperature control ±0.5°C",
    priceUSD: 8900,
    priceEUR: 8200,
    priceNGN: 4337500,
    taxRate: 0.1, // Africa average
  },
  {
    id: "prod_004",
    name: "Research Microscope Platinum",
    category: "Microscope",
    description: "Trinocular research microscope with 40x-1000x magnification",
    priceUSD: 18500,
    priceEUR: 17000,
    priceNGN: 9062500,
    taxRate: 0.05,
  },
  {
    id: "prod_005",
    name: "UV-Vis Spectrophotometer 450",
    category: "Spectrophotometer",
    description: "Double beam spectrophotometer with 190-1100nm range",
    priceUSD: 22000,
    priceEUR: 20200,
    priceNGN: 10625000,
    taxRate: 0.19,
  },
  {
    id: "prod_006",
    name: "Real-Time PCR System Q-Track",
    category: "PCR",
    description: "48-well qPCR system with optical detection and data analysis",
    priceUSD: 45000,
    priceEUR: 41500,
    priceNGN: 21937500,
    taxRate: 0.21, // EU higher rate
  },
];

export const getProductById = (id: string): Product | undefined =>
  PRODUCTS.find((p) => p.id === id);

export const getProductsByCategory = (category: Product["category"]): Product[] =>
  PRODUCTS.filter((p) => p.category === category);
