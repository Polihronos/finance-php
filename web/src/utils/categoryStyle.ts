import {
  Baby,
  Briefcase,
  Bus,
  Car,
  Coffee,
  Droplet,
  Dumbbell,
  Film,
  Fuel,
  Gamepad2,
  Gift,
  GraduationCap,
  HeartPulse,
  House,
  PawPrint,
  Pill,
  PiggyBank,
  Plane,
  Shirt,
  ShoppingCart,
  Smartphone,
  Tag,
  Utensils,
  Wifi,
  Zap,
  type LucideIcon,
} from '@lucide/vue'

import type { CategoryColor, CategoryIcon } from '@/api/types'

export const CATEGORY_ICONS: Record<CategoryIcon, LucideIcon> = {
  'shopping-cart': ShoppingCart,
  utensils: Utensils,
  coffee: Coffee,
  car: Car,
  fuel: Fuel,
  bus: Bus,
  plane: Plane,
  house: House,
  zap: Zap,
  droplet: Droplet,
  wifi: Wifi,
  smartphone: Smartphone,
  'heart-pulse': HeartPulse,
  pill: Pill,
  shirt: Shirt,
  gift: Gift,
  film: Film,
  'gamepad-2': Gamepad2,
  dumbbell: Dumbbell,
  'graduation-cap': GraduationCap,
  baby: Baby,
  'paw-print': PawPrint,
  briefcase: Briefcase,
  'piggy-bank': PiggyBank,
  tag: Tag,
}

export const CATEGORY_COLORS: Record<CategoryColor, string> = {
  red: '#de0d22',
  orange: '#ff4800',
  yellow: '#e6a100',
  green: '#2e9e5b',
  blue: '#2563eb',
  purple: '#7c3aed',
}

export const ICON_NAMES = Object.keys(CATEGORY_ICONS) as CategoryIcon[]
export const COLOR_NAMES = Object.keys(CATEGORY_COLORS) as CategoryColor[]

export const DEFAULT_ICON: CategoryIcon = 'tag'
export const DEFAULT_COLOR: CategoryColor = 'blue'
