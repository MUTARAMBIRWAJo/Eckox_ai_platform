import { AuthUser } from '@/lib/api/auth.api';

/**
 * Check if the user has a specific role.
 */
export function hasRole(user: AuthUser | null | undefined, role: string): boolean {
  if (!user || !user.roles) return false;
  return user.roles.includes(role);
}

/**
 * Check if the user has any of the specified roles.
 */
export function hasAnyRole(user: AuthUser | null | undefined, roles: string[]): boolean {
  if (!user || !user.roles) return false;
  return roles.some((role) => user.roles.includes(role));
}

/**
 * Define path permissions map.
 * Key represents the route prefix.
 * Value represents the roles allowed to access the route prefix.
 */
export const ROUTE_ROLE_MAP: Record<string, string[]> = {
  '/admin': ['admin', 'super-admin'],
  '/crm': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/leads': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/conversations': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/quotes': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/products': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/analytics': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/automation': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/dashboard': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/chat': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/knowledge': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/notifications': ['sales-agent', 'manager', 'admin', 'super-admin'],
  '/settings': ['sales-agent', 'manager', 'admin', 'super-admin'],
  // Manager/Admin only pages
  '/marketing': ['manager', 'admin', 'super-admin'],
  '/traces': ['manager', 'admin', 'super-admin'],
};

/**
 * Check if the user is authorized to access a given pathname.
 */
export function canAccess(user: AuthUser | null | undefined, pathname: string): boolean {
  // If not logged in, they can't access any protected path
  if (!user) return false;

  // Check matching prefixes (starting from longest matching path prefixes)
  const matchedKey = Object.keys(ROUTE_ROLE_MAP)
    .sort((a, b) => b.length - a.length) // Longest prefixes first
    .find((prefix) => pathname === prefix || pathname.startsWith(prefix + '/'));

  // If no specific prefix rule is found, default to allowing all authenticated users
  if (!matchedKey) {
    return true;
  }

  const allowedRoles = ROUTE_ROLE_MAP[matchedKey];
  return hasAnyRole(user, allowedRoles);
}
