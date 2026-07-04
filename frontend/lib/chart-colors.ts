// Get theme-aware colors for charts
export function getChartColors() {
  if (typeof window === 'undefined') {
    // Server-side fallback
    return {
      gridStroke: '#e5e7eb',
      axisStroke: '#9ca3af',
      tooltipBg: '#ffffff',
      tooltipBorder: '#e5e7eb',
      primary: '#10b981',
      accent: '#8b5cf6',
      destructive: '#ef4444',
      warning: '#f59e0b',
    };
  }

  const root = document.documentElement;
  const isDark = root.classList.contains('dark');
  
  return {
    // Grid and axis colors adapt to theme
    gridStroke: isDark ? '#2a2a2a' : '#e5e7eb',
    axisStroke: isDark ? '#7a7a7a' : '#9ca3af',
    tooltipBg: isDark ? '#1a1a1a' : '#ffffff',
    tooltipBorder: isDark ? '#2a2a2a' : '#e5e7eb',
    
    // Chart data colors stay consistent
    primary: '#10b981',
    accent: '#8b5cf6',
    destructive: '#ef4444',
    warning: '#f59e0b',
  };
}
