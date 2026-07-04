import { COMPANIES, getRandomCompany } from "./companies";

export type LeadStatus = "new" | "contacted" | "qualified" | "proposal" | "negotiation" | "closed-won" | "closed-lost";
export type LeadSource = "inbound" | "outbound" | "referral" | "event" | "partnership";

export interface Lead {
  id: string;
  company: string;
  contact: string;
  email: string;
  phone: string;
  status: LeadStatus;
  source: LeadSource;
  value: number; // USD
  currency: "USD" | "EUR" | "NGN";
  createdAt: string;
  lastActivity: string;
  notes: string;
  probability: number; // 0-100
}

const FIRST_NAMES = [
  "James", "Mary", "Robert", "Patricia", "Michael", "Jennifer", "William", "Linda",
  "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica", "Thomas", "Sarah",
  "Charles", "Karen", "Christopher", "Nancy", "Daniel", "Lisa", "Matthew", "Betty",
  "Ahmed", "Amira", "Hassan", "Fatima", "Mohammed", "Layla", "Ibrahim", "Hana",
];

const LAST_NAMES = [
  "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis",
  "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson",
  "Thomas", "Taylor", "Moore", "Jackson", "Martin", "Lee", "Perez", "Thompson",
  "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson", "Walker",
];

const generateLeads = (count: number): Lead[] => {
  const leads: Lead[] = [];
  const statuses: LeadStatus[] = ["new", "contacted", "qualified", "proposal", "negotiation", "closed-won", "closed-lost"];
  const sources: LeadSource[] = ["inbound", "outbound", "referral", "event", "partnership"];
  const currencies = ["USD", "EUR", "NGN"] as const;

  for (let i = 0; i < count; i++) {
    const firstName = FIRST_NAMES[Math.floor(Math.random() * FIRST_NAMES.length)];
    const lastName = LAST_NAMES[Math.floor(Math.random() * LAST_NAMES.length)];
    const currency = currencies[Math.floor(Math.random() * currencies.length)];
    const baseValue = Math.floor(Math.random() * 150000) + 15000;
    
    // Adjust by currency
    const value = currency === "USD" ? baseValue : 
                  currency === "EUR" ? Math.floor(baseValue * 0.92) : 
                  Math.floor(baseValue * 485); // NGN conversion

    const daysAgo = Math.floor(Math.random() * 90);
    const createdDate = new Date();
    createdDate.setDate(createdDate.getDate() - daysAgo);

    const lastActivityDate = new Date(createdDate);
    lastActivityDate.setDate(lastActivityDate.getDate() + Math.floor(Math.random() * daysAgo));

    leads.push({
      id: `lead_${String(i + 1).padStart(3, "0")}`,
      company: getRandomCompany(),
      contact: `${firstName} ${lastName}`,
      email: `${firstName.toLowerCase()}.${lastName.toLowerCase()}@company.com`,
      phone: `+${Math.floor(Math.random() * 9) + 1}${String(Math.floor(Math.random() * 1000000000)).padStart(9, "0")}`,
      status: statuses[Math.floor(Math.random() * statuses.length)],
      source: sources[Math.floor(Math.random() * sources.length)],
      value,
      currency: currency as "USD" | "EUR" | "NGN",
      createdAt: createdDate.toISOString(),
      lastActivity: lastActivityDate.toISOString(),
      notes: `Prospect from ${currency} region. Interested in lab equipment solutions.`,
      probability: Math.floor(Math.random() * 100),
    });
  }

  return leads;
};

export const LEADS = generateLeads(50);

export const getLeadById = (id: string): Lead | undefined =>
  LEADS.find((l) => l.id === id);

export const getLeadsByStatus = (status: LeadStatus): Lead[] =>
  LEADS.filter((l) => l.status === status);

export const getLeadsBySource = (source: LeadSource): Lead[] =>
  LEADS.filter((l) => l.source === source);

export const getLeadValue = (lead: Lead, targetCurrency: "USD" | "EUR" | "NGN"): number => {
  // Convert to USD first if needed
  let usdValue = lead.value;
  if (lead.currency === "EUR") {
    usdValue = Math.floor(lead.value * 1.09); // EUR to USD rough rate
  } else if (lead.currency === "NGN") {
    usdValue = Math.floor(lead.value / 485); // NGN to USD rough rate
  }

  // Then convert to target currency
  if (targetCurrency === "EUR") {
    return Math.floor(usdValue * 0.92);
  } else if (targetCurrency === "NGN") {
    return Math.floor(usdValue * 485);
  }
  return usdValue;
};
