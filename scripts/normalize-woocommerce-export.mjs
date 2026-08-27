import fs from 'node:fs'

const [input, output] = process.argv.slice(2)

if (!input) {
  throw new Error('Usage: node scripts/normalize-woocommerce-export.mjs <store-api.json> [normalized.json]')
}

const products = JSON.parse(fs.readFileSync(input, 'utf8'))
const entities = {
  amp: '&', apos: "'", quot: '"', nbsp: ' ', hellip: '...', mdash: '-', ndash: '-',
  rsquo: "'", lsquo: "'", rdquo: '"', ldquo: '"'
}

const decode = (value = '') => value.replace(/&(#x?[0-9a-f]+|[a-z]+);/gi, (_, entity) => {
  const lower = entity.toLowerCase()
  if (lower.startsWith('#x')) return String.fromCodePoint(Number.parseInt(lower.slice(2), 16))
  if (lower.startsWith('#')) return String.fromCodePoint(Number.parseInt(lower.slice(1), 10))
  return entities[lower] ?? `&${entity};`
})

const strip = (value = '') => decode(value)
  .replace(/<br\s*\/?>/gi, '\n')
  .replace(/<\/p>/gi, '\n')
  .replace(/<[^>]+>/g, '')
  .replace(/\n{3,}/g, '\n\n')
  .replace(/[ \t]{2,}/g, ' ')
  .trim()

const printSlugs = ['printed-books', 'merle-visano-printed']
const ebookSlugs = ['e-books', 'merle-visano-ebooks']

const extractAuthor = (value = '') => {
  const lines = strip(value).split('\n').map(line => line.trim()).filter(Boolean)
  const found = lines.find(line => /^(authors?:|by\s+|edited by:?\s+)/i.test(line))
  if (found) return found.replace(/^authors?:\s*/i, '').replace(/^edited by:?\s+/i, '').replace(/^by\s+/i, '').trim()
  const candidate = lines.length >= 2 ? lines.at(-1) : ''
  return candidate && candidate.length <= 100 && !candidate.endsWith('.') ? candidate : ''
}

const normalized = products.map(product => {
  const slugs = product.categories.map(category => category.slug)
  const kind = slugs.some(slug => ebookSlugs.includes(slug))
    ? 'ebook'
    : slugs.some(slug => printSlugs.includes(slug)) ? 'print_book' : 'other'

  return {
    source_id: product.id,
    slug: product.slug,
    title: decode(product.name),
    summary: strip(product.short_description),
    description: strip(product.description),
    author: extractAuthor(product.short_description),
    kind,
    price_amount: product.is_purchasable && product.prices.price !== '0' ? Number(product.prices.price) : null,
    currency: product.prices.currency_code || 'CAD',
    purchasable: Boolean(product.is_purchasable),
    in_stock: Boolean(product.is_in_stock),
    stock_quantity: product.stock_quantity !== null && product.stock_quantity !== undefined && Number.isFinite(Number(product.stock_quantity)) ? Number(product.stock_quantity) : null,
    sku: product.sku?.trim() || null,
    categories: product.categories.map(category => ({ name: decode(category.name), slug: category.slug })),
    image_url: product.images[0]?.src ?? '',
    source_url: product.permalink
  }
})

const json = `${JSON.stringify(normalized, null, 2)}\n`
if (output) fs.writeFileSync(output, json)
else process.stdout.write(json)
