"use client";

import { ThemeProvider as NextThemesProvider } from "next-themes";
import { ReactNode, useEffect } from "react";

export function ThemeProvider({ children }: { children: ReactNode }) {
  return (
    <NextThemesProvider
      attribute="class"
      defaultTheme="light"
      enableSystem={false}
      enableColorScheme={false}
      disableTransitionOnChange={false}
      storageKey="eckox-theme"
      forcedTheme={undefined}
      themes={["light", "dark"]}
    >
      {children}
    </NextThemesProvider>
  );
}
