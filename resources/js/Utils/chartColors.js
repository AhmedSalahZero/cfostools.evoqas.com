// A curated set of hand-picked, visually distinct colors matching the
// app's existing palette (teal, gold, green, amber, red, plus a few more
// to cover charts with many slices). Used before falling back to
// generated hues, so small charts still get the "brand" look.
const BASE_PALETTE = [
  '#00b4c8', // teal
  '#c9a84c', // gold
  '#10b981', // success green
  '#f59e0b', // amber
  '#ef4444', // danger red
  '#8b5cf6', // purple
  '#3b82f6', // blue
  '#ec4899', // pink
  '#84cc16', // lime
  '#f97316', // orange
  '#06b6d4', // cyan
  '#a855f7', // violet
]

/**
 * Returns exactly `n` colors, guaranteed never to repeat within the
 * returned set — unlike `PALETTE[i % PALETTE.length]`, which silently
 * duplicates once a chart has more slices than the base palette length.
 * Beyond the curated palette, additional colors are generated as evenly
 * spaced hues around the color wheel, so it never runs out.
 */
export function generateDistinctColors(n) {
  const colors = BASE_PALETTE.slice(0, n)
  if (colors.length >= n) return colors

  const needed = n - colors.length
  for (let i = 0; i < needed; i++) {
    const hue = Math.round((360 / needed) * i)
    colors.push(`hsl(${hue}, 65%, 55%)`)
  }
  return colors
}

/**
 * Lightens (positive amt) or darkens (negative amt) a color for the
 * radial-gradient "glossy" slice effect. Handles both hex (#rrggbb) and
 * hsl(...) strings, since generateDistinctColors can produce either.
 */
export function shadeColor(color, amt) {
  if (color.startsWith('hsl')) {
    const m = color.match(/hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/)
    if (!m) return color
    const [, h, s, l] = m.map(Number)
    return `hsl(${h}, ${s}%, ${Math.max(0, Math.min(100, l + amt / 3))}%)`
  }
  const n = parseInt(color.slice(1), 16)
  let r = (n >> 16) + amt, g = ((n >> 8) & 0xff) + amt, b = (n & 0xff) + amt
  r = Math.max(0, Math.min(255, r)); g = Math.max(0, Math.min(255, g)); b = Math.max(0, Math.min(255, b))
  return '#' + (0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1)
}