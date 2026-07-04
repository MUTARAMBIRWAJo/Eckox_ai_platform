// African & European company names for B2B context
export const COMPANIES = [
  // European Tech/Science
  "Siemens Diagnostics",
  "Philips Healthcare",
  "Roche Laboratories",
  "Bayer Scientific",
  "ThermFisher Scientific",
  "Eppendorf Analytics",
  "Analytik Jena",
  "PerkinElmer Europe",
  "Waters Corporation",
  "Shimadzu Scientific",

  // African Distributors & Importers
  "Lagos Scientific Supplies",
  "Nairobi Lab Equipment",
  "Cairo Medical Imports",
  "Accra Diagnostics Ltd",
  "Johannesburg Analytics",
  "Kampala Research Institute",
  "Dar es Salaam Clinical Labs",
  "Abuja Hospital Supply",
  "Kigali Medical Center",
  "Douala Biotech Solutions",

  // Pan-African Organizations
  "African Health Initiative",
  "East African Medical Group",
  "West African Science Consortium",
  "Southern Africa Research Hub",
  "Continental Lab Services",
  "Pan-Africa Diagnostics",
  "ECOWAS Medical Equipment",
  "SADC Health Solutions",

  // European Retailers & Distributors
  "London Lab Supplies",
  "Berlin Scientific Imports",
  "Amsterdam Bio Lab",
  "Stockholm Life Sciences",
  "Zurich Analytics Solutions",
  "Brussels Healthcare Group",
  "Madrid Medical Distributors",
  "Milan Biotech Imports",
  "Frankfurt Scientific Supplies",
  "Vienna Research Equipment",

  // Hybrid African-European
  "Afro-European Science Alliance",
  "Global Africa Diagnostics",
  "Europe-Africa Medical Hub",
  "TransContinental Lab Services",
  "Africa Rising Biotech",
  "Pan-Global Healthcare Solutions",
  "United Africa Laboratories",
  "NextGen Africa Scientific",
  "African Enterprise Lab Solutions",
  "Europe Partners Africa",

  // Additional Tech Companies
  "Precision Labs Africa",
  "Innovate Science Solutions",
  "Quality Assurance Diagnostics",
  "Advanced Analytics Group",
  "Future Technologies Lab",
  "Elite Research Solutions",
  "Premium Healthcare Services",
  "Strategic Medical Solutions",
  "Integrated Lab Systems",
  "Technology Forward Enterprises",
];

export const getRandomCompany = () =>
  COMPANIES[Math.floor(Math.random() * COMPANIES.length)];
