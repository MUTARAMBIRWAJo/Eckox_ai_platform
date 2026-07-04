"use client";

import { Sidebar } from "./sidebar";
import { Header, HeaderProps } from "./header";
import { ReactNode } from "react";

export interface AppLayoutProps {
  children: ReactNode;
  headerProps?: HeaderProps;
}

export function AppLayout({ children, headerProps }: AppLayoutProps) {
  return (
    <div className="relative flex w-full min-h-screen bg-background">
      {/* Fixed Sidebar - Desktop only */}
      <Sidebar />

      {/* Main Content with Header */}
      <div className="flex-1 flex flex-col ml-0 lg:ml-64">
        {/* Fixed Header */}
        <div className="sticky top-0 z-40">
          <Header {...headerProps} />
        </div>

        {/* Main Content Area */}
        <main className="flex-1 overflow-auto">
          <div className="min-h-[calc(100vh-64px)] px-4 py-6 sm:px-6 lg:px-8">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
