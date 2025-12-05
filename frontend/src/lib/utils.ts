import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function parseDate(dateStr: string): Date {
  if (!dateStr) return new Date(0);
  
  // Try standard constructor first (YYYY-MM-DD works here)
  const date = new Date(dateStr);
  if (!isNaN(date.getTime())) return date;

  // Try DD.MM.YYYY
  const parts = dateStr.split('.');
  if (parts.length === 3) {
    // Create ISO string YYYY-MM-DD
    const isoDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
    if (!isNaN(isoDate.getTime())) return isoDate;
  }
  
  return new Date(0); // Invalid date fallback
}
