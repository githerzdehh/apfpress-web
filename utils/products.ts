import type { ProductCardData, WooCategory, WooProduct } from '~/types/woocommerce'

export type ProductKind = 'all' | 'print' | 'ebook'

export const PRINT_CATEGORY_SLUGS = ['printed-books', 'merle-visano-printed']
export const EBOOK_CATEGORY_SLUGS = ['e-books', 'merle-visano-ebooks']

const entityMap: Record<string, string> = {
  amp: '&',
  apos: "'",
  hellip: '...',
  mdash: '-',
  ndash: '-',
  nbsp: ' ',
  quot: '"',
  rsquo: "'",
  lsquo: "'",
  rdquo: '"',
  ldquo: '"'
}

const codePointMap: Record<number, string> = {
  8211: '-',
  8212: '-',
  8216: "'",
  8217: "'",
  8220: '"',
  8221: '"',
  8230: '...'
}

export function decodeHtml(value = '') {
  return value.replace(/&(#x?[0-9a-f]+|[a-z]+);/gi, (_, entity: string) => {
    const lower = entity.toLowerCase()

    if (lower.startsWith('#x')) {
      const codePoint = Number.parseInt(lower.slice(2), 16)
      return codePointMap[codePoint] ?? String.fromCodePoint(codePoint)
    }

    if (lower.startsWith('#')) {
      const codePoint = Number.parseInt(lower.slice(1), 10)
      return codePointMap[codePoint] ?? String.fromCodePoint(codePoint)
    }

    return entityMap[lower] ?? `&${entity};`
  })
}

export function stripHtml(value = '') {
  return decodeHtml(value)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/[ \t]{2,}/g, ' ')
    .trim()
}

export function formatWooPrice(product: WooProduct) {
  if (!product.is_purchasable || product.prices.price === '0') {
    return ''
  }

  const price = Number(product.prices.price) / 10 ** product.prices.currency_minor_unit
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: product.prices.currency_code
  }).format(price)
}

export function getProductKind(categories: WooCategory[]): ProductCardData['kind'] {
  const slugs = categories.map((category) => category.slug)

  if (slugs.some((slug) => EBOOK_CATEGORY_SLUGS.includes(slug))) {
    return 'ebook'
  }

  if (slugs.some((slug) => PRINT_CATEGORY_SLUGS.includes(slug))) {
    return 'print'
  }

  return 'uncategorized'
}

export function productMatchesKind(product: WooProduct, kind: ProductKind) {
  if (kind === 'all') {
    return true
  }

  return getProductKind(product.categories) === kind
}

export function extractAuthor(shortDescription: string) {
  const text = stripHtml(shortDescription)
  const lines = text
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)

  const authorLine = lines.find((line) => /^author[s]?:/i.test(line) || /^by\s+/i.test(line) || /^edited by:?\s+/i.test(line))

  if (authorLine) {
    return authorLine
      .replace(/^author[s]?:\s*/i, '')
      .replace(/^edited by:?\s+/i, '')
      .replace(/^by\s+/i, '')
      .trim()
  }

  if (lines.length > 1) {
    return lines[1].trim()
  }

  return ''
}

export function mapWooProduct(product: WooProduct): ProductCardData {
  const excerpt = stripHtml(product.short_description || product.description)
  const image = product.images[0]

  return {
    id: product.id,
    title: decodeHtml(product.name),
    slug: product.slug,
    url: product.permalink.replace('https://www.apfpress.com', 'https://apfpress.com'),
    excerpt,
    author: extractAuthor(product.short_description),
    price: formatWooPrice(product),
    image: image?.src ?? '',
    imageAlt: image?.alt || decodeHtml(product.name),
    categories: product.categories,
    isPurchasable: product.is_purchasable,
    isInStock: product.is_in_stock,
    kind: getProductKind(product.categories)
  }
}

export function sortProductsByTitle(products: ProductCardData[]) {
  return [...products].sort((a, b) => a.title.localeCompare(b.title))
}
