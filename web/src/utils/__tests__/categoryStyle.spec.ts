import { expect, test } from 'vitest'

import {
  CATEGORY_COLORS,
  CATEGORY_ICONS,
  COLOR_NAMES,
  DEFAULT_COLOR,
  DEFAULT_ICON,
  ICON_NAMES,
} from '../categoryStyle'

// Icons and colors: the same names the API accepts, each with something to draw

test('every icon name the API accepts has an icon', () => {
  expect(ICON_NAMES).toStrictEqual([
    'shopping-cart',
    'utensils',
    'coffee',
    'car',
    'fuel',
    'bus',
    'plane',
    'house',
    'zap',
    'droplet',
    'wifi',
    'smartphone',
    'heart-pulse',
    'pill',
    'shirt',
    'gift',
    'film',
    'gamepad-2',
    'dumbbell',
    'graduation-cap',
    'baby',
    'paw-print',
    'briefcase',
    'piggy-bank',
    'tag',
  ])
  for (const name of ICON_NAMES) {
    expect(CATEGORY_ICONS[name]).toBeDefined()
  }
})

test('every color name the API accepts has a color code', () => {
  expect(COLOR_NAMES).toStrictEqual(['red', 'orange', 'yellow', 'green', 'blue', 'purple'])
  for (const name of COLOR_NAMES) {
    expect(CATEGORY_COLORS[name]).toMatch(/^#[0-9a-f]{6}$/)
  }
})

test('new categories start as a blue tag, like in the API', () => {
  expect(DEFAULT_ICON).toBe('tag')
  expect(DEFAULT_COLOR).toBe('blue')
})
